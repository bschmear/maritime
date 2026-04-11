<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $this->dropForeignKeysReferencingCustomers();

        $now = now();

        foreach (DB::table('customers')->orderBy('id')->get() as $customer) {
            $contactId = DB::table('contacts')->insertGetId([
                'display_name' => $customer->display_name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'type' => 'person',
                'email' => $customer->email,
                'secondary_email' => $customer->secondary_email,
                'phone' => $customer->phone,
                'mobile' => $customer->mobile,
                'company' => $customer->company,
                'title' => $customer->title,
                'position' => $customer->position,
                'assigned_user_id' => $customer->assigned_user_id,
                'preferred_contact_method' => $customer->preferred_contact_method,
                'preferred_contact_time' => $customer->preferred_contact_time,
                'source' => null,
                'status' => $customer->inactive ? 'inactive' : 'active',
                'inactive' => (bool) $customer->inactive,
                'website' => $customer->website,
                'linkedin' => $customer->linkedin,
                'facebook' => $customer->facebook,
                'notes' => $customer->notes,
                'created_at' => $customer->created_at ?? $now,
                'updated_at' => $customer->updated_at ?? $now,
            ]);

            $hasAddress = $customer->address_line_1 || $customer->address_line_2 || $customer->city
                || $customer->state || $customer->postal_code || $customer->country
                || $customer->latitude !== null || $customer->longitude !== null;

            if ($hasAddress) {
                DB::table('contact_addresses')->insert([
                    'contact_id' => $contactId,
                    'label' => null,
                    'is_primary' => true,
                    'address_line_1' => $customer->address_line_1,
                    'address_line_2' => $customer->address_line_2,
                    'city' => $customer->city,
                    'state' => $customer->state,
                    'postal_code' => $customer->postal_code,
                    'country' => $customer->country,
                    'latitude' => $customer->latitude,
                    'longitude' => $customer->longitude,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('customer_profiles')->insert([
                'id' => $customer->id,
                'contact_id' => $contactId,
                'password' => $customer->password ?? null,
                'email_verified_at' => $customer->email_verified_at ?? null,
                'remember_token' => $customer->remember_token ?? null,
                'account_status' => null,
                'customer_type' => null,
                'tier' => null,
                'status_id' => $customer->status_id ?? 1,
                'priority_id' => $customer->priority_id,
                'assigned_user_id' => $customer->assigned_user_id,
                'credit_limit' => null,
                'current_balance' => 0,
                'payment_terms' => null,
                'payment_method' => null,
                'currency' => 'USD',
                'tax_id' => null,
                'tax_exempt' => false,
                'tax_exempt_reason' => null,
                'billing_email' => null,
                'purchase_order_required' => null,
                'first_purchase_at' => null,
                'last_purchase_at' => null,
                'contract_start' => null,
                'contract_end' => null,
                'auto_renew' => false,
                'lifetime_value' => 0,
                'total_orders' => 0,
                'average_order_value' => null,
                'loyalty_points' => 0,
                'converted_from_lead_id' => null,
                'source_id' => $customer->source_id,
                'referrer' => $customer->referrer,
                'notes' => null,
                'lead_score' => $customer->lead_score,
                'campaign' => $customer->campaign,
                'medium' => $customer->medium,
                'source_details' => $customer->source_details,
                'last_contacted_at' => $customer->last_contacted_at,
                'next_followup_at' => $customer->next_followup_at,
                'purchase_timeline' => $customer->purchase_timeline,
                'budget_min' => $customer->budget_min,
                'budget_max' => $customer->budget_max,
                'interested_model' => $customer->interested_model,
                'has_trade_in' => (bool) $customer->has_trade_in,
                'trade_in_value' => $customer->trade_in_value,
                'marketing_opt_in' => (bool) $customer->marketing_opt_in,
                'utm_source' => $customer->utm_source,
                'utm_medium' => $customer->utm_medium,
                'utm_campaign' => $customer->utm_campaign,
                'utm_term' => $customer->utm_term,
                'utm_content' => $customer->utm_content,
                'website' => null,
                'linkedin' => null,
                'facebook' => null,
                'created_by_user_id' => null,
                'last_updated_by_user_id' => null,
                'latest_score_id' => null,
                'latest_score' => null,
                'created_at' => $customer->created_at ?? $now,
                'updated_at' => $customer->updated_at ?? $now,
            ]);
        }

        $this->syncAutoIncrement('contacts');
        $this->syncAutoIncrement('customer_profiles');

        Schema::rename('customers', 'customers_legacy');

        $this->createForeignKeysToCustomerProfiles();
    }

    public function down(): void
    {
        throw new \RuntimeException('This migration cannot be reversed safely; restore from backup if needed.');
    }

    private function dropForeignKeysReferencingCustomers(): void
    {
        $map = [
            'lead_profiles' => 'converted_customer_id',
            'opportunities' => 'customer_id',
            'estimates' => 'customer_id',
            'transactions' => 'customer_id',
            'portal_accesses' => 'customer_id',
            'service_tickets' => 'customer_id',
        ];

        foreach ($map as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->dropForeign([$column]);
                });
            } catch (\Throwable) {
                //
            }
        }
    }

    private function createForeignKeysToCustomerProfiles(): void
    {
        if (Schema::hasTable('lead_profiles')) {
            Schema::table('lead_profiles', function (Blueprint $table) {
                $table->foreign('converted_customer_id')
                    ->references('id')
                    ->on('customer_profiles')
                    ->nullOnDelete();
            });
        }

        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('portal_accesses', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer_profiles')
                ->cascadeOnDelete();
        });

        Schema::table('service_tickets', function (Blueprint $table) {
            $table->foreign('customer_id')
                ->references('id')
                ->on('customer_profiles')
                ->cascadeOnDelete();
        });
    }

    private function syncAutoIncrement(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $max = (int) (DB::table($table)->max('id') ?? 0);
        $next = $max + 1;

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `'.$table.'` AUTO_INCREMENT = '.$next);
        }

        if ($driver === 'sqlite' && $max > 0) {
            $row = DB::selectOne('SELECT 1 as ok FROM sqlite_sequence WHERE name = ?', [$table]);
            if ($row) {
                DB::update('UPDATE sqlite_sequence SET seq = ? WHERE name = ?', [$max, $table]);
            } else {
                DB::insert('INSERT INTO sqlite_sequence (name, seq) VALUES (?, ?)', [$table, $max]);
            }
        }
    }
};
