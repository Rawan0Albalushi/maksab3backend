<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Contract test for the BOGO and Happy Hour schema.
 *
 * Guards the three decisions that are expensive to discover later: that a bundle line names
 * exactly one target, that both features are module-scoped, and that BOGO's grouping columns
 * reached carts and order_details together. A schema mistake found while writing the placement
 * code is a migration plus a data fix; found here it is an edit.
 *
 * DatabaseTransactions rather than RefreshDatabase on purpose -- phpunit.xml has the sqlite
 * in-memory connection commented out, so the suite runs against the configured database and
 * RefreshDatabase would drop the developer's data.
 */
class PromotionSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_every_promotion_table_exists(): void
    {
        foreach ([
            'bogo_offers',
            'bogo_offer_store',
            'bogo_offer_items',
            'bogo_offer_usages',
            'happy_hours',
            'happy_hour_dates',
            'happy_hour_store',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing table: {$table}");
        }
    }

    public function test_bogo_offer_columns_are_present(): void
    {
        $this->assertTrue(Schema::hasColumns('bogo_offers', [
            'module_id', 'title', 'buy_qty', 'get_qty', 'start_date', 'end_date',
            'usage_limit_total', 'usage_limit_per_customer', 'total_uses',
            'order_types', 'status', 'slug', 'admin_id',
        ]));
    }

    public function test_happy_hour_columns_are_present(): void
    {
        $this->assertTrue(Schema::hasColumns('happy_hours', [
            'module_id', 'title', 'discount', 'min_order_amount', 'is_permanent',
            'duration_type', 'weekly_days', 'custom_days', 'custom_times',
            'start_date', 'end_date', 'start_time', 'end_time', 'cover_image', 'icon',
        ]));

        $this->assertTrue(Schema::hasColumns('happy_hour_dates', [
            'happy_hour_id', 'module_id', 'applicable_date', 'start_time', 'end_time', 'status',
        ]));
    }

    /**
     * D2. A promotion that did not name a module would let a grocery one discount a food order.
     * Nullable would reintroduce exactly that.
     */
    public function test_module_id_is_required_on_both_offer_tables(): void
    {
        foreach ([['bogo_offers', 'module_id'], ['happy_hours', 'module_id'], ['happy_hour_dates', 'module_id']] as [$table, $column]) {
            $this->assertSame(
                'NO',
                $this->nullability($table, $column),
                "{$table}.{$column} must be NOT NULL"
            );
        }
    }

    /**
     * Happy Hour and BOGO run in grocery, food, pharmacy and ecommerce -- and nowhere else.
     *
     * Both need a line-item cart with priced products. Parcel carries nothing to bundle or
     * discount, and rental, ride-share and service have no per-item price for a store-wide
     * percentage to apply to.
     *
     * The capability is stated once, in config/module.php beside stock and add_on, because the
     * sidebar, the route guard and the seeders all read it -- a literal list repeated in each is
     * how they drift apart.
     */
    public function test_promotions_are_enabled_only_for_the_capable_module_types(): void
    {
        $capable = ['grocery', 'food', 'pharmacy', 'ecommerce'];

        foreach (config('module.module_type') as $type) {
            $this->assertSame(
                in_array($type, $capable, true),
                (bool) config('module.'.$type.'.promotions'),
                "module type {$type} has the wrong promotions capability"
            );
        }
    }

    /** And no promotion copy was seeded for a type that cannot run one. */
    public function test_no_promotion_notification_copy_exists_outside_those_types(): void
    {
        $stray = DB::table('notification_messages')
            ->where(fn ($q) => $q->where('key', 'like', '%bogo%')->orWhere('key', 'like', '%happy_hour%'))
            ->whereNotIn('module_type', ['grocery', 'food', 'pharmacy', 'ecommerce'])
            ->pluck('module_type')
            ->unique()
            ->values()
            ->all();

        $this->assertSame([], $stray, 'promotion copy seeded for: '.implode(', ', $stray));
    }

    /**
     * And a happy hour carries no zone of its own.
     *
     * The admin panel has no zone context to inherit one from -- it is module-scoped throughout --
     * and how far a window reaches follows from which stores enrol in it, each carrying its own
     * zone. A zone column here would express that reach a second time, and the two would drift.
     */
    public function test_happy_hours_carry_no_zone(): void
    {
        $this->assertFalse(Schema::hasColumn('happy_hours', 'zone_id'));
        $this->assertFalse(Schema::hasColumn('happy_hour_dates', 'zone_id'));
    }

    /** The grouping columns are useless unless both sides of the cart-to-order handover have them. */
    public function test_bogo_grouping_columns_reached_carts_and_order_details(): void
    {
        $this->assertTrue(Schema::hasColumns('carts', ['bogo_offer_id', 'bogo_group_id', 'is_free_item']));

        $this->assertTrue(Schema::hasColumns('order_details', [
            'bogo_offer_id', 'bogo_group_id', 'is_free_item', 'bogo_free_value',
        ]));

        $this->assertTrue(Schema::hasColumns('orders', ['happy_hour_id', 'bogo_discount_amount']));
    }

    public function test_group_id_is_indexed_on_both_tables(): void
    {
        $this->assertTrue($this->indexExists('carts', 'carts_bogo_group_id_index'));
        $this->assertTrue($this->indexExists('order_details', 'order_details_bogo_group_id_index'));
    }

    /** A store joins an offer once. This unique key is what a double-clicked join relies on. */
    public function test_a_store_can_only_enrol_once_per_offer(): void
    {
        $this->assertTrue($this->indexExists('bogo_offer_store', 'bogo_offer_store_bogo_offer_id_store_id_unique'));
        $this->assertTrue($this->indexExists('happy_hour_store', 'happy_hour_store_happy_hour_id_store_id_unique'));
    }

    /** §6.2: the source shipped this as an unbounded text column. */
    public function test_rejection_reason_is_bounded(): void
    {
        foreach (['bogo_offer_store', 'happy_hour_store'] as $table) {
            $column = DB::selectOne(
                'SELECT character_maximum_length AS len FROM information_schema.columns
                 WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                [DB::getDatabaseName(), $table, 'rejection_reason']
            );

            $this->assertNotNull($column, "{$table}.rejection_reason is missing");
            $this->assertSame(255, (int) $column->len, "{$table}.rejection_reason must be bounded at 255");
        }
    }

    /**
     * No screen may accept a rejection reason longer than the column can hold.
     *
     * The admin BOGO reject allowed 1000 characters into a varchar(255). This server's sql_mode is
     * not strict, so MySQL did not refuse it -- it truncated silently, and the store was shown a
     * refusal cut off mid-sentence. A validation cap that disagrees with its column is worse than
     * no cap, because it reads as deliberate.
     */
    public function test_no_rejection_reason_input_outruns_its_column(): void
    {
        $limit = (int) DB::selectOne(
            'SELECT character_maximum_length AS len FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [DB::getDatabaseName(), 'bogo_offer_store', 'rejection_reason']
        )->len;

        $offenders = [];

        foreach ([
            'app/Http/Controllers/Admin/Promotion/BogoOfferController.php',
            'app/Http/Controllers/Admin/Promotion/HappyHourController.php',
            'app/Http/Controllers/Vendor/Promotion/BogoOfferController.php',
            'app/Http/Controllers/Vendor/Promotion/HappyHourController.php',
            'resources/views/admin-views/promotions/bogo-offer/view.blade.php',
            'resources/views/admin-views/promotions/happy-hour/view.blade.php',
            'resources/views/vendor-views/promotions/bogo-offer/list.blade.php',
            'resources/views/vendor-views/promotions/happy-hour/list.blade.php',
        ] as $file) {
            $path = base_path($file);

            if (! is_file($path)) {
                continue;
            }

            $body = file_get_contents($path);

            // Both the server rule and the textarea's own cap.
            preg_match_all('/rejection_reason[^\n]*?max:(\d+)|maxlength="(\d+)"[^\n]*\n[^\n]*rejection_reason|rejection_reason[^\n]*maxlength="(\d+)"/', $body, $m, PREG_SET_ORDER);

            foreach ($m as $hit) {
                $found = (int) (($hit[1] ?? 0) ?: ($hit[2] ?? 0) ?: ($hit[3] ?? 0));

                if ($found > $limit) {
                    $offenders[] = $file.' allows '.$found.' into a '.$limit.'-character column';
                }
            }
        }

        $this->assertSame([], $offenders);
    }

    /**
     * rejected_by names a side -- 'admin' or 'store' -- so it has to be able to hold a word.
     *
     * It shipped as an unsignedBigInteger, and nothing complained: writing cast 'admin' to 0, and
     * every read compared that 0 against a string and quietly answered false. The rule that an
     * admin's denial may only be reworked, never cancelled away with its reason, was off for as
     * long as that lasted.
     */
    public function test_rejected_by_holds_a_side_and_not_a_number(): void
    {
        foreach (['bogo_offer_store', 'happy_hour_store'] as $table) {
            $column = DB::selectOne(
                'SELECT data_type AS type, character_maximum_length AS len FROM information_schema.columns
                 WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                [DB::getDatabaseName(), $table, 'rejected_by']
            );

            $this->assertNotNull($column, "{$table}.rejected_by is missing");
            $this->assertSame('varchar', strtolower($column->type), "{$table}.rejected_by must hold a side, not an id");
            $this->assertGreaterThanOrEqual(6, (int) $column->len, "{$table}.rejected_by must fit 'store'");
        }
    }

    /** The models must not cast the word back to a number, which is how the column type hid. */
    public function test_neither_enrolment_model_casts_rejected_by(): void
    {
        foreach ([new \App\Models\BogoOfferStore, new \App\Models\HappyHourStore] as $model) {
            $this->assertArrayNotHasKey(
                'rejected_by',
                $model->getCasts(),
                $model::class.' must leave rejected_by as the string it is'
            );
        }
    }

    /**
     * D1. A bundle line points at an item or at a service, never both and never neither. MySQL 9
     * honours CHECK, so this is enforced by the database and not only by the model.
     */
    public function test_a_bundle_line_must_name_exactly_one_target(): void
    {
        [$enrolmentId, $itemId, $serviceId] = $this->makeEnrolment();

        $line = [
            'bogo_offer_store_id' => $enrolmentId,
            'type' => 'buy',
            'quantity' => 1,
            'item_name' => 'schema probe',
            'price' => 0,
            'original_price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Neither target set.
        try {
            DB::table('bogo_offer_items')->insert($line);
            $this->fail('a line with no target was accepted');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('bogo_offer_items_one_target', $e->getMessage());
        }

        // Both targets set.
        if ($serviceId !== null) {
            try {
                DB::table('bogo_offer_items')->insert($line + ['item_id' => $itemId, 'service_id' => $serviceId]);
                $this->fail('a line naming both an item and a service was accepted');
            } catch (\Throwable $e) {
                $this->assertStringContainsString('bogo_offer_items_one_target', $e->getMessage());
            }
        }

        // Exactly one target is the only accepted shape.
        DB::table('bogo_offer_items')->insert($line + ['item_id' => $itemId]);

        $this->assertDatabaseHas('bogo_offer_items', [
            'bogo_offer_store_id' => $enrolmentId,
            'item_id' => $itemId,
            'service_id' => null,
        ]);
    }

    /**
     * §20.2. The source allowed dine_in; 6amMart has no dine-in concept -- PlaceOrderRequest
     * validates in:take_away,delivery,parcel -- and parcel carries nothing to bundle. The column
     * is nullable because "no order-type restriction" is a legal state.
     */
    public function test_order_types_is_nullable_json(): void
    {
        $column = DB::selectOne(
            'SELECT is_nullable AS n, data_type AS t FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [DB::getDatabaseName(), 'bogo_offers', 'order_types']
        );

        $this->assertSame('YES', $column->n, 'order_types must stay nullable');
        $this->assertSame('json', strtolower($column->t));
    }

    public function test_notification_copy_is_seeded_for_every_capable_module_type(): void
    {
        // Both features seed the same four types, and only those four.
        foreach (['grocery', 'food', 'pharmacy', 'ecommerce'] as $moduleType) {
            foreach (['bogo_join_accepted', 'happy_hour_join_accepted'] as $key) {
                $this->assertDatabaseHas('notification_messages', [
                    'key' => $key,
                    'module_type' => $moduleType,
                ]);
            }
        }

        foreach (['parcel', 'rental', 'ride-share', 'service'] as $moduleType) {
            foreach (['bogo_join_accepted', 'happy_hour_join_accepted'] as $key) {
                $this->assertDatabaseMissing('notification_messages', [
                    'key' => $key,
                    'module_type' => $moduleType,
                ]);
            }
        }
    }

    public function test_email_templates_are_seeded_for_both_features(): void
    {
        foreach (['bogo_request', 'bogo_approve', 'bogo_deny',
            'happy_hour_request', 'happy_hour_approve', 'happy_hour_deny'] as $emailType) {
            $this->assertDatabaseHas('email_templates', ['type' => 'store', 'email_type' => $emailType]);
        }
    }

    /**
     * notification_settings does not follow notification_messages' convention: it uses
     * module_type 'all' for the core verticals. Seeding these per concrete type would produce
     * rows the settings screen never reads.
     */
    public function test_notification_settings_use_the_all_module_convention(): void
    {
        foreach (['bogo_offer_enrollment', 'happy_hour_enrollment'] as $key) {
            $this->assertDatabaseHas('notification_settings', [
                'key' => $key,
                'type' => 'store',
                'module_type' => 'all',
            ]);
        }
    }

    /** Returns 'YES' or 'NO' for a column's IS_NULLABLE, aliased so MySQL and MariaDB agree. */
    private function nullability(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            'SELECT is_nullable AS n FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [DB::getDatabaseName(), $table, $column]
        );

        return $row?->n;
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [DB::getDatabaseName(), $table, $name]
        ) !== null;
    }

    /**
     * Builds an offer and an enrolment from rows the dataset already has. No factories exist for
     * stores or items, so the house approach is to read seeded rows and skip when there are none.
     *
     * @return array{0:int,1:int,2:?int}
     */
    private function makeEnrolment(): array
    {
        $moduleId = DB::table('modules')->value('id');
        $storeId = DB::table('stores')->value('id');
        $itemId = DB::table('items')->value('id');

        if (! $moduleId || ! $storeId || ! $itemId) {
            $this->markTestSkipped('dataset has no module, store or item to build an enrolment from');
        }

        $offerId = DB::table('bogo_offers')->insertGetId([
            'module_id' => $moduleId,
            'title' => 'schema probe',
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrolmentId = DB::table('bogo_offer_store')->insertGetId([
            'bogo_offer_id' => $offerId,
            'store_id' => $storeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$enrolmentId, $itemId, DB::table('services')->value('id')];
    }
}
