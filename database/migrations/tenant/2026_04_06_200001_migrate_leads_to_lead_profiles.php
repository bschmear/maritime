<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        $now = now();

        $leads = DB::table('leads')->orderBy('id')->get();
        foreach ($leads as $lead) {
            $contactId = DB::table('contacts')->insertGetId([
                'display_name' => $lead->display_name,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'type' => 'person',
                'email' => $lead->email,
                'secondary_email' => $lead->secondary_email,
                'phone' => $lead->phone,
                'mobile' => $lead->mobile,
                'company' => $lead->company,
                'title' => $lead->title,
                'position' => $lead->position,
                'assigned_user_id' => $lead->assigned_user_id,
                'preferred_contact_method' => $lead->preferred_contact_method,
                'preferred_contact_time' => $lead->preferred_contact_time,
                'source' => null,
                'status' => $lead->inactive ? 'inactive' : 'active',
                'inactive' => (bool) $lead->inactive,
                'website' => $lead->website,
                'linkedin' => $lead->linkedin,
                'facebook' => $lead->facebook,
                'notes' => $lead->notes,
                'created_at' => $lead->created_at ?? $now,
                'updated_at' => $lead->updated_at ?? $now,
            ]);

            $hasAddress = $lead->address_line_1 || $lead->address_line_2 || $lead->city
                || $lead->state || $lead->postal_code || $lead->country
                || $lead->latitude !== null || $lead->longitude !== null;

            if ($hasAddress) {
                DB::table('contact_addresses')->insert([
                    'contact_id' => $contactId,
                    'label' => null,
                    'is_primary' => true,
                    'address_line_1' => $lead->address_line_1,
                    'address_line_2' => $lead->address_line_2,
                    'city' => $lead->city,
                    'state' => $lead->state,
                    'postal_code' => $lead->postal_code,
                    'country' => $lead->country,
                    'latitude' => $lead->latitude,
                    'longitude' => $lead->longitude,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('lead_profiles')->insert([
                'id' => $lead->id,
                'contact_id' => $contactId,
                'status_id' => $lead->status_id ?? 1,
                'lead_score' => $lead->lead_score,
                'lead_status' => null,
                'qualification_stage' => null,
                'is_qualified' => false,
                'qualified_at' => null,
                'assigned_user_id' => $lead->assigned_user_id,
                'priority_id' => $lead->priority_id,
                'last_contacted_at' => $lead->last_contacted_at,
                'next_followup_at' => $lead->next_followup_at,
                'contact_attempts' => 0,
                'source_id' => $lead->source_id,
                'source_details' => $lead->source_details,
                'referrer' => $lead->referrer,
                'campaign' => $lead->campaign,
                'medium' => $lead->medium,
                'utm_source' => $lead->utm_source,
                'utm_medium' => $lead->utm_medium,
                'utm_campaign' => $lead->utm_campaign,
                'utm_term' => $lead->utm_term,
                'utm_content' => $lead->utm_content,
                'purchase_timeline' => $lead->purchase_timeline,
                'budget_range' => $lead->budget_range ?? 1,
                'budget_min' => $lead->budget_min,
                'budget_max' => $lead->budget_max,
                'interested_model' => $lead->interested_model,
                'has_trade_in' => (bool) $lead->has_trade_in,
                'trade_in_value' => $lead->trade_in_value,
                'marketing_opt_in' => (bool) $lead->marketing_opt_in,
                'opted_in_at' => null,
                'notes' => null,
                'converted' => (bool) $lead->converted,
                'converted_at' => $lead->converted_at,
                'converted_customer_id' => $lead->converted_customer_id,
                'created_by_user_id' => null,
                'last_updated_by_user_id' => null,
                'latest_score_id' => null,
                'latest_score' => null,
                'created_at' => $lead->created_at ?? $now,
                'updated_at' => $lead->updated_at ?? $now,
            ]);
        }

        $this->syncAutoIncrement('contacts');
        $this->syncAutoIncrement('lead_profiles');

        Schema::table('qualifications', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
        });

        Schema::rename('leads', 'leads_legacy');

        Schema::table('qualifications', function (Blueprint $table) {
            $table->foreign('lead_id')->references('id')->on('lead_profiles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        throw new \RuntimeException('This migration cannot be reversed safely; restore from backup if needed.');
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
