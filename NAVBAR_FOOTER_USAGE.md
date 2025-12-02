# Global Navbar & Footer Documentation

This document explains how to use the global navbar and footer components in your Laravel + Vue.js + Breeze application.

## Components Created

### 1. **Navbar.vue** (`resources/js/Components/Navbar.vue`)
A global navigation bar that:
- Automatically detects if a user is authenticated
- Shows appropriate links for authenticated/guest users
- Includes responsive mobile menu
- Supports user dropdown menu with profile and logout options
- Can be customized with additional navigation links

### 2. **Footer.vue** (`resources/js/Components/Footer.vue`)
A global footer that includes:
- Company information
- Quick links section
- Support links
- Social media icons
- Copyright notice with dynamic year
- Fully responsive design

### 3. **AppLayout.vue** (`resources/js/Layouts/AppLayout.vue`)
A complete layout wrapper that includes:
- Global navbar at the top
- Optional page header slot
- Main content area (flex-grow to push footer down)
- Global footer at the bottom

## Updated Layouts

### **AuthenticatedLayout.vue**
Now uses the global Navbar and Footer components, providing a consistent experience across all authenticated pages.

### **GuestLayout.vue**
Now includes the global Navbar and Footer while maintaining the centered card design for login/register forms.

## Usage Examples

### Option 1: Using Existing Layouts (Recommended)

Your existing pages will automatically get the navbar and footer since `AuthenticatedLayout.vue` and `GuestLayout.vue` have been updated.

**Example - Dashboard Page:**
```vue
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        Your dashboard content here
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
```

### Option 2: Using AppLayout Directly

For new pages that need the navbar and footer:

```vue
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="About Us" />

    <AppLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                About Us
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        About us content here
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
```

### Option 3: Using Navbar and Footer Independently

If you need custom layout logic:

```vue
<script setup>
import Navbar from '@/Components/Navbar.vue';
import Footer from '@/Components/Footer.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Custom Page" />

    <div class="flex min-h-screen flex-col">
        <Navbar />
        
        <main class="flex-grow bg-gray-100">
            <!-- Your custom content -->
        </main>
        
        <Footer />
    </div>
</template>
```

## Customization

### Adding Navigation Links

Edit `resources/js/Components/Navbar.vue`:

```vue
<!-- Around line 33, add new navigation links -->
<NavLink
    :href="route('about')"
    :active="route().current('about')"
>
    About
</NavLink>

<NavLink
    :href="route('contact')"
    :active="route().current('contact')"
>
    Contact
</NavLink>
```

Don't forget to add the same links to the responsive menu (around line 145):

```vue
<ResponsiveNavLink
    :href="route('about')"
    :active="route().current('about')"
>
    About
</ResponsiveNavLink>
```

### Customizing Footer Content

Edit `resources/js/Components/Footer.vue`:

1. **Update company info** (lines 13-18)
2. **Add/modify quick links** (lines 22-42)
3. **Update support links** (lines 46-70)
4. **Customize social media links** (lines 82-115)

### Tenant-Specific Customization

Since you're using multi-tenancy, you can customize the navbar/footer per tenant:

```vue
<script setup>
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const tenant = page.props.tenant; // Assuming tenant data is passed

// Use tenant data to customize appearance
const brandColor = tenant?.brand_color || '#1f2937';
</script>
```

## Styling

All components use Tailwind CSS classes. To customize:

1. **Colors**: Search and replace color classes (e.g., `gray-800` → `blue-800`)
2. **Spacing**: Adjust padding/margin classes (e.g., `py-8` → `py-12`)
3. **Layout**: Modify max-width classes (e.g., `max-w-7xl` → `max-w-6xl`)

## Features

### Navbar Features
- ✅ Automatic authentication detection
- ✅ Responsive mobile menu
- ✅ User dropdown with profile/logout
- ✅ Guest login/register buttons
- ✅ Active link highlighting
- ✅ Smooth transitions

### Footer Features
- ✅ Multi-column responsive layout
- ✅ Dynamic copyright year
- ✅ Social media icons
- ✅ Quick links section
- ✅ Support/legal links
- ✅ Consistent styling with navbar

## Multi-Tenant Considerations

### Passing Tenant Data

In your controller, pass tenant-specific data:

```php
return Inertia::render('Dashboard', [
    'tenant' => [
        'name' => tenant('name'),
        'logo' => tenant('logo_url'),
        'brand_color' => tenant('brand_color'),
    ]
]);
```

### Using Tenant Data in Components

Access tenant data in your components:

```vue
<script setup>
const page = usePage();
const tenantName = page.props.tenant?.name || 'Your Company';
</script>

<template>
    <h3>{{ tenantName }}</h3>
</template>
```

## Testing

Test the navbar and footer across:
- ✅ Authenticated pages (Dashboard, Profile)
- ✅ Guest pages (Login, Register)
- ✅ Different screen sizes (mobile, tablet, desktop)
- ✅ Different tenants (if applicable)

## Troubleshooting

### Issue: Route not found error
**Solution**: Make sure the route exists in your `routes/web.php` or `routes/tenant.php` file.

### Issue: Navbar/Footer not showing
**Solution**: Ensure you're using one of the layouts or have imported the components correctly.

### Issue: Styling looks broken
**Solution**: Run `npm run dev` to compile Tailwind CSS classes.

### Issue: User data not showing
**Solution**: Ensure `auth` data is being shared via Inertia middleware.

## Next Steps

1. **Add more navigation links** as your application grows
2. **Customize colors** to match your brand
3. **Add tenant-specific branding** (logos, colors)
4. **Implement breadcrumbs** in the header slot
5. **Add notifications** to the navbar
6. **Create a settings page** for tenant customization

## File Structure

```
resources/js/
├── Components/
│   ├── Navbar.vue          # Global navigation bar
│   └── Footer.vue          # Global footer
├── Layouts/
│   ├── AppLayout.vue       # Complete layout with navbar + footer
│   ├── AuthenticatedLayout.vue  # Updated to use navbar + footer
│   └── GuestLayout.vue     # Updated to use navbar + footer
```

## Support

For questions or issues, refer to:
- [Laravel Documentation](https://laravel.com/docs)
- [Inertia.js Documentation](https://inertiajs.com)
- [Vue 3 Documentation](https://vuejs.org)
- [Tailwind CSS Documentation](https://tailwindcss.com)

