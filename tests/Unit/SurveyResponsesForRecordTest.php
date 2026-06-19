<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Lead\Models\Lead;
use App\Domain\Survey\Models\Survey;
use App\Domain\Survey\Models\SurveyResponse;
use App\Domain\User\Models\User;
use App\Support\Survey\SurveyResponsesForRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SurveyResponsesForRecordTest extends TestCase
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

        Schema::connection('tenant')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('lead_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('surveys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('title');
            $table->boolean('status')->default(true);
            $table->string('visibility')->default('public');
            $table->string('type')->default('custom');
            $table->timestamps();
        });

        Schema::connection('tenant')->create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->nullableMorphs('sourceable');
            $table->nullableMorphs('owner');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_contact_includes_sourceable_and_owner_responses(): void
    {
        $user = User::query()->create(['email' => 'staff@example.com']);
        $contact = Contact::query()->create(['email' => 'pat@example.com']);
        $survey = $this->makeSurvey($user);

        $viaSourceable = $this->makeResponse($survey, [
            'sourceable_type' => Contact::class,
            'sourceable_id' => $contact->id,
        ]);
        $viaOwner = $this->makeResponse($survey, [
            'owner_type' => Contact::class,
            'owner_id' => $contact->id,
        ]);
        $this->makeResponse($survey, [
            'sourceable_type' => Contact::class,
            'sourceable_id' => 999,
        ]);

        $ids = SurveyResponsesForRecord::forContact($contact)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$viaSourceable->id, $viaOwner->id], $ids);
    }

    public function test_lead_includes_owner_and_linked_contact_responses(): void
    {
        $user = User::query()->create(['email' => 'staff@example.com']);
        $contact = Contact::query()->create(['email' => 'pat@example.com']);
        $lead = Lead::query()->create(['contact_id' => $contact->id]);
        $survey = $this->makeSurvey($user);

        $viaLeadOwner = $this->makeResponse($survey, [
            'owner_type' => Lead::class,
            'owner_id' => $lead->id,
        ]);
        $viaContact = $this->makeResponse($survey, [
            'sourceable_type' => Contact::class,
            'sourceable_id' => $contact->id,
        ]);

        $ids = SurveyResponsesForRecord::forLead($lead)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$viaLeadOwner->id, $viaContact->id], $ids);
    }

    public function test_customer_uses_linked_contact_responses(): void
    {
        $user = User::query()->create(['email' => 'staff@example.com']);
        $contact = Contact::query()->create(['email' => 'pat@example.com']);
        $customer = Customer::query()->create(['contact_id' => $contact->id]);
        $survey = $this->makeSurvey($user);

        $response = $this->makeResponse($survey, [
            'sourceable_type' => Contact::class,
            'sourceable_id' => $contact->id,
        ]);

        $ids = SurveyResponsesForRecord::forCustomer($customer)->pluck('id')->all();

        $this->assertSame([$response->id], $ids);
    }

    protected function makeSurvey(User $user): Survey
    {
        return Survey::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => 'Test Survey',
            'status' => true,
            'visibility' => 'public',
            'type' => 'feedback',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeResponse(Survey $survey, array $attributes = []): SurveyResponse
    {
        return SurveyResponse::query()->create(array_merge([
            'survey_id' => $survey->id,
            'submitted_at' => now(),
        ], $attributes));
    }
}
