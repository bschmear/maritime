<?php

namespace Tests\Unit;

use App\Mail\AccountInvitation;
use App\Mail\ContactPortalLink;
use App\Mail\DocumentRequestMail;
use App\Mail\InvoiceViewRequest;
use App\Mail\ServiceTicketApprovalNotification;
use App\Mail\ServiceTicketApproved;
use App\Mail\SurveyInvitationMail;
use App\Mail\VendorPortalLink;
use App\Services\Mail\TenantMailService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Mail\Mailable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantMailServiceTest extends TestCase
{
    #[Test]
    public function internal_staff_notifications_are_sandbox_exempt(): void
    {
        $service = new TenantMailService;

        $this->assertTrue($service->isExemptMailableClass(AccountInvitation::class));
        $this->assertTrue($service->isExemptMailableClass(ServiceTicketApprovalNotification::class));
        $this->assertFalse($service->isExemptMailableClass(ContactPortalLink::class));
        $this->assertFalse($service->isExemptMailableClass(VendorPortalLink::class));
        $this->assertFalse($service->isExemptMailableClass(DocumentRequestMail::class));
        $this->assertFalse($service->isExemptMailableClass(InvoiceViewRequest::class));
        $this->assertFalse($service->isExemptMailableClass(SurveyInvitationMail::class));
        $this->assertFalse($service->isExemptMailableClass(ServiceTicketApproved::class));
        $this->assertTrue($service->isExemptMailableClass(\App\Mail\EstimateBoatOptionsSubmittedMail::class));
        $this->assertFalse($service->isExemptMailableClass(\App\Mail\EstimateBoatOptionsInvite::class));
    }

    #[Test]
    public function resolve_blocks_customer_recipient_in_sandbox_without_staff_actor(): void
    {
        $service = $this->partialMock(TenantMailService::class, function ($mock) {
            $mock->shouldReceive('isSandboxActive')->andReturn(true);
        });

        $resolved = $service->resolveRecipients(
            'customer@example.com',
            $this->mailableStub(ServiceTicketApproved::class),
        );

        $this->assertSame([], $resolved);
    }

    #[Test]
    public function resolve_returns_intended_recipients_when_sandbox_is_inactive(): void
    {
        $service = $this->partialMock(TenantMailService::class, function ($mock) {
            $mock->shouldReceive('isSandboxActive')->andReturn(false);
        });

        $resolved = $service->resolveRecipients(
            ['vendor@example.com', ''],
            $this->mailableStub(InvoiceViewRequest::class),
        );

        $this->assertSame(['vendor@example.com'], $resolved);
    }

    #[Test]
    public function resolve_redirects_to_actor_when_sandbox_is_active(): void
    {
        $service = $this->partialMock(TenantMailService::class, function ($mock) {
            $mock->shouldReceive('isSandboxActive')->andReturn(true);
        });

        $actor = new class implements Authenticatable
        {
            public string $email = 'staff@example.com';

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): mixed
            {
                return 1;
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): ?string
            {
                return null;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }
        };

        $resolved = $service->resolveRecipients(
            'customer@example.com',
            $this->mailableStub(InvoiceViewRequest::class),
            $actor,
        );

        $this->assertSame(['staff@example.com'], $resolved);
    }

    #[Test]
    public function display_recipient_adds_sandbox_suffix_when_redirected(): void
    {
        $service = $this->partialMock(TenantMailService::class, function ($mock) {
            $mock->shouldReceive('isSandboxActive')->andReturn(true);
        });

        $actor = new class implements Authenticatable
        {
            public string $email = 'staff@example.com';

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): mixed
            {
                return 1;
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): ?string
            {
                return null;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }
        };

        $label = $service->displayRecipient(
            'customer@example.com',
            $this->mailableStub(InvoiceViewRequest::class),
            $actor,
        );

        $this->assertSame('staff@example.com (sandbox — you)', $label);
    }

    /**
     * @param  class-string<Mailable>  $class
     */
    private function mailableStub(string $class): Mailable
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
