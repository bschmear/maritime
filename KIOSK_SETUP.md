# Kiosk Subdomain Setup Documentation

## Overview

The Kiosk system is a complete blog management platform accessible via subdomain (e.g., `kiosk.example.com`). It includes:

- Admin dashboard with statistics
- Blog post management (CRUD)
- Category management (CRUD)
- Tag management (CRUD)
- FAQ management (CRUD)
- User role management (Admin only)
- Role-based access control

## Database Structure

### Tables Created

1. **`kiosk_roles`** - Stores role definitions
   - id, name, slug, description, permissions (JSON), timestamps

2. **`kiosk_user`** - Pivot table linking users to kiosk roles
   - user_id, kiosk_role_id, timestamps

3. **`categories`** - Blog post categories
   - id, name, slug, description, timestamps

4. **`posts`** - Blog posts
   - id, user_id, title, body, slug, category_id, featured, short_description, faqs (JSON), cover_image, published, published_at, soft deletes, timestamps

5. **`tags`** - Post tags
   - id, name, timestamps

6. **`post_tag`** - Pivot table for posts and tags
   - post_id, tag_id

7. **`faqs`** - Frequently asked questions
   - id, question, answer, timestamps

## Installation Steps

### 1. Run Migrations

```bash
php artisan migrate
```

This will create all necessary tables.

### 2. Seed Kiosk Roles

```bash
php artisan db:seed --class=KioskRoleSeeder
```

This creates four default roles:
- **Admin**: Full access including user management
- **Editor**: Can manage all content but not users
- **Author**: Can create and edit own posts
- **Viewer**: Read-only access

### 3. Assign Admin Role to a User

```php
use App\Models\User;
use App\Models\KioskRole;

$user = User::find(1); // Your user
$adminRole = KioskRole::where('slug', 'admin')->first();
$user->kioskRoles()->attach($adminRole->id);
```

Or via Tinker:

```bash
php artisan tinker
>>> $user = User::find(1);
>>> $adminRole = \App\Models\KioskRole::where('slug', 'admin')->first();
>>> $user->kioskRoles()->attach($adminRole->id);
```

### 4. Configure Subdomain

Add to your `.env`:

```env
APP_URL=https://example.com
```

For local development, update your `/etc/hosts` file:

```
127.0.0.1 kiosk.localhost
```

Or use Laravel Valet:

```bash
valet link example
# Access via kiosk.example.test
```

## Routes

All routes are prefixed with `/kiosk` and require authentication.

### Dashboard
- `GET /kiosk` - Dashboard with statistics

### Posts
- `GET /kiosk/posts` - List all posts
- `GET /kiosk/posts/create` - Create post form
- `POST /kiosk/posts` - Store new post
- `GET /kiosk/posts/{post}` - View post
- `GET /kiosk/posts/{post}/edit` - Edit post form
- `PUT/PATCH /kiosk/posts/{post}` - Update post
- `DELETE /kiosk/posts/{post}` - Delete post

### Categories
- `GET /kiosk/categories` - List all categories
- `GET /kiosk/categories/create` - Create category form
- `POST /kiosk/categories` - Store new category
- `GET /kiosk/categories/{category}` - View category
- `GET /kiosk/categories/{category}/edit` - Edit category form
- `PUT/PATCH /kiosk/categories/{category}` - Update category
- `DELETE /kiosk/categories/{category}` - Delete category

### Tags
- `GET /kiosk/tags` - List all tags
- `GET /kiosk/tags/create` - Create tag form
- `POST /kiosk/tags` - Store new tag
- `GET /kiosk/tags/{tag}` - View tag
- `GET /kiosk/tags/{tag}/edit` - Edit tag form
- `PUT/PATCH /kiosk/tags/{tag}` - Update tag
- `DELETE /kiosk/tags/{tag}` - Delete tag

### FAQs
- `GET /kiosk/faqs` - List all FAQs
- `GET /kiosk/faqs/create` - Create FAQ form
- `POST /kiosk/faqs` - Store new FAQ
- `GET /kiosk/faqs/{faq}` - View FAQ
- `GET /kiosk/faqs/{faq}/edit` - Edit FAQ form
- `PUT/PATCH /kiosk/faqs/{faq}` - Update FAQ
- `DELETE /kiosk/faqs/{faq}` - Delete FAQ

### User Management (Admin Only)
- `GET /kiosk/users` - List all kiosk users
- `GET /kiosk/users/create` - Assign role form
- `POST /kiosk/users` - Assign role to user
- `DELETE /kiosk/users/{user}` - Remove role from user

## Middleware

### EnsureKioskDomain
Verifies that requests come from the kiosk subdomain.

```php
// Applied to all kiosk routes
if (!str_starts_with($host, 'kiosk.')) {
    abort(403, 'Access denied.');
}
```

### EnsureKioskAdmin
Verifies that the authenticated user has the admin role.

```php
// Applied to user management routes
if (!auth()->user()->isKioskAdmin()) {
    abort(403, 'Admin role required.');
}
```

## Models

### Post
```php
// Relationships
$post->user;        // Author
$post->category;    // Category
$post->tags;        // Tags (many-to-many)

// Scopes
Post::published()->get();  // Only published posts
Post::featured()->get();   // Only featured posts
```

### Category
```php
$category->posts;  // All posts in category
```

### Tag
```php
$tag->posts;  // All posts with tag
```

### KioskRole
```php
$role->users;  // All users with role
$role->hasPermission('manage_posts');  // Check permission
```

### User (Extended)
```php
$user->kioskRoles;  // User's kiosk roles
$user->posts;       // User's posts
$user->hasKioskRole('admin');  // Check role
$user->isKioskAdmin();         // Check if admin
```

## Permissions System

Roles have a JSON `permissions` field with an array of permission strings:

```php
[
    'manage_users',
    'manage_posts',
    'manage_categories',
    'manage_tags',
    'manage_faqs',
    'publish_posts',
    'delete_posts',
]
```

Check permissions:

```php
if ($user->kioskRoles->first()->hasPermission('manage_posts')) {
    // Allow action
}
```

## Usage Examples

### Create a Post

```php
use App\Models\Post;
use App\Models\Tag;

$post = Post::create([
    'user_id' => auth()->id(),
    'title' => 'My First Post',
    'slug' => 'my-first-post',
    'body' => 'Post content here...',
    'category_id' => 1,
    'short_description' => 'A brief description',
    'featured' => true,
    'published' => true,
    'published_at' => now(),
]);

// Attach tags
$post->tags()->attach([1, 2, 3]);
```

### Query Published Posts

```php
$posts = Post::published()
    ->with(['user', 'category', 'tags'])
    ->latest('published_at')
    ->paginate(10);
```

### Assign Role to User

```php
$user = User::find(1);
$role = KioskRole::where('slug', 'editor')->first();
$user->kioskRoles()->attach($role->id);
```

## Frontend Integration

Create Vue components in `resources/js/Pages/Kiosk/`:

```
resources/js/Pages/Kiosk/
├── Dashboard.vue
├── Posts/
│   ├── Index.vue
│   ├── Create.vue
│   ├── Edit.vue
│   └── Show.vue
├── Categories/
│   ├── Index.vue
│   ├── Create.vue
│   ├── Edit.vue
│   └── Show.vue
├── Tags/
│   ├── Index.vue
│   ├── Create.vue
│   ├── Edit.vue
│   └── Show.vue
├── Faqs/
│   ├── Index.vue
│   ├── Create.vue
│   ├── Edit.vue
│   └── Show.vue
└── Users/
    ├── Index.vue
    └── Create.vue
```

## Security Features

1. **Subdomain Verification**: Only accessible via kiosk subdomain
2. **Authentication Required**: All routes require login
3. **Role-Based Access**: Admin routes protected by role check
4. **CSRF Protection**: Built-in Laravel CSRF protection
5. **SQL Injection Prevention**: Eloquent ORM with parameter binding
6. **XSS Protection**: Blade/Vue escaping

## Testing

### Test Subdomain Access

```php
// tests/Feature/KioskAccessTest.php
public function test_kiosk_requires_subdomain()
{
    $response = $this->get('/kiosk');
    $response->assertStatus(403);
}
```

### Test Admin Access

```php
public function test_user_management_requires_admin()
{
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->get('/kiosk/users')
        ->assertStatus(403);
}
```

## Troubleshooting

### Issue: 403 Access Denied
**Solution**: Ensure you're accessing via kiosk subdomain (e.g., `kiosk.example.com`)

### Issue: Admin routes return 403
**Solution**: Verify user has admin role:
```bash
php artisan tinker
>>> auth()->user()->kioskRoles;
```

### Issue: Routes not found
**Solution**: Clear route cache:
```bash
php artisan route:clear
php artisan route:cache
```

## Next Steps

1. Create Vue components for all pages
2. Add image upload functionality for post cover images
3. Implement rich text editor for post body
4. Add search and filtering to index pages
5. Create API endpoints for frontend consumption
6. Add activity logging
7. Implement post drafts and scheduling
8. Add email notifications

## Configuration

### Customize Subdomain Name

Edit `app/Http/Middleware/EnsureKioskDomain.php`:

```php
$kioskSubdomain = 'admin'; // Change from 'kiosk' to 'admin'
```

### Add Custom Permissions

Edit the seeder or add via code:

```php
$role = KioskRole::find(1);
$permissions = $role->permissions;
$permissions[] = 'custom_permission';
$role->permissions = $permissions;
$role->save();
```

## Support

For issues or questions, refer to:
- Laravel Documentation: https://laravel.com/docs
- Inertia.js Documentation: https://inertiajs.com
- This project's README.md

