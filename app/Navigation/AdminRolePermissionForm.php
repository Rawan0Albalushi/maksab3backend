<?php

namespace App\Navigation;

class AdminRolePermissionForm
{
    public function __construct(
        private readonly bool $rideShareEnabled,
        private readonly bool $rentalEnabled,
        private readonly bool $serviceEnabled,
        private readonly bool $taxModuleEnabled,
    ) {}

    public static function make(): self
    {
        return new self(
            rideShareEnabled: (bool) addon_published_status('RideShare'),
            rentalEnabled: (bool) addon_published_status('Rental'),
            serviceEnabled: (bool) addon_published_status('Service'),
            taxModuleEnabled: (bool) addon_published_status('TaxModule'),
        );
    }

    public function groups(): array
    {
        return $this->prune($this->definition());
    }

    public function labels(): array
    {
        $labels = [];
        foreach ($this->definition() as $group) {
            foreach ($group['cards'] as $card) {
                foreach ($card['items'] as $key => $labelKey) {
                    $labels[$key] = translate($labelKey);
                }
            }
        }
        return $labels;
    }

    private function definition(): array
    {
        return [
            [
                'key' => 'general',
                'label' => translate('messages.General'),
                'icon' => 'tio-dashboard-outlined',
                'cards' => [
                    [
                        'key' => 'profile',
                        'label' => translate('messages.Profile Management'),
                        'items' => [
                            'dashboard' => 'messages.Dashboard',
                            'profile' => 'messages.Profile',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'users',
                'label' => translate('messages.User Management'),
                'icon' => 'tio-user-big-outlined',
                'cards' => [
                    [
                        'key' => 'overview',
                        'label' => translate('User overview'),
                        'items' => ['user_overview' => 'User Overview'],
                    ],
                    [
                        'key' => 'customer',
                        'label' => translate('Customer management'),
                        'items' => [
                            'customer_management' => 'Customer accounts',
                            'customer_wallet' => 'messages.Customer wallet',
                            'customer_loyalty_point' => 'messages.Customer loyalty point',
                            'cashback' => 'messages.Promotion management',
                        ],
                    ],
                    [
                        'key' => 'deliveryman',
                        'label' => translate('Deliveryman management'),
                        'items' => ['deliveryman' => 'Deliveryman management'],
                    ],
                    [
                        'key' => 'rider',
                        'label' => translate('Rider').' '.translate('management'),
                        'show' => $this->rideShareEnabled,
                        'items' => [
                            'rider' => 'messages.Rider',
                            'rider_level' => 'messages.Rider level',
                            'rider_review' => 'messages.Reviews',
                        ],
                    ],
                    [
                        'key' => 'ride-vehicle',
                        'label' => translate('Vehicle management'),
                        'show' => $this->rideShareEnabled,
                        'items' => ['ride_vehicle' => 'messages.Vehicle management'],
                    ],
                    [
                        'key' => 'employee',
                        'label' => translate('messages.Employee Management'),
                        'items' => ['employee' => 'messages.Employee'],
                    ],
                    [
                        'key' => 'serviceman',
                        'label' => translate('Serviceman management'),
                        'show' => $this->serviceEnabled,
                        'items' => ['service_provider' => 'messages.Serviceman'],
                    ],
                ],
            ],
            [
                'key' => 'finance',
                'label' => translate('messages.Finance'),
                'icon' => 'tio-wallet-outlined',
                'cards' => [
                    [
                        'key' => 'withdraw',
                        'label' => translate('Withdraw management'),
                        'items' => ['withdraw_list' => 'Withdraw Management'],
                    ],
                    [
                        'key' => 'disbursement',
                        'label' => translate('Auto disbursements'),
                        'items' => ['disbursement' => 'Auto Disbursements'],
                    ],
                    [
                        'key' => 'cash',
                        'label' => translate('Cash operations'),
                        'items' => [
                            'collect_cash' => 'messages.Collect cash',
                            'provide_dm_earning' => 'Pay Earnings',
                            'withdraw_method' => 'messages.Withdraw method',
                        ],
                    ],
                    [
                        'key' => 'tax',
                        'label' => translate('Tax & compliance'),
                        'items' => [
                            'admin_text_module' => 'Admin Tax Report',
                            'vendor_vat_report' => 'Vendor Tax Report',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'dispatch',
                'label' => translate('dispatch'),
                'icon' => 'tio-navigate-outlined',
                'cards' => [
                    [
                        'key' => 'dashboard',
                        'label' => translate('Dispatch Dashboard'),
                        'items' => ['dispatch' => 'Dispatch Dashboard'],
                    ],
                ],
            ],
            [
                'key' => 'reports',
                'label' => translate('messages.Report & Analytics'),
                'icon' => 'tio-chart-bar-4',
                'cards' => [
                    [
                        'key' => 'transaction',
                        'label' => translate('Transaction report'),
                        'items' => ['report' => 'Transaction report'],
                    ],
                    [
                        'key' => 'earning',
                        'label' => translate('Earning reports'),
                        'items' => ['earning_report' => 'Earning Reports'],
                    ],
                    [
                        'key' => 'payout',
                        'label' => translate('Payout & disbursement'),
                        'items' => [
                            'disbursement_report' => 'messages.Disbursement report',
                            'expense_report' => 'messages.Expense report',
                        ],
                    ],
                    [
                        'key' => 'sales',
                        'label' => translate('Sales reports'),
                        'items' => ['sales_report' => 'Sales Reports'],
                    ],
                    [
                        'key' => 'performance',
                        'label' => translate('Performance reports'),
                        'items' => ['performance_report' => 'Performance Reports'],
                    ],
                ],
            ],
            [
                'key' => 'settings',
                'label' => translate('Settings'),
                'icon' => 'tio-settings-outlined',
                'cards' => [
                    [
                        'key' => 'business',
                        'label' => translate('Business setup'),
                        'items' => ['settings' => 'Business Setup'],
                    ],
                    [
                        'key' => 'module',
                        'label' => translate('Module setup'),
                        'items' => ['module' => 'messages.Module setup'],
                    ],
                    [
                        'key' => 'subscription',
                        'label' => translate('Subscription management'),
                        'items' => [
                            'subscription' => 'Vendor Subscription',
                            'pro_customer_subscription' => 'messages.Pro customer management',
                        ],
                    ],
                    [
                        'key' => 'tax',
                        'label' => translate('Finance & tax'),
                        'show' => $this->taxModuleEnabled,
                        'items' => ['system_tax' => 'messages.System Tax'],
                    ],
                    [
                        'key' => 'pages',
                        'label' => translate('Website, pages & content'),
                        'items' => [
                            'social_media' => 'Social Media',
                            'landing_pages' => 'Landing Pages',
                            'business_pages' => 'Business Pages',
                            'seo' => 'SEO & Metadata',
                        ],
                    ],
                    [
                        'key' => 'system',
                        'label' => translate('System configuration'),
                        'items' => ['system_config' => 'System Configuration'],
                    ],
                    [
                        'key' => 'auth',
                        'label' => translate('Authentication & access'),
                        'items' => ['login_setup' => 'messages.Login Setup'],
                    ],
                    [
                        'key' => 'communication',
                        'label' => translate('Communication setup'),
                        'items' => [
                            'email_setups' => 'messages.Email Setup',
                            'notification_setup' => 'messages.Notification setup',
                        ],
                    ],
                    [
                        'key' => 'integrations',
                        'label' => translate('Integrations & Third-Party'),
                        'items' => ['third_party-ms' => 'messages.Third-party & configuration'],
                    ],
                    [
                        'key' => 'service-settings',
                        'label' => translate('Service module settings'),
                        'show' => $this->serviceEnabled,
                        'items' => ['service_settings' => 'Service Module Settings'],
                    ],
                    [
                        'key' => 'ride-settings',
                        'label' => translate('Ride share settings'),
                        'show' => $this->rideShareEnabled,
                        'items' => ['ride_settings' => 'Ride Share Settings'],
                    ],
                    [
                        'key' => 'media',
                        'label' => translate('Media & file management'),
                        'items' => ['gallery' => 'messages.gallery'],
                    ],
                    [
                        'key' => 'maintenance',
                        'label' => translate('Maintenance & database'),
                        'items' => [
                            'clean_database' => 'messages.Clean database',
                            'database_backup' => 'Backup & Restore Database',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'modules',
                'label' => translate('messages.Modules Wise Management'),
                'icon' => 'tio-shop-outlined',
                'cards' => [
                    [
                        'key' => 'sales',
                        'label' => translate('Sales'),
                        'items' => [
                            'order' => 'messages.Orders',
                            'pos' => 'messages.POS orders',
                            'parcel' => 'messages.Parcel',
                        ],
                    ],
                    [
                        'key' => 'marketing',
                        'label' => translate('Marketing'),
                        'items' => [
                            'campaign' => 'messages.Campaign',
                            'banner' => 'messages.Banner',
                            'coupon' => 'Promotions',
                            'notification' => 'messages.Push notification',
                            'reels' => 'messages.Reels',
                        ],
                    ],
                    [
                        'key' => 'catalog',
                        'label' => translate('Catalog'),
                        'items' => [
                            'category' => 'Setup',
                            'addon' => 'messages.Addons',
                            'item' => 'messages.item',
                        ],
                    ],
                    [
                        'key' => 'stores',
                        'label' => translate('messages.Stores'),
                        'items' => [
                            'store' => 'messages.Store',
                            'store_bulk' => 'Bulk',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'rental',
                'label' => translate('Rental management'),
                'icon' => 'tio-car',
                'show' => $this->rentalEnabled,
                'cards' => [
                    [
                        'key' => 'sales',
                        'label' => translate('Sales'),
                        'items' => ['trip' => 'messages.Trip'],
                    ],
                    [
                        'key' => 'catalog',
                        'label' => translate('Catalog'),
                        'items' => [
                            'vehicle' => 'messages.Vehicle',
                            'rental_vehicle_setup' => 'Setup',
                        ],
                    ],
                    [
                        'key' => 'provider',
                        'label' => translate('messages.Provider management'),
                        'items' => [
                            'provider' => 'messages.Provider',
                            'driver' => 'messages.Driver',
                            'rental_provider_bulk' => 'Bulk',
                        ],
                    ],
                    [
                        'key' => 'marketing',
                        'label' => translate('Marketing'),
                        'items' => [
                            'promotion' => 'messages.Promotion',
                            'rental_banners' => 'banners',
                            'rental_communication' => 'Communication',
                        ],
                    ],
                    [
                        'key' => 'apps',
                        'label' => translate('Apps'),
                        'items' => ['download_app' => 'messages.Download app'],
                    ],
                ],
            ],
            [
                'key' => 'rideshare',
                'label' => translate('messages.Ride Share Management'),
                'icon' => 'tio-android-phone-vs',
                'show' => $this->rideShareEnabled,
                'cards' => [
                    [
                        'key' => 'dashboard',
                        'label' => translate('Dashboard'),
                        'items' => ['heat_map' => 'dashboard'],
                    ],
                    [
                        'key' => 'ride',
                        'label' => translate('messages.Ride Management'),
                        'items' => ['ride' => 'messages.Ride'],
                    ],
                    [
                        'key' => 'catalog',
                        'label' => translate('Catalog'),
                        'items' => ['fare' => 'messages.Fare'],
                    ],
                    [
                        'key' => 'marketing',
                        'label' => translate('Marketing'),
                        'items' => ['ride_promotion' => 'messages.Ride promotion'],
                    ],
                ],
            ],
            [
                'key' => 'service',
                'label' => translate('Service management'),
                'icon' => 'tio-tools',
                'show' => $this->serviceEnabled,
                'cards' => [
                    [
                        'key' => 'bookings',
                        'label' => translate('messages.Bookings'),
                        'items' => ['service_booking' => 'messages.Service booking'],
                    ],
                    [
                        'key' => 'catalog',
                        'label' => translate('Catalog'),
                        'items' => ['service_management' => 'messages.Service management'],
                    ],
                ],
            ],
        ];
    }

    private function prune(array $groups): array
    {
        $visible = [];
        foreach ($groups as $group) {
            if (! ($group['show'] ?? true)) {
                continue;
            }
            $cards = [];
            foreach ($group['cards'] as $card) {
                if (! ($card['show'] ?? true) || empty($card['items'])) {
                    continue;
                }
                $cards[] = $card;
            }
            if ($cards === []) {
                continue;
            }
            $group['cards'] = $cards;
            $visible[] = $group;
        }
        return $visible;
    }
}
