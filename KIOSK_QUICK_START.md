# Kiosk Quick Start Guide

## 🚀 Quick Setup (5 Minutes)

### 1. Run Migrations
```bash
php artisan migrate
```

### 2. Seed Roles
```bash
php artisan db:seed --class=KioskRoleSeeder
```

### 3. Make Yourself Admin
```bash
php artisan tinker
```
Then in Tinker:
```php
$user = User::first(); // Or User::find(YOUR_ID)
$adminRole = \App\Models\KioskRole::where('slug', 'admin')->first();
$user->kioskRoles()->attach($adminRole->id);
exit
```

### 4. Configure Local Domain
Add to `/etc/hosts`:
```
127.0.0.1 kiosk.localhost
```

### 5. Access Kiosk
Visit: `http://kiosk.localhost:8000/kiosk`

## 📋 What You Get

### ✅ Complete Blog System
- Posts with categories, tags, and FAQs
- Featured posts
- Published/Draft status
- Rich metadata (cover images, descriptions)
- Full-text search capability

### ✅ Admin Dashboard
- Statistics overview
- Recent posts
- Quick access to all resources

### ✅ Role-Based Access
- **Admin**: Full access + user management
- **Editor**: Manage all content
- **Author**: Create own posts
- **Viewer**: Read-only

### ✅ All CRUD Operations
- Posts (create, read, update, delete)
- Categories (create, read, update, delete)
- Tags (create, read, update, delete)
- FAQs (create, read, update, delete)
- User roles (admin only)

## 🔐 Security Features

- ✅ Subdomain verification (kiosk.* only)
- ✅ Authentication required
- ✅ Role-based authorization
- ✅ CSRF protection
- ✅ SQL injection prevention
- ✅ XSS protection

## 📁 Files Created

### Migrations
- `2025_11_28_055847_create_kiosk_roles_table.php`
- `2025_11_28_055852_create_kiosk_user_table.php`
- `2025_11_28_052845_create_categories_table.php`
- `2025_11_28_052845_create_faqs_table.php`
- `2025_11_28_052846_create_posts_table.php`
- `2025_11_28_052846_create_tags_table.php`
- `2025_11_28_052846_create_post_tag_table.php`

### Models
- `app/Models/Post.php`
- `app/Models/Category.php`
- `app/Models/Tag.php`
- `app/Models/Faq.php`
- `app/Models/KioskRole.php`
- `app/Models/User.php` (updated)

### Controllers
- `app/Http/Controllers/Kiosk/DashboardController.php`
- `app/Http/Controllers/Kiosk/PostController.php`
- `app/Http/Controllers/Kiosk/CategoryController.php`
- `app/Http/Controllers/Kiosk/TagController.php`
- `app/Http/Controllers/Kiosk/FaqController.php`
- `app/Http/Controllers/Kiosk/UserController.php`

### Middleware
- `app/Http/Middleware/EnsureKioskDomain.php`
- `app/Http/Middleware/EnsureKioskAdmin.php`

### Routes
- `routes/kiosk.php`

### Seeders
- `database/seeders/KioskRoleSeeder.php`

## 🎯 Common Tasks

### Create a Post
```php
use App\Models\Post;

$post = Post::create([
    'user_id' => auth()->id(),
    'title' => 'My Post',
    'slug' => 'my-post',
    'body' => 'Content here...',
    'category_id' => 1,
    'published' => true,
    'published_at' => now(),
]);
```

### Assign Role to User
```php
$user = User::find(1);
$role = \App\Models\KioskRole::where('slug', 'editor')->first();
$user->kioskRoles()->attach($role->id);
```

### Check User Role
```php
if (auth()->user()->isKioskAdmin()) {
    // Admin actions
}

if (auth()->user()->hasKioskRole('editor')) {
    // Editor actions
}
```

### Query Published Posts
```php
$posts = Post::published()
    ->with(['user', 'category', 'tags'])
    ->latest('published_at')
    ->paginate(10);
```

## 🌐 Route List

| Method | URI | Name | Middleware |
|--------|-----|------|------------|
| GET | /kiosk | kiosk.dashboard | auth, kiosk.domain |
| GET | /kiosk/posts | kiosk.posts.index | auth, kiosk.domain |
| POST | /kiosk/posts | kiosk.posts.store | auth, kiosk.domain |
| GET | /kiosk/posts/create | kiosk.posts.create | auth, kiosk.domain |
| GET | /kiosk/posts/{post} | kiosk.posts.show | auth, kiosk.domain |
| PUT | /kiosk/posts/{post} | kiosk.posts.update | auth, kiosk.domain |
| DELETE | /kiosk/posts/{post} | kiosk.posts.destroy | auth, kiosk.domain |
| GET | /kiosk/posts/{post}/edit | kiosk.posts.edit | auth, kiosk.domain |
| ... | ... | ... | ... |
| GET | /kiosk/users | kiosk.users.index | auth, kiosk.domain, **kiosk.admin** |

## 🔧 Configuration

### Change Subdomain Name
Edit `app/Http/Middleware/EnsureKioskDomain.php`:
```php
$kioskSubdomain = 'admin'; // Change to your preference
```

### Add Custom Role
```php
KioskRole::create([
    'name' => 'Moderator',
    'slug' => 'moderator',
    'description' => 'Can moderate content',
    'permissions' => ['moderate_posts', 'manage_comments'],
]);
```

## 🐛 Troubleshooting

### Can't Access /kiosk
- ✅ Check you're on kiosk subdomain
- ✅ Verify you're logged in
- ✅ Clear route cache: `php artisan route:clear`

### 403 on User Management
- ✅ Verify you have admin role
- ✅ Check: `auth()->user()->isKioskAdmin()`

### Subdomain Not Working Locally
- ✅ Add to `/etc/hosts`: `127.0.0.1 kiosk.localhost`
- ✅ Or use Valet: `valet link example`

## 📝 Next Steps

1. ✅ Create Vue components for frontend
2. ✅ Add image upload for post covers
3. ✅ Implement rich text editor
4. ✅ Add search functionality
5. ✅ Create public blog pages
6. ✅ Add comments system
7. ✅ Implement post scheduling

## 📚 Full Documentation

See `KIOSK_SETUP.md` for complete documentation including:
- Detailed architecture
- All route definitions
- Model relationships
- Security features
- Testing examples
- Advanced usage

## 🎉 You're Ready!

Your kiosk system is now fully set up and ready to use. Visit `http://kiosk.localhost:8000/kiosk` to get started!

