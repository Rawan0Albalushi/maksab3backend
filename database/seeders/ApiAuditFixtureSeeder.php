<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ApiAuditFixtureSeeder extends Seeder
{
    private const CUSTOMER_ID = 8;

    private const GROCERY_STORE_ID = 3;

    private const RENTAL_STORE_ID = 60;

    private const SERVICE_STORE_ID = 229;

    private const PASSWORD = 'Str0ng@Pass1';

    public function run(): void
    {
        $this->restoreAccounts();
        $this->ensureCustomerAddress();
        $this->ensureCart();
        $this->ensureRentalCart();
        $this->ensureRentalVehicle();
        $this->ensureSecondServiceman();
        $this->ensureVendorCoupon();
        $this->ensureStoreSchedule();
        $this->ensureCustomServiceRequest();
        $this->ensureVendorAddon();
        $this->ensurePendingItem();
        $this->ensureServiceCampaignAndReview();
        $this->ensureRecentNotifications();
        $this->ensureRecentExpense();
    }

    private function restoreAccounts(): void
    {
        $password = Hash::make(self::PASSWORD);

        DB::table('users')->where('id', self::CUSTOMER_ID)->update([
            'email' => '6amtech@gmail.com', 'phone' => '+8801700000000',
            'f_name' => '6am', 'l_name' => 'Tech', 'password' => $password, 'status' => 1,
        ]);

        DB::table('delivery_men')->whereIn('id', [1, 56])->update([
            'password' => $password, 'status' => 1, 'application_status' => 'approved',
        ]);
        DB::table('delivery_men')->where('id', 1)->update([
            'email' => 'deliveryman@6amtech.com', 'phone' => '+8801700000000',
            'f_name' => 'Jhon', 'l_name' => 'Doe',
        ]);

        DB::table('servicemen')->where('id', 9)->update([
            'phone' => '+8801700000903', 'email' => 'svcseed.sm0@mart.test',
            'f_name' => 'Seed', 'l_name' => 'Serviceman', 'password' => $password,
            'status' => 1, 'application_status' => 'approved',
        ]);

        DB::table('vendors')->whereIn('email', [
            'grocery.store2@demo.com', 'pharmacy.store1@demo.com', 'ecommerce.store6@demo.com',
            'test.restaurant@gmail.com', 'provider1@demo.com', 'svcseed.vendor@mart.test',
        ])->update(['password' => $password, 'status' => 1]);

        // Status-toggle endpoints exercised by the audit can suspend these stores, which then
        // blocks vendor login on the next run.
        DB::table('stores')->whereIn('id', [
            self::GROCERY_STORE_ID, self::RENTAL_STORE_ID, self::SERVICE_STORE_ID, 2, 21, 46,
        ])->update(['status' => 1, 'active' => 1]);

        $this->command?->info('  accounts restored');
    }

    private function clone(string $table, array $where, array $overrides, string $label): void
    {
        if (DB::table($table)->where($where)->exists()) {
            $this->command?->line("  $label already present");

            return;
        }

        $template = DB::table($table)->first();

        if (! $template) {
            $this->command?->warn("  $label skipped - no row in `$table` to clone");

            return;
        }

        $row = (array) $template;
        unset($row['id']);

        foreach (array_keys($row) as $column) {
            if (! array_key_exists($column, $overrides)) {
                continue;
            }
            $row[$column] = $overrides[$column];
        }

        $row['created_at'] = now();
        $row['updated_at'] = now();

        DB::table($table)->insert($row);
        $this->command?->info("  $label created");
    }

    /**
     * Feeds that filter to a recent window (notifications: 7-15 days, expense reports:
     * current month) show nothing against demo rows that are months old, so those need a
     * freshly dated row rather than merely any row.
     */
    private function cloneFresh(string $table, array $where, array $overrides, string $label, int $withinDays = 3): void
    {
        $recent = DB::table($table)->where($where)
            ->where('created_at', '>=', now()->subDays($withinDays))
            ->exists();

        if ($recent) {
            $this->command?->line("  $label already recent");

            return;
        }

        $template = DB::table($table)->where($where)->first() ?? DB::table($table)->first();

        if (! $template) {
            $this->command?->warn("  $label skipped - no row in `$table` to clone");

            return;
        }

        $row = (array) $template;
        unset($row['id']);
        foreach ($overrides as $column => $value) {
            if (array_key_exists($column, $row)) {
                $row[$column] = $value;
            }
        }
        $row['created_at'] = now();
        $row['updated_at'] = now();

        DB::table($table)->insert($row);
        $this->command?->info("  $label created (fresh-dated)");
    }

    private function ensureVendorAddon(): void
    {
        $this->clone('add_ons',
            ['store_id' => self::GROCERY_STORE_ID],
            ['store_id' => self::GROCERY_STORE_ID, 'status' => 1],
            'vendor addon');
    }

    private function ensurePendingItem(): void
    {
        $this->clone('temp_products',
            ['store_id' => self::GROCERY_STORE_ID],
            ['store_id' => self::GROCERY_STORE_ID, 'module_id' => 1],
            'pending item request');
    }

    private function ensureServiceCampaignAndReview(): void
    {
        $this->clone('service_campaigns',
            ['store_id' => self::SERVICE_STORE_ID],
            ['store_id' => self::SERVICE_STORE_ID, 'module_id' => 16, 'status' => 1],
            'service campaign');

        $this->clone('service_reviews',
            ['store_id' => self::SERVICE_STORE_ID],
            ['store_id' => self::SERVICE_STORE_ID, 'module_id' => 16, 'user_id' => self::CUSTOMER_ID],
            'service review');
    }

    private function ensureRecentNotifications(): void
    {
        $vendorId = DB::table('stores')->where('id', self::GROCERY_STORE_ID)->value('vendor_id');

        $this->cloneFresh('user_notifications', ['delivery_man_id' => 1],
            ['delivery_man_id' => 1], 'delivery-man notification');

        if ($vendorId) {
            $this->cloneFresh('user_notifications', ['vendor_id' => $vendorId],
                ['vendor_id' => $vendorId], 'vendor notification');
        }

        $this->cloneFresh('user_notifications', ['user_id' => self::CUSTOMER_ID],
            ['user_id' => self::CUSTOMER_ID], 'customer notification');
    }

    private function ensureRecentExpense(): void
    {
        $this->cloneFresh('expenses',
            ['store_id' => self::GROCERY_STORE_ID],
            ['store_id' => self::GROCERY_STORE_ID],
            'store expense', 25);
    }

    private function ensureCustomerAddress(): void
    {
        $this->clone('customer_addresses',
            ['user_id' => self::CUSTOMER_ID],
            ['user_id' => self::CUSTOMER_ID, 'contact_person_name' => 'Audit Fixture', 'is_guest' => 0],
            'customer address');
    }

    private function ensureCart(): void
    {
        $this->clone('carts',
            ['user_id' => self::CUSTOMER_ID, 'is_guest' => 0],
            ['user_id' => self::CUSTOMER_ID, 'is_guest' => 0],
            'cart row');
    }

    private function ensureRentalCart(): void
    {
        $this->clone('rental_carts',
            ['user_id' => self::CUSTOMER_ID],
            ['user_id' => self::CUSTOMER_ID, 'is_guest' => 0],
            'rental cart');

        $this->clone('rental_cart_user_data',
            ['user_id' => self::CUSTOMER_ID],
            ['user_id' => self::CUSTOMER_ID, 'is_guest' => 0],
            'rental cart user data');
    }

    private function ensureRentalVehicle(): void
    {
        $this->clone('vehicles',
            ['provider_id' => self::RENTAL_STORE_ID],
            ['provider_id' => self::RENTAL_STORE_ID, 'status' => 1],
            'rental vehicle for provider '.self::RENTAL_STORE_ID);
    }

    private function ensureSecondServiceman(): void
    {
        if (DB::table('servicemen')->count() >= 2) {
            $this->command?->line('  second serviceman already present');

            return;
        }

        $template = DB::table('servicemen')->where('id', 9)->first();

        if (! $template) {
            $this->command?->warn('  second serviceman skipped - serviceman 9 missing');

            return;
        }

        $row = (array) $template;
        unset($row['id']);
        $row['phone'] = '+8801700000904';
        $row['email'] = 'svcseed.sm1@mart.test';
        $row['f_name'] = 'Seed';
        $row['l_name'] = 'Serviceman Two';
        $row['auth_token'] = null;
        $row['provider_id'] = self::SERVICE_STORE_ID;
        $row['password'] = Hash::make(self::PASSWORD);
        $row['created_at'] = now();
        $row['updated_at'] = now();

        DB::table('servicemen')->insert($row);
        $this->command?->info('  second serviceman created (vendor-managed target)');
    }

    private function ensureVendorCoupon(): void
    {
        $this->clone('coupons',
            ['store_id' => self::GROCERY_STORE_ID],
            [
                'store_id' => self::GROCERY_STORE_ID,
                'created_by' => 'vendor',
                'code' => 'AUDITFIXTURE',
                'status' => 1,
                'start_date' => now()->subMonth()->toDateString(),
                'expire_date' => now()->addYear()->toDateString(),
            ],
            'vendor coupon for store '.self::GROCERY_STORE_ID);
    }

    private function ensureStoreSchedule(): void
    {
        $this->clone('store_schedule',
            ['store_id' => self::GROCERY_STORE_ID],
            ['store_id' => self::GROCERY_STORE_ID],
            'store schedule');
    }

    private function ensureCustomServiceRequest(): void
    {
        if (DB::table('custom_service_requests')->exists()) {
            $this->command?->line('  custom service request already present');

            return;
        }

        $category = DB::table('services')->value('category_id');

        DB::table('custom_service_requests')->insert([
            'user_id' => self::CUSTOMER_ID,
            'module_id' => 16,
            'category_id' => $category,
            'sub_category_id' => null,
            'customer_information' => json_encode([
                'name' => 'Audit Fixture',
                'phone' => '+8801700000000',
                'address' => 'Audit fixture address',
                'latitude' => '23.735129',
                'longitude' => '90.425614',
            ]),
            'description' => 'Audit fixture custom service request',
            'booking_date' => now()->addDays(3)->toDateString(),
            'booking_time' => '10:00:00',
            'status' => 'pending',
            'bid_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info('  custom service request created');
    }
}
