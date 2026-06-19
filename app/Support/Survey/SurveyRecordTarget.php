<?php

namespace App\Support\Survey;

final class SurveyRecordTarget
{
    public function __construct(
        public string $recordType,
        public int $recordId,
        public int $contactId,
        public ?string $email,
        public ?string $mobile,
        public ?int $assignedUserId,
        public string $signedRecipientType,
        public int $signedRecipientId,
        public ?string $displayName,
    ) {}
}
