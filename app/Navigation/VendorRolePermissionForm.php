<?php

namespace App\Navigation;

class VendorRolePermissionForm
{
    public function __construct(
        private readonly ?object $store,
        private readonly bool $isServiceStore,
        private readonly bool $isRentalStore,
        private readonly bool $reelsEnabled,
        private readonly bool $websiteBuilderEnabled,
    ) {}

    public static function forCurrentStore(): self
    {
        $store = \App\CentralLogics\Helpers::get_store_data();
        $moduleType = $store?->module?->module_type;

        $reelsEnabled = addon_published_status('ReelsModule')
            && \App\CentralLogics\Helpers::get_business_settings('vendor_can_upload_reels')
            && \Modules\ReelsModule\Support\ReelModuleConfig::isAllowedType($moduleType);

        return new self(
            $store,
            $moduleType === 'service',
            $moduleType === 'rental',
            (bool) $reelsEnabled,
            (bool) \App\CentralLogics\Helpers::check_website_builder_status(),
        );
    }

    public function groups(): array
    {
        return $this->prune($this->definition());
    }

    public function total(): int
    {
        return array_sum(array_map(
            fn (array $group) => array_sum(array_map(fn (array $card) => count($card['items']), $group['cards'])),
            $this->groups()
        ));
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
                        'key' => 'overview',
                        'label' => translate('Overview'),
                        'items' => [
                            'dashboard' => 'messages.Dashboard',
                            'profile' => 'messages.Profile',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'store',
                'label' => translate('Store management'),
                'icon' => 'tio-shop-outlined',
                'show' => ! $this->isServiceStore && ! $this->isRentalStore,
                'cards' => [
                    [
                        'key' => 'sales',
                        'label' => translate('Sales'),
                        'items' => VendorNav::formPermissions('sales'),
                    ],
                    [
                        'key' => 'catalog',
                        'label' => translate('Catalog'),
                        'items' => VendorNav::formPermissions('catalog'),
                    ],
                ],
            ],
            [
                'key' => 'service',
                'label' => translate('Service management'),
                'icon' => 'tio-tools',
                'show' => $this->isServiceStore,
                'cards' => [
                    [
                        'key' => 'operations',
                        'label' => translate('Operations'),
                        'items' => [
                            'service_booking' => 'messages.Bookings',
                            'service_request' => 'messages.Service requests',
                            'custom_request' => 'messages.Custom_Requests',
                            'serviceman' => 'messages.Serviceman',
                        ],
                    ],
                    [
                        'key' => 'catalog',
                        'label' => translate('Catalog'),
                        'items' => [
                            'service_management' => 'messages.Services',
                            'category' => 'messages.Categories',
                        ],
                    ],
                    [
                        'key' => 'reports',
                        'label' => translate('Report section'),
                        'items' => [
                            'report' => 'messages.Reports',
                            'vat_report' => 'messages.VAT report',
                            'expense_report' => 'messages.Expense report',
                            'disbursement_report' => 'messages.Disbursement report',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'rental',
                'label' => translate('Rental management'),
                'icon' => 'tio-car',
                'show' => $this->isRentalStore,
                'cards' => [
                    [
                        'key' => 'operations',
                        'label' => translate('Operations'),
                        'items' => [
                            'trip' => 'messages.Trip',
                            'vehicle' => 'messages.Vehicle',
                            'driver' => 'messages.Driver',
                        ],
                    ],
                    [
                        'key' => 'marketing',
                        'label' => translate('Marketing'),
                        'items' => ['marketing' => 'messages.Marketing'],
                    ],
                ],
            ],
            [
                'key' => 'marketing',
                'label' => translate('Marketing'),
                'icon' => 'tio-bookmark-outlined',
                'cards' => [
                    [
                        'key' => 'promotions',
                        'label' => translate('Promotions'),
                        'items' => VendorNav::formPermissions('marketing')
                            + ($this->reelsEnabled ? ['reels' => 'messages.Reels'] : []),
                    ],
                ],
            ],
            [
                'key' => 'team',
                'label' => translate('Team'),
                'icon' => 'tio-user-big-outlined',
                'cards' => [
                    [
                        'key' => 'deliveryman',
                        'label' => translate('Deliveryman'),
                        'show' => ! $this->isServiceStore && (bool) ($this->store?->sub_self_delivery),
                        'items' => VendorNav::formPermissions('deliveryman'),
                    ],
                    [
                        'key' => 'employee',
                        'label' => translate('Employee section'),
                        'items' => VendorNav::formPermissions('employee'),
                    ],
                ],
            ],
            [
                'key' => 'finance',
                'label' => translate('messages.Finance'),
                'icon' => 'tio-wallet-outlined',
                'cards' => [
                    [
                        'key' => 'wallet',
                        'label' => translate('Wallet'),
                        'items' => VendorNav::formPermissions('wallet'),
                    ],
                    [
                        'key' => 'reports',
                        'label' => translate('Report section'),
                        'show' => ! $this->isServiceStore,
                        'items' => ($this->isRentalStore ? ['report' => 'messages.Reports'] : [])
                            + VendorNav::formPermissions('reports'),
                    ],
                ],
            ],
            [
                'key' => 'business',
                'label' => translate('Settings'),
                'icon' => 'tio-settings-outlined',
                'cards' => [
                    [
                        'key' => 'business',
                        'label' => translate('Business section'),
                        'items' => VendorNav::formPermissions('business')
                            + ($this->websiteBuilderEnabled ? ['custom_website' => 'messages.Custom website'] : []),
                    ],
                    [
                        'key' => 'engagement',
                        'label' => translate('Customer engagement'),
                        'items' => VendorNav::formPermissions('engagement'),
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
