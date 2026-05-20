<?php

namespace App\Domain\Qualification\Actions;

use App\Domain\Qualification\Models\Qualification;

class SyncQualificationNote
{
    public function __invoke(Qualification $qualification, mixed $body): void
    {
        $body = is_string($body) ? trim($body) : '';
        $note = $qualification->notes()->latest('id')->first();

        if ($body === '') {
            if ($note) {
                $note->delete();
            }

            return;
        }

        $userId = auth()->id();

        if ($note) {
            $note->update([
                'body' => $body,
                'user_id' => $userId ?? $note->user_id,
            ]);

            return;
        }

        $qualification->notes()->create([
            'body' => $body,
            'user_id' => $userId,
        ]);
    }
}
