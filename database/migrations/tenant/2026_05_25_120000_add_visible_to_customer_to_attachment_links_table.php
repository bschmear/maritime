<?php

declare(strict_types=1);

use App\Domain\ServiceTicket\Models\ServiceTicket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachment_links', function (Blueprint $table) {
            $table->boolean('visible_to_customer')->default(false)->after('is_primary');
        });

        // Preserve existing portal behavior: tickets already linked stay customer-visible.
        DB::table('attachment_links')
            ->where('attachable_type', ServiceTicket::class)
            ->update(['visible_to_customer' => true]);
    }

    public function down(): void
    {
        Schema::table('attachment_links', function (Blueprint $table) {
            $table->dropColumn('visible_to_customer');
        });
    }
};
