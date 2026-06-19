<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Communication\Actions\CreateCommunication;
use App\Domain\Contact\Models\Contact;
use App\Domain\Lead\Models\Lead;
use App\Domain\Notification\Actions\CreateNotification;
use App\Domain\Survey\Actions\SendSurveyInvitation;
use App\Domain\Survey\Models\Survey;
use App\Domain\Survey\Models\SurveyInvitation;
use App\Domain\Survey\Models\SurveyResponse;
use App\Domain\User\Models\User;
use App\Enums\Surveys\InvitationStatus;
use App\Http\Controllers\Tenant\Surveys\PublicSurveyController;
use App\Http\Controllers\Tenant\Surveys\SurveyController;
use App\Jobs\ProcessSurveyResponse;
use App\Jobs\SendSurveyInvitationJob;
use App\Mail\SurveyAssigneeNewResponse;
use App\Services\Mail\TenantMailService;
use App\Services\SMS\SmsService;
use App\Support\Survey\SurveyRecordResolver;
use App\Tenancy\CurrentTenantProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class SurveyInvitationFeatureTest extends TestCase
{
    protected User $staff;

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
            $table->string('display_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->unsignedBigInteger('current_role')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('secondary_email')->nullable();
            $table->string('mobile')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('contact_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('lead_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('surveys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('feedback');
            $table->string('visibility')->default('public');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('survey_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('record_type', 32);
            $table->unsignedBigInteger('record_id');
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('recipient_email');
            $table->string('recipient_mobile')->nullable();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->unsignedBigInteger('sent_by_user_id')->nullable();
            $table->unsignedBigInteger('sent_by_web_user_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->boolean('send_email')->default(true);
            $table->boolean('send_sms')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('email')->nullable();
            $table->string('owner_type')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();
        });

        $this->staff = User::query()->create([
            'display_name' => 'Staff User',
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => 'staff@example.com',
        ]);

        $this->actingAsTenantUser($this->staff);
    }

    public function test_send_to_record_creates_invitation_and_dispatches_job(): void
    {
        Bus::fake();

        $contact = Contact::query()->create([
            'display_name' => 'Pat Contact',
            'email' => 'pat@example.com',
            'assigned_user_id' => $this->staff->id,
        ]);

        $survey = Survey::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->staff->id,
            'title' => 'Feedback survey',
            'type' => 'feedback',
            'status' => true,
        ]);

        $controller = app(SurveyController::class);
        $response = $controller->sendToRecord(
            Request::create('/surveys/send-to-record', 'POST', [
                'survey_uuid' => $survey->uuid,
                'record_type' => 'contact',
                'record_id' => $contact->id,
                'delivery' => 'email',
            ]),
            app(SurveyRecordResolver::class),
            app(SmsService::class),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('survey_invitations', [
            'survey_id' => $survey->id,
            'record_type' => 'contact',
            'record_id' => $contact->id,
            'recipient_email' => 'pat@example.com',
            'status' => InvitationStatus::Scheduled->value,
        ], 'tenant');

        Bus::assertDispatched(SendSurveyInvitationJob::class);
    }

    public function test_send_to_record_schedules_job_for_later(): void
    {
        Bus::fake();

        $contact = Contact::query()->create([
            'email' => 'later@example.com',
        ]);

        $survey = Survey::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->staff->id,
            'title' => 'Later survey',
            'status' => true,
        ]);

        $sendAt = now()->addDay()->toIso8601String();

        $controller = app(SurveyController::class);
        $response = $controller->sendToRecord(
            Request::create('/surveys/send-to-record', 'POST', [
                'survey_uuid' => $survey->uuid,
                'record_type' => 'contact',
                'record_id' => $contact->id,
                'delivery' => 'email',
                'send_at' => $sendAt,
            ]),
            app(SurveyRecordResolver::class),
            app(SmsService::class),
        );

        $this->assertSame(200, $response->getStatusCode());

        $invitation = SurveyInvitation::query()->first();
        $this->assertNotNull($invitation?->scheduled_at);
        Bus::assertDispatched(SendSurveyInvitationJob::class);
    }

    public function test_cancel_scheduled_invitation_prevents_send(): void
    {
        $contact = Contact::query()->create(['email' => 'cancel@example.com']);
        $survey = Survey::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->staff->id,
            'title' => 'Cancel test',
            'status' => true,
        ]);

        $invitation = SurveyInvitation::query()->create([
            'survey_id' => $survey->id,
            'record_type' => 'contact',
            'record_id' => $contact->id,
            'contact_id' => $contact->id,
            'recipient_email' => 'cancel@example.com',
            'sent_by_user_id' => $this->staff->id,
            'status' => InvitationStatus::Scheduled,
            'send_email' => true,
            'send_sms' => false,
        ]);

        $controller = app(SurveyController::class);
        $cancelResponse = $controller->cancelInvitation((int) $invitation->id);
        $this->assertSame(200, $cancelResponse->getStatusCode());

        $mail = $this->createMock(TenantMailService::class);
        $mail->expects($this->never())->method('send');

        $action = new SendSurveyInvitation($mail, app(SmsService::class));
        $result = $action($invitation->fresh());

        $this->assertFalse($result['success']);
        $this->assertSame(InvitationStatus::Cancelled, $invitation->fresh()->status);
    }

    public function test_destroy_cancelled_invitation_deletes_record(): void
    {
        $contact = Contact::query()->create(['email' => 'delete@example.com']);
        $survey = Survey::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->staff->id,
            'title' => 'Delete test',
            'status' => true,
        ]);

        $invitation = SurveyInvitation::query()->create([
            'survey_id' => $survey->id,
            'record_type' => 'contact',
            'record_id' => $contact->id,
            'contact_id' => $contact->id,
            'recipient_email' => 'delete@example.com',
            'sent_by_user_id' => $this->staff->id,
            'status' => InvitationStatus::Cancelled,
            'send_email' => true,
            'send_sms' => false,
        ]);

        $controller = app(SurveyController::class);
        $response = $controller->destroyInvitation((int) $invitation->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseMissing('survey_invitations', ['id' => $invitation->id], 'tenant');
    }

    public function test_destroy_rejects_non_cancelled_invitation(): void
    {
        $contact = Contact::query()->create(['email' => 'nodelete@example.com']);
        $survey = Survey::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->staff->id,
            'title' => 'No delete test',
            'status' => true,
        ]);

        $invitation = SurveyInvitation::query()->create([
            'survey_id' => $survey->id,
            'record_type' => 'contact',
            'record_id' => $contact->id,
            'contact_id' => $contact->id,
            'recipient_email' => 'nodelete@example.com',
            'sent_by_user_id' => $this->staff->id,
            'status' => InvitationStatus::Sent,
            'send_email' => true,
            'send_sms' => false,
        ]);

        $controller = app(SurveyController::class);
        $response = $controller->destroyInvitation((int) $invitation->id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertDatabaseHas('survey_invitations', ['id' => $invitation->id], 'tenant');
    }

    public function test_resolve_assigned_to_uses_lead_owner_assignee(): void
    {
        $assignee = User::query()->create([
            'display_name' => 'Assignee',
            'first_name' => 'A',
            'last_name' => 'User',
            'email' => 'assignee@example.com',
        ]);

        $contact = Contact::query()->create(['email' => 'owner@example.com']);
        $lead = Lead::query()->create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $assignee->id,
        ]);

        $controller = new PublicSurveyController;
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('assignedUserIdFromOwner');
        $method->setAccessible(true);

        $assignedTo = $method->invoke(
            $controller,
            Lead::class,
            (int) $lead->id,
        );

        $this->assertSame((int) $assignee->id, $assignedTo);
    }

    public function test_process_survey_response_notifies_assignee(): void
    {
        Mail::fake();

        $assignee = User::query()->create([
            'display_name' => 'Notify Me',
            'first_name' => 'Notify',
            'last_name' => 'Me',
            'email' => 'notify@example.com',
        ]);

        $survey = Survey::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->staff->id,
            'title' => 'Notify survey',
            'status' => true,
        ]);

        $response = SurveyResponse::query()->create([
            'survey_id' => $survey->id,
            'assigned_to' => $assignee->id,
            'email' => null,
        ]);

        $job = new ProcessSurveyResponse($survey, $response);
        $job->handle(
            $this->createMock(CreateNotification::class),
            $this->createMock(CreateCommunication::class),
        );

        Mail::assertSent(SurveyAssigneeNewResponse::class, function ($mail) use ($assignee) {
            return $mail->hasTo($assignee->email);
        });
    }

    protected function actingAsTenantUser(User $tenantUser): void
    {
        $mock = $this->mock(CurrentTenantProfile::class);
        $mock->shouldReceive('profile')->andReturn($tenantUser);
        $this->app->instance(CurrentTenantProfile::class, $mock);
    }
}
