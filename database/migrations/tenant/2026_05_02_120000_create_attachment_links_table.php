<?php

declare(strict_types=1);

use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\WarrantyClaim\Models\WarrantyClaim;
use App\Domain\WorkOrder\Models\WorkOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_image_id')->constrained('inventory_images')->cascadeOnDelete();
            $table->morphs('attachable');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(
                ['inventory_image_id', 'attachable_type', 'attachable_id'],
                'attachment_links_image_attachable_unique'
            );
        });

        $linkableTypes = [
            ServiceTicket::class,
            WorkOrder::class,
            WarrantyClaim::class,
        ];

        DB::table('inventory_images')
            ->whereIn('imageable_type', $linkableTypes)
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('attachment_links')->insert([
                        'inventory_image_id' => $row->id,
                        'attachable_type' => $row->imageable_type,
                        'attachable_id' => $row->imageable_id,
                        'sort_order' => (int) ($row->sort_order ?? 0),
                        'is_primary' => (bool) ($row->is_primary ?? false),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_links');
    }
};
