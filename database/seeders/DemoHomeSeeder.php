<?php

namespace Database\Seeders;

use App\Models\Advertisement;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Discount;
use App\Models\EcommerceItemDetails;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\Store;
use App\Models\StoreConfig;
use App\Models\StoreSchedule;
use App\Support\Cache\ApiCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fills the grocery demo module home so the customer app is not a set of empty sections.
 * Safe to run again: every row is matched by name before it is inserted.
 */
class DemoHomeSeeder extends Seeder
{
    private const MODULE_ID = 1;

    private const STORE_ID = 1;

    private const ZONE_ID = 1;

    public function run(): void
    {
        $store = Store::withoutGlobalScopes()->find(self::STORE_ID);
        if (! $store || (int) $store->module_id !== self::MODULE_ID) {
            $this->command?->warn('Demo store for module 1 was not found. Nothing was seeded.');

            return;
        }

        $this->openStore($store);

        $categories = $this->seedCategories();
        $brands = $this->seedBrands();
        $items = $this->seedItems($categories, $brands);
        $this->enrichExistingProduct($brands['Almarai'] ?? null);
        $this->seedAdvertisements((int) $store->vendor_id);
        $this->seedFlashSale($items);
        $this->seedItemCampaigns($items);
        $this->seedBasicCampaign((int) $store->id);
        $this->seedStoreDiscount((int) $store->id);

        ApiCache::flush();
        $this->command?->info('Demo home data is ready for module '.self::MODULE_ID.'.');
    }

    private function openStore(Store $store): void
    {
        $store->delivery_time = '15-25 min';
        $store->order_count = max((int) $store->order_count, 86);
        $store->total_order = max((int) $store->total_order, 86);
        $store->featured = 1;
        $store->save();

        StoreConfig::query()->updateOrCreate(
            ['store_id' => $store->id],
            ['is_recommended' => 1, 'is_recommended_deleted' => 0]
        );

        foreach (range(0, 6) as $day) {
            StoreSchedule::query()->updateOrCreate(
                ['store_id' => $store->id, 'day' => $day],
                ['opening_time' => '00:00:00', 'closing_time' => '23:59:00']
            );
        }
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $names = [
            'Fresh Produce' => $this->image('category', 'maksab-cat-produce.jpg'),
            'Dairy & Eggs' => $this->image('category', 'maksab-cat-dairy.jpg'),
            'Bakery' => $this->image('category', 'maksab-cat-bakery.jpg'),
            'Beverages' => $this->image('category', 'maksab-cat-drinks.jpg'),
            'Pantry' => $this->image('category', 'maksab-cat-pantry.jpg'),
        ];

        $categories = [];
        $priority = 1;
        foreach ($names as $name => $file) {
            $categories[$name] = $this->firstByName(Category::class, $name, [
                'name' => $name,
                'image' => $file,
                'parent_id' => 0,
                'position' => 0,
                'priority' => $priority,
                'status' => 1,
                'featured' => 1,
                'module_id' => self::MODULE_ID,
            ]);
            $priority++;
        }

        return $categories;
    }

    /**
     * @return array<string, Brand>
     */
    private function seedBrands(): array
    {
        $files = [
            'Almarai' => $this->image('brand', 'maksab-brand-almarai.jpg'),
            'Al Ain' => $this->image('brand', 'maksab-brand-alain.jpg'),
            'Sadia' => $this->image('brand', 'maksab-brand-sadia.jpg'),
            'Lurpak' => $this->image('brand', 'maksab-brand-lurpak.jpg'),
            'Nestle' => $this->image('brand', 'maksab-brand-nestle.jpg'),
        ];

        $brands = [];
        foreach ($files as $name => $file) {
            $brands[$name] = $this->firstByName(Brand::class, $name, [
                'name' => $name,
                'image' => $file,
                'status' => 1,
                'module_id' => self::MODULE_ID,
            ]);
        }

        return $brands;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Brand>  $brands
     * @return array<string, Item>
     */
    private function seedItems(array $categories, array $brands): array
    {
        $photos = [
            'Fresh Milk 1L' => $this->image('product', 'maksab-item-milk.jpg'),
            'Orange Juice' => $this->image('product', 'maksab-item-juice.jpg'),
            'Whole Wheat Bread' => $this->image('product', 'maksab-item-bread.jpg'),
            'Tomatoes 1kg' => $this->image('product', 'maksab-item-tomatoes.jpg'),
            'Eggs 12pcs' => $this->image('product', 'maksab-item-eggs.jpg'),
            'Basmati Rice 5kg' => $this->image('product', 'maksab-item-rice.jpg'),
            'Chicken Breast 1kg' => $this->image('product', 'maksab-item-chicken.jpg'),
            'Greek Yogurt' => $this->image('product', 'maksab-item-yogurt.jpg'),
        ];

        $rows = [
            ['Fresh Milk 1L', 'Dairy & Eggs', 'Almarai', 1.20, 15, 48, 4.7, 32, 1],
            ['Orange Juice', 'Beverages', 'Al Ain', 1.80, 10, 27, 4.4, 19, 1],
            ['Whole Wheat Bread', 'Bakery', 'Lurpak', 0.90, 20, 41, 4.6, 22, 0],
            ['Tomatoes 1kg', 'Fresh Produce', 'Sadia', 0.75, 25, 63, 4.3, 15, 1],
            ['Eggs 12pcs', 'Dairy & Eggs', 'Almarai', 1.50, 5, 36, 4.8, 40, 1],
            ['Basmati Rice 5kg', 'Pantry', 'Nestle', 4.90, 18, 22, 4.5, 11, 0],
            ['Chicken Breast 1kg', 'Fresh Produce', 'Sadia', 3.40, 12, 18, 4.2, 9, 0],
            ['Greek Yogurt', 'Dairy & Eggs', 'Lurpak', 1.10, 30, 29, 4.6, 17, 1],
        ];

        $items = [];
        foreach ($rows as $index => $row) {
            [$name, $categoryName, $brandName, $price, $discount, $orders, $rating, $reviews, $veg] = $row;
            $category = $categories[$categoryName];
            $image = $photos[$name];

            $item = $this->firstByName(Item::class, $name, [
                'name' => $name,
                'description' => $name.' from Demo Store.',
                'image' => $image,
                'images' => [['img' => $image, 'storage' => 'public']],
                'category_id' => $category->id,
                'category_ids' => json_encode([['id' => (string) $category->id, 'position' => 1]]),
                'variations' => '[]',
                'add_ons' => '[]',
                'attributes' => '[]',
                'choice_options' => '[]',
                'food_variations' => '[]',
                'price' => $price,
                'tax' => 0,
                'tax_type' => 'percent',
                'discount' => $discount,
                'discount_type' => 'percent',
                'available_time_starts' => '00:00:00',
                'available_time_ends' => '23:59:59',
                'veg' => $veg,
                'status' => 1,
                'store_id' => self::STORE_ID,
                'order_count' => $orders,
                'avg_rating' => $rating,
                'rating_count' => $reviews,
                'rating' => json_encode([5 => $reviews]),
                'module_id' => self::MODULE_ID,
                'stock' => 40,
                'recommended' => 1,
                'organic' => $index % 2,
                'maximum_cart_quantity' => 10,
                'is_approved' => 1,
                'is_halal' => 1,
                'top_category_id' => $category->id,
                'zone_id' => self::ZONE_ID,
            ]);

            EcommerceItemDetails::query()->updateOrCreate(
                ['item_id' => $item->id],
                ['brand_id' => $brands[$brandName]->id]
            );

            $items[$name] = $item;
        }

        return $items;
    }

    private function enrichExistingProduct(?Brand $brand): void
    {
        $item = Item::withoutGlobalScopes()->withoutGlobalScope('translate')->where('slug', 'demo-product')->first();
        if (! $item) {
            return;
        }

        $item->discount = 10;
        $item->discount_type = 'percent';
        $item->order_count = max((int) $item->order_count, 24);
        $item->avg_rating = 4.5;
        $item->rating_count = max((int) $item->rating_count, 18);
        $item->recommended = 1;
        $item->stock = max((int) $item->stock, 20);
        $item->save();

        if ($brand) {
            EcommerceItemDetails::query()->updateOrCreate(
                ['item_id' => $item->id],
                ['brand_id' => $brand->id]
            );
        }
    }

    private function seedAdvertisements(int $vendorId): void
    {
        $profile = $this->image('advertisement', 'maksab-store-logo.jpg');

        $ads = [
            ['Weekend grocery deals', 'Fresh picks, delivered from Demo Store.', 'maksab-ad-weekend.jpg'],
            ['Family basket offer', 'Stock the pantry and save on this week\'s essentials.', 'maksab-ad-family.jpg'],
        ];

        foreach ($ads as $index => [$title, $description, $coverFile]) {
            $this->firstByName(Advertisement::class, $title, [
                'store_id' => self::STORE_ID,
                'module_id' => self::MODULE_ID,
                'module_type' => 'grocery',
                'add_type' => 'store_promotion',
                'title' => $title,
                'description' => $description,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'cover_image' => $this->image('advertisement', $coverFile),
                'profile_image' => $profile,
                'priority' => $index + 1,
                'is_rating_active' => 1,
                'is_review_active' => 1,
                'is_paid' => 1,
                'is_updated' => 0,
                'created_by_id' => $vendorId,
                'created_by_type' => 'App\\Models\\Vendor',
                'status' => 'approved',
            ]);
        }
    }

    /**
     * @param  array<string, Item>  $items
     */
    private function seedFlashSale(array $items): void
    {
        $sale = $this->firstByName(FlashSale::class, 'Demo Flash Sale', [
            'module_id' => self::MODULE_ID,
            'title' => 'Demo Flash Sale',
            'is_publish' => 1,
            'admin_discount_percentage' => 0,
            'vendor_discount_percentage' => 0,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDays(14),
        ]);

        foreach (['Fresh Milk 1L', 'Greek Yogurt', 'Tomatoes 1kg'] as $name) {
            $item = $items[$name] ?? null;
            if (! $item) {
                continue;
            }

            $discount = 35;
            $amount = round(((float) $item->price) * $discount / 100, 2);
            FlashSaleItem::query()->updateOrCreate(
                ['flash_sale_id' => $sale->id, 'item_id' => $item->id],
                [
                    'stock' => 20,
                    'sold' => 4,
                    'available_stock' => 16,
                    'discount_type' => 'percent',
                    'discount' => $discount,
                    'discount_amount' => $amount,
                    'price' => round(((float) $item->price) - $amount, 2),
                    'status' => 1,
                ]
            );
        }
    }

    /**
     * @param  array<string, Item>  $items
     */
    private function seedItemCampaigns(array $items): void
    {
        $photos = [
            'Fresh Milk 1L' => $this->image('campaign', 'maksab-item-milk.jpg'),
            'Basmati Rice 5kg' => $this->image('campaign', 'maksab-item-rice.jpg'),
            'Chicken Breast 1kg' => $this->image('campaign', 'maksab-item-chicken.jpg'),
        ];
        $adminId = DB::table('admins')->value('id');

        $offers = [
            'Fresh Milk 1L' => 20,
            'Basmati Rice 5kg' => 15,
            'Chicken Breast 1kg' => 10,
        ];

        foreach ($offers as $name => $discount) {
            $item = $items[$name] ?? null;
            if (! $item) {
                continue;
            }

            $title = $name.' deal';
            $this->firstByName(ItemCampaign::class, $title, [
                'title' => $title,
                'image' => $photos[$name],
                'description' => 'Limited demo offer on '.$name.'.',
                'status' => 1,
                'admin_id' => $adminId,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'category_id' => $item->category_id,
                'category_ids' => $item->category_ids,
                'variations' => '[]',
                'add_ons' => '[]',
                'attributes' => '[]',
                'choice_options' => '[]',
                'food_variations' => '[]',
                'price' => $item->price,
                'tax' => 0,
                'tax_type' => 'percent',
                'discount' => $discount,
                'discount_type' => 'percent',
                'store_id' => self::STORE_ID,
                'veg' => $item->veg,
                'module_id' => self::MODULE_ID,
                'stock' => 25,
                'maximum_cart_quantity' => 5,
            ]);
        }
    }

    private function seedBasicCampaign(int $storeId): void
    {
        $image = $this->image('campaign', 'maksab-campaign-weekly.jpg');
        $adminId = DB::table('admins')->value('id');

        $campaign = $this->firstByName(Campaign::class, 'Demo Weekly Campaign', [
            'title' => 'Demo Weekly Campaign',
            'image' => $image,
            'description' => 'A running campaign for the demo grocery store.',
            'status' => 1,
            'admin_id' => $adminId,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
            'module_id' => self::MODULE_ID,
        ]);

        DB::table('campaign_store')->updateOrInsert(
            ['campaign_id' => $campaign->id, 'store_id' => $storeId],
            ['campaign_status' => 'confirmed', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function seedStoreDiscount(int $storeId): void
    {
        Discount::query()->updateOrCreate(
            ['store_id' => $storeId],
            [
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'min_purchase' => 0,
                'max_discount' => 5,
                'discount' => 20,
                'discount_type' => 'percent',
            ]
        );
    }

    /**
     * @param  class-string  $model
     */
    private function firstByName(string $model, string $name, array $attributes): mixed
    {
        $column = array_key_exists('name', $attributes) ? 'name' : 'title';
        $existing = $model::withoutGlobalScopes()->where($column, $name)->first();

        if ($existing) {
            return $existing;
        }

        $created = new $model;
        $created->forceFill($attributes);
        $created->save();

        return $created->fresh() ?? $created;
    }

    private function image(string $folder, string $filename): string
    {
        $directory = storage_path('app/public/'.$folder);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $target = $directory.DIRECTORY_SEPARATOR.$filename;
        $from = database_path('seeders/images/'.$filename);
        if (! is_file($target) && is_file($from)) {
            copy($from, $target);
        }

        return $filename;
    }
}
