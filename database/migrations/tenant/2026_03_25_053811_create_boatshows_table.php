<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boat_shows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->bigInteger('sequence')->nullable()->unique();

            $table->string('display_name'); // "Miami International Boat Show"
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->string('website')->nullable();

            $table->string('logo')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('boat_show_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->bigInteger('sequence')->unique();

            $table->foreignId('boat_show_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('display_name');
            $table->integer('year')->index();

            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();

            // Location
            $table->string('venue')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Optional dealer-specific info
            $table->string('booth')->nullable();

            $table->boolean('active')->default(true);

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['boat_show_id', 'year']);
        });

        Schema::create('boat_show_leads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('boat_show_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('boat_show_event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->morphs('leadable');

            $table->unsignedBigInteger('captured_by_id')->nullable();
            $table->timestamp('captured_at')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['boat_show_id']);
            $table->index(['boat_show_event_id']);
        });

        Schema::create('boat_show_layouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('boat_show_event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name')->nullable(); // "Main Floor", "Dock A"

            // Space dimensions (feet)
            $table->integer('width_ft');  // SPACE_W
            $table->integer('height_ft'); // SPACE_H

            // Optional config
            $table->integer('grid_size')->default(1); // 1ft grid (future flexibility)
            $table->integer('scale')->default(10);    // matches your SCALE

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['boat_show_event_id']);
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::dropIfExists('boat_show_layouts');
        Schema::dropIfExists('boat_show_leads');
        Schema::dropIfExists('boat_show_events');
        Schema::dropIfExists('boat_shows');
    }
};
