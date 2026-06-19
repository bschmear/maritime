<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendors') && ! Schema::hasColumn('vendors', 'quickbooks_id')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('quickbooks_id', 64)->nullable()->unique()->after('vendor_code');
            });

            if (Schema::hasColumn('vendors', 'quickbooks_vendor_id')) {
                DB::table('vendors')
                    ->whereNotNull('quickbooks_vendor_id')
                    ->update(['quickbooks_id' => DB::raw('quickbooks_vendor_id')]);

                Schema::table('vendors', function (Blueprint $table) {
                    $table->dropUnique(['quickbooks_vendor_id']);
                    $table->dropColumn('quickbooks_vendor_id');
                });
            }
        }

        if (Schema::hasTable('bills') && ! Schema::hasColumn('bills', 'quickbooks_vendor_id')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->string('quickbooks_vendor_id', 64)->nullable()->after('vendor_id');
                $table->index('quickbooks_vendor_id');
            });
        }

        if (Schema::hasTable('billpayments') && ! Schema::hasColumn('billpayments', 'quickbooks_vendor_id')) {
            Schema::table('billpayments', function (Blueprint $table) {
                $table->string('quickbooks_vendor_id', 64)->nullable()->after('vendor_id');
                $table->index('quickbooks_vendor_id');
            });
        }

        if (Schema::hasTable('chart_of_accounts')) {
            return;
        }

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('quickbooks_account_id', 64)->nullable()->unique();
            $table->string('account_type')->nullable();
            $table->string('detail_type')->nullable();
            $table->string('fully_qualified_name')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();
            $table->bigInteger('sequence')->unique();

            $table->string('quickbooks_bill_id', 64)->nullable()->unique();
            $table->string('quickbooks_sync_token', 32)->nullable();

            $table->foreignId('vendor_id')
                ->nullable()
                ->constrained('vendors')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('quickbooks_vendor_id', 64)->nullable();

            $table->foreignId('chart_of_account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('doc_number')->nullable();
            $table->date('txn_date')->nullable();
            $table->date('due_date')->nullable();

            $table->string('ap_account_ref_id', 64)->nullable();
            $table->string('ap_account_ref_name')->nullable();
            $table->string('department_ref_id', 64)->nullable();
            $table->string('department_ref_name')->nullable();

            $table->decimal('total_amt', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);

            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 6)->nullable();

            $table->text('private_note')->nullable();

            $table->enum('status', ['open', 'overdue', 'paid', 'void'])->default('open')->index();

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('vendor_id');
            $table->index('quickbooks_vendor_id');
            $table->index('txn_date');
            $table->index('due_date');
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_id')
                ->constrained('bills')
                ->cascadeOnDelete();

            $table->string('quickbooks_line_id', 64)->nullable();

            $table->decimal('amount', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('detail_type')->nullable();

            $table->foreignId('chart_of_account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('expense_account_ref_id', 64)->nullable();
            $table->string('expense_account_ref_name')->nullable();
            $table->string('item_ref_id', 64)->nullable();
            $table->string('item_ref_name')->nullable();

            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
        });

        Schema::create('billpayments', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();
            $table->bigInteger('sequence')->unique();

            $table->string('quickbooks_bill_payment_id', 64)->nullable()->unique();
            $table->string('quickbooks_sync_token', 32)->nullable();

            $table->foreignId('vendor_id')
                ->nullable()
                ->constrained('vendors')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('quickbooks_vendor_id', 64)->nullable();

            $table->string('doc_number')->nullable();
            $table->date('txn_date')->nullable();
            $table->decimal('total_amt', 12, 2)->default(0);

            $table->string('pay_type', 32)->nullable();

            $table->string('ap_account_ref_id', 64)->nullable();
            $table->string('ap_account_ref_name')->nullable();
            $table->string('bank_account_ref_id', 64)->nullable();
            $table->string('bank_account_ref_name')->nullable();
            $table->string('cc_account_ref_id', 64)->nullable();
            $table->string('cc_account_ref_name')->nullable();
            $table->string('check_print_status', 32)->nullable();

            $table->string('currency_code', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 6)->nullable();

            $table->text('private_note')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('vendor_id');
            $table->index('quickbooks_vendor_id');
            $table->index('txn_date');
        });

        Schema::create('bill_payment_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_payment_id')
                ->constrained('billpayments')
                ->cascadeOnDelete();

            $table->foreignId('bill_id')
                ->nullable()
                ->constrained('bills')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('quickbooks_bill_id', 64)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payment_lines');
        Schema::dropIfExists('billpayments');
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('chart_of_accounts');

        if (Schema::hasTable('vendors') && Schema::hasColumn('vendors', 'quickbooks_id')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropUnique(['quickbooks_id']);
                $table->dropColumn('quickbooks_id');
            });
        }
    }
};
