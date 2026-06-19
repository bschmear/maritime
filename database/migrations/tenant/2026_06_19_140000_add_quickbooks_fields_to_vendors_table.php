<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            return;
        }

        if (! Schema::hasColumn('vendors', 'quickbooks_sync_token')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('quickbooks_sync_token', 32)->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'company_name')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('company_name')->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'print_on_check_name')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('print_on_check_name')->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'qbo_acct_num')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('qbo_acct_num', 64)->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'qbo_active')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->boolean('qbo_active')->default(true);
            });
        }

        if (! Schema::hasColumn('vendors', 'open_balance')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->decimal('open_balance', 12, 2)->default(0);
            });
        }

        if (! Schema::hasColumn('vendors', 'overdue_balance')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->decimal('overdue_balance', 12, 2)->default(0);
            });
        }

        if (! Schema::hasColumn('vendors', 'vendor_1099')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->boolean('vendor_1099')->default(false);
            });
        }

        if (! Schema::hasColumn('vendors', 'term_ref_id')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('term_ref_id', 64)->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'term_ref_name')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('term_ref_name')->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'contact_title')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('contact_title')->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'mobile_phone')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('mobile_phone', 50)->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'fax')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('fax', 50)->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'ach_bank_name')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('ach_bank_name')->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'ach_account_number')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->text('ach_account_number')->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'ach_routing_number')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->text('ach_routing_number')->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'tax_identifier')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->text('tax_identifier')->nullable();
            });
        }

        if (! Schema::hasColumn('vendors', 'quickbooks_id') && ! Schema::hasColumn('vendors', 'quickbooks_vendor_id')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('quickbooks_id', 64)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendors')) {
            return;
        }

        $columns = [
            'quickbooks_sync_token',
            'company_name',
            'print_on_check_name',
            'qbo_acct_num',
            'qbo_active',
            'open_balance',
            'overdue_balance',
            'vendor_1099',
            'term_ref_id',
            'term_ref_name',
            'contact_title',
            'mobile_phone',
            'fax',
            'ach_bank_name',
            'ach_account_number',
            'ach_routing_number',
            'tax_identifier',
        ];

        $existing = array_filter($columns, fn (string $col) => Schema::hasColumn('vendors', $col));

        if ($existing !== []) {
            Schema::table('vendors', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
