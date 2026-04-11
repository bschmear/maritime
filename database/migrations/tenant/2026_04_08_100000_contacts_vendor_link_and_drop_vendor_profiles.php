<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add primary_contact_id to vendors (nullable FK → contacts, null on delete)
        Schema::table('vendors', function (Blueprint $table) {
            $table->foreignId('primary_contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();
        });

        // Pivot table: a contact can belong to many vendors, a vendor can have many contacts
        Schema::create('contact_vendor', function (Blueprint $table) {
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->primary(['vendor_id', 'contact_id']);
            $table->index(['vendor_id', 'is_primary']);
        });

        if (Schema::hasTable('vendor_profiles')) {
            $this->migrateVendorProfilesToVendors();
            Schema::dropIfExists('vendor_profiles');
        }
    }

    private function migrateVendorProfilesToVendors(): void
    {
        $profiles = DB::table('vendor_profiles')->orderBy('id')->get();

        foreach ($profiles as $p) {
            $contact = DB::table('contacts')->where('id', $p->contact_id)->first();
            if (! $contact) {
                continue;
            }

            $displayName = $contact->company
                ?: trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''))
                ?: $contact->display_name
                ?: 'Vendor';

            $rating = null;
            if ($p->rating !== null) {
                $rating = (int) round((float) $p->rating);
                $rating = max(1, min(5, $rating));
            }

            $verifiedAt = null;
            if (! empty($p->verified_at)) {
                try {
                    $verifiedAt = \Carbon\Carbon::parse($p->verified_at)->toDateString();
                } catch (\Throwable) {
                    $verifiedAt = null;
                }
            }

            $vendorId = DB::table('vendors')->insertGetId([
                'display_name' => $displayName,
                'vendor_type' => null,
                'industry' => $p->industry,
                'vendor_code' => null,
                'tags' => null,
                'secondary_email' => null,
                'secondary_phone' => null,
                'preferred_contact_method' => null,
                'address_line_1' => null,
                'address_line_2' => null,
                'city' => null,
                'state' => null,
                'postal_code' => null,
                'country' => null,
                'latitude' => null,
                'longitude' => null,
                'status_id' => 1,
                'status_reason' => null,
                'assigned_user_id' => null,
                'notes' => $p->notes,
                'rating' => $rating,
                'payment_terms' => $p->payment_terms,
                'credit_limit' => $p->credit_limit,
                'website' => null,
                'linkedin' => null,
                'facebook' => null,
                'contract_start' => $p->contract_start,
                'contract_end' => $p->contract_end,
                'contract_status' => null,
                'is_verified' => (bool) $p->is_verified,
                'verified_at' => $verifiedAt,
                'primary_contact_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Link contact to vendor via pivot (migrated profile contact is the primary)
            DB::table('contact_vendor')->insert([
                'vendor_id' => $vendorId,
                'contact_id' => $p->contact_id,
                'is_primary' => true,
            ]);

            // Set as primary contact
            DB::table('vendors')->where('id', $vendorId)->update([
                'primary_contact_id' => $p->contact_id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_vendor');

        if (Schema::hasTable('vendors')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropForeign(['primary_contact_id']);
                $table->dropColumn('primary_contact_id');
            });
        }
    }
};
