<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin')) {
            Schema::create('admin', function (Blueprint $t): void {
                $t->id();
                $t->string('first_name', 25);
                $t->string('last_name', 25);
                $t->string('email')->unique();
                $t->string('mobile1', 20)->default('');
                $t->string('mobile2', 20)->default('');
                $t->string('password');
                $t->string('role', 5)->default('Basic');
                $t->dateTime('created_on')->useCurrent();
                $t->dateTime('last_login')->nullable();
                $t->dateTime('last_seen')->nullable();
                $t->timestamp('last_edited')->useCurrent();
                $t->char('account_status', 1)->default('1');
                $t->char('deleted', 1)->default('0');
            });
        }
        if (! Schema::hasColumn('admin', 'auth_version')) {
            Schema::table('admin', fn (Blueprint $t) => $t->unsignedInteger('auth_version')->default(0));
        }
        if (! Schema::hasTable('items')) {
            Schema::create('items', function (Blueprint $t): void {
                $t->id();
                $t->string('name', 50)->unique();
                $t->string('code', 50)->unique();
                $t->text('description')->nullable();
                $t->decimal('unitPrice', 12, 2);
                $t->integer('quantity');
                $t->dateTime('dateAdded')->useCurrent();
                $t->dateTime('lastUpdated')->useCurrent();
                $t->integer('min')->default(0);
            });
        }
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $t): void {
                $t->id();
                $t->string('nom');
                $t->string('description')->default('');
                $t->dateTime('created_on')->useCurrent();
            });
        }
        if (! Schema::hasTable('item_category')) {
            Schema::create('item_category', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('id_item')->index();
                $t->unsignedBigInteger('id_category')->index();
            });
        }
        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $t): void {
                $t->bigIncrements('transId');
                $t->string('ref', 10)->index();
                $t->string('itemName', 50);
                $t->string('itemCode', 50)->index();
                $t->text('description')->nullable();
                $t->integer('quantity');
                foreach (['unitPrice', 'totalPrice', 'totalMoneySpent', 'amountTendered', 'discount_amount', 'discount_percentage', 'vatPercentage', 'vatAmount', 'changeDue', 'pos', 'cash'] as $field) {
                    $t->decimal($field, 12, 2)->default(0);
                }
                $t->string('modeOfPayment', 20);
                $t->string('cust_name', 255)->nullable();
                $t->string('cust_phone', 30)->nullable();
                $t->string('cust_email')->nullable();
                $t->char('transType', 1)->default('1');
                $t->unsignedBigInteger('staffId')->index();
                $t->dateTime('transDate')->index();
                $t->dateTime('lastUpdated')->useCurrent();
                $t->char('cancelled', 1)->default('0');
            });
        } elseif (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('transactions', function (Blueprint $t): void {
                $t->decimal('pos', 12, 2)->nullable()->change();
                $t->decimal('cash', 12, 2)->nullable()->change();
            });
        }
        if (! Schema::hasTable('couts')) {
            Schema::create('couts', function (Blueprint $t): void {
                $t->bigIncrements('coutsId');
                $t->decimal('montant', 12, 2);
                $t->text('motif');
                $t->string('author');
                $t->date('date_sortie');
                $t->unsignedBigInteger('staffId');
                $t->dateTime('created_on')->useCurrent();
                $t->dateTime('lastUpdate')->useCurrent();
                $t->integer('deleted')->default(0);
            });
        }
        if (! Schema::hasTable('eventlog')) {
            Schema::create('eventlog', function (Blueprint $t): void {
                $t->id();
                $t->string('event', 200);
                $t->string('eventRowIdOrRef');
                $t->text('eventDesc');
                $t->string('eventTable');
                $t->unsignedBigInteger('staffInCharge');
                $t->dateTime('eventTime')->index();
            });
        }
        if (! Schema::hasTable('backups')) {
            Schema::create('backups', function (Blueprint $t): void {
                $t->id();
                $t->string('file_name');
                $t->string('file_url');
                $t->timestamp('date')->useCurrent();
                $t->boolean('online')->default(false);
            });
        }
        if (! Schema::hasTable('payements')) {
            Schema::create('payements', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('staff_id');
                $t->string('ref', 10)->index();
                $t->decimal('montant_percu', 12, 2);
                $t->decimal('montant_retourne', 12, 2);
                $t->decimal('montant_paye', 12, 2);
                $t->dateTime('created_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('nyota_requests')) {
            Schema::create('nyota_requests', function (Blueprint $t): void {
                $t->id();
                $t->string('request_key', 100)->unique();
                $t->unsignedBigInteger('actor_id');
                $t->string('payload_hash', 64);
                $t->text('result');
                $t->dateTime('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        throw new LogicException('Cette migration conserve les données historiques. Restaurer une sauvegarde vérifiée pour revenir en arrière.');
    }
};
