# Layout Structure Visual Guide

## Current Layout Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      NAVBAR (Global)                         │
│  [Logo] [Dashboard] [Links...]     [User ▼] or [Login/Reg]  │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                   HEADER (Optional)                          │
│                    Page Title Here                           │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                                                               │
│                                                               │
│                    MAIN CONTENT                              │
│                    (flex-grow)                               │
│                                                               │
│                  Your page content here                      │
│                                                               │
│                                                               │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                     FOOTER (Global)                          │
│  Company Info | Quick Links | Support | Social Media         │
│              © 2025 Your Company                             │
└─────────────────────────────────────────────────────────────┘
```

## Component Hierarchy

```
AppLayout.vue
├── Navbar.vue
│   ├── ApplicationLogo.vue
│   ├── NavLink.vue (multiple)
│   ├── Dropdown.vue
│   │   └── DropdownLink.vue (multiple)
│   └── ResponsiveNavLink.vue (mobile menu)
├── Header (slot - optional)
├── Main Content (slot - required)
└── Footer.vue
```

## Layout Options

### 1. AuthenticatedLayout (For logged-in users)

```vue
<AuthenticatedLayout>
    <template #header>
        <h2>Page Title</h2>
    </template>
    
    <!-- Your content -->
</AuthenticatedLayout>
```

**Used by:**
- Dashboard
- Profile pages
- Any authenticated page

**Includes:**
- ✅ Navbar (with user dropdown)
- ✅ Optional header slot
- ✅ Footer

---

### 2. GuestLayout (For login/register pages)

```vue
<GuestLayout>
    <!-- Your form content -->
</GuestLayout>
```

**Used by:**
- Login
- Register
- Password reset
- Email verification

**Includes:**
- ✅ Navbar (with login/register buttons)
- ✅ Centered card for forms
- ✅ Footer

---

### 3. AppLayout (For custom pages)

```vue
<AppLayout>
    <template #header>
        <h2>Page Title</h2>
    </template>
    
    <!-- Your content -->
</AppLayout>
```

**Use for:**
- Public pages (About, Contact, etc.)
- Landing pages
- Any custom page needing navbar + footer

**Includes:**
- ✅ Navbar (auth-aware)
- ✅ Optional header slot
- ✅ Footer

---

## Responsive Behavior

### Desktop (≥640px)
```
┌─────────────────────────────────────────────────────┐
│ [Logo] [Nav Links...]        [User Dropdown/Login]  │
└─────────────────────────────────────────────────────┘
```

### Mobile (<640px)
```
┌─────────────────────────────────────────────────────┐
│ [Logo]                                    [☰ Menu]  │
└─────────────────────────────────────────────────────┘
│ [Dashboard]                                         │
│ [Other Links...]                                    │
│ ─────────────────                                   │
│ [User Name]                                         │
│ [Profile]                                           │
│ [Logout]                                            │
└─────────────────────────────────────────────────────┘
```

## Navbar States

### Authenticated User
```
┌─────────────────────────────────────────────────────┐
│ [Logo] [Dashboard] [Links...]           [John Doe ▼]│
└─────────────────────────────────────────────────────┘
                                              ↓ Click
                                    ┌─────────────────┐
                                    │ Profile         │
                                    │ Log Out         │
                                    └─────────────────┘
```

### Guest User
```
┌─────────────────────────────────────────────────────┐
│ [Logo]                          [Log in] [Register] │
└─────────────────────────────────────────────────────┘
```

## Footer Layout

### Desktop (≥768px)
```
┌──────────────┬──────────────┬──────────────┬──────────────┐
│ Company Info │ Quick Links  │   Support    │ Social Media │
│ (2 columns)  │              │              │              │
└──────────────┴──────────────┴──────────────┴──────────────┘
                    © 2025 Your Company
```

### Mobile (<768px)
```
┌─────────────────────────────────────────────────────┐
│              Company Info                            │
├─────────────────────────────────────────────────────┤
│              Quick Links                             │
├─────────────────────────────────────────────────────┤
│              Support                                 │
├─────────────────────────────────────────────────────┤
│              Social Media                            │
└─────────────────────────────────────────────────────┘
                © 2025 Your Company
```

## Page Examples

### Dashboard Page Flow
```
User visits /dashboard
        ↓
AuthenticatedLayout loads
        ↓
    ┌───────────────────┐
    │ Navbar (with user)│
    ├───────────────────┤
    │ Header: Dashboard │
    ├───────────────────┤
    │ Dashboard Content │
    ├───────────────────┤
    │ Footer            │
    └───────────────────┘
```

### Login Page Flow
```
User visits /login
        ↓
GuestLayout loads
        ↓
    ┌───────────────────┐
    │ Navbar (guest)    │
    ├───────────────────┤
    │                   │
    │  ┌─────────────┐  │
    │  │ Logo        │  │
    │  ├─────────────┤  │
    │  │ Login Form  │  │
    │  └─────────────┘  │
    │                   │
    ├───────────────────┤
    │ Footer            │
    └───────────────────┘
```

### Custom Page Flow
```
User visits /about
        ↓
AppLayout loads
        ↓
    ┌───────────────────┐
    │ Navbar (auth-aware)│
    ├───────────────────┤
    │ Header: About Us  │
    ├───────────────────┤
    │ About Content     │
    ├───────────────────┤
    │ Footer            │
    └───────────────────┘
```

## Multi-Tenant Considerations

Each tenant can have:
- Custom logo in navbar
- Custom brand colors
- Tenant-specific navigation links
- Custom footer content

```
Tenant A                    Tenant B
┌─────────────────┐        ┌─────────────────┐
│ [Logo A] [Nav]  │        │ [Logo B] [Nav]  │
│ Blue theme      │        │ Green theme     │
└─────────────────┘        └─────────────────┘
```

## Customization Points

### Navbar
- **Logo**: Line 24 in `Navbar.vue`
- **Navigation Links**: Line 33 in `Navbar.vue`
- **User Dropdown**: Line 48 in `Navbar.vue`
- **Colors**: Search for `bg-white`, `text-gray-800`, etc.

### Footer
- **Company Info**: Line 13 in `Footer.vue`
- **Quick Links**: Line 22 in `Footer.vue`
- **Support Links**: Line 46 in `Footer.vue`
- **Social Icons**: Line 82 in `Footer.vue`

## Best Practices

1. **Use AuthenticatedLayout** for pages requiring login
2. **Use GuestLayout** for auth pages (login, register)
3. **Use AppLayout** for public pages or custom layouts
4. **Always include `<Head title="..."/>` for SEO
5. **Use the header slot** for page titles
6. **Keep navbar links consistent** across all pages
7. **Test responsive behavior** on mobile devices

## Common Patterns

### With Header
```vue
<AppLayout>
    <template #header>
        <h2 class="text-xl font-semibold">Page Title</h2>
    </template>
    
    <div class="py-12">
        <!-- Content -->
    </div>
</AppLayout>
```

### Without Header
```vue
<AppLayout>
    <div class="py-12">
        <!-- Content -->
    </div>
</AppLayout>
```

### Full Width Content
```vue
<AppLayout>
    <div class="w-full">
        <!-- Full width content -->
    </div>
</AppLayout>
```

### Centered Content
```vue
<AppLayout>
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <!-- Centered content -->
        </div>
    </div>
</AppLayout>
```

## Summary

✅ **Navbar**: Always visible, auth-aware, responsive
✅ **Footer**: Always visible, customizable, responsive  
✅ **Layouts**: Three options for different use cases
✅ **Responsive**: Mobile-friendly hamburger menu
✅ **Multi-tenant**: Ready for tenant customization
✅ **Consistent**: Same look across all pages

