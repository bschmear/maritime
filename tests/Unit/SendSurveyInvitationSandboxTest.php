<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Mail\SurveyInvitationMail;
use App\Services\Mail\TenantMailService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendSurveyInvitationSandboxTest extends TestCase
{
    #[Test]
    public function survey_invitation_mail_is_not_sandbox_exempt(): void
    {
        $service = new TenantMailService;
        $this->assertFalse($service->isExemptMailableClass(SurveyInvitationMail::class));
    }
}
