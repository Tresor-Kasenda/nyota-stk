<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NyotaMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function account(string $role = 'Super'): array
    {
        $id = DB::table('admin')->insertGetId(['first_name' => 'Test', 'last_name' => 'Account', 'email' => uniqid().'@example.test', 'mobile1' => uniqid(), 'password' => password_hash('test-password', PASSWORD_BCRYPT, ['cost' => 4]), 'role' => $role, 'created_on' => '2026-10-22 10:00:00', 'account_status' => '1', 'deleted' => '0', 'auth_version' => 0]);

        return ['admin_id' => $id, 'admin_role' => $role, 'admin_name' => 'Test Account', 'admin_initial' => 'T', 'auth_version' => 0];
    }

    private function product(int $quantity = 5): int
    {
        return DB::table('items')->insertGetId(['name' => 'Produit témoin', 'code' => 'TEST-1', 'unitPrice' => '10.00', 'quantity' => $quantity, 'description' => 'Description', 'min' => 1]);
    }

    private function cart(): array
    {
        return ['_aoi' => json_encode([['_iC' => 'TEST-1', 'qty' => 1, 'unitPrice' => '10.00', 'totalPrice' => '10.00']]), '_mop' => 'Cash', '_at' => '20', '_ca' => '10', 'ca' => '10', 'pos' => '0', 'vat' => '0', 'discount' => '0', 'cn' => 'Client témoin'];
    }

    public static function screens(): array
    {
        return array_map(fn (string $url): array => [$url], ['/dashboard', '/items', '/category', '/couts', '/administrators', '/eventlog', '/eventlog/approvisionnement', '/transactions', '/transactions/dettes', '/dbmanagement', '/audit', '/customers', '/reports']);
    }

    #[DataProvider('screens')]
    public function test_renders_existing_screen_with_original_layout(string $url): void
    {
        $session = $this->account();

        $response = $this->withSession($session)->get($url);

        $response->assertOk()->assertSee('public/css/main.css', false)->assertSee('public/images/icon.png', false);
    }

    public static function fragments(): array
    {
        return [['/items/lilt', 'itemsListTable'], ['/category/laad_', 'categoriesTable'], ['/couts/laad_', 'coutsTable'], ['/administrators/laad_', 'adminTable'], ['/eventlog/lilt', 'allevents'], ['/transactions/latr_', 'transTable'], ['/search/itemsearch?v=TEST', 'itemsListTable'], ['/search/categorysearch?v=x', 'categoriesTable']];
    }

    #[DataProvider('fragments')]
    public function test_returns_legacy_ajax_fragment_contract(string $url, string $key): void
    {
        $session = $this->account();
        $this->product();

        $response = $this->withSession($session)->getJson($url);

        $response->assertOk()->assertJsonStructure([$key]);
    }

    public function test_rejects_transaction_search_without_authentication(): void
    {
        $this->getJson('/search/transsearch?v=1')->assertUnauthorized();
    }

    public function test_basic_user_cannot_mutate_catalog(): void
    {
        $session = $this->account('Basic');

        $this->withSession($session)->postJson('/category/add', ['nom' => 'Interdit'])->assertForbidden();

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_creates_sale_with_matching_receipt_and_atomic_stock_update(): void
    {
        $session = $this->account();
        $this->product();

        $response = $this->withSession($session)->postJson('/transactions/nso_', $this->cart());

        $response->assertOk()->assertJsonPath('status', 1)->assertJsonStructure(['transReceipt', 'totalEarnedToday']);
        $this->assertStringContainsString('QUIN GRADI', $response->json('transReceipt'));
        $this->assertStringContainsString('Client témoin', $response->json('transReceipt'));
        $this->assertDatabaseHas('items', ['code' => 'TEST-1', 'quantity' => 4]);
        $this->assertDatabaseHas('transactions', ['totalMoneySpent' => 10, 'changeDue' => 10, 'cash' => 10, 'pos' => 0]);
        $this->assertDatabaseCount('eventlog', 1);
    }

    public function test_rejects_negative_quantity_without_any_write(): void
    {
        $session = $this->account();
        $this->product();
        $cart = $this->cart();
        $cart['_aoi'] = json_encode([['_iC' => 'TEST-1', 'qty' => -1, 'unitPrice' => '10.00', 'totalPrice' => '-10.00']]);

        $this->withSession($session)->postJson('/transactions/nso_', $cart)->assertJsonPath('status', 0);

        $this->assertDatabaseHas('items', ['code' => 'TEST-1', 'quantity' => 5]);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('eventlog', 0);
    }

    public function test_rolls_back_sale_when_stock_is_insufficient(): void
    {
        $session = $this->account();
        $this->product(0);

        $this->withSession($session)->postJson('/transactions/nso_', $this->cart())->assertJsonPath('status', 0)->assertJsonPath('msg', 'Stock indisponible');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('eventlog', 0);
    }

    public function test_combines_duplicate_product_lines_before_reserving_stock(): void
    {
        $session = $this->account();
        $this->product(1);
        $cart = $this->cart();
        $cart['_aoi'] = json_encode(array_fill(0, 2, ['_iC' => 'TEST-1', 'qty' => 1, 'unitPrice' => 10, 'totalPrice' => 10]));
        $cart['_ca'] = '20';
        $cart['ca'] = '20';

        $this->withSession($session)->postJson('/transactions/nso_', $cart)->assertJsonPath('status', 0);

        $this->assertDatabaseHas('items', ['quantity' => 1]);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_settles_debt_and_rejects_payment_above_remaining_balance(): void
    {
        $session = $this->account();
        $this->product();
        $cart = $this->cart();
        $cart['_mop'] = 'POS';
        $cart['ca'] = '0';
        $cart['pos'] = '10';
        $this->withSession($session)->postJson('/transactions/nso_', $cart)->assertJsonPath('status', 1);
        $reference = DB::table('transactions')->value('ref');

        $this->postJson('/transactions/payment', ['ref' => $reference, 'montantPercu' => '7', 'monnaieDue' => '1'])->assertJsonPath('status', 1);

        $this->assertDatabaseHas('transactions', ['ref' => $reference, 'pos' => 4, 'cash' => 6]);
        $this->assertDatabaseHas('payements', ['ref' => $reference, 'montant_paye' => 6]);
        $this->postJson('/transactions/payment', ['ref' => $reference, 'montantPercu' => '5', 'monnaieDue' => '0'])->assertJsonPath('status', 0);
        $this->assertDatabaseCount('payements', 1);
    }

    public function test_creates_edits_and_removes_item_with_its_categories(): void
    {
        $session = $this->account();
        $category = DB::table('categories')->insertGetId(['nom' => 'Boissons']);
        $data = ['itemName' => 'Article', 'itemCode' => 'CODE1', 'itemPrice' => '2.75', 'itemQuantity' => 10, 'stockMin' => 2, 'itemCategories' => [$category], 'itemDescription' => 'Description'];

        $this->withSession($session)->postJson('/items/add', $data)->assertJsonPath('status', 1);
        $id = DB::table('items')->value('id');

        $this->assertDatabaseHas('item_category', ['id_item' => $id, 'id_category' => $category]);
        $this->postJson('/items/edit', array_merge($data, ['_iId' => $id, 'itemName' => 'Article modifié', 'itemDesc' => 'Nouvelle description']))->assertJsonPath('status', 1);
        $this->assertDatabaseHas('items', ['id' => $id, 'name' => 'Article modifié', 'quantity' => 10]);
        $this->postJson('/items/delete', ['i' => $id])->assertJsonPath('status', 1);
        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('item_category', 0);
    }

    public function test_invalid_stock_adjustment_does_not_create_event(): void
    {
        $session = $this->account();
        $id = $this->product();

        $this->withSession($session)->postJson('/items/updatestock', ['_iId' => $id, '_upType' => 'deficit', 'qty' => 6, 'desc' => 'Sortie'])->assertJsonPath('status', 0);

        $this->assertDatabaseHas('items', ['id' => $id, 'quantity' => 5]);
        $this->assertDatabaseCount('eventlog', 0);
    }

    public function test_renders_escaped_customer_and_product_text(): void
    {
        $session = $this->account();
        $this->product();
        DB::table('items')->update(['name' => '<script>bad()</script>']);
        $cart = $this->cart();
        $cart['cn'] = '<img src=x onerror=bad()>';

        $response = $this->withSession($session)->postJson('/transactions/nso_', $cart);

        $response->assertJsonPath('status', 1);
        $this->assertStringNotContainsString('<img src=x', $response->json('transReceipt'));
        $this->assertStringNotContainsString('<script>bad()', $response->json('transReceipt'));
        $this->assertStringContainsString('&lt;script&gt;', $response->json('transReceipt'));
    }

    public function test_arbitrary_sort_expression_cannot_change_query(): void
    {
        $session = $this->account();
        $this->product();

        $this->withSession($session)->getJson('/items/lilt?orderBy='.urlencode('name; DROP TABLE items').'&orderFormat='.urlencode('DESC; DROP TABLE admin'))->assertOk()->assertJsonStructure(['itemsListTable']);

        $this->assertDatabaseCount('items', 1);
        $this->assertDatabaseCount('admin', 1);
    }

    public function test_suspended_account_loses_access_with_existing_session(): void
    {
        $session = $this->account();
        DB::table('admin')->where('id', $session['admin_id'])->update(['account_status' => '0']);

        $this->withSession($session)->getJson('/transactions/latr_')->assertUnauthorized();
    }

    public function test_authenticates_historical_bcrypt_password_and_rotates_session(): void
    {
        $session = $this->account();
        $email = DB::table('admin')->where('id', $session['admin_id'])->value('email');
        $this->get('/')->assertOk();
        $previous = session()->getId();

        $response = $this->postJson('/access/login', ['email' => $email, 'password' => 'test-password']);

        $response->assertJsonPath('status', 1)->assertSessionHas('admin_id', $session['admin_id']);
        $this->assertNotSame($previous, session()->getId());
    }

    public function test_saves_backup_privately_and_restores_exported_data(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $session = $this->account();
        $this->product();

        $response = $this->withSession($session)->postJson('/misc/dldb');

        $response->assertJsonPath('status', 1);
        $path = DB::table('backups')->value('file_url');
        Storage::disk('local')->assertExists($path);
        $sql = Storage::disk('local')->get($path);
        DB::table('items')->update(['quantity' => 0]);
        $file = UploadedFile::fake()->createWithContent('restore.sql', $sql);
        $this->postJson('/misc/importdb', ['dbfile' => $file])->assertJsonPath('status', 1);
        $this->assertDatabaseHas('items', ['code' => 'TEST-1', 'quantity' => 5]);
        $this->getJson('/items/lilt')->assertUnauthorized();
    }

    public function test_rejects_backup_restore_expression_without_changing_data(): void
    {
        Storage::fake('local');
        $session = $this->account();
        $this->product();
        $file = UploadedFile::fake()->createWithContent('restore.sql', 'INSERT INTO `items` (`name`) VALUES ((SELECT password FROM admin));');

        $this->withSession($session)->postJson('/misc/importdb', ['dbfile' => $file])->assertJsonPath('status', 0);

        $this->assertDatabaseHas('items', ['quantity' => 5]);
    }

    public function test_disallows_sensitive_mutations_using_get(): void
    {
        $this->get('/backup/db_backup')->assertMethodNotAllowed();
        $this->get('/misc/dldb')->assertMethodNotAllowed();
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_preserves_styles_images_fonts_and_vendored_scripts(): void
    {
        $manifest = json_decode(file_get_contents(__DIR__.'/../fixtures/assets.json'), true);
        foreach ($manifest as $path => $hash) {
            if (in_array($path, ['public/js/main.js', 'public/js/transactions.js', 'public/js/dettes.js'], true)) {
                continue;
            }
            $this->assertFileExists(public_path($path));
            $this->assertSame($hash, hash_file('sha256', public_path($path)), $path);
        }
    }

    public function test_preserves_original_template_markup_and_inline_styles(): void
    {
        $contract = json_decode(file_get_contents(__DIR__.'/../fixtures/presentation.json'), true);
        $normalize = static function (string $html): string {
            $html = preg_replace('/<\?(?:php|=)?.*?\?>/s', '', $html);
            $html = preg_replace('/<meta name="(?:csrf-token|nyota-base)"[^>]*>/', '', $html);
            $html = str_replace('<script src="public/js/laravel-transport.js"></script>', '', $html);

            return trim(preg_replace('/\s+/u', ' ', $html));
        };
        foreach ($contract as $view => $expected) {
            $this->assertSame($expected, $normalize(file_get_contents(resource_path('views/nyota/'.$view))), $view);
        }
    }

    public function test_same_idempotency_key_does_not_duplicate_sale_or_stock_change(): void
    {
        $session = $this->account();
        $this->product();
        $this->withHeader('Idempotency-Key', 'same-sale-request-0001');

        $this->withSession($session)->postJson('/transactions/nso_', $this->cart())->assertJsonPath('status', 1);
        $this->postJson('/transactions/nso_', $this->cart())->assertJsonPath('status', 1);

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('items', ['quantity' => 4]);
        $this->assertDatabaseCount('eventlog', 1);
    }

    public function test_rejects_cross_site_write_without_csrf_token(): void
    {
        $session = $this->account();
        $this->app['env'] = 'local';

        $this->withSession($session)->withHeader('Sec-Fetch-Site', 'cross-site')->postJson('/category/add', ['nom' => 'Interdit'])->assertStatus(419);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_accepts_valid_csrf_token_with_existing_ajax_payload(): void
    {
        $session = $this->account();
        $this->app['env'] = 'local';

        $this->withSession($session + ['_token' => 'known-test-token'])->withHeader('X-CSRF-TOKEN', 'known-test-token')->postJson('/category/add', ['nom' => 'Boisson'])->assertJsonPath('status', 1);

        $this->assertDatabaseHas('categories', ['nom' => 'Boisson']);
    }

    public function test_creates_updates_and_soft_deletes_cost(): void
    {
        $session = $this->account();

        $this->withSession($session)->postJson('/couts/add', ['montant' => '25.75', 'motif' => 'Achat', 'author' => 'Test', 'date_sortie' => '2026-10-22'])->assertJsonPath('status', 1);
        $id = DB::table('couts')->value('coutsId');
        $this->postJson('/couts/update', ['coutId' => $id, 'montantEdit' => '30', 'motifEdit' => 'Achat modifié', 'authorEdit' => 'Autre', 'date_sortieEdit' => '2026-10-21'])->assertJsonPath('status', 1);

        $this->assertDatabaseHas('couts', ['coutsId' => $id, 'montant' => 30, 'motif' => 'Achat modifié']);
        $this->postJson('/couts/delete', ['_aId' => $id])->assertJsonPath('_nv', 1);
        $this->assertDatabaseHas('couts', ['coutsId' => $id, 'deleted' => 1]);
    }

    public function test_creates_account_and_invalidates_its_session_on_password_change(): void
    {
        $session = $this->account();

        $this->withSession($session)->postJson('/administrators/add', ['firstName' => 'Autre', 'lastName' => 'Compte', 'email' => 'other@example.test', 'mobile1' => '+243999000001', 'role' => 'Basic', 'passwordOrig' => 'long-password', 'passwordDup' => 'long-password'])->assertJsonPath('status', 1);
        $account = DB::table('admin')->where('email', 'other@example.test')->first();
        $this->postJson('/administrators/password_update', ['adminId' => $account->id, 'password' => 'changed-password', 'passwordDup' => 'changed-password'])->assertJsonPath('status', 1);

        $this->assertTrue(password_verify('changed-password', DB::table('admin')->where('id', $account->id)->value('password')));
        $this->withSession(['admin_id' => $account->id, 'admin_role' => 'Basic', 'auth_version' => 0])->getJson('/transactions/latr_')->assertUnauthorized();
    }

    public function test_reports_monthly_earnings_and_printable_date_range(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-10-22 10:00:00'));
        $session = $this->account();
        $this->product();
        $this->withSession($session)->postJson('/transactions/nso_', $this->cart())->assertJsonPath('status', 1);

        $this->getJson('/dashboard/earningsGraph/2026')->assertJsonPath('total_earnings.9', 10);
        $this->getJson('/dashboard/paymentmethodchart/2026')->assertJsonPath('cash', 100);
        $this->get('/transactions/report/2026-10-01/2026-10-31')->assertOk()->assertSee('Client témoin');
    }

    public function test_exports_all_categories_across_page_boundaries(): void
    {
        $session = $this->account();
        for ($offset = 0; $offset < 5001; $offset += 500) {
            $rows = [];
            for ($number = $offset; $number < min(5001, $offset + 500); $number++) {
                $rows[] = ['nom' => 'Category'.$number];
            }
            DB::table('categories')->insert($rows);
        }

        $response = $this->withSession($session)->get('/category/excel');

        $response->assertOk()->assertDownload('category.xls');
        $this->assertStringContainsString('Category5000', $response->streamedContent());
    }

    public function test_does_not_embed_unescaped_script_in_sale_page(): void
    {
        $session = $this->account();
        $this->product();
        DB::table('items')->update(['name' => '</script><script>attack()</script>']);

        $response = $this->withSession($session)->get('/transactions');

        $response->assertOk()->assertDontSee('</script><script>attack()', false);
        $response->assertSee('\\u003C', false);
    }

    public function test_retains_local_backup_if_remote_service_cannot_connect(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['nyota.backup_url' => 'https://backup.example.test/upload', 'nyota.backup_token' => 'test-token', 'nyota.backup_key' => base64_encode(str_repeat('k', 32))]);
        Http::fake(['https://backup.example.test/upload' => Http::failedConnection()]);
        $session = $this->account();
        $this->product();

        $response = $this->withSession($session)->postJson('/misc/dldb');

        $response->assertJsonPath('status', 1);
        $this->assertDatabaseHas('backups', ['online' => 0]);
        Storage::disk('local')->assertExists(DB::table('backups')->value('file_url'));
        Http::assertSentCount(1);
    }

    public function test_restore_preserves_quotes_semicolons_and_backslashes_in_data(): void
    {
        Storage::fake('local');
        $session = $this->account();
        $this->product();
        $text = "L'article ; \\ exemple ' double ''";
        DB::table('items')->update(['description' => $text]);
        $this->withSession($session)->postJson('/misc/dldb')->assertJsonPath('status', 1);
        $sql = Storage::disk('local')->get(DB::table('backups')->value('file_url'));
        DB::table('items')->update(['description' => 'Modifié']);

        $this->postJson('/misc/importdb', ['dbfile' => UploadedFile::fake()->createWithContent('backup.sql', $sql)])->assertJsonPath('status', 1);

        $this->assertDatabaseHas('items', ['description' => $text]);
    }
}
