<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Faq;
use App\Support\PublicPageCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('faqs')) {
            return;
        }

        if (Faq::query()->exists()) {
            return;
        }

        $now = now();

        foreach ($this->definitions() as $row) {
            Faq::query()->create([
                ...$row,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        PublicPageCache::forgetFaqs();
    }

    /**
     * Starter FAQs for the marketing site (central `faqs` table).
     * `featured` rows appear on the homepage; all rows appear on /faq.
     *
     * @return list<array{question: string, answer: string, featured: bool}>
     */
    private function definitions(): array
    {
        return [
            [
                'question' => 'What is Helmful?',
                'answer' => 'Helmful is an all-in-one platform for marine dealerships and service operations. It connects sales, inventory, service, deliveries, and customer records so your whole team works from the same information.',
                'featured' => true,
            ],
            [
                'question' => 'Who is Helmful built for?',
                'answer' => 'Boat dealerships, marinas, and service yards that sell and service vessels and want one system instead of spreadsheets and disconnected tools. If you run leads, deals, work orders, or boat shows, Helmful is built for you.',
                'featured' => true,
            ],
            [
                'question' => 'Is there a free trial?',
                'answer' => 'Yes. Every plan includes a 14-day free trial with no credit card required. You can explore the features that matter to your operation before you subscribe.',
                'featured' => true,
            ],
            [
                'question' => 'Can I connect QuickBooks Online?',
                'answer' => 'Yes. Connect your QuickBooks company to sync customers, push invoices, and pull payments when you enable those options. You stay in control of what syncs and when.',
                'featured' => true,
            ],
            [
                'question' => 'How do online invoice payments work?',
                'answer' => 'Helmful uses Stripe Connect so payments go to your business, not ours. Connect your Stripe account once, then customers can pay open invoices through secure checkout links you send from Helmful.',
                'featured' => false,
            ],
            [
                'question' => 'Can multiple locations or teams use one account?',
                'answer' => 'Yes. Helmful supports workspaces for your organization with role-based access, so sales, service, and office staff see what they need without sharing logins.',
                'featured' => false,
            ],
            [
                'question' => 'Do you help us move off our current software?',
                'answer' => 'We can walk through your current setup on a demo and recommend a practical rollout. For data migration, contact us — scope depends on what you use today and which modules you turn on first.',
                'featured' => false,
            ],
            [
                'question' => 'Is Helmful available on phones and tablets?',
                'answer' => 'Helmful runs in the browser on desktop and mobile, so your team can check schedules, tickets, and customer details from the floor or the dock without installing a separate app.',
                'featured' => false,
            ],
            [
                'question' => 'How is our data kept secure?',
                'answer' => 'Each customer workspace is isolated, access is permission-based, and sensitive credentials (such as payment and accounting tokens) are encrypted at rest. We follow standard practices for authentication, HTTPS, and secure integrations.',
                'featured' => false,
            ],
            [
                'question' => 'How do I get help or schedule a demo?',
                'answer' => 'Use the Contact page to request a walkthrough or ask a question. We typically respond within one business day and can tailor a demo to how your dealership operates.',
                'featured' => false,
            ],
        ];
    }
}
