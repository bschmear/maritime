<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->json('recipients')->nullable();
            $table->string('email_subject');
            $table->longText('email_message');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $subject = 'Thanks for visiting {{ event_name }}';
        $message = '<p>Hi {{ lead_name }},</p>'
            .'<p>Thank you for stopping by our booth at <strong>{{ event_name }}</strong> at {{ event_venue }}. We appreciate your interest.</p>'
            .'<p>Here are the units you asked about:</p>'
            .'{{ selected_asset_list }}'
            .'<p>If you would like a sea trial, more photos, or pricing on any of these boats (or similar inventory), reply to this email or call us anytime.</p>'
            .'<p>Best regards,<br>'
            .'{{ salesperson_name }}<br>'
            .'{{ dealer_name }}</p>';

        DB::table('email_templates')->insert([
            'type' => 'boat_show_event_followup',
            'recipients' => null,
            'email_subject' => $subject,
            'email_message' => $message,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $templateId = (int) DB::table('email_templates')
            ->where('type', 'boat_show_event_followup')
            ->value('id');

        Schema::table('boat_show_events', function (Blueprint $table) {
            $table->boolean('auto_followup')->default(true);
            $table->unsignedInteger('delay_amount')->default(1);
            $table->string('delay_unit', 32)->default('days');
            $table->json('recipients')->nullable();
            $table->foreignId('email_template_id')
                ->nullable()
                ->constrained('email_templates')
                ->nullOnDelete();
        });

        // DB::table('boat_show_events')->update([
        //     'email_template_id' => $templateId,
        //     'auto_followup' => true,
        //     'delay_amount' => 1,
        //     'delay_unit' => 'days',
        // ]);
    }

    public function down(): void
    {
        Schema::table('boat_show_events', function (Blueprint $table) {
            $table->dropForeign(['email_template_id']);
            $table->dropColumn([
                'email_template_id',
                'recipients',
                'delay_unit',
                'delay_amount',
                'auto_followup',
            ]);
        });

        Schema::dropIfExists('email_templates');
    }
};
