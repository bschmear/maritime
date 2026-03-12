<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('estimates', function (Blueprint $table) {

            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('sequence')->unique();

            $table->foreignId('opportunity_id')
            ->nullable()
            ->constrained()
            ->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // salesperson

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();

            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_postal')->nullable();
            $table->string('billing_country')->nullable();

            $table->decimal('tax_rate', 6, 3)->nullable();
            $table->string('tax_jurisdiction')->nullable();

            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            // $table->foreignId('primary_version_id')
            //     ->nullable()
            //     ->constrained('estimate_versions')
            //     ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('estimate_versions', function (Blueprint $table) {

            $table->id();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();

            $table->foreignId('copied_from_version_id')
                ->nullable()
                ->constrained('estimate_versions')
                ->nullOnDelete();

            $table->unsignedInteger('version');

            $table->string('status')->default('draft');
            // draft
            // sent
            // viewed
            // negotiation
            // approved
            // rejected
            // expired
            $table->decimal('tax_rate', 6, 3)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->boolean('is_primary')->default(false);

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();

            $table->unique(['estimate_id', 'version']);
        });

        Schema::create('estimate_line_items', function (Blueprint $table) {

            $table->id();
            $table->foreignId('estimate_version_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->nullableMorphs('itemable');

            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->integer('quantity')->default(1);

            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);

            $table->integer('position')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
