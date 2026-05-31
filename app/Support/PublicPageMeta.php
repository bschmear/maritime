<?php

namespace App\Support;

use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class PublicPageMeta
{
    public static function siteName(): string
    {
        $name = config('public-pages.site_name') ?? config('app.name', 'Helmful');

        return is_string($name) && $name !== '' ? $name : 'Helmful';
    }

    public static function author(): string
    {
        return self::siteName();
    }

    public static function defaultImage(): string
    {
        $path = config('public-pages.default_og_image', '/assets/icons/android-chrome-512x512.png');

        return url($path);
    }

    public static function logoUrl(): string
    {
        return url('/assets/helmful-logo-512.svg');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function meta(array $overrides = []): array
    {
        return array_merge([
            'author' => self::author(),
            'type' => 'website',
            'site_name' => self::siteName(),
        ], $overrides);
    }

    /**
     * @return array<string, bool>
     */
    public static function authProps(): array
    {
        return [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
        ];
    }

    /**
     * @param  array<string, mixed>  $pageProps
     * @return array<string, mixed>
     */
    public static function withAuth(array $pageProps): array
    {
        return array_merge($pageProps, self::authProps());
    }

    /**
     * @return array<string, mixed>
     */
    public static function home(): array
    {
        return self::withAuth([
            'meta' => self::meta([
                'title' => self::siteName().' | Dealership CRM for Marine Sales & Service',
                'description' => 'Helmful unifies leads, inventory, deals, service, and payments in one platform built for marine dealerships.',
                'url' => url('/'),
                'canonical' => url('/'),
                'image' => self::defaultImage(),
            ]),
            'schemaData' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    self::organizationSchema(),
                    [
                        '@type' => 'WebSite',
                        'name' => self::siteName(),
                        'url' => url('/'),
                        'publisher' => ['@id' => url('/#organization')],
                    ],
                    [
                        '@type' => 'SoftwareApplication',
                        'name' => self::siteName(),
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem' => 'Web',
                        'url' => url('/'),
                        'description' => 'Dealership CRM for marine sales, service, boat shows, and operations.',
                        'offers' => [
                            '@type' => 'Offer',
                            'url' => url('/pricing'),
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function blogIndex(): array
    {
        return self::withAuth([
            'meta' => self::meta([
                'title' => 'Blog | '.self::siteName(),
                'description' => 'Product updates, dealership operations tips, and marine industry insights from the Helmful team.',
                'url' => url('/blog'),
                'canonical' => url('/blog'),
                'image' => self::defaultImage(),
            ]),
            'schemaData' => [
                '@context' => 'https://schema.org',
                '@type' => 'Blog',
                'name' => self::siteName().' Blog',
                'url' => url('/blog'),
                'publisher' => self::organizationSchema(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function blogCategory(string $name, string $slug): array
    {
        $url = url('/blog/category?slug='.$slug);

        return self::withAuth([
            'meta' => self::meta([
                'title' => $name.' | Blog | '.self::siteName(),
                'description' => 'Articles in the '.$name.' category on the Helmful blog.',
                'url' => $url,
                'canonical' => $url,
                'image' => self::defaultImage(),
            ]),
            'schemaData' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $name,
                'url' => $url,
                'isPartOf' => [
                    '@type' => 'Blog',
                    'name' => self::siteName().' Blog',
                    'url' => url('/blog'),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function blogTag(string $name, string $slug): array
    {
        $url = url('/blog/tag?slug='.$slug);

        return self::withAuth([
            'meta' => self::meta([
                'title' => $name.' | Blog | '.self::siteName(),
                'description' => 'Posts tagged '.$name.' on the Helmful blog.',
                'url' => $url,
                'canonical' => $url,
                'image' => self::defaultImage(),
            ]),
            'schemaData' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $name,
                'url' => $url,
                'isPartOf' => [
                    '@type' => 'Blog',
                    'name' => self::siteName().' Blog',
                    'url' => url('/blog'),
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    public static function blogPost(array $post): array
    {
        $slug = $post['slug'] ?? '';
        $url = url('/blog/'.$slug);
        $title = (string) ($post['title'] ?? 'Blog post');
        $description = (string) ($post['short_description'] ?? $post['excerpt'] ?? '');
        if ($description === '' && ! empty($post['body'])) {
            $description = strip_tags((string) $post['body']);
            $description = mb_strlen($description) > 160 ? mb_substr($description, 0, 157).'...' : $description;
        }
        $image = self::absoluteImageUrl($post['cover_image'] ?? null);
        $published = $post['published_at_iso'] ?? null;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $title,
            'description' => $description,
            'url' => $url,
            'mainEntityOfPage' => $url,
            'image' => $image,
            'author' => [
                '@type' => 'Person',
                'name' => is_array($post['author'] ?? null)
                    ? ($post['author']['name'] ?? self::author())
                    : (string) ($post['author'] ?? self::author()),
            ],
            'publisher' => self::organizationSchema(),
        ];

        if ($published) {
            $schema['datePublished'] = $published;
        }

        return self::withAuth([
            'meta' => self::meta([
                'title' => $title.' | '.self::siteName(),
                'description' => $description,
                'type' => 'article',
                'url' => $url,
                'canonical' => $url,
                'image' => $image,
            ]),
            'schemaData' => $schema,
        ]);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $faqs
     * @return array<string, mixed>
     */
    public static function faq(iterable $faqs): array
    {
        $faqList = collect($faqs);

        return self::withAuth([
            'meta' => self::meta([
                'title' => 'FAQ | '.self::siteName(),
                'description' => 'Answers about Helmful features, pricing, boat shows, service, integrations, and getting started.',
                'url' => url('/faq'),
                'canonical' => url('/faq'),
                'image' => self::defaultImage(),
            ]),
            'schemaData' => self::faqPageSchema($faqList),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $faqs
     * @return array<string, mixed>
     */
    public static function faqPageSchema(Collection $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(function (array $faq) {
                return [
                    '@type' => 'Question',
                    'name' => $faq['question'] ?? '',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => strip_tags((string) ($faq['answer'] ?? '')),
                    ],
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function contact(): array
    {
        return self::withAuth([
            'meta' => self::meta([
                'title' => 'Contact '.self::siteName().' | Request a Demo',
                'description' => 'Contact the Helmful team for demos, support, and partnership questions.',
                'url' => url('/contact'),
                'canonical' => url('/contact'),
                'image' => self::defaultImage(),
            ]),
            'schemaData' => [
                '@context' => 'https://schema.org',
                '@type' => 'ContactPage',
                'name' => 'Contact '.self::siteName(),
                'url' => url('/contact'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function about(): array
    {
        return self::withAuth([
            'meta' => self::meta([
                'title' => 'About | '.self::siteName(),
                'description' => 'Learn how Helmful helps marine dealerships connect sales, service, inventory, and customer data on one platform.',
                'url' => url('/about'),
                'canonical' => url('/about'),
                'image' => self::defaultImage(),
            ]),
            'schemaData' => [
                '@context' => 'https://schema.org',
                '@type' => 'AboutPage',
                'name' => 'About '.self::siteName(),
                'url' => url('/about'),
                'mainEntity' => self::organizationSchema(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function features(): array
    {
        return self::featurePage(
            'Features',
            'Explore Helmful features for marine dealerships — CRM, boat shows, service, deliveries, surveys, and integrations.',
            '/features',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function featureBoatShows(): array
    {
        return self::featurePage(
            'Boat Shows & Events',
            'Capture leads at boat shows and events, score interest, and sync every lead into your Helmful CRM.',
            '/features/boat-shows',
        );
    }

    public static function featureServiceDepartment(): array
    {
        return self::featurePage(
            'Service Department',
            'Run marine service with tickets, labor, parts, estimates, and customer history in Helmful.',
            '/features/service-department',
        );
    }

    public static function featurePerformanceTracking(): array
    {
        return self::featurePage(
            'Performance Tracking',
            'Track sales and team performance with dashboards and goals built for dealerships.',
            '/features/performance-tracking',
        );
    }

    public static function featureDeliverySystem(): array
    {
        return self::featurePage(
            'Delivery System',
            'Coordinate boat deliveries with checklists, locations, and customer-ready handoffs.',
            '/features/delivery-system',
        );
    }

    public static function featureSmartSurveys(): array
    {
        return self::featurePage(
            'Smart Surveys',
            'Send branded surveys, capture responses, and route insights back to leads and customers.',
            '/features/smart-surveys',
        );
    }

    public static function featureStripePayments(): array
    {
        return self::featurePage(
            'Stripe Payments',
            'Accept payments with Stripe inside Helmful — invoices, deposits, and connected accounts.',
            '/features/stripe-payments',
        );
    }

    public static function featureMailchimp(): array
    {
        return self::featurePage(
            'Mailchimp Integration',
            'Sync audiences and campaigns between Helmful and Mailchimp.',
            '/features/mailchimp',
        );
    }

    public static function featureQuickbooks(): array
    {
        return self::featurePage(
            'QuickBooks Integration',
            'Connect QuickBooks to sync customers, invoices, and payments with Helmful.',
            '/features/quickbooks',
        );
    }

    /**
     * @param  iterable<int, Plan>  $plans
     * @return array<string, mixed>
     */
    public static function pricing(iterable $plans): array
    {
        $planCollection = collect($plans);

        return self::withAuth([
            'meta' => self::meta([
                'title' => 'Pricing | '.self::siteName(),
                'description' => 'Compare Helmful plans for marine dealerships. Monthly and annual billing with a free trial.',
                'url' => url('/pricing'),
                'canonical' => url('/pricing'),
                'image' => self::defaultImage(),
            ]),
            'schemaData' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'Pricing',
                'url' => url('/pricing'),
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'itemListElement' => $planCollection->values()->map(function (Plan $plan, int $index) {
                        return [
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'item' => [
                                '@type' => 'Offer',
                                'name' => $plan->name,
                                'description' => $plan->description,
                                'url' => url('/pricing'),
                                'price' => (float) $plan->monthly_price,
                                'priceCurrency' => 'USD',
                                'availability' => ($plan->coming_soon ?? false)
                                    ? 'https://schema.org/preorder'
                                    : 'https://schema.org/InStock',
                            ],
                        ];
                    })->all(),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function terms(): array
    {
        return self::withAuth([
            'meta' => self::meta([
                'title' => 'Terms of Service | '.self::siteName(),
                'description' => 'Review the terms and conditions for using the Helmful platform.',
                'url' => url('/terms'),
                'canonical' => url('/terms'),
                'robots' => 'noindex, follow',
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function privacy(): array
    {
        return self::withAuth([
            'meta' => self::meta([
                'title' => 'Privacy Policy | '.self::siteName(),
                'description' => 'Learn how Helmful collects, uses, and protects your personal information.',
                'url' => url('/privacy'),
                'canonical' => url('/privacy'),
                'robots' => 'noindex, follow',
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function featurePage(string $title, string $description, string $path): array
    {
        $url = url($path);

        return self::withAuth([
            'meta' => self::meta([
                'title' => $title.' | '.self::siteName(),
                'description' => $description,
                'url' => $url,
                'canonical' => $url,
                'image' => self::defaultImage(),
            ]),
            'schemaData' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'description' => $description,
                'url' => $url,
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => self::siteName(),
                    'url' => url('/'),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function organizationSchema(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/#organization'),
            'name' => self::siteName(),
            'url' => url('/'),
            'logo' => self::logoUrl(),
        ];
    }

    protected static function absoluteImageUrl(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return self::defaultImage();
        }

        $url = trim($url);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
