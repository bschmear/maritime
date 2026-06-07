<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\AccountSetup\Models\AccountSetupStep;
use App\Domain\AccountSetup\Models\AccountSetupStepProgress;
use App\Domain\AccountSetup\Services\AccountSetupService;
use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\Location\Models\Location;
use App\Domain\Subsidiary\Models\Subsidiary;
use App\Enums\AccountSetupStepStatus;
use Illuminate\Database\Seeder;

class AccountSetupStepSeeder extends Seeder
{
    /**
     * Seed workspace tour steps for the current tenant database.
     *
     * Manual prod run (required --force):
     *   php artisan tenants:seed --class=Database\\Seeders\\AccountSetupStepSeeder --force
     */
    public function run(): void
    {
        static::seed();
    }

    public static function seed(): void
    {
        $steps = [
            [
                'key' => 'configure_service_tickets',
                'title' => 'Service tickets',
                'description' => 'Set estimate approval thresholds, customer acknowledgment text, and who gets notified when a ticket is signed. These defaults apply across your service yard.',
                'feature_area' => 'account',
                'icon' => 'assignment',
                'route_name' => 'account.index',
                'route_params' => ['tab' => 'service_ticket'],
                'sort_order' => 1,
            ],
            [
                'key' => 'setup_scheduling',
                'title' => 'Scheduling',
                'description' => 'Configure workday hours, start time, and whether appointments can overlap on the service yard scheduling board.',
                'feature_area' => 'account',
                'icon' => 'calendar_view_week',
                'route_name' => 'account.index',
                'route_params' => ['tab' => 'scheduling'],
                'sort_order' => 2,
            ],
            [
                'key' => 'setup_transactions',
                'title' => 'Transactions',
                'description' => 'Set default contract, payment, and delivery terms that pre-fill on new deals so your team starts from a consistent template.',
                'feature_area' => 'account',
                'icon' => 'receipt_long',
                'route_name' => 'account.index',
                'route_params' => ['tab' => 'transactions'],
                'sort_order' => 3,
            ],
            [
                'key' => 'invite_users',
                'title' => 'Users',
                'description' => 'Invite team members and manage who has access to your workspace. You will find user management under Company in the main nav.',
                'feature_area' => 'team',
                'icon' => 'people',
                'route_name' => 'users.index',
                'route_params' => null,
                'permission' => 'user.view',
                'sort_order' => 4,
            ],
            [
                'key' => 'configure_roles',
                'title' => 'Roles & permissions',
                'description' => 'Define what each role can view, create, edit, and delete. Roles control access across the entire application.',
                'feature_area' => 'team',
                'icon' => 'shield',
                'route_name' => 'roles.index',
                'route_params' => null,
                'permission' => 'role.view',
                'sort_order' => 5,
            ],
            [
                'key' => 'add_subsidiaries',
                'title' => 'Subsidiaries',
                'description' => 'Organize dealerships, brands, or related entities. Subsidiaries tie locations, deals, and reporting together.',
                'feature_area' => 'account',
                'icon' => 'corporate_fare',
                'route_name' => 'subsidiaries.index',
                'route_params' => null,
                'permission' => 'subsidiary.view',
                'sort_order' => 6,
            ],
            [
                'key' => 'add_locations',
                'title' => 'Locations',
                'description' => 'Add physical dealership and service locations. Locations link to subsidiaries and appear throughout scheduling and operations.',
                'feature_area' => 'account',
                'icon' => 'location_on',
                'route_name' => 'locations.index',
                'route_params' => null,
                'permission' => 'location.view',
                'sort_order' => 7,
            ],
            [
                'key' => 'import_boat_makes',
                'title' => 'Boat makes & models',
                'description' => 'Build your brand catalog and import models from manufacturer data. This is the foundation for assets, estimates, and inventory.',
                'feature_area' => 'inventory',
                'icon' => 'directions_boat',
                'route_name' => 'boatmakes.index',
                'route_params' => null,
                'permission' => 'boatmake.view',
                'sort_order' => 8,
            ],
            [
                'key' => 'create_boat_options',
                'title' => 'Boat options',
                'description' => 'Create options customers choose on estimates — colors, packages, and upgrades. Options can be assigned to specific makes and models.',
                'feature_area' => 'inventory',
                'icon' => 'tune',
                'route_name' => 'asset-options.index',
                'route_params' => null,
                'sort_order' => 9,
            ],
            [
                'key' => 'asset_specifications',
                'title' => 'Asset specifications',
                'description' => 'Define spec sheets and grouped attributes for your units. Specs power listings, comparisons, and customer-facing detail pages.',
                'feature_area' => 'inventory',
                'icon' => 'fact_check',
                'route_name' => 'asset-specs.index',
                'route_params' => null,
                'sort_order' => 10,
            ],
            [
                'key' => 'add_fleet',
                'title' => 'Fleet',
                'description' => 'Register trucks and trailers used for deliveries and yard moves. Fleet appears when scheduling and tracking deliveries.',
                'feature_area' => 'operations',
                'icon' => 'local_shipping',
                'route_name' => 'fleet.index',
                'route_params' => null,
                'sort_order' => 11,
            ],
            [
                'key' => 'delivery_checklists',
                'title' => 'Delivery checklists',
                'description' => 'Create checklist templates used when handing off a unit to a customer. Templates standardize your delivery process.',
                'feature_area' => 'operations',
                'icon' => 'checklist',
                'route_name' => 'delivery-checklist-templates.index',
                'route_params' => null,
                'sort_order' => 12,
            ],
            [
                'key' => 'setup_payments',
                'title' => 'Payments',
                'description' => 'Connect Stripe and choose which payment methods you accept on invoices and customer checkout.',
                'feature_area' => 'account',
                'icon' => 'payments',
                'route_name' => 'account.payments',
                'route_params' => null,
                'sort_order' => 13,
            ],
            [
                'key' => 'consignment_policy',
                'title' => 'Consignment',
                'description' => 'Set consignment fees, terms, and policy bullets shown on owner-facing consignment agreements.',
                'feature_area' => 'account',
                'icon' => 'article',
                'route_name' => 'account.consignment.index',
                'route_params' => null,
                'sort_order' => 14,
            ],
            [
                'key' => 'sms_notifications',
                'title' => 'Text notifications',
                'description' => 'Turn on transactional SMS alerts and choose which events send a text to customers or your team.',
                'feature_area' => 'account',
                'icon' => 'sms',
                'route_name' => 'account.notifications.sms.index',
                'route_params' => null,
                'sort_order' => 15,
            ],
            [
                'key' => 'integrations',
                'title' => 'Integrations',
                'description' => 'Connect QuickBooks, Mailchimp, and other services to sync data with your workspace.',
                'feature_area' => 'account',
                'icon' => 'extension',
                'route_name' => 'integrations',
                'route_params' => null,
                'sort_order' => 16,
            ],
        ];

        foreach ($steps as $step) {
            AccountSetupStep::query()->updateOrCreate(
                ['key' => $step['key']],
                $step,
            );
        }

        app(AccountSetupService::class)->ensureProgressRows();
        static::backfillWizardProgress();
        app(AccountSetupService::class)->syncAccountSetupComplete();
    }

    private static function backfillWizardProgress(): void
    {
        $backfill = [];

        if (Subsidiary::query()->exists()) {
            $backfill[] = 'add_subsidiaries';
        }

        if (Location::query()->exists()) {
            $backfill[] = 'add_locations';
        }

        if (BoatMake::query()->exists()) {
            $backfill[] = 'import_boat_makes';
        }

        if ($backfill === []) {
            return;
        }

        $stepIds = AccountSetupStep::query()
            ->whereIn('key', $backfill)
            ->pluck('id', 'key');

        foreach ($backfill as $key) {
            $stepId = $stepIds[$key] ?? null;
            if ($stepId === null) {
                continue;
            }

            AccountSetupStepProgress::query()->updateOrCreate(
                ['account_setup_step_id' => $stepId],
                [
                    'status' => AccountSetupStepStatus::Completed,
                    'resolved_at' => now(),
                ],
            );
        }
    }
}
