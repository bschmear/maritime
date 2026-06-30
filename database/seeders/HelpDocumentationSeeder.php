<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class HelpDocumentationSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::connection('pgsql')->hasTable('help_categories')) {
            return;
        }

        $now = now();

        foreach ($this->categories() as $category) {
            HelpCategory::query()->firstOrCreate(
                ['slug' => $category['slug']],
                [
                    ...$category,
                    'parent_id' => null,
                    'active' => true,
                ],
            );
        }

        $categoryIds = HelpCategory::query()
            ->whereIn('slug', array_column($this->categories(), 'slug'))
            ->pluck('id', 'slug');

        foreach ($this->articles() as $article) {
            $categorySlug = $article['category_slug'];
            unset($article['category_slug']);

            HelpArticle::query()->firstOrCreate(
                ['slug' => $article['slug']],
                [
                    ...$article,
                    'category_id' => $categoryIds[$categorySlug] ?? null,
                    'active' => true,
                    'published_at' => $now,
                ],
            );
        }
    }

    /**
     * @return list<array{name: string, slug: string, description: string|null, sort_order: int}>
     */
    private function categories(): array
    {
        return [
            [
                'name' => 'Getting started',
                'slug' => 'getting-started',
                'description' => 'New to Helmful? Start here for workspaces, sign-in, and navigation basics.',
                'sort_order' => 0,
            ],
            [
                'name' => 'Account & team',
                'slug' => 'account-team',
                'description' => 'Invite colleagues, manage pending invitations, and control who can access your workspace.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Workspace settings',
                'slug' => 'workspace-settings',
                'description' => 'Account preferences inside your dealership workspace, including sandbox mode, navigation menus, and safe testing.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Inventory & assets',
                'slug' => 'inventory-assets',
                'description' => 'Brands, catalog models, and your asset library—the foundation for estimates, inventory, and boat shows.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Specs & options',
                'slug' => 'specs-and-options',
                'description' => 'Describe what a boat is (specifications) versus what a customer can choose and pay for (options).',
                'sort_order' => 4,
            ],
            [
                'name' => 'Deliveries',
                'slug' => 'deliveries',
                'description' => 'Schedule boat deliveries, assign drivers, notify customers, and capture signatures.',
                'sort_order' => 5,
            ],
            [
                'name' => 'Boat shows & events',
                'slug' => 'boat-shows-events',
                'description' => 'Plan show participation, booth layouts, public inventory pages, and lead capture.',
                'sort_order' => 6,
            ],
            [
                'name' => 'Service yard',
                'slug' => 'service-yard',
                'description' => 'Service tickets, work orders, customer approvals, and technician scheduling.',
                'sort_order' => 7,
            ],
            [
                'name' => 'Reports',
                'slug' => 'reports',
                'description' => 'Financial and sales reporting with date, subsidiary, and location filters.',
                'sort_order' => 8,
            ],
            [
                'name' => 'Customer portal',
                'slug' => 'customer-portal',
                'description' => 'Let customers view estimates, invoices, service tickets, and documents online.',
                'sort_order' => 9,
            ],
            [
                'name' => 'Warranty & vendor portal',
                'slug' => 'warranty-vendor-portal',
                'description' => 'Manufacturer warranty claims and the vendor portal for approvals.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Integrations',
                'slug' => 'integrations',
                'description' => 'Connect Helmful to external systems such as your WordPress marketing site.',
                'sort_order' => 11,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        return [
            [
                'category_slug' => 'getting-started',
                'title' => 'What is a workspace?',
                'slug' => 'what-is-a-workspace',
                'excerpt' => 'How Helmful separates your subscription, team, and dealership data.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p>Helmful is organized around <strong>accounts</strong> (your subscription and billing) and <strong>workspaces</strong> (your dealership’s live data).</p>
<ul>
<li><strong>Account</strong> — Where you manage your plan, payment method, and who is on your team.</li>
<li><strong>Workspace</strong> — Your dealership app: contacts, inventory, service, invoices, and day-to-day work.</li>
</ul>
<p>Most owners invite team members at the account level. When someone accepts, they can open the workspace tied to that account.</p>
HTML,
            ],
            [
                'category_slug' => 'getting-started',
                'title' => 'Open your dealership workspace',
                'slug' => 'open-your-dealership-workspace',
                'excerpt' => 'Sign in and switch from the central dashboard into your tenant app.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => false,
                'body' => <<<'HTML'
<ol>
<li>Sign in at the Helmful site with the email and password you registered with.</li>
<li>From your <strong>Dashboard</strong>, open the workspace card for your dealership.</li>
<li>You land in the workspace app (your unique subdomain) with sales, service, and account tools.</li>
</ol>
<p>If you do not see a workspace, confirm that your invitation was accepted or contact your account owner.</p>
HTML,
            ],
            [
                'category_slug' => 'account-team',
                'title' => 'Invite a team member',
                'slug' => 'invite-a-team-member',
                'excerpt' => 'Send an email invitation and assign a workspace role before they join.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p>Only the <strong>account owner</strong> can invite new users.</p>
<ol>
<li>Go to <strong>Dashboard</strong> and open your account.</li>
<li>In the team section, click <strong>Invite User</strong>.</li>
<li>Enter the person’s email address.</li>
<li>Choose their <strong>workspace role</strong> (for example admin, sales, or service). They receive this role when they accept.</li>
<li>Click <strong>Send invitation</strong>.</li>
</ol>
<p>Helmful emails them a link to accept. Until they accept, they appear under pending invitations on the same page.</p>
<p>Additional users beyond your plan’s included seats may be billed monthly—see your plan details on the account page.</p>
HTML,
            ],
            [
                'category_slug' => 'account-team',
                'title' => 'Accept an invitation',
                'slug' => 'accept-an-invitation',
                'excerpt' => 'Join a workspace when you receive an invite email from an account owner.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<ol>
<li>Open the invitation email from Helmful and click the acceptance link.</li>
<li>Sign in with an existing Helmful account, or create one if you are new.</li>
<li>Review the workspace you are joining and confirm acceptance.</li>
</ol>
<p>After you accept, the workspace appears on your dashboard. Open it to start working in the dealership app.</p>
<p>If the link expired or you need a new one, ask the account owner to resend the invitation from the account team page.</p>
HTML,
            ],
            [
                'category_slug' => 'account-team',
                'title' => 'Manage pending invitations',
                'slug' => 'manage-pending-invitations',
                'excerpt' => 'Resend or cancel invitations that have not been accepted yet.',
                'article_type' => 'guide',
                'sort_order' => 2,
                'featured' => false,
                'body' => <<<'HTML'
<p>On your account’s team page, pending invitations are listed separately from active members.</p>
<ul>
<li><strong>Resend</strong> — Sends a fresh email if someone did not receive the first message or the link expired.</li>
<li><strong>Delete</strong> — Cancels the invitation so the link no longer works.</li>
</ul>
<p>You can send a new invitation later with the same email if you still want that person on the team.</p>
HTML,
            ],
            [
                'category_slug' => 'account-team',
                'title' => 'Remove a team member',
                'slug' => 'remove-a-team-member',
                'excerpt' => 'Revoke a user’s access to your account and workspace.',
                'article_type' => 'guide',
                'sort_order' => 3,
                'featured' => false,
                'body' => <<<'HTML'
<p>Account owners can remove users who are no longer on the team.</p>
<ol>
<li>Open your account from the dashboard.</li>
<li>Find the user in the active members table.</li>
<li>Click <strong>Remove</strong> and confirm.</li>
</ol>
<p>They lose access to the account and workspace immediately. To bring them back, send a new invitation.</p>
<p>Changing someone’s permissions without removing them is done in the workspace by an administrator (roles and permissions), not on the central account page.</p>
HTML,
            ],
            [
                'category_slug' => 'workspace-settings',
                'title' => 'What is sandbox mode?',
                'slug' => 'what-is-sandbox-mode',
                'excerpt' => 'Test customer emails and SMS safely—they are delivered to you, not to real customers.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p><strong>Sandbox mode</strong> is a workspace safety switch. While it is on, operational messages that would normally go to customers or vendors are redirected to <strong>you</strong> (the signed-in user) instead.</p>
<h3>What gets redirected</h3>
<ul>
<li><strong>Email</strong> — Customer-facing mail such as estimate approvals, delivery notifications, warranty submissions to vendors, and similar operational messages are sent to your login email address, not the intended recipient.</li>
<li><strong>SMS</strong> — When SMS is enabled for a notification type, texts are sent to the <strong>mobile or office phone on your staff user profile</strong> (matched by your login email), not the customer’s number.</li>
</ul>
<p>Modals and previews in the app often show a sandbox notice and list your email or phone so you know where messages will land.</p>
<h3>What is not redirected</h3>
<p>Some messages are exempt because they are not “customer tests.” For example, <strong>team invitations</strong> to join your Helmful account still go to the invitee’s email so onboarding works normally.</p>
<h3>Where to turn it on or off</h3>
<ol>
<li>Open your workspace.</li>
<li>Go to <strong>Company → Overview</strong> (account settings).</li>
<li>On the <strong>General</strong> tab, find the <strong>Sandbox mode</strong> checkbox.</li>
</ol>
<p>When sandbox mode is active, an amber <strong>Sandbox</strong> badge appears in the top navigation; click it to jump to account settings.</p>
<h3>Before you go live</h3>
<p>New workspaces often start with sandbox mode enabled. Turn it <strong>off</strong> when you are ready for real customers to receive email and text notifications. If SMS does not send in sandbox, confirm your staff profile has a phone number and that your login email matches that staff user.</p>
HTML,
            ],
            [
                'category_slug' => 'workspace-settings',
                'title' => 'Customize navigation menus',
                'slug' => 'customize-navigation-menus',
                'excerpt' => 'Rename top navigation links, regroup pages, and create role-specific menus for your workspace.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<p>Every workspace has a <strong>top navigation bar</strong> (Overview, Sales, Operations, Inventory, and similar sections). Administrators can customize what appears there—change labels, reorder items, group pages differently, or show a different menu to each role.</p>
<h3>Who can customize menus</h3>
<p>Only users with the <strong>Administrator</strong> workspace role can open the menu builder. Managers, employees, and guests see the menu you configure for their role but cannot edit it.</p>
<h3>Where to open the menu builder</h3>
<ol>
<li>Open your workspace.</li>
<li>Go to <strong>Company → Overview</strong> (the account landing page).</li>
<li>Click the <strong>Navigation menus</strong> card.</li>
</ol>
<p>You can also go directly to <strong>Navigation menus</strong> from that account page if you have administrator access.</p>
<h3>Default menu vs role menus</h3>
<ul>
<li><strong>Default menu</strong> — Used for every role unless you create a custom menu for that role. New workspaces are seeded with a standard menu that matches Helmful’s default page groupings.</li>
<li><strong>Role menu</strong> — A full replacement menu for one role (for example Manager or Employee). Create one from the default menu, then edit it independently.</li>
</ul>
<p>If a role has no custom menu, users with that role see the <strong>default</strong> menu.</p>
<h3>Edit the menu structure</h3>
<p>Open a menu and click <strong>Edit</strong>. In the builder you can:</p>
<ul>
<li><strong>Change labels</strong> — Rename any link (for example “Service Yard” → “Yard”).</li>
<li><strong>Pick a route</strong> — Each row links to a workspace page. Choose <strong>Group (no link)</strong> for a parent that only expands a dropdown.</li>
<li><strong>Reorder</strong> — Drag the handle on the left to move items up or down within a group.</li>
<li><strong>Add or remove</strong> — Use <strong>Add group</strong> or <strong>Add link</strong> to build new sections; use the trash icon to remove a row.</li>
<li><strong>Collapse groups</strong> — Click the chevron on rows with children, or use <strong>Collapse all groups</strong> / <strong>Expand all groups</strong> at the top to scan a large menu quickly.</li>
</ul>
<p>Click <strong>Save menu</strong> when you are finished. Changes apply on the next page load for users who use that menu.</p>
<h3>Role menus and permissions</h3>
<p>When editing a <strong>role</strong> menu, rows may show a badge such as <strong>Missing permission</strong> if that role cannot access the linked page. The item can still be saved in the menu; users without access will not see that link when they sign in. Use the badges to spot links that would be hidden for that role.</p>
<h3>Create a menu for a role</h3>
<ol>
<li>On the Navigation menus page, choose a role under <strong>Create role menu</strong>.</li>
<li>Click <strong>Create from default</strong>. Helmful copies the current default menu as a starting point.</li>
<li>Edit and save. Users with that role will see this menu instead of the default.</li>
</ol>
<p>To remove a role override, delete that role’s menu from the list. The role will fall back to the default menu.</p>
<h3>Tips</h3>
<ul>
<li>Keep group names short—they appear in the top bar on desktop and in the mobile menu.</li>
<li>If someone reports a missing page, check both their <strong>role menu</strong> (if any) and their <strong>role permissions</strong> under Company → Roles.</li>
<li>The default menu is not deletable; you can only edit it.</li>
</ul>
HTML,
            ],
            [
                'category_slug' => 'inventory-assets',
                'title' => 'Add brands you sell',
                'slug' => 'add-brands-you-sell',
                'excerpt' => 'Pick manufacturers from the catalog or add a custom brand before importing models.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p>Your asset library is organized by <strong>brand</strong> (boat make). Each brand can link to a shared catalog key so you can import model lines and specifications later.</p>
<h3>Open Asset Brands</h3>
<ol>
<li>In your workspace, open <strong>Inventory → Assets → Asset Brands</strong>.</li>
<li>Click <strong>What brands do you work with?</strong></li>
<li>Search the manufacturer list and select every brand you represent.</li>
<li>Confirm to add them to your workspace.</li>
</ol>
<p>Each selection creates a brand with a stable <strong>catalog key</strong> (slug) that matches the shared inventory database—for example <code>mastercraft</code> or <code>sea-ray</code>.</p>
<h3>If your brand is not listed</h3>
<p>Use <strong>I don't see my brand…</strong> to add a custom name. Helmful may warn if the name looks similar to an existing catalog brand; linking the catalog brand when possible keeps import paths open later.</p>
<h3>Asset types</h3>
<p>Brands can be tagged for the kinds of products you sell (boat, engine, trailer, other). That helps filters and spec definitions apply to the right records.</p>
<p>After brands exist, open a brand’s page to import catalog models or add assets manually.</p>
HTML,
            ],
            [
                'category_slug' => 'inventory-assets',
                'title' => 'Import models from the catalog',
                'slug' => 'import-models-from-catalog',
                'excerpt' => 'Pull model lines, variants, and dimensions from the shared library into your workspace.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<p>When a brand has a <strong>catalog key</strong>, you can import pre-built model data instead of typing everything by hand.</p>
<h3>Import from a brand page</h3>
<ol>
<li>Go to <strong>Inventory → Assets → Asset Brands</strong> and open the brand.</li>
<li>Use <strong>Import from library</strong> (or the equivalent catalog action on that page).</li>
<li>Select the models you want. Models already in your workspace are skipped so you do not get duplicates.</li>
<li>Start the import. Large imports may queue in the background; refresh the brand or assets list when processing finishes.</li>
</ol>
<h3>What import creates</h3>
<ul>
<li>An <strong>asset</strong> (model line) for each catalog model, with length, beam, capacity, power, hull type, and related fields when the catalog provides them.</li>
<li><strong>Variants</strong> when the catalog defines trim levels or lengths under that model.</li>
<li><strong>Specification values</strong> on the asset or variant when your workspace has matching spec definitions (for example weight, max people, max HP).</li>
</ul>
<p>Imported assets store a <code>catalog_asset_key</code> so Helmful knows they came from the library. Re-importing the same model does not create a second copy.</p>
<h3>When the library is empty</h3>
<p>If no models appear, the shared inventory may not yet list that brand. You can still add assets manually under <strong>Inventory → Assets</strong>, or use catalog tooling (where enabled) to add a model to the library first, then import it.</p>
<h3>After import</h3>
<p>Review each asset on its detail page: adjust specs, add options, and attach units (physical inventory) as needed. Catalog import is a starting point, not a substitute for your dealership’s pricing and packages.</p>
HTML,
            ],
            [
                'category_slug' => 'inventory-assets',
                'title' => 'Manage assets and variants',
                'slug' => 'manage-assets-and-variants',
                'excerpt' => 'Model lines, trim levels, and physical units—and how they fit together.',
                'article_type' => 'guide',
                'sort_order' => 2,
                'featured' => false,
                'body' => <<<'HTML'
<p>An <strong>asset</strong> is a product definition: a boat model line, engine series, trailer model, and so on. It belongs to one brand and can hold specifications and optional equipment definitions.</p>
<h3>Variants</h3>
<p>Use <strong>variants</strong> when one model line has multiple configurations—for example different lengths, engine packages, or colorways that share a name family. Specifications can be set on the asset (defaults for all variants) or overridden on a specific variant.</p>
<h3>Units</h3>
<p>A <strong>unit</strong> is a physical item in stock (hull number, serial, status, location). Units link to a variant (or directly to the asset when you do not use variants). Sales, service, and consignment workflows operate on units; estimates and quotes usually start from an asset or variant.</p>
<h3>Where to work in the app</h3>
<ul>
<li><strong>Inventory → Assets</strong> — Browse and edit model lines.</li>
<li>On an asset’s page — Edit details, specifications, variants, and linked options.</li>
<li><strong>Inventory → Assets → All Units</strong> — Fleet-wide view of physical inventory.</li>
</ul>
<p>Configure reusable fields under <strong>Asset Specifications</strong> and customer choices under <strong>Asset Options</strong> (see the Specs &amp; options category).</p>
HTML,
            ],
            [
                'category_slug' => 'specs-and-options',
                'title' => 'Specifications vs options',
                'slug' => 'specifications-vs-options',
                'excerpt' => 'Specs describe the product; options are choices the customer makes (often with a price).',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p>Dealers often mix these up. In Helmful they are separate systems on purpose.</p>
<table>
<thead>
<tr><th></th><th><strong>Asset specifications</strong></th><th><strong>Asset options</strong></th></tr>
</thead>
<tbody>
<tr>
<td><strong>Purpose</strong></td>
<td>Describe facts about the boat or product (length, beam, max HP, dry weight).</td>
<td>Configurable choices on a quote (hull color, stereo package, extended warranty).</td>
</tr>
<tr>
<td><strong>Typical examples</strong></td>
<td>24 ft length, 102 in beam, 12 persons, 350 HP max.</td>
<td>Midnight black hull (+$800), Premium audio (+$2,400), Bimini package.</td>
</tr>
<tr>
<td><strong>Where you define them</strong></td>
<td><strong>Inventory → Assets → Asset Specifications</strong> (Spec Builder).</td>
<td><strong>Inventory → Assets → Asset Options</strong>.</td>
</tr>
<tr>
<td><strong>Where you enter values</strong></td>
<td>On each asset or variant (specification fields on the edit form).</td>
<td>On the option: define values and prices, then <strong>assign</strong> the option to brands/models/variants.</td>
</tr>
<tr>
<td><strong>On an estimate</strong></td>
<td>Informational context for the model; not usually line-item upsells.</td>
<td>Customer selections with <strong>snapshot pricing</strong> stored on the estimate.</td>
</tr>
</tbody>
</table>
<h3>Quick rule of thumb</h3>
<p>If the answer is the same for every buyer of that model (or variant), it is probably a <strong>spec</strong>. If the buyer picks one of several choices and it may change the price, it is an <strong>option</strong>.</p>
<h3>Catalog import</h3>
<p>Library import fills many <strong>spec values</strong> automatically (dimensions, capacity, weight). It does not replace your dealership’s <strong>options</strong>—you still define packages and assign them to the models you sell.</p>
HTML,
            ],
            [
                'category_slug' => 'specs-and-options',
                'title' => 'Set up asset specifications',
                'slug' => 'set-up-asset-specifications',
                'excerpt' => 'Use the Spec Builder to define fields, then enter values on each asset or variant.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<p><strong>Asset specifications</strong> are custom fields your workspace uses to describe products—grouped under headings like Dimensions or Engine.</p>
<h3>Step 1 — Define fields in the Spec Builder</h3>
<ol>
<li>Open <strong>Inventory → Assets → Asset Specifications</strong>.</li>
<li>Review or create <strong>spec groups</strong> (for example Dimensions, Capacity, Engine).</li>
<li>Add a spec for each fact you want to track. For each one you choose:
<ul>
<li><strong>Label</strong> — What users see (“Overall length”).</li>
<li><strong>Key</strong> — Internal identifier (unique), used by import and integrations.</li>
<li><strong>Type</strong> — Number, text, yes/no, or dropdown select.</li>
<li><strong>Units</strong> — For numbers: imperial/metric (ft, m, lb, HP, etc.).</li>
<li><strong>Asset types</strong> — Boat, engine, trailer, or other—so boat-only specs do not appear on trailers.</li>
<li><strong>Required</strong> — Whether the field must be filled when editing an asset of that type.</li>
</ul>
</li>
<li>Drag specs within a group to control display order.</li>
</ol>
<h3>Step 2 — Enter values on assets</h3>
<ol>
<li>Open an asset (or variant) under <strong>Inventory → Assets</strong>.</li>
<li>Edit the record. The specifications section lists every definition that applies to that asset type.</li>
<li>Fill in values (numbers with units, dropdown choices, etc.) and save.</li>
</ol>
<p>Values are stored per asset or per variant. Variant values override the asset when both exist—useful when only the 27 ft trim has a different beam.</p>
<h3>Examples</h3>
<ul>
<li><strong>Number + unit</strong> — Overall length: <code>24</code> ft; Max HP: <code>350</code> HP.</li>
<li><strong>Select</strong> — Engine shaft: <code>L</code> or <code>XL</code> (dropdown options you define on the spec).</li>
<li><strong>Boolean</strong> — Ballast system: Yes/No.</li>
</ul>
<h3>Import tip</h3>
<p>Default definitions such as <code>boat_weight</code>, <code>max_people</code>, and <code>max_hp</code> can be populated automatically when you import from the catalog, as long as those definitions still exist in your workspace.</p>
HTML,
            ],
            [
                'category_slug' => 'specs-and-options',
                'title' => 'Set up asset options',
                'slug' => 'set-up-asset-options',
                'excerpt' => 'Create choosable packages, assign them to models, and use them on estimates.',
                'article_type' => 'guide',
                'sort_order' => 2,
                'featured' => true,
                'body' => <<<'HTML'
<p><strong>Asset options</strong> are dealer-defined choices customers pick on a quote—colors, equipment packages, and similar upsells—with optional cost and retail price on each choice.</p>
<h3>Step 1 — Create the option</h3>
<ol>
<li>Open <strong>Inventory → Assets → Asset Options</strong> and create a new option.</li>
<li>Name it clearly (for example “Hull color” or “Stereo package”).</li>
<li>Choose an <strong>input type</strong>:
<ul>
<li><strong>Single select</strong> — One choice (one color).</li>
<li><strong>Multi select</strong> — Several choices allowed (accessories bundle).</li>
<li><strong>Color</strong> — Swatches with hex colors.</li>
<li><strong>Toggle</strong> — On/off (no separate value list).</li>
</ul>
</li>
<li>Set whether the choice is <strong>required</strong> and whether <strong>multiple values</strong> are allowed.</li>
</ol>
<h3>Step 2 — Add values and pricing</h3>
<p>For select, multi-select, and color options, add one row per choice:</p>
<ul>
<li><strong>Label</strong> — “Midnight black”, “Premium audio”.</li>
<li><strong>Cost</strong> — Your internal cost (optional).</li>
<li><strong>Price</strong> — What the customer pays on the estimate (optional).</li>
</ul>
<p>Example: Hull color → Black ($0), White ($0), Custom flake (+$1,200 price).</p>
<h3>Step 3 — Assign where the option applies</h3>
<p>On the option’s detail page, use <strong>Assignments</strong> to control which models see it:</p>
<ul>
<li><strong>Entire brand</strong> — Applies to every model under that manufacturer (good for universal color programs).</li>
<li><strong>Specific models or variants</strong> — Only certain trims get the option (good for engine-specific packages).</li>
<li><strong>All brands</strong> — Rare; use when the same option truly applies workspace-wide.</li>
</ul>
<p>Assignment precedence: a <strong>variant-specific</strong> assignment wins over the model-level assignment, which wins over a <strong>brand-wide</strong> assignment. You can override cost or price per assignment when needed.</p>
<h3>On estimates</h3>
<p>When you build an estimate for an asset (and variant), Helmful loads only the options assigned to that combination. Selections are <strong>snapshotted</strong> on the estimate—later price changes to the option definition do not rewrite closed quotes.</p>
<h3>Do not use options for…</h3>
<p>Fixed facts like length or beam—use <strong>specifications</strong> instead. Options are for configurable, priced choices.</p>
HTML,
            ],
            [
                'category_slug' => 'deliveries',
                'title' => 'Delivery overview',
                'slug' => 'delivery-overview',
                'excerpt' => 'What deliveries are, where to find them, and how status moves from scheduled to delivered.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p>A <strong>delivery</strong> schedules getting one or more units (boats, engines, trailers) to a customer. Each delivery ties together a customer, what is being delivered, where it is going, when it is scheduled, and who is driving.</p>
<h3>Where to work</h3>
<p>In your workspace, open <strong>Operations → Deliveries</strong>:</p>
<ul>
<li><strong>All Deliveries</strong> — Dashboard with stats, a day timeline, calendar, and searchable list.</li>
<li><strong>Delivery Scheduler</strong> — Full-day board by technician (drag-and-drop scheduling).</li>
<li><strong>Common Locations</strong> — Saved delivery sites (marinas, ramps, storage yards) you reuse.</li>
<li><strong>Templates</strong> — Checklist templates applied on delivery completion.</li>
</ul>
<h3>Create a delivery</h3>
<ol>
<li>From <strong>All Deliveries</strong>, click <strong>New Delivery</strong>, or start from a related work order or sale when your workflow offers a delivery shortcut.</li>
<li>Choose the <strong>customer</strong> and the <strong>unit(s)</strong> to deliver.</li>
<li>Set the <strong>deliver-to</strong> address: customer address, a common location, or a custom address.</li>
<li>Assign a <strong>technician</strong> (driver), <strong>scheduled date and time</strong>, and optional <strong>depart-from location</strong>, truck, and trailer.</li>
<li>Save. The delivery appears on the list and schedule once it has a technician and scheduled time.</li>
</ol>
<h3>Statuses</h3>
<ul>
<li><strong>Scheduled / Confirmed / Rescheduled</strong> — Planned but not yet on the road.</li>
<li><strong>En route</strong> — Driver is traveling to the customer; you can optionally text the customer with a tracking link.</li>
<li><strong>Delivered</strong> — Completed (with or without a customer signature).</li>
<li><strong>Cancelled</strong> — No longer happening.</li>
</ul>
<p>Open any delivery’s detail page to update scheduling, mark progress, send notifications, run checklists, and finish the job.</p>
HTML,
            ],
            [
                'category_slug' => 'deliveries',
                'title' => 'Schedule deliveries',
                'slug' => 'schedule-deliveries',
                'excerpt' => 'Use the day timeline on All Deliveries or the Delivery Scheduler board to plan technicians and times.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<p>Helmful offers two scheduling views. Both respect your workspace <strong>timezone</strong> (under Company → Overview).</p>
<h3>Day timeline (All Deliveries)</h3>
<p>On <strong>Operations → Deliveries → All Deliveries</strong>, the main card shows that day’s deliveries in time order—defaulting to today. Use the arrows to move to another day, or pick a date from the <strong>calendar</strong> in the sidebar (days with deliveries show a count).</p>
<p>Each row shows customer, deliver-to location, technician, status, and scheduled time. Click a row to open the full delivery.</p>
<p>Use <strong>Schedule board</strong> in the card header to open the full scheduler for the same day.</p>
<h3>Delivery Scheduler (board view)</h3>
<p>Open <strong>Operations → Deliveries → Delivery Scheduler</strong> for a multi-column day view:</p>
<ul>
<li>One column per <strong>technician</strong> (users marked as technicians in your workspace).</li>
<li>Horizontal time axis for the day; adjust visible <strong>start</strong> and <strong>end</strong> hours if needed.</li>
<li>Each delivery appears as a block: <strong>amber</strong> for travel time, <strong>blue</strong> for on-site time at the destination.</li>
</ul>
<h3>Scheduling actions on the board</h3>
<ul>
<li><strong>Drag</strong> a delivery to another technician and/or time slot (snaps to 15-minute increments).</li>
<li><strong>Resize</strong> the left edge to change arrival time, or the right edge to change on-site duration.</li>
<li><strong>Click</strong> a delivery to open the detail panel—edit scheduled time there and save.</li>
</ul>
<p>If two deliveries would overlap on the same technician, the board blocks the change and shows an error.</p>
<h3>Filter by depart-from location</h3>
<p>On the scheduler page, choose a <strong>Depart-from location</strong> to limit the board to deliveries leaving that store or yard. Leave it on “All locations” to see everyone.</p>
<h3>Drive times</h3>
<p>When a departure location, scheduled time, and complete delivery address (or coordinates) are set, Helmful can estimate travel from Google Maps. Use <strong>Recalculate drive times</strong> on the delivery detail page if the address or schedule changes.</p>
<h3>Fleet conflicts</h3>
<p>When creating or editing a delivery, assigning a <strong>truck</strong> or <strong>trailer</strong> from Fleet checks for overlapping use on the same day and warns you before you save.</p>
HTML,
            ],
            [
                'category_slug' => 'deliveries',
                'title' => 'Customer delivery notifications',
                'slug' => 'customer-delivery-notifications',
                'excerpt' => 'Email and SMS for en route, arrival, and signature requests—including sandbox testing.',
                'article_type' => 'guide',
                'sort_order' => 2,
                'featured' => true,
                'body' => <<<'HTML'
<p>Deliveries can notify customers by <strong>email</strong> and, when enabled, <strong>SMS</strong>. Notifications are optional per action—you choose at send time.</p>
<h3>Turn on delivery SMS</h3>
<ol>
<li>Go to <strong>Company → Overview</strong> and ensure <strong>Text notifications</strong> (or the SMS link from account settings) is available.</li>
<li>Open <strong>Text notifications</strong>, turn on the master <strong>Text notifications</strong> switch, and enable the <strong>Delivery</strong> category.</li>
<li>Customers need a <strong>mobile or phone</strong> number on their record for live SMS. Your staff user needs a phone on file for sandbox testing (see below).</li>
</ol>
<h3>En route</h3>
<p>On a delivery in <strong>Scheduled</strong>, <strong>Confirmed</strong>, or <strong>Rescheduled</strong> status, use <strong>Mark en route</strong>. You can:</p>
<ul>
<li>Mark en route only (no text), or</li>
<li>Mark en route <strong>and notify by SMS</strong> — sends the customer a message with a link to review/track the delivery on your workspace site.</li>
</ul>
<p>Status changes to <strong>En route</strong> and estimated arrival is updated.</p>
<h3>Arrived on site</h3>
<p>While <strong>En route</strong>, use <strong>Confirm arrival</strong>. You can confirm without texting, or <strong>notify the customer by SMS</strong> that the driver has arrived (using the assigned technician’s name). Arrival can only be confirmed once per delivery.</p>
<h3>Signature request (email and SMS)</h3>
<p>When completing a delivery, choose <strong>Send Signature Request</strong>. The preview lets you send:</p>
<ul>
<li><strong>Email only</strong> — Link to review and sign the delivery receipt online.</li>
<li><strong>Email and SMS</strong> — Same link via text when delivery SMS is enabled and the customer has a mobile number.</li>
</ul>
<p>The customer opens the link on your workspace domain, reviews items, and signs electronically.</p>
<h3>Sandbox mode</h3>
<p>If <strong>Sandbox mode</strong> is on (Company → Overview → General), customer emails go to <strong>your login email</strong> and delivery SMS go to <strong>your staff profile phone</strong>—not the customer. Modals show a sandbox reminder so you can test safely before go-live.</p>
<h3>If SMS is unavailable</h3>
<p>The app explains why: delivery SMS disabled in settings, missing customer phone, missing staff phone in sandbox, or SMS not configured for the tenant. Email may still work when an address is on file.</p>
HTML,
            ],
            [
                'category_slug' => 'deliveries',
                'title' => 'Complete a delivery',
                'slug' => 'complete-a-delivery',
                'excerpt' => 'Mark delivered, capture signatures, and use delivery checklists.',
                'article_type' => 'guide',
                'sort_order' => 3,
                'featured' => false,
                'body' => <<<'HTML'
<p>When the unit reaches the customer, finish the delivery from its <strong>detail page</strong>.</p>
<h3>Complete delivery options</h3>
<p>Use <strong>Complete delivery</strong> (or equivalent action) and choose:</p>
<ul>
<li><strong>Send Signature Request</strong> — Email the customer (and optionally SMS) a secure link to review line items and sign. After they sign, the delivery is recorded as signed with timestamp and signature on file.</li>
<li><strong>Mark as Delivered</strong> — Close the delivery without a customer signature when your process allows it.</li>
</ul>
<p>You cannot send a new signature request after the delivery is already signed.</p>
<h3>Line items</h3>
<p>Deliveries can include multiple units. On the detail page you can mark individual items delivered when your workflow tracks partial completion.</p>
<h3>Delivery checklists</h3>
<p>Apply a checklist template (<strong>Operations → Deliveries → Templates</strong>) to walk through inspection or handoff steps on the delivery. Check off items on the delivery’s checklist tab before or after signing.</p>
<h3>Print and records</h3>
<p>Use <strong>Print</strong> for a paper copy of the delivery summary. Related work orders or sales records are linked under <strong>Related Records</strong> when present.</p>
<h3>After delivery</h3>
<p>Status becomes <strong>Delivered</strong>. The delivery remains in history for reporting and customer records but no longer appears as active on the scheduler for that day’s planning.</p>
HTML,
            ],
            [
                'category_slug' => 'boat-shows-events',
                'title' => 'Boat shows and events overview',
                'slug' => 'boat-shows-and-events-overview',
                'excerpt' => 'How shows, events, inventory, and public pages fit together.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p>Helmful helps you run dealership participation at boat shows—from planning inventory on the stand to capturing leads on the show floor.</p>
<h3>Shows vs events</h3>
<ul>
<li><strong>Boat show</strong> — The recurring exhibition (for example “Miami International Boat Show”). You store its name, logo, website, and description once.</li>
<li><strong>Event</strong> — Your dealership’s participation in a specific year: dates, venue, booth number, inventory, layout, and public links. Each event belongs to one boat show.</li>
</ul>
<p>Example: Boat show <em>MIBS</em> → Events <em>MIBS 2025</em>, <em>MIBS 2026</em>.</p>
<h3>Where to work</h3>
<p>Open <strong>Boat Shows</strong> in the workspace menu:</p>
<ul>
<li><strong>All Shows</strong> — Create and manage boat show records; open a show to see its upcoming and past events.</li>
<li><strong>Events</strong> — Flat list of every event across all shows (useful for cross-show search).</li>
<li><strong>Follow-up emails</strong> — One shared email template for automated thank-you messages after lead capture.</li>
</ul>
<h3>Typical workflow</h3>
<ol>
<li>Create or select the <strong>boat show</strong>.</li>
<li>Add an <strong>event</strong> with dates, venue, and booth details.</li>
<li>Assign <strong>assets</strong> (boats, engines, trailers) you are displaying.</li>
<li>Plan the <strong>booth layout</strong> and publish the <strong>public event page</strong> for visitors.</li>
<li>Review <strong>event leads</strong> in CRM and let follow-up emails run automatically.</li>
</ol>
<p>Each event also has <strong>checklists</strong> and <strong>tasks</strong> so your team can coordinate setup and breakdown.</p>
<h3>WordPress (Beta)</h3>
<p>If your dealership website runs on WordPress, you can sync boat shows and events to your marketing site with the <strong>Helmful Sync</strong> plugin. See <strong>Integrations → WordPress integration (Beta)</strong> in Help for setup steps, shortcodes, and display options.</p>
HTML,
            ],
            [
                'category_slug' => 'boat-shows-events',
                'title' => 'Set up a boat show event',
                'slug' => 'set-up-a-boat-show-event',
                'excerpt' => 'Create the show, add an event, assign inventory, and activate the public page.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<h3>Create a boat show</h3>
<ol>
<li>Go to <strong>Boat Shows → All Shows</strong> and add a new show.</li>
<li>Enter the exhibition name, optional website, logo, and description.</li>
<li>Save. You can attach many yearly events to this show over time.</li>
</ol>
<h3>Add an event</h3>
<p>From the boat show’s page, click <strong>Add event</strong>, or use <strong>Boat Shows → Events</strong> and pick the parent show when creating.</p>
<p>Important fields:</p>
<ul>
<li><strong>Display name</strong> — How staff and visitors see this participation (often includes the year).</li>
<li><strong>Year</strong> — Used for sorting and reporting.</li>
<li><strong>Starts / Ends</strong> — Show dates; the event detail page shows Upcoming, Live now, or Past from these dates.</li>
<li><strong>Venue and address</strong> — Location for maps and the public page header.</li>
<li><strong>Booth</strong> — Your stand or slip number at the show.</li>
<li><strong>Active</strong> — Must be on for public showcase and lead URLs to work. Turn off when the event is over or you are not ready to go live.</li>
</ul>
<h3>Assign inventory</h3>
<p>On the event page, open the <strong>Asset List</strong> tab and click <strong>Add asset</strong>. Pick boats, engines, or trailers from your workspace inventory (often linked to physical <strong>units</strong> on the lot).</p>
<p>Only assets on this list appear on the public showcase and can be selected on the lead form. Length and width on the layout come from asset or variant specs (converted to feet for display).</p>
<h3>Checklist and tasks</h3>
<ul>
<li><strong>Checklist</strong> — Track prep steps (transport, signage, demo rigging). Apply a template or save your list as a reusable template.</li>
<li><strong>Tasks</strong> — Assign work to team members with due dates, tied to this event.</li>
</ul>
<h3>Follow-up settings</h3>
<p>On <strong>Edit event</strong>, configure automatic follow-up email timing and which staff receive internal lead notifications. The message body itself is edited under <strong>Follow-up emails</strong> (workspace-wide template).</p>
HTML,
            ],
            [
                'category_slug' => 'boat-shows-events',
                'title' => 'Booth layout builder',
                'slug' => 'booth-layout-builder',
                'excerpt' => 'Place boats on a feet-based canvas to plan your stand before the show.',
                'article_type' => 'guide',
                'sort_order' => 2,
                'featured' => false,
                'body' => <<<'HTML'
<p>The <strong>Layout Builder</strong> tab on an event is a 2D plan of your booth or dock space in <strong>feet</strong>.</p>
<h3>Canvas basics</h3>
<ul>
<li>Set the layout <strong>width</strong> and <strong>height</strong> (in feet) to match your allotted booth or dock space.</li>
<li>Changes save automatically after you stop editing (you may see a brief “Saving layout…” indicator).</li>
</ul>
<h3>Placing boats</h3>
<ol>
<li>Click to <strong>add</strong> a boat from inventory assigned to the event (or attach a unit from the picker).</li>
<li><strong>Drag</strong> to move; positions snap to whole feet.</li>
<li><strong>Rotate</strong> in 90° steps (length and width swap on the canvas when rotated).</li>
<li><strong>Resize</strong> individual boat dimensions on the layout if the plotted size should differ from catalog specs.</li>
</ol>
<p>Each placed boat stores a <strong>snapshot</strong> of name and dimensions—later changes to the asset record do not move or resize items on the layout until you edit the layout again.</p>
<h3>Why use it</h3>
<p>Plan traffic flow, confirm everything fits before transport, and align sales staff on which hull sits where. The layout is internal planning; visitors see the separate <strong>public event page</strong> asset list, not this canvas.</p>
<h3>Tips</h3>
<ul>
<li>Assign assets on the <strong>Asset List</strong> tab first so they are available to place.</li>
<li>Boats drawn outside the canvas bounds are highlighted as out of bounds—shrink the boat or expand the layout.</li>
<li>Boats, engines, and trailers use different colors on the canvas; leave walkways that match fire lanes and customer flow at the venue.</li>
</ul>
HTML,
            ],
            [
                'category_slug' => 'boat-shows-events',
                'title' => 'Public pages and lead capture',
                'slug' => 'public-pages-and-lead-capture',
                'excerpt' => 'Share the showcase, QR codes, and a lead form visitors use on their phones.',
                'article_type' => 'guide',
                'sort_order' => 3,
                'featured' => true,
                'body' => <<<'HTML'
<p>Each <strong>active</strong> event has guest-facing pages on your workspace subdomain. Staff open them from the event sidebar: <strong>Public event page</strong>.</p>
<h3>Public showcase</h3>
<p>Visitors see your branding, event name, dates, venue, and a list of boats, engines, and trailers on the event. Each item can link to a <strong>detail page</strong> with photos and specs.</p>
<p>From the showcase they can open the <strong>lead form</strong> to request contact or more information.</p>
<h3>Lead form</h3>
<p>Guests enter name, email, phone, optional notes, trade-in interest, and marketing opt-in. They can select one or more boats they are interested in (only from the event asset list).</p>
<p>Submissions create a <strong>CRM lead</strong> assigned to your account’s default salesperson, tagged with the event as source, and appear on the event’s <strong>Event leads</strong> tab.</p>
<h3>QR codes</h3>
<ul>
<li><strong>Lead QR</strong> — On the showcase, a prominent code links straight to the lead form (ideal for booth signage).</li>
<li><strong>Per-asset QR</strong> — Each inventory row can have its own code linking to that asset’s public detail page; the lead form can pre-select that boat when scanned.</li>
</ul>
<h3>Print flyer</h3>
<p>When you are signed in and view the public showcase, use <strong>Print flyer</strong> to open a print-friendly page with event branding, inventory, and QR codes—handy for table tents or handouts.</p>
<h3>Before you open the doors</h3>
<ul>
<li>Confirm the event is <strong>Active</strong>.</li>
<li>Add every unit you want visible on the <strong>Asset List</strong>.</li>
<li>Test the public page and lead form on a phone on the show floor Wi‑Fi.</li>
</ul>
HTML,
            ],
            [
                'category_slug' => 'boat-shows-events',
                'title' => 'Follow-up emails and event leads',
                'slug' => 'follow-up-emails-and-event-leads',
                'excerpt' => 'Automated thank-you messages, merge tags, and reviewing captured leads.',
                'article_type' => 'guide',
                'sort_order' => 4,
                'featured' => false,
                'body' => <<<'HTML'
<p>After someone submits the public lead form, Helmful can email them automatically and notify your team.</p>
<h3>Workspace email template</h3>
<p>Go to <strong>Boat Shows → Follow-up emails</strong> to edit the shared template:</p>
<ul>
<li><strong>Subject and body</strong> — Rich text with merge variables (customer name, event name, etc.).</li>
<li><code>{{ selected_asset_list }}</code> — Inserts the boats or products the visitor selected on the form.</li>
<li><strong>Send test</strong> — Email yourself a preview before the show.</li>
</ul>
<p>One template serves all events; timing and recipients are set per event.</p>
<h3>Per-event follow-up settings</h3>
<p>On <strong>Edit event</strong>:</p>
<ul>
<li><strong>Auto follow-up</strong> — When on, visitors with an email address receive the template after a delay.</li>
<li><strong>Delay</strong> — Wait minutes, hours, or days after submission (for example 1 day).</li>
<li><strong>Staff recipients</strong> — Optional list of users who receive an internal notification when a lead is captured; if none are chosen, the account owner is used.</li>
</ul>
<p>The event <strong>Details</strong> tab summarizes whether auto follow-up is on and the current delay.</p>
<h3>Reviewing leads</h3>
<p>On the event page, open <strong>Event leads</strong> to see everyone who submitted the public form, when they were captured, and a link to the full <strong>Lead</strong> record in Relationships → Leads.</p>
<p>Leads are scored and include show context in notes so sales knows which event generated the inquiry.</p>
<h3>Email requirements</h3>
<p>Automatic follow-up only runs when the visitor provides an <strong>email</strong> and auto follow-up is enabled. Visitors without email still create a lead if they submit the form with phone or name.</p>
<p>Customer-facing mail respects <strong>sandbox mode</strong> the same as other workspace notifications—test with sandbox on before sending live messages at the show.</p>
HTML,
            ],
            [
                'category_slug' => 'service-yard',
                'title' => 'Service tickets and work orders',
                'slug' => 'service-tickets-and-work-orders',
                'excerpt' => 'How the service department coordinates prep and repairs using tickets and work orders.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p>The <strong>service yard</strong> is where your team plans labor on boats, engines, and trailers after the sale—or for customer pay repairs.</p>
<h3>Two levels of work</h3>
<table>
<thead>
<tr><th></th><th><strong>Service ticket</strong></th><th><strong>Work order</strong></th></tr>
</thead>
<tbody>
<tr>
<td><strong>Purpose</strong></td>
<td>The customer-facing job: estimate, approval, and overall status for one repair or prep project.</td>
<td>A technician assignment under that ticket—rigging, electronics, detail, water test, and so on.</td>
</tr>
<tr>
<td><strong>Typical use</strong></td>
<td>One ticket per boat prep or service visit (ST-1001).</td>
<td>Multiple work orders per ticket (WO-1001 Motor rigging, WO-1002 Electronics).</td>
</tr>
<tr>
<td><strong>Customer</strong></td>
<td>Receives estimate and signs or approves the ticket.</td>
<td>Internal execution; customer usually interacts with the ticket, not each WO.</td>
</tr>
</tbody>
</table>
<h3>Where to work</h3>
<p>Under <strong>Operations → Service Yard</strong>:</p>
<ul>
<li><strong>Overview</strong> — Open tickets and work orders grouped by <strong>location</strong> (quick yard snapshot).</li>
<li><strong>Service Tickets</strong> — Full list; create and manage estimates.</li>
<li><strong>Work Orders</strong> — Full list; assign technicians and track execution.</li>
<li><strong>Scheduler</strong> — Week grid to place work orders on technicians’ calendars.</li>
</ul>
<p>Line-item labor and parts on tickets come from <strong>Inventory → Service Items</strong> (your catalog of billable tasks).</p>
<h3>Typical flow</h3>
<ol>
<li>Create a <strong>service ticket</strong> for the customer, asset unit, and location.</li>
<li>Add <strong>service items</strong> (labor/parts lines) and send the estimate for <strong>customer approval</strong>.</li>
<li>After approval, create one or more <strong>work orders</strong> linked to the ticket.</li>
<li><strong>Schedule</strong> work orders on the Service Yard scheduler.</li>
<li>Complete work orders, then mark the service ticket <strong>Completed</strong> when the job is done.</li>
</ol>
<p>Tickets can link to a <strong>deal</strong> (transaction) when prep is part of a sale. Completing a ticket can optionally close open work orders at the same time.</p>
HTML,
            ],
            [
                'category_slug' => 'service-yard',
                'title' => 'Create and approve a service ticket',
                'slug' => 'create-and-approve-a-service-ticket',
                'excerpt' => 'Build an estimate, send it for customer approval, and lock the ticket after signing.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<h3>Create a ticket</h3>
<ol>
<li>Go to <strong>Operations → Service Yard → Service Tickets</strong> and create a new ticket.</li>
<li>Select <strong>customer</strong>, <strong>location</strong>, and <strong>subsidiary</strong> (for tax).</li>
<li>Choose the <strong>asset unit</strong> (boat, engine, or trailer) being serviced, if applicable.</li>
<li>Enter the <strong>repair description</strong> and any <strong>internal notes</strong> (staff only).</li>
<li>Optional: mark <strong>Expedite</strong> for rush jobs, or set a requested pickup/delivery date.</li>
</ol>
<h3>Add service items</h3>
<p>On the ticket form, add lines from your <strong>service items</strong> catalog. Each line can include quantity, estimated hours, labor price, parts, warranty flag, and billable toggles. Totals roll up to:</p>
<ul>
<li>Estimated labor and parts</li>
<li>Subtotal, tax (from location tax rate), and <strong>estimated total</strong></li>
</ul>
<h3>Send for customer approval</h3>
<p>From the ticket detail page, open <strong>Customer Preview</strong> and use <strong>Send Approval Request</strong>. The customer receives an email (and optionally SMS when enabled under <strong>Text notifications → Service ticket</strong>) with a link to review the estimate on your workspace site.</p>
<p>On the public review page they can <strong>approve</strong> (with signature) or <strong>decline</strong>. After approval, the ticket is <strong>locked</strong>—line items and pricing cannot be edited without a formal revision process.</p>
<h3>Other approval methods</h3>
<p>Staff can also record approval via paper signature (upload a document), verbal approval, or in-person signing depending on your process and the ticket’s signature method fields.</p>
<h3>Ticket statuses</h3>
<ul>
<li><strong>Draft</strong> — Still being built.</li>
<li><strong>Open</strong> — Active, not yet in the shop or awaiting scheduling.</li>
<li><strong>In progress</strong> — Work is underway (often when linked work orders are active).</li>
<li><strong>Completed</strong> — Job finished; you may be prompted to complete open work orders too.</li>
<li><strong>Closed / Cancelled</strong> — Archived or voided.</li>
</ul>
<h3>Sandbox mode</h3>
<p>Approval emails and SMS follow the same <strong>sandbox</strong> rules as deliveries—while testing, messages go to you, not the customer.</p>
HTML,
            ],
            [
                'category_slug' => 'service-yard',
                'title' => 'Manage work orders',
                'slug' => 'manage-work-orders',
                'excerpt' => 'Create technician jobs from a ticket, track status, and keep the ticket in sync.',
                'article_type' => 'guide',
                'sort_order' => 2,
                'featured' => true,
                'body' => <<<'HTML'
<h3>Create a work order</h3>
<ol>
<li>Open the parent <strong>service ticket</strong> and click <strong>Create Work Order</strong>, or go to <strong>Work Orders → New</strong> and select the linked ticket.</li>
<li>The work order inherits <strong>customer</strong> and <strong>asset unit</strong> from the ticket (the ticket link cannot be changed later).</li>
<li>Enter a <strong>description</strong>, <strong>work type</strong>, <strong>priority</strong>, and assign a <strong>technician</strong> when known.</li>
<li>Set <strong>location</strong>, estimated hours, and optional internal or customer notes.</li>
<li>Save. You can create multiple work orders for different tasks on the same ticket.</li>
</ol>
<h3>Work order statuses</h3>
<ul>
<li><strong>Open / Scheduled</strong> — Queued or placed on the calendar.</li>
<li><strong>In progress</strong> — Technician is actively working.</li>
<li><strong>Waiting / Blocked</strong> — Paused (parts, weather, customer, and so on).</li>
<li><strong>Completed / Closed</strong> — Done.</li>
<li><strong>Cancelled</strong> — Will not be performed.</li>
</ul>
<p>Updating a work order’s status can roll up to the parent service ticket (for example, in progress on a WO moves the ticket toward in progress).</p>
<h3>Service items on work orders</h3>
<p>Work orders can carry their own service item lines for labor and parts used on that specific task, separate from the ticket-level estimate the customer approved.</p>
<h3>Complete the job</h3>
<p>When all work orders are finished, set the service ticket to <strong>Completed</strong>. If any work orders are still open, Helmful asks whether to mark them completed at the same time.</p>
<h3>Warranty and deliveries</h3>
<p>Work orders may tie into <strong>warranty claims</strong> (photos from the ticket) and can feed <strong>deliveries</strong> when a unit is ready for customer handoff—use related links on the work order or ticket detail pages.</p>
HTML,
            ],
            [
                'category_slug' => 'service-yard',
                'title' => 'Service yard scheduler',
                'slug' => 'service-yard-scheduler',
                'excerpt' => 'Assign work orders to technicians on a weekly calendar board.',
                'article_type' => 'guide',
                'sort_order' => 3,
                'featured' => false,
                'body' => <<<'HTML'
<p>The <strong>Service Yard → Scheduler</strong> is a week-based board for <strong>work orders only</strong>. Customer deliveries use the separate <strong>Delivery Scheduler</strong> under Operations → Deliveries.</p>
<h3>Open the scheduler</h3>
<p>Go to <strong>Operations → Service Yard → Scheduler</strong>. You can also open it from the Service Yard <strong>Overview</strong> page.</p>
<h3>What you see</h3>
<ul>
<li>One row per <strong>technician</strong> (users marked as technicians in your workspace).</li>
<li>Columns across the week; navigate forward and back by week.</li>
<li>Each block is a scheduled work order showing number, customer, and timing.</li>
<li>Filter by <strong>location</strong> to focus on a specific store or yard.</li>
</ul>
<h3>Scheduling actions</h3>
<ul>
<li><strong>Drag</strong> a work order to another day or technician (times snap to 15-minute increments).</li>
<li><strong>Resize</strong> the block to change duration.</li>
<li><strong>Click</strong> a block to open details and edit scheduled start time in the side panel.</li>
</ul>
<p>Changes save to the work order’s <strong>scheduled start</strong> (and related fields). If overlap is disabled, the board prevents double-booking the same technician.</p>
<h3>Scheduling defaults</h3>
<p>Default <strong>workday length</strong>, <strong>start hour</strong>, and whether <strong>overlap</strong> is allowed come from <strong>Company → Overview</strong> under scheduling settings. Adjust them there if the grid’s visible hours do not match your shop day.</p>
<h3>Work orders without schedule</h3>
<p>Work orders that only have a <strong>due date</strong> or no schedule still appear when they fall in the visible range. Assign a technician on the work order record before dragging it on the board.</p>
<h3>Service yard overview</h3>
<p>For a simple list—not a calendar—use <strong>Service Yard → Overview</strong>. It groups open tickets and work orders by location so managers can see backlog without opening the scheduler.</p>
HTML,
            ],
            [
                'category_slug' => 'reports',
                'title' => 'Reports overview',
                'slug' => 'reports-overview',
                'excerpt' => 'Where to find reports and how date range and location filters work.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p>Helmful reports summarize invoiced sales, costs, tax, and cash movement from your workspace data. They are read-only views for management and accounting—not live dashboards.</p>
<h3>Open reports</h3>
<p>Go to <strong>Reports</strong> in the main menu. Each report page includes a <strong>report switcher</strong> dropdown to jump between reports without returning to the menu.</p>
<h3>Common filters</h3>
<p>Most reports share:</p>
<ul>
<li><strong>Date range</strong> — From and to dates (defaults often include the last 30 days).</li>
<li><strong>Subsidiary</strong> — Limit to one legal entity when you use multiple subsidiaries.</li>
<li><strong>Location</strong> — Limit to one store or yard.</li>
</ul>
<p>Change filters and apply to refresh the numbers. Profit &amp; Loss also offers a <strong>cards</strong> or <strong>table</strong> view where available.</p>
<h3>Report groups</h3>
<ul>
<li><strong>Financial</strong> — Profit &amp; Loss, Cash Flow, Sales Tax Liability, Sales Tax Payable.</li>
<li><strong>Sales</strong> — Sales by Customer, Sales by Item (Summary), Sales by Item (Detail).</li>
</ul>
<p>Data comes from posted invoices and related line items. Warranty and internal billing lines may be classified separately on Profit &amp; Loss (customer billable vs warranty costs).</p>
HTML,
            ],
            [
                'category_slug' => 'reports',
                'title' => 'Financial reports',
                'slug' => 'financial-reports',
                'excerpt' => 'Profit & Loss, cash flow, and sales tax reports explained.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<h3>Profit &amp; Loss</h3>
<p><strong>Reports → Financial → Profit &amp; Loss</strong> shows revenue and cost of goods sold for the selected period, broken down by:</p>
<ul>
<li><strong>Boat sales</strong> — Customer-billable asset/unit lines on invoices.</li>
<li><strong>Parts sales</strong> — Inventory item lines.</li>
<li><strong>Service revenue</strong> — Labor and service lines (including work tied to work orders).</li>
</ul>
<p>Matching <strong>cost</strong> sections use line costs, unit costs, and option COGS where applicable. Warranty-related costs may appear in separate warranty rows for dealership vs manufacturer coverage.</p>
<p>Use this report to see gross margin by category for a month, quarter, or custom range.</p>
<h3>Cash Flow</h3>
<p><strong>Cash Flow</strong> summarizes money in and out over the date range—helpful for understanding liquidity alongside accrual-style P&amp;L.</p>
<h3>Sales tax</h3>
<ul>
<li><strong>Sales Tax Liability</strong> — Tax collected on sales in the period (what you may owe to tax authorities).</li>
<li><strong>Sales Tax Payable</strong> — Payable-oriented view for reconciling tax balances.</li>
</ul>
<p>Ensure invoice tax rates and locations are set correctly on transactions for accurate tax reporting.</p>
<h3>Tips</h3>
<ul>
<li>Compare subsidiary and location filters when you run multiple stores.</li>
<li>P&amp;L focuses on <strong>customer-billable</strong> revenue; manufacturer warranty reimbursement is tracked through warranty claims, not customer invoices.</li>
</ul>
HTML,
            ],
            [
                'category_slug' => 'reports',
                'title' => 'Sales reports',
                'slug' => 'sales-reports',
                'excerpt' => 'Sales by customer and by item (summary and detail).',
                'article_type' => 'guide',
                'sort_order' => 2,
                'featured' => false,
                'body' => <<<'HTML'
<h3>Sales by Customer</h3>
<p><strong>Reports → Sales → Sales by Customer</strong> ranks customers by invoiced sales in the date range. Click a customer to drill into their invoices for the same period.</p>
<p>Use it for account reviews, rep commissions, or identifying top buyers.</p>
<h3>Sales by Item (Summary)</h3>
<p><strong>Sales by Item (Summary)</strong> rolls up revenue by product or service line type across all customers—boats, parts, service items, and other billable categories.</p>
<h3>Sales by Item (Detail)</h3>
<p><strong>Sales by Item (Detail)</strong> lists individual invoice line items with quantities, amounts, and references. Use it when you need to audit specific SKUs, options, or service codes.</p>
<h3>Filters</h3>
<p>Apply the same <strong>date range</strong>, <strong>subsidiary</strong>, and <strong>location</strong> filters as other reports so numbers align with Profit &amp; Loss for the same period.</p>
HTML,
            ],
            [
                'category_slug' => 'customer-portal',
                'title' => 'Customer portal overview',
                'slug' => 'customer-portal-overview',
                'excerpt' => 'What customers can do after they register on your workspace portal.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p>The <strong>customer portal</strong> is a secure site on your dealership’s workspace domain where customers sign in to see their own records—without calling the store for every update.</p>
<h3>Portal URL</h3>
<p>Customers use your tenant subdomain, for example:</p>
<ul>
<li><strong>Sign in</strong> — <code>/portal/login</code></li>
<li><strong>Create account</strong> — <code>/portal/register</code> (first-time setup with their email)</li>
</ul>
<p>Registration only works when their email matches an existing <strong>contact</strong> linked to a <strong>customer</strong> profile in Helmful.</p>
<h3>What customers can access</h3>
<p>After login, the portal home shows shortcuts to:</p>
<ul>
<li><strong>Estimates</strong> — View sent quotes; approve or decline online.</li>
<li><strong>Invoices</strong> — View balances and pay online when QuickBooks/payment options are enabled.</li>
<li><strong>Service tickets</strong> — See open and past service work and totals.</li>
<li><strong>Documents</strong> — Download shared files and fulfill document requests from your team.</li>
<li><strong>Specification sheets</strong> — Review spec sheets you shared; optionally save option selections.</li>
</ul>
<h3>Staff vs customer</h3>
<p>Portal users authenticate as <strong>contacts</strong> (customer portal accounts), separate from your internal staff login. Each contact only sees data for their linked customer.</p>
HTML,
            ],
            [
                'category_slug' => 'customer-portal',
                'title' => 'Invite customers to the portal',
                'slug' => 'invite-customers-to-the-portal',
                'excerpt' => 'Send portal login and registration links from a contact record.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<h3>Prerequisites</h3>
<ul>
<li>The person exists as a <strong>contact</strong> with a primary <strong>email</strong>.</li>
<li>That contact is linked to a <strong>customer</strong> record (portal registration checks the customer link).</li>
</ul>
<h3>Send the portal email</h3>
<ol>
<li>Open <strong>Relationships → Contacts</strong> and select the contact.</li>
<li>In the <strong>Customer portal</strong> section, click <strong>Send portal email</strong>.</li>
<li>The message explains how to create an account (first visit) or sign in with the email on file.</li>
</ol>
<p>If they already registered, they should use <strong>Sign in</strong> instead of creating a duplicate account.</p>
<h3>First-time registration flow</h3>
<ol>
<li>Customer opens the link and goes to <strong>Create account</strong>.</li>
<li>They enter the same email Helmful has on the contact and choose a password.</li>
<li>After registration, they land on the portal overview.</li>
</ol>
<h3>Estimates and service tickets in the portal</h3>
<p>Customers can approve estimates from the portal without a separate email link. Service tickets appear for visibility; detailed approval may still use the dedicated review link you send from the service ticket screen (same as email approval workflow).</p>
<h3>Sandbox mode</h3>
<p>Operational emails (including portal invitations) follow workspace <strong>sandbox mode</strong> when applicable—test with sandbox on so messages come to you first.</p>
HTML,
            ],
            [
                'category_slug' => 'warranty-vendor-portal',
                'title' => 'Warranty claims overview',
                'slug' => 'warranty-claims-overview',
                'excerpt' => 'Manufacturer-paid warranty work from work order to claim payment.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p>A <strong>warranty claim</strong> is how your dealership requests payment from a <strong>manufacturer (vendor)</strong> for warranty work—not from the customer.</p>
<h3>How it fits together</h3>
<ol>
<li>Technicians perform warranty work on a <strong>work order</strong> with lines marked manufacturer warranty.</li>
<li>The customer invoice may show $0 for covered lines.</li>
<li>You create a <strong>warranty claim</strong> linked to that work order and vendor.</li>
<li>After <strong>submit</strong>, the manufacturer reviews and approves or rejects in the <strong>vendor portal</strong>.</li>
<li>When paid, you record <strong>Paid</strong> status for reporting.</li>
</ol>
<h3>Where to work</h3>
<p><strong>Operations → Warranty claims</strong> — List and open claims.</p>
<p><strong>Relationships → Vendors</strong> — Manufacturers; attach contacts with <strong>portal access</strong> for online responses.</p>
<h3>Claim statuses</h3>
<ul>
<li><strong>Draft</strong> — Building line items and amounts.</li>
<li><strong>Submitted</strong> — Sent to the manufacturer for review.</li>
<li><strong>Approved / Rejected</strong> — Manufacturer decision (via portal or your internal update).</li>
<li><strong>Paid</strong> — Reimbursement received.</li>
<li><strong>Voided</strong> — Cancelled claim.</li>
</ul>
HTML,
            ],
            [
                'category_slug' => 'warranty-vendor-portal',
                'title' => 'Create and submit a warranty claim',
                'slug' => 'create-and-submit-a-warranty-claim',
                'excerpt' => 'Pull warranty lines from a work order, submit, and notify manufacturer contacts.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<h3>Create a claim</h3>
<ol>
<li>From a qualifying <strong>work order</strong> or <strong>Warranty claims → New</strong>, create a claim.</li>
<li>Select the <strong>vendor</strong> (manufacturer), <strong>subsidiary</strong>, and <strong>location</strong>.</li>
<li>Link the <strong>work order</strong> when the claim is tied to specific warranty labor/parts.</li>
<li>Review <strong>line items</strong>—descriptions, quantities, and amounts the manufacturer should pay. Adjust pricing to match manufacturer rate sheets.</li>
<li>Attach supporting <strong>documents</strong> or photos if needed.</li>
</ol>
<h3>Submit to the manufacturer</h3>
<p>While the claim is <strong>Draft</strong>, use <strong>Submit warranty claim</strong> on the claim detail page.</p>
<ul>
<li>Optionally select <strong>manufacturer contacts</strong> to email. Each receives a link to preview the claim and instructions to sign in to the vendor portal.</li>
<li>You can submit <strong>without</strong> emailing if you will coordinate offline—status still moves to <strong>Submitted</strong>.</li>
</ul>
<p>Only contacts with <strong>portal access</strong> enabled on the vendor relationship can be selected for notification (configure on the vendor’s contact list).</p>
<h3>After submit</h3>
<ul>
<li>Use <strong>Send to vendor</strong> later to email additional contacts if needed.</li>
<li>Track status on the claim page and in the vendor portal.</li>
<li>When the manufacturer approves, you may mark the claim <strong>Paid</strong> when funds arrive.</li>
</ul>
<h3>Sandbox mode</h3>
<p>Submit and vendor notification emails respect <strong>sandbox mode</strong>—while testing, messages go to you, not manufacturer contacts.</p>
HTML,
            ],
            [
                'category_slug' => 'warranty-vendor-portal',
                'title' => 'Vendor portal for manufacturers',
                'slug' => 'vendor-portal-for-manufacturers',
                'excerpt' => 'How manufacturer contacts register, verify email, and approve warranty claims.',
                'article_type' => 'guide',
                'sort_order' => 2,
                'featured' => true,
                'body' => <<<'HTML'
<p>The <strong>vendor portal</strong> (manufacturer portal) lets OEM and supplier contacts review and respond to warranty claims online.</p>
<h3>Enable portal access (dealership)</h3>
<ol>
<li>Open <strong>Relationships → Vendors</strong> and edit the manufacturer.</li>
<li>On linked <strong>contacts</strong>, turn on <strong>Portal access</strong> for people who should use the vendor portal.</li>
<li>Use <strong>Send vendor portal link</strong> (vendor or contact level) to email registration/sign-in instructions.</li>
</ol>
<h3>Manufacturer registration</h3>
<p>Contacts visit <code>/vendor/portal/register</code> on your workspace domain. Registration requires:</p>
<ul>
<li>An email that matches a <strong>contact</strong> already linked to at least one <strong>vendor</strong>.</li>
<li>A new password (or sign in if they already registered).</li>
</ul>
<p>They must <strong>verify their email</strong> before accessing claims. Contacts without portal access on any manufacturer see a <strong>no access</strong> message after login.</p>
<h3>Sign in</h3>
<p>Returning users use <code>/vendor/portal/login</code>.</p>
<h3>Responding to claims</h3>
<p>On the portal home, manufacturers open <strong>Warranty claims</strong> to see submitted claims for vendors they are allowed to access.</p>
<p>On a claim they can:</p>
<ul>
<li>Review line items and download attachments.</li>
<li>Add <strong>line feedback</strong> or notes where provided.</li>
<li><strong>Approve</strong> or <strong>Reject</strong> the claim (updates status in your workspace and notifies your team).</li>
</ul>
<p>They may also open a <strong>review link</strong> from email without signing in first; full approve/reject actions require the vendor portal when that is your process.</p>
<h3>Public review link</h3>
<p>Submitted claims have a guest <strong>review</strong> URL (from notification emails) for read-only preview. The review page links to the vendor portal for official approval.</p>
HTML,
            ],
            [
                'category_slug' => 'integrations',
                'title' => 'WordPress integration (Beta)',
                'slug' => 'wordpress-integration-beta',
                'excerpt' => 'Sync boat shows, events, brands, and inventory to WordPress with the Helmful Sync plugin.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p><strong>Beta:</strong> The WordPress integration is in active development. Core sync and shortcode display work today, but features and plugin behavior may change between releases. Report issues to your Helmful contact before relying on it for a live marketing launch.</p>
<h3>What it does</h3>
<p>Helmful pushes (or WordPress pulls) content into your WordPress site as local custom posts—no live API calls when visitors load pages.</p>
<ul>
<li><strong>Boat shows</strong> — Exhibition records with name, logo, description, and website.</li>
<li><strong>Events</strong> — Yearly participation (dates, venue, booth) linked to each boat show.</li>
<li><strong>Brands</strong> — Manufacturer brands from your workspace catalog.</li>
<li><strong>Inventory</strong> — Boats and related catalog items for public browsing and quote requests.</li>
</ul>
<p>Shortcodes on WordPress pages render synced data with layouts you control in the plugin. Links on the public site go to WordPress URLs (for example <code>/boat-shows/your-show/</code>), not back into Helmful.</p>
<h3>Before you start</h3>
<ul>
<li>A self-hosted WordPress site where you can install plugins and save permalinks.</li>
<li>Admin access to both Helmful and WordPress.</li>
<li>Boat shows and events already created in Helmful (or ready to sync after setup).</li>
</ul>
<h3>Step 1 — Install the plugin</h3>
<ol>
<li>In Helmful, open <strong>Integrations → WordPress</strong>.</li>
<li>Download <strong>helmful-sync.zip</strong> and note the plugin version shown on the page.</li>
<li>In WordPress, go to <strong>Plugins → Add New → Upload Plugin</strong>, upload the zip, and activate <strong>Helmful Sync</strong>.</li>
</ol>
<h3>Step 2 — Connect Helmful → WordPress</h3>
<ol>
<li>On the Helmful WordPress page, click <strong>Generate integration key</strong> (or replace an existing key) and copy the <strong>Helmful integration key</strong> and <strong>tenant domain</strong>.</li>
<li>Paste the tenant domain and Helmful key into WordPress under <strong>Settings → Helmful Sync → Connection</strong>, then save.</li>
<li>In WordPress, click <strong>Generate new API key</strong> and copy the WordPress API key shown once.</li>
<li>Back in Helmful, enter your <strong>WordPress site URL</strong> and the WordPress API key, then click <strong>Save settings</strong>.</li>
<li>Use <strong>Test connection</strong> on either side to confirm credentials.</li>
</ol>
<h3>Step 3 — Sync content</h3>
<p>You can move data in either direction:</p>
<ul>
<li><strong>Push all to WordPress</strong> (Helmful) — Sends all boat shows and events from your workspace.</li>
<li><strong>Pull from Helmful</strong> (WordPress → Settings → Helmful Sync) — Boat shows, events, brands, and inventory each have separate pull buttons. Pull <strong>brands</strong> before inventory if you filter inventory by brand on the site.</li>
<li><strong>Auto-push</strong> (Helmful) — When enabled, saving boat shows and events in Helmful queues an update to WordPress.</li>
</ul>
<p>After a plugin update, open WordPress <strong>Settings → Permalinks</strong> and click <strong>Save Changes</strong> once so single show and event URLs work.</p>
<h3>Step 4 — Add pages and shortcodes</h3>
<p>Create regular WordPress <strong>Pages</strong> (not post-type archives) and paste shortcodes from <strong>Settings → Helmful Sync → Shortcodes</strong>.</p>
<ul>
<li><code>[helmful_boat_shows]</code> — Recommended listing page (for example slug <code>boat-shows</code>). Layout comes from plugin Display settings.</li>
<li><code>[helmful_boat_shows layout="grid"]</code> — Override layout: stacked, grid, timeline, or compact.</li>
<li><code>[helmful_boat_show_events]</code> — Events only; optional <code>year="2026"</code> filter.</li>
<li><code>[helmful_brands]</code> — Brand grid linking to inventory filtered by brand.</li>
<li>Inventory shortcodes — See the Shortcodes tab in the plugin for catalog listing and detail embeds.</li>
</ul>
<p>Single boat show and event URLs use Helmful templates automatically (hero, events, maps link)—you do not need a shortcode on those URLs.</p>
<h3>Display settings (WordPress)</h3>
<p>Under <strong>Settings → Helmful Sync → Display</strong> choose default layout, grid columns, accent color, card style, and spacing for boat show shortcodes. Inventory display options are on the <strong>Inventory</strong> tab.</p>
<h3>Where to manage content</h3>
<ul>
<li><strong>Source of truth</strong> — Edit boat shows, events, brands, and inventory in Helmful, then sync.</li>
<li><strong>WordPress admin</strong> — <strong>Boat Shows</strong> and <strong>Events</strong> appear under the Boat Shows menu for reference; synced fields are read-only from Helmful’s perspective.</li>
</ul>
<h3>Beta limitations</h3>
<ul>
<li>Requires the Helmful Sync plugin—there is no generic REST export without it.</li>
<li>Theme CSS on some sites may need extra spacing around shortcodes; use the plugin’s scoped shell classes if your theme overrides lists or typography.</li>
<li>Re-install or update the plugin from Helmful when your contact ships a new zip—version numbers are shown on the integration page.</li>
</ul>
<p>When you are ready for production, run a full push or pull, test every shortcode page on mobile, and confirm event dates and logos on at least one boat show single page.</p>
HTML,
            ],
        ];
    }
}
