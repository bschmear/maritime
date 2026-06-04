<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class HelpSmartSurveysSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::connection('pgsql')->hasTable('help_categories')) {
            return;
        }

        $now = now();

        HelpCategory::query()->firstOrCreate(
            ['slug' => 'smart-surveys'],
            [
                'name' => 'Smart Surveys',
                'slug' => 'smart-surveys',
                'description' => 'Build surveys, capture leads and feedback, share public links, and follow up from Helmful.',
                'parent_id' => null,
                'active' => true,
                'sort_order' => 11,
            ],
        );

        $categoryId = HelpCategory::query()
            ->where('slug', 'smart-surveys')
            ->value('id');

        if ($categoryId === null) {
            return;
        }

        foreach ($this->articles() as $article) {
            HelpArticle::query()->firstOrCreate(
                ['slug' => $article['slug']],
                [
                    ...$article,
                    'category_id' => $categoryId,
                    'active' => true,
                    'published_at' => $now,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        return [
            [
                'title' => 'Smart Surveys overview',
                'slug' => 'smart-surveys-overview',
                'excerpt' => 'Collect feedback and leads in one place—connected to contacts, deals, and your team inbox.',
                'article_type' => 'guide',
                'sort_order' => 0,
                'featured' => true,
                'body' => <<<'HTML'
<p><strong>Smart Surveys</strong> lets your dealership create branded questionnaires for post-sale feedback, service follow-up, boat show outreach, and lead intake—without a separate form tool.</p>
<h3>Where to find it</h3>
<p>In your workspace, open <strong>Relationships → Surveys</strong>:</p>
<ul>
<li><strong>All Surveys</strong> — List, filter, and open surveys you have built.</li>
<li><strong>Create</strong> — Start a new survey from a marine template or from scratch.</li>
<li><strong>Responses</strong> — Review submissions across every survey.</li>
</ul>
<h3>Survey types</h3>
<ul>
<li><strong>Feedback</strong> — Satisfaction, NPS, and service experience after a sale or visit.</li>
<li><strong>Lead</strong> — Intake forms that can be converted into leads in your pipeline.</li>
<li><strong>Follow-up</strong> — Re-engagement with past clients or show attendees.</li>
<li><strong>Custom</strong> — Any other use case you define.</li>
</ul>
<h3>How it connects to Helmful</h3>
<ul>
<li>Responses can log <strong>communications</strong> on matching contacts when an email is known.</li>
<li>The survey <strong>assignee</strong> gets in-app and email notifications when someone submits.</li>
<li><strong>Lead</strong> surveys track conversion metrics on the survey dashboard and support one-click conversion to a lead record.</li>
<li>You can <strong>send</strong> a survey link from a contact, deal, or related record when you are ready—nothing blasts automatically without your confirmation.</li>
</ul>
HTML,
            ],
            [
                'title' => 'Create a survey',
                'slug' => 'create-a-smart-survey',
                'excerpt' => 'Pick a template or start blank, then walk through Info, Questions, and Delivery in three steps.',
                'article_type' => 'guide',
                'sort_order' => 1,
                'featured' => true,
                'body' => <<<'HTML'
<p>Creating a survey is a guided three-step flow. Open <strong>Relationships → Surveys → Create</strong>.</p>
<h3>Step 1 — Choose a template</h3>
<p>When you start, Helmful offers a <strong>template library</strong> built for marine retail, including:</p>
<ul>
<li>Post-purchase feedback</li>
<li>Service department feedback</li>
<li>Boat show / event feedback</li>
<li>Lead intake (buyer or trade-in)</li>
<li>Financing experience</li>
<li>Follow-up with past clients</li>
<li>Lead re-engagement</li>
</ul>
<p>Select a template to pre-fill questions and settings, or choose <strong>Start from Scratch</strong> for a blank survey.</p>
<h3>Step 2 — Survey info</h3>
<ul>
<li><strong>Title</strong> and internal description for your team.</li>
<li><strong>Public description</strong> — Shown to respondents on the public form.</li>
<li><strong>Type</strong> — Feedback, lead, follow-up, or custom.</li>
<li><strong>Owner</strong> — Assignee who receives notifications and owns follow-up.</li>
<li><strong>Visibility</strong> — <em>Public</em> surveys can use shareable links; <em>private</em> surveys are for internal sends only.</li>
<li><strong>Status</strong> — Active surveys accept responses; inactive surveys do not.</li>
</ul>
<h3>Step 3 — Question builder</h3>
<p>Add, reorder, duplicate, and preview questions. Supported types include short text, long answer, multiple choice, dropdown, star rating, and NPS. Mark questions required and add <strong>conditional logic</strong> so follow-ups only appear when earlier answers match (OR rules).</p>
<h3>Step 4 — Delivery &amp; settings</h3>
<p>Configure thank-you message, optional redirect URL, privacy rules, color scheme, and automation triggers. Save when finished—you can edit the survey later from its detail page.</p>
HTML,
            ],
            [
                'title' => 'Question types and conditional logic',
                'slug' => 'survey-questions-and-conditional-logic',
                'excerpt' => 'Build readable branching surveys without a separate form builder.',
                'article_type' => 'guide',
                'sort_order' => 2,
                'featured' => false,
                'body' => <<<'HTML'
<h3>Question types</h3>
<ul>
<li><strong>Text</strong> — Single-line answers (name, boat model, etc.).</li>
<li><strong>Long text</strong> — Paragraph responses for comments.</li>
<li><strong>Multiple choice</strong> — One option from a list you define.</li>
<li><strong>Dropdown</strong> — Compact single-select list.</li>
<li><strong>Star rating</strong> — Configurable scale for satisfaction questions.</li>
<li><strong>NPS</strong> — Net Promoter Score (0–10) with standard follow-up labeling.</li>
</ul>
<p>Use <strong>Preview</strong> in the builder to see the respondent view before you publish.</p>
<h3>Reordering and duplicating</h3>
<p>Drag questions to change order. Duplicate a block when several similar questions share the same structure (for example multiple product lines).</p>
<h3>Conditional logic</h3>
<p>On any question after the first, you can show it only when a <strong>previous</strong> question’s answer matches a value you choose. Rules use <strong>OR</strong> logic—if any listed condition is true, the question appears.</p>
<p>Example: Show “Which service advisor helped you?” only when “Did you visit our service department?” is <em>Yes</em>.</p>
<p>Keep branches shallow when possible so mobile respondents are not overwhelmed by long hidden sections.</p>
HTML,
            ],
            [
                'title' => 'Delivery, privacy, and thank-you settings',
                'slug' => 'survey-delivery-and-privacy',
                'excerpt' => 'Thank-you copy, redirects, identity rules, and automation triggers that always ask before sending.',
                'article_type' => 'guide',
                'sort_order' => 3,
                'featured' => false,
                'body' => <<<'HTML'
<p>The <strong>Delivery &amp; Settings</strong> step controls what happens after someone finishes your survey and how Helmful may prompt your team to send it.</p>
<h3>Thank-you and redirect</h3>
<ul>
<li><strong>Thank-you message</strong> — Shown on the confirmation screen after submit.</li>
<li><strong>Thank-you email</strong> — Optional message to the respondent when an email was collected.</li>
<li><strong>Redirect URL</strong> — Send visitors to your website or a special offer page after they submit.</li>
</ul>
<h3>Privacy settings</h3>
<p>Typical options include:</p>
<ul>
<li><strong>Anonymous responses</strong> — Hide identity fields on the public form.</li>
<li><strong>Require name / email / phone</strong> — Control which contact fields respondents must complete.</li>
<li><strong>One response per email</strong> — Prevent duplicate submissions from the same address.</li>
<li><strong>Show results after submit</strong> — Display a summary to the respondent when enabled.</li>
</ul>
<h3>Automation triggers</h3>
<p>Surveys are <strong>never sent automatically</strong>. When a trigger condition is met, Helmful creates a <strong>prompt</strong> for your team to confirm before anything goes out.</p>
<p>Available triggers include:</p>
<ul>
<li><strong>Manual only</strong> — You send links when you choose.</li>
<li><strong>After transaction closes</strong> — Prompt after a deal completes (immediate or delayed by N days).</li>
<li><strong>On lead conversion</strong> — Prompt when a lead becomes a customer.</li>
</ul>
<p>Choose immediate notification or delay by a number of days so post-sale surveys land at the right time.</p>
HTML,
            ],
            [
                'title' => 'Share and embed a public survey',
                'slug' => 'share-and-embed-surveys',
                'excerpt' => 'Copy a direct link or iframe code for your website, email, or boat show signage.',
                'article_type' => 'guide',
                'sort_order' => 4,
                'featured' => false,
                'body' => <<<'HTML'
<p>Public surveys can be shared outside Helmful so customers and prospects respond on their own device.</p>
<h3>Requirements</h3>
<ul>
<li>The survey must be <strong>Active</strong>.</li>
<li><strong>Visibility</strong> must be <strong>Public</strong> for anonymous link sharing.</li>
</ul>
<h3>Direct link</h3>
<p>On the survey detail page, copy the <strong>public link</strong>. You can append an agent parameter (<code>?aid=</code>) so responses attribute to a specific salesperson when your team uses personal outreach.</p>
<p>Share the link in email signatures, SMS, QR codes at a boat show, or post-transaction follow-up messages.</p>
<h3>Embed on your website</h3>
<p>Copy the <strong>iframe embed code</strong> to drop the survey into a page on your dealership site. The embed uses the same question flow and privacy rules as the standalone link.</p>
<h3>Private surveys</h3>
<p>Private surveys are not listed on the public survey index. Send them using <strong>Send to contact</strong>, <strong>Send to deal</strong>, or similar actions from records inside Helmful so only invited recipients receive the URL.</p>
HTML,
            ],
            [
                'title' => 'Review responses and convert leads',
                'slug' => 'survey-responses-and-lead-conversion',
                'excerpt' => 'Read submissions, reassign ownership, and turn lead survey answers into pipeline records.',
                'article_type' => 'guide',
                'sort_order' => 5,
                'featured' => true,
                'body' => <<<'HTML'
<h3>All responses</h3>
<p>Open <strong>Relationships → Surveys → Responses</strong> for a workspace-wide list. Filter by survey, date, or assignee. Click a row to open the full submission.</p>
<h3>Per-survey responses</h3>
<p>From <strong>All Surveys</strong>, open a survey and go to its <strong>Responses</strong> tab for metrics and a filtered list tied to that form.</p>
<p>The survey dashboard shows helpful stats such as responses this month, average satisfaction (when rating questions exist), and—for lead surveys—<strong>conversion rate</strong> to leads.</p>
<h3>After someone submits</h3>
<ul>
<li>The survey <strong>assignee</strong> receives an in-app notification and email.</li>
<li>If the respondent provided an email, a <strong>thank-you</strong> message can be sent automatically per your delivery settings.</li>
<li>A <strong>communication</strong> entry may be added on the matching contact record.</li>
</ul>
<h3>Convert lead survey responses</h3>
<p>For surveys typed as <strong>Lead</strong>, open an individual response and use <strong>Convert to lead</strong> (or equivalent action) to create a lead in Helmful with answers mapped into your CRM workflow. Converted responses are marked so your team does not duplicate work.</p>
<h3>Reassign a response</h3>
<p>If the wrong salesperson was attributed, reassign the response to another team member from the response detail view so notifications and follow-up land with the right owner.</p>
HTML,
            ],
            [
                'title' => 'Send a survey from a contact or deal',
                'slug' => 'send-survey-from-contact-or-deal',
                'excerpt' => 'Deliver an active survey link when you are ready—one recipient at a time from CRM records.',
                'article_type' => 'guide',
                'sort_order' => 6,
                'featured' => false,
                'body' => <<<'HTML'
<p>Beyond public links, you can send surveys directly from records your team already works in.</p>
<h3>From a contact</h3>
<p>On a contact record, use <strong>Send survey</strong> (or the surveys action in the communication area). Pick an <strong>active</strong> survey and confirm. Helmful prepares the message with the survey link for that person.</p>
<h3>From a deal or opportunity</h3>
<p>On an open deal, use <strong>Send survey</strong> to attach a feedback or follow-up form to that transaction context. This is useful for post-close NPS or finance experience surveys tied to a specific sale.</p>
<h3>From other records</h3>
<p>Where supported, related records expose the same send action so service or delivery teams can request feedback without leaving the record they are viewing.</p>
<h3>Best practices</h3>
<ul>
<li>Confirm the survey is <strong>active</strong> and questions are finalized before sending.</li>
<li>Match survey <strong>type</strong> to the moment—lead intake on the show floor, feedback after delivery.</li>
<li>Prefer automation <strong>prompts</strong> for repeatable timing (for example 7 days after close) instead of manual sends when you want consistency.</li>
</ul>
HTML,
            ],
            [
                'title' => 'Clone, edit, and retire surveys',
                'slug' => 'manage-existing-surveys',
                'excerpt' => 'Duplicate a proven form, update questions safely, and deactivate surveys you no longer use.',
                'article_type' => 'guide',
                'sort_order' => 7,
                'featured' => false,
                'body' => <<<'HTML'
<h3>Edit a survey</h3>
<p>From <strong>All Surveys</strong>, open a survey and choose <strong>Edit</strong> to return to the three-step builder. Changes apply to new responses; past submissions keep the answers they were given.</p>
<h3>Clone a survey</h3>
<p>Use <strong>Clone</strong> on the survey list or detail page to duplicate title, questions, and settings. Rename the copy (for example “2026 Boat Show Lead Form”) before activating it for a new season.</p>
<h3>Activate and deactivate</h3>
<p>Toggle <strong>status</strong> off when a campaign ends. Inactive surveys stop accepting new responses but remain in history for reporting.</p>
<h3>Delete surveys</h3>
<p>You can delete individual surveys or select multiple on the index and remove them in bulk. Deleting a survey removes its questions; consider deactivating instead if you still need historical responses for reporting.</p>
<h3>Filters on the index</h3>
<p>Filter by name, <strong>type</strong>, <strong>status</strong>, and owner to manage a large library of forms across departments.</p>
HTML,
            ],
        ];
    }
}
