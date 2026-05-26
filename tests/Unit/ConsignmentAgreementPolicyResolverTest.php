<?php

namespace Tests\Unit;

use App\Domain\ConsignmentAgreement\Models\ConsignmentAgreement;
use App\Domain\ConsignmentAgreement\Support\ConsignmentAgreementPolicyResolver;
use Carbon\Carbon;
use Tests\TestCase;

class ConsignmentAgreementPolicyResolverTest extends TestCase
{
    public function test_signed_agreement_uses_snapshot_not_live_list(): void
    {
        $agreement = new ConsignmentAgreement([
            'signed_at' => Carbon::parse('2026-01-01 12:00:00'),
            'policies_snapshot' => [
                ['id' => 99, 'body' => 'Locked policy text', 'sort_order' => 0],
            ],
        ]);

        $policies = ConsignmentAgreementPolicyResolver::forAgreement($agreement);

        $this->assertCount(1, $policies);
        $this->assertSame('Locked policy text', $policies[0]['body']);
        $this->assertSame(99, $policies[0]['id']);
    }

    public function test_unsigned_agreement_does_not_use_snapshot(): void
    {
        $agreement = new ConsignmentAgreement([
            'signed_at' => null,
            'policies_snapshot' => [
                ['id' => 1, 'body' => 'Should be ignored', 'sort_order' => 0],
            ],
        ]);

        $this->assertFalse(ConsignmentAgreementPolicyResolver::policiesAreLocked($agreement));
    }

    public function test_policies_are_locked_when_signed(): void
    {
        $agreement = new ConsignmentAgreement([
            'signed_at' => now(),
        ]);

        $this->assertTrue(ConsignmentAgreementPolicyResolver::policiesAreLocked($agreement));
    }
}
