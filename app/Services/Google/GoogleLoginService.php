<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleLoginService
{
    /**
     * Find an existing user by Google ID or email and link Google when needed.
     * Creates a new account when no match exists.
     *
     * @param  array{id: string, email: string, first_name?: string|null, last_name?: string|null, name?: string|null, verified?: bool}  $profile
     */
    public function resolveUser(array $profile): User
    {
        $googleId = (string) ($profile['id'] ?? '');
        $email = strtolower(trim((string) ($profile['email'] ?? '')));

        if ($googleId === '' || $email === '') {
            throw new RuntimeException('Google did not return a complete user profile.');
        }

        $byGoogleId = User::query()->where('google_id', $googleId)->first();
        if ($byGoogleId !== null) {
            return $byGoogleId;
        }

        $byEmail = User::query()->where('email', $email)->first();
        if ($byEmail !== null) {
            if ($byEmail->google_id !== null && $byEmail->google_id !== $googleId) {
                throw new RuntimeException('This email is already linked to a different Google account.');
            }

            $updates = ['google_id' => $googleId];

            if ($byEmail->email_verified_at === null && ($profile['verified'] ?? false)) {
                $updates['email_verified_at'] = now();
            }

            if ($updates !== ['google_id' => $googleId] || $byEmail->google_id === null) {
                $byEmail->update($updates);
            }

            return $byEmail->fresh() ?? $byEmail;
        }

        $firstName = filled($profile['first_name'] ?? null) ? (string) $profile['first_name'] : null;
        $lastName = filled($profile['last_name'] ?? null) ? (string) $profile['last_name'] : null;
        $displayName = filled($profile['name'] ?? null)
            ? (string) $profile['name']
            : trim(($firstName ?? '').' '.($lastName ?? ''));

        $user = User::create([
            'google_id' => $googleId,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $displayName !== '' ? $displayName : $email,
            'password' => Hash::make(Str::password(32)),
            'email_verified_at' => ($profile['verified'] ?? false) ? now() : null,
        ]);

        event(new Registered($user));

        return $user;
    }
}
