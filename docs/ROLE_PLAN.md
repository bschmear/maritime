Permission System Refactor Plan
Objective

Implement a scalable role-based permissions system using:

existing roles table
existing users.current_role_id
existing RecordType enum

The system should:

support granular permissions
support future expansion beyond CRUD
integrate cleanly with policies/middleware
auto-generate permissions from RecordType
support admin UI management
Existing Architecture
Current Tables
users
users
- id
- current_role_id
roles
roles
- id
- name
Existing Enum
App\Enums\RecordType

This enum will become the authoritative source for permission domains.

Recommended Architecture
New Tables
permissions

Stores all available permission keys.

permissions
- id
- key
- domain
- action
- label
- description nullable
- created_at
- updated_at
role_permissions

Pivot table assigning permissions to roles.

role_permissions
- id
- role_id
- permission_id

Unique index:

unique(role_id, permission_id)
Permission Naming Convention

Use:

domain.action

Examples:

lead.view
lead.create
lead.edit
lead.delete

invoice.send
invoice.void

workorder.complete
workorder.assign
Core CRUD Actions

Every RecordType should initially generate:

view
create
edit
delete

Resulting in:

customer.view
customer.create
customer.edit
customer.delete
Future Non-CRUD Actions

The architecture must support future actions like:

invoice.send
invoice.refund
transaction.approve
workorder.complete
delivery.dispatch
document.esign

Do NOT hardcode permissions into columns.

Permissions must remain row-based.

Database Migrations
Create permissions table

Migration should include:

Schema::create('permissions', function (Blueprint $table) {
    $table->id();

    $table->string('key')->unique();

    $table->string('domain');
    $table->string('action');

    $table->string('label');

    $table->text('description')->nullable();

    $table->timestamps();
});
Create role_permissions table
Schema::create('role_permissions', function (Blueprint $table) {
    $table->id();

    $table->foreignId('role_id')->constrained()->cascadeOnDelete();

    $table->foreignId('permission_id')->constrained()->cascadeOnDelete();

    $table->unique([
        'role_id',
        'permission_id',
    ]);
});
Eloquent Models
Permission Model

Create:

App\Models\Permission

Relationships:

public function roles()
{
    return $this->belongsToMany(Role::class);
}
Role Model

Add:

public function permissions()
{
    return $this->belongsToMany(Permission::class);
}
User Model

Add helper:

public function role()
{
    return $this->belongsTo(Role::class, 'current_role_id');
}
User Permission Helper

Add to User model:

public function hasPermission(string $permission): bool
{
    if (! $this->role) {
        return false;
    }

    return $this->role
        ->permissions
        ->contains('key', $permission);
}
RecordType Integration
Create Permission Generator

Create:

App\Services\PermissionGenerator

Purpose:

loop all RecordType::cases()
auto-generate CRUD permissions
Example Generator Logic
$actions = [
    'view',
    'create',
    'edit',
    'delete',
];

foreach (RecordType::cases() as $recordType) {

    foreach ($actions as $action) {

        Permission::firstOrCreate([
            'key' => $recordType->key() . '.' . $action,
        ], [
            'domain' => $recordType->key(),
            'action' => $action,
            'label' => $recordType->title() . ' ' . ucfirst($action),
        ]);
    }
}
Artisan Command

Create:

php artisan permissions:sync

All tenants (Stancl):

php artisan permissions:sync --all-tenants

Specific tenants only:

php artisan permissions:sync --all-tenants --tenants=762332 --tenants=123456

Permission rows only (do not change role assignments):

php artisan permissions:sync --catalog-only

Purpose:

regenerate missing permissions
keep enum and DB synchronized
apply default role permission sets (admin: all; manager: all except user create/delete; employee: view+edit; guest: view) unless --catalog-only

This becomes critical long term.

Middleware

Create middleware:

CheckPermission

Usage:

Route::middleware([
    'permission:invoice.edit'
]);

Middleware logic:

if (! auth()->user()?->hasPermission($permission)) {
    abort(403);
}
Policy Integration

Eventually integrate with Laravel Policies.

Example:

public function update(User $user, Invoice $invoice)
{
    return $user->hasPermission('invoice.edit');
}

Policies remain the cleanest architecture.

Admin UI Requirements
Role Editor Screen

Display grouped permissions by domain:

Invoices
☑ View
☑ Create
☐ Delete

Customers
☑ View
☑ Edit

Group using:

permission.domain
Important Recommendations
DO NOT Store Permissions As Columns

Avoid:

can_view
can_edit
can_delete

This becomes unmaintainable.

DO NOT Hardcode Role Names

Avoid:

if ($user->role === 'admin')

Always permission-check.

Keep RecordType As Source Of Truth

This is already excellent architecture.

Your enum gives:

domain key
labels
titles
paths

Perfect for permission automation.

Suggested Future Enhancements
Add Super Admin Bypass

Example:

roles.is_super_admin

Or:

users.is_super_admin
Add Permission Caching

Later optimize using:

Cache::remember()

Especially once permission count grows.

Final Recommended Structure
users
    current_role_id

roles

permissions
    key
    domain
    action

role_permissions
    role_id
    permission_id

This architecture is:

scalable
ERP-ready
CRM-ready
policy-friendly
maintainable long-term
compatible with your enum-driven architecture.