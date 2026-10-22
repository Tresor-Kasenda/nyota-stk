<?php

use App\Http\Controllers\AccountsController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\NyotaController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SessionController::class, 'index'])->name('login');
Route::post('/access/login', [SessionController::class, 'login'])->middleware('throttle:nyota-login')->name('session.login');
Route::post('/home/login', [SessionController::class, 'login'])->middleware('throttle:nyota-login');
Route::post('/logout', [SessionController::class, 'logout'])->name('logout');

Route::middleware('nyota')->group(function (): void {
    Route::get('/access/css', [SessionController::class, 'status'])->name('session.status');
    Route::get('/misc/check_session_status', [SessionController::class, 'status']);
    Route::get('/misc/totalearnedtoday', [NyotaController::class, 'total']);
    Route::get('/dashboard', [NyotaController::class, 'page'])->defaults('resource', 'dashboard')->name('dashboard');
    Route::get('/dashboard/earningsGraph/{year?}', [NyotaController::class, 'chart'])->defaults('chart', 'earnings');
    Route::get('/dashboard/paymentmethodchart/{year?}', [NyotaController::class, 'chart'])->defaults('chart', 'methods');
    Route::get('/items', [NyotaController::class, 'page'])->defaults('resource', 'items')->name('items');
    Route::get('/items/lilt/{page?}', [NyotaController::class, 'listing'])->defaults('resource', 'items')->whereNumber('page');
    Route::get('/items/gcoandqty', [CatalogController::class, 'product']);
    Route::get('/items/gettablecol/id/code/{code}', [CatalogController::class, 'code'])->where('code', '[a-zA-Z0-9_-]+');
    Route::get('/search/itemsearch', [NyotaController::class, 'listing'])->defaults('resource', 'items');
    Route::get('/transactions', [NyotaController::class, 'page'])->defaults('resource', 'transactions')->name('transactions');
    Route::get('/transactions/dettes', [NyotaController::class, 'page'])->defaults('resource', 'dettes')->name('debts');
    Route::get('/transactions/latr_/{page?}', [NyotaController::class, 'listing'])->defaults('resource', 'transactions')->whereNumber('page');
    Route::get('/search/transsearch', [NyotaController::class, 'listing'])->defaults('resource', 'transactions');
    Route::post('/transactions/nso_', [SalesController::class, 'create'])->name('sales.create');
    Route::post('/transactions/vtr_', [SalesController::class, 'receipt']);
    Route::post('/transactions/payment', [SalesController::class, 'payment'])->name('payments.create');
    Route::get('/transactions/report/{from}/{to?}', [SalesController::class, 'report'])->name('sales.report');
    Route::get('/transactions/excel', [NyotaController::class, 'export'])->defaults('resource', 'transactions');
    Route::get('/items/excel', [NyotaController::class, 'export'])->defaults('resource', 'items');
});

Route::middleware('nyota:Super')->group(function (): void {
    foreach (['category' => 'laad_', 'couts' => 'laad_', 'administrators' => 'laad_', 'eventlog' => 'lilt'] as $resource => $listing) {
        Route::get('/'.$resource, [NyotaController::class, 'page'])->defaults('resource', $resource)->name($resource);
        Route::get('/'.$resource.'/'.$listing.'/{page?}', [NyotaController::class, 'listing'])->defaults('resource', $resource)->whereNumber('page');
    }
    foreach (['category', 'couts', 'eventlog'] as $resource) {
        Route::get('/'.$resource.'/excel', [NyotaController::class, 'export'])->defaults('resource', $resource);
    }
    foreach (['audit', 'customers', 'reports', 'dbmanagement'] as $resource) {
        Route::get('/'.$resource, [NyotaController::class, 'page'])->defaults('resource', $resource)->name($resource);
    }
    Route::get('/misc/dbmanagement', [NyotaController::class, 'page'])->defaults('resource', 'dbmanagement');
    Route::get('/eventlog/approvisionnement', [NyotaController::class, 'page'])->defaults('resource', 'approvisionnement');
    foreach (['categorysearch' => 'category', 'adminsearch' => 'administrators', 'coutsSearch' => 'couts', 'eventsSearch' => 'eventlog'] as $search => $resource) {
        Route::get('/search/'.$search, [NyotaController::class, 'listing'])->defaults('resource', $resource);
    }
    foreach (['add', 'edit'] as $operation) {
        Route::post('/items/'.$operation, [CatalogController::class, 'item'])->defaults('operation', $operation);
    }
    Route::post('/items/updatestock', [CatalogController::class, 'stock']);
    foreach (['items', 'category', 'couts'] as $resource) {
        Route::post('/'.$resource.'/delete', [CatalogController::class, 'delete'])->defaults('resource', $resource);
    }
    foreach (['add', 'update'] as $operation) {
        Route::post('/category/'.$operation, [CatalogController::class, 'category'])->defaults('operation', $operation);
        Route::post('/couts/'.$operation, [CatalogController::class, 'cost'])->defaults('operation', $operation);
        Route::post('/administrators/'.$operation, [AccountsController::class, 'save'])->defaults('operation', $operation);
    }
    foreach (['suspend', 'delete'] as $operation) {
        Route::post('/administrators/'.$operation, [AccountsController::class, 'status'])->defaults('operation', $operation);
    }
    Route::post('/administrators/password_update', [AccountsController::class, 'password']);
    Route::post('/misc/dldb', [BackupController::class, 'create'])->middleware('throttle:2,1');
    Route::post('/backup/db_backup', [BackupController::class, 'create'])->middleware('throttle:2,1');
    Route::post('/misc/importdb', [BackupController::class, 'restore'])->middleware('throttle:2,1');
    Route::get('/backups/{id}/download', [BackupController::class, 'download'])->whereNumber('id')->name('backup.download');
    /** These screens returned no content in the source application. */
    Route::get('/supplier', fn () => response(''))->name('supplier');
    Route::get('/command', fn () => response(''))->name('command');
});
