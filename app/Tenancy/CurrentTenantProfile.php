<?php

namespace App\Tenancy;

use App\Domain\Role\Models\Role;
use App\Domain\User\Models\UserProfile;
use App\Enums\RecordType;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolves the tenant `users` row (and role) for the central authenticated user.
 * Only meaningful when Stancl tenancy is initialized (tenant subdomain / context).
 */
class CurrentTenantProfile
{
    private ?UserProfile $profile = null;

    private bool $resolved = false;

    public function reset(): void
    {
        $this->profile = null;
        $this->resolved = false;
    }

    public function centralUser(): ?Authenticatable
    {
        return auth()->user();
    }

    public function profile(): ?UserProfile
    {
        if ($this->resolved) {
            return $this->profile;
        }

        $this->resolved = true;

        if (! tenancy()->initialized) {
            return $this->profile = null;
        }

        $central = $this->centralUser();
        if ($central === null || ! isset($central->email) || $central->email === '') {
            return $this->profile = null;
        }

        $this->profile = UserProfile::query()
            ->where('email', $central->email)
            ->with('role')
            ->first();

        return $this->profile;
    }

    public function role(): ?Role
    {
        return $this->profile()?->role;
    }

    public function roleSlug(): ?string
    {
        return $this->role()?->slug;
    }

    public function canAccessRecordType(RecordType $type): bool
    {
        $slug = $this->roleSlug();
        if ($slug === null) {
            return false;
        }

        $superAdmins = config('record_type_access.superadmin_slugs', []);
        if (is_array($superAdmins) && in_array($slug, $superAdmins, true)) {
            return true;
        }

        $allowed = config('record_type_access.types.'.$type->value);
        if (! is_array($allowed)) {
            return false;
        }

        return in_array($slug, $allowed, true);
    }
}
