<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacySchemaUpgradeTest extends TestCase
{
    public function test_upgrades_historical_mysql_schema_without_losing_accounts_or_items(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Le schéma historique fourni est au format MySQL');
        }
        $this->assertTrue(app()->environment('testing'));
        Schema::dropAllTables();
        try {
            DB::unprepared(file_get_contents(__DIR__.'/../fixtures/legacy-schema.mysql.sql'));
            $hash = password_hash('test-password', PASSWORD_BCRYPT, ['cost' => 4]);
            DB::table('admin')->insert(['id' => 42, 'first_name' => 'Historique', 'last_name' => 'Compte', 'email' => 'history@example.test', 'mobile1' => '+243999000001', 'mobile2' => '', 'password' => $hash, 'role' => 'Super', 'created_on' => '2021-01-01 12:00:00', 'last_login' => '2021-01-01 12:00:00', 'last_seen' => '2021-01-01 12:00:00']);
            DB::table('items')->insert(['id' => 99, 'name' => 'Historique', 'code' => 'OLD-1', 'description' => 'Préservé', 'unitPrice' => '2.75', 'quantity' => 7, 'dateAdded' => '2021-01-01 12:00:00', 'lastUpdated' => '2021-01-01 12:00:00', 'min' => 1]);
            $migration = require __DIR__.'/../../database/migrations/2026_10_22_125037_create_nyota_tables.php';

            $migration->up();

            $this->assertDatabaseHas('admin', ['id' => 42, 'email' => 'history@example.test', 'password' => $hash, 'auth_version' => 0]);
            $this->assertDatabaseHas('items', ['id' => 99, 'code' => 'OLD-1', 'quantity' => 7, 'unitPrice' => '2.75']);
            $this->assertSame('decimal', Schema::getColumnType('transactions', 'pos'));
            $this->assertSame('decimal', Schema::getColumnType('transactions', 'cash'));
            $this->assertTrue(Schema::hasTable('payements'));
        } finally {
            Schema::dropAllTables();
            RefreshDatabaseState::$migrated = false;
        }
    }
}
