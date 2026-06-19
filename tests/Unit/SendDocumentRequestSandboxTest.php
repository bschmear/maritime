<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\DocumentRequest\Actions\SendDocumentRequest;
use App\Domain\SystemLog\Models\SystemLog;
use App\Domain\User\Models\User as TenantUser;
use App\Enums\System\SystemLogAction;
use App\Mail\DocumentRequestMail;
use App\Models\User as WebUser;
use App\Services\Mail\TenantMailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SendDocumentRequestSandboxTest extends TestCase
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
            'database.connections.central_test' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
            'tenancy.database.central_connection' => 'central_test',
        ]);
        DB::purge('tenant');
        DB::purge('central_test');

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

        Schema::connection('tenant')->create('contact_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('account_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('sandbox_mode')->default(false);
            $table->boolean('sms_enabled')->default(false);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles')->cascadeOnDelete();
            $table->nullableMorphs('source');
            $table->foreignId('requested_by_user_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('loggable');
            $table->unsignedTinyInteger('action');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::connection('central_test')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function test_document_request_mail_redirects_to_staff_in_sandbox(): void
    {
        Mail::fake();

        $webUser = WebUser::on('central_test')->create(['email' => 'staff@example.com']);
        $this->actingAs($webUser);

        TenantUser::query()->create(['email' => 'staff@example.com']);

        DB::connection('tenant')->table('account_settings')->insert([
            'sandbox_mode' => true,
            'sms_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contact = Contact::query()->create(['email' => 'customer@example.com']);
        Customer::query()->create(['contact_id' => $contact->id]);

        $this->partialMock(TenantMailService::class, function ($mock) {
            $mock->shouldReceive('isSandboxActive')->andReturn(true);
        });

        $action = app(SendDocumentRequest::class);
        $result = $action($contact, 'Driver License', 'Please upload');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['email']['sandbox_mode']);
        $this->assertSame('customer@example.com', $result['email']['intended_recipient']);
        $this->assertStringContainsString('staff@example.com', $result['email']['delivery_recipient']);

        Mail::assertQueued(DocumentRequestMail::class, function (DocumentRequestMail $mail) {
            return $mail->hasTo('staff@example.com');
        });

        $this->assertSame(1, SystemLog::query()->count());
        $this->assertSame(SystemLogAction::Updated->value, SystemLog::query()->value('action'));
    }
}
