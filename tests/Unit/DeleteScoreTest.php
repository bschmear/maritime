<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Lead\Models\Lead;
use App\Domain\Score\Actions\DeleteScore;
use App\Domain\Score\Models\Score;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeleteScoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
        ]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('scores', function (Blueprint $table) {
            $table->id();
            $table->morphs('scorable');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('assigned_id')->nullable();
            $table->string('score_type')->default('manual');
            $table->decimal('score_value', 5, 2)->default(0);
            $table->json('meta')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('lead_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('latest_score_id')->nullable()->constrained('scores')->nullOnDelete();
            $table->decimal('latest_score', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function test_delete_current_score_clears_and_promotes_next_latest_score(): void
    {
        $contact = Contact::query()->create(['email' => 'lead@example.com']);
        $lead = Lead::query()->create(['contact_id' => $contact->id]);

        $older = Score::query()->create([
            'scorable_type' => Lead::class,
            'scorable_id' => $lead->id,
            'score_type' => 'manual',
            'score_value' => 40,
            'is_current' => false,
            'meta' => [],
        ]);

        $current = Score::query()->create([
            'scorable_type' => Lead::class,
            'scorable_id' => $lead->id,
            'score_type' => 'behavior',
            'score_value' => 72,
            'is_current' => true,
            'meta' => ['breakdown' => []],
        ]);

        $lead->update([
            'latest_score_id' => $current->id,
            'latest_score' => $current->score_value,
        ]);

        $result = app(DeleteScore::class)($current->id);

        $this->assertTrue($result['success']);
        $this->assertNull(Score::query()->find($current->id));
        $this->assertTrue((bool) Score::query()->find($older->id)?->is_current);

        $lead = Lead::query()->setEagerLoads([])->findOrFail($lead->id);
        $this->assertSame($older->id, $lead->latest_score_id);
        $this->assertSame('40.00', (string) $lead->latest_score);
    }

    public function test_delete_only_score_clears_latest_score_on_lead(): void
    {
        $contact = Contact::query()->create(['email' => 'solo@example.com']);
        $lead = Lead::query()->create(['contact_id' => $contact->id]);

        $score = Score::query()->create([
            'scorable_type' => Lead::class,
            'scorable_id' => $lead->id,
            'score_type' => 'behavior',
            'score_value' => 55,
            'is_current' => true,
            'meta' => ['breakdown' => []],
        ]);

        $lead->update([
            'latest_score_id' => $score->id,
            'latest_score' => $score->score_value,
        ]);

        $result = app(DeleteScore::class)($score->id);

        $this->assertTrue($result['success']);

        $lead = Lead::query()->setEagerLoads([])->findOrFail($lead->id);
        $this->assertNull($lead->latest_score_id);
        $this->assertNull($lead->latest_score);
    }
}
