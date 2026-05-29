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

        if (HelpCategory::query()->exists()) {
            return;
        }

        $now = now();

        foreach ($this->categories() as $category) {
            HelpCategory::query()->create([
                ...$category,
                'parent_id' => null,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categoryIds = HelpCategory::query()
            ->whereIn('slug', array_column($this->categories(), 'slug'))
            ->pluck('id', 'slug');

        foreach ($this->articles() as $article) {
            $categorySlug = $article['category_slug'];
            unset($article['category_slug']);

            HelpArticle::query()->create([
                ...$article,
                'category_id' => $categoryIds[$categorySlug] ?? null,
                'active' => true,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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
        ];
    }
}
