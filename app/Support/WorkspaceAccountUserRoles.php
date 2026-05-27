<?php

namespace App\Support;

/**
 * Roles stored on the central {@code account_user} pivot (and invitation {@code role}).
 * Slugs align with tenant {@see \App\Domain\Role\Models\Role} seed data.
 */
final class WorkspaceAccountUserRoles
{
    /** Owner is set only for the billing owner (e.g. checkout); not offered on the invite form. */
    public const OWNER = 'owner';

    /**
     * Roles an account owner may assign when inviting a team member.
     *
     * @var list<array{slug: string, display_name: string, description: string}>
     */
    public const INVITABLE = [
        [
            'slug' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'Full system access with all permissions',
        ],
        [
            'slug' => 'manager',
            'display_name' => 'Manager',
            'description' => 'Can manage team members and most data',
        ],
        [
            'slug' => 'employee',
            'display_name' => 'Employee',
            'description' => 'Standard user with basic access',
        ],
        [
            'slug' => 'guest',
            'display_name' => 'Guest',
            'description' => 'Limited read-only access',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function invitableSlugs(): array
    {
        return array_column(self::INVITABLE, 'slug');
    }

    public static function labelForSlug(string $slug): string
    {
        foreach (self::INVITABLE as $row) {
            if ($row['slug'] === $slug) {
                return $row['display_name'];
            }
        }

        return match ($slug) {
            self::OWNER => 'Owner',
            'member' => 'Employee', // legacy pivot / invitation
            default => ucfirst(str_replace('_', ' ', $slug)),
        };
    }
}
