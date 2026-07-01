<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->foreignId('subsidiary_id')->nullable()->constrained('subsidiaries')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->json('from_address');
            $table->json('to_address');
            $table->json('parcel');
            $table->string('easypost_shipment_id')->nullable();
            $table->string('easypost_tracker_id')->nullable();
            $table->string('carrier')->nullable();
            $table->string('service')->nullable();
            $table->unsignedInteger('rate_cents')->nullable();
            $table->string('tracking_code')->nullable();
            $table->text('label_url')->nullable();
            $table->text('public_tracking_url')->nullable();
            $table->json('rates_snapshot')->nullable();
            $table->json('tracking_events')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('easypost_shipment_id');
            $table->index('easypost_tracker_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
