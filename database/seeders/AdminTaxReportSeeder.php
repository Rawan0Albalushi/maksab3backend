<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminTaxReportSeeder extends Seeder
{
    private const ORDER_COUNT = 30;
    private const SUBSCRIPTION_COUNT = 10;

    private const SCALED_TRANSACTION_COLUMNS = [
        'order_amount', 'store_amount', 'admin_commission', 'delivery_charge', 'original_delivery_charge',
        'tax', 'delivery_fee_comission', 'admin_expense', 'discount_amount_by_store',
    ];

    private const SCALED_ORDER_COLUMNS = [
        'order_amount', 'total_tax_amount', 'store_discount_amount', 'delivery_charge', 'original_delivery_charge',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->seedOrderTransactions();
            $this->seedSubscriptionTransactions();
        });
    }

    private function seedOrderTransactions(): void
    {
        $template = DB::table('order_transactions')
            ->join('orders', 'orders.id', '=', 'order_transactions.order_id')
            ->whereNull('order_transactions.status')
            ->where('orders.order_type', 'delivery')
            ->where('orders.order_status', 'delivered')
            ->where('order_transactions.admin_commission', '>', 0)
            ->latest('order_transactions.id')
            ->select('order_transactions.*')
            ->first();

        if (!$template) {
            $this->command?->warn('No delivered order transaction to copy from.');
            return;
        }

        $order = DB::table('orders')->where('id', $template->order_id)->first();
        $details = DB::table('order_details')->where('order_id', $order->id)->get();
        $orderColumns = array_flip(Schema::getColumnListing('orders'));
        $detailColumns = array_flip(Schema::getColumnListing('order_details'));

        for ($i = 0; $i < self::ORDER_COUNT; $i++) {
            $createdAt = $this->createdAt($i < 20 ? rand(0, 6) : rand(7, 60));
            $factor = rand(60, 260) / 100;

            $newOrder = (array) $order;
            unset($newOrder['id']);
            foreach (self::SCALED_ORDER_COLUMNS as $column) {
                if (isset($newOrder[$column])) {
                    $newOrder[$column] = round($newOrder[$column] * $factor, 2);
                }
            }
            $newOrder = array_intersect_key(array_merge($newOrder, [
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'schedule_at' => $createdAt,
                'pending' => $createdAt,
                'accepted' => $createdAt->copy()->addMinutes(3),
                'confirmed' => $createdAt->copy()->addMinutes(5),
                'processing' => $createdAt->copy()->addMinutes(10),
                'handover' => $createdAt->copy()->addMinutes(25),
                'picked_up' => $createdAt->copy()->addMinutes(30),
                'delivered' => $createdAt->copy()->addMinutes(55),
            ]), $orderColumns);
            $orderId = DB::table('orders')->insertGetId($newOrder);

            foreach ($details as $detail) {
                $newDetail = (array) $detail;
                unset($newDetail['id']);
                DB::table('order_details')->insert(array_intersect_key(array_merge($newDetail, [
                    'order_id' => $orderId,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]), $detailColumns));
            }

            $transaction = (array) $template;
            unset($transaction['id']);
            foreach (self::SCALED_TRANSACTION_COLUMNS as $column) {
                $transaction[$column] = round((float) $transaction[$column] * $factor, 2);
            }
            DB::table('order_transactions')->insert(array_merge($transaction, [
                'order_id' => $orderId,
                'additional_charge' => [5, 10, 15][$i % 3],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]));
        }

        $this->command?->info('Seeded ' . self::ORDER_COUNT . ' delivered orders with order transactions.');
    }

    private function seedSubscriptionTransactions(): void
    {
        $template = DB::table('subscription_transactions')->where('is_trial', 0)->latest('id')->first();

        if (!$template) {
            $this->command?->warn('No paid subscription transaction to copy from.');
            return;
        }

        $packages = DB::table('subscription_packages')->where('status', 1)->pluck('price', 'id');
        $storeIds = DB::table('store_subscriptions')->pluck('store_id', 'id');

        for ($i = 0; $i < self::SUBSCRIPTION_COUNT; $i++) {
            $createdAt = $this->createdAt($i < 4 ? rand(0, 6) : rand(7, (int) now()->startOfYear()->diffInDays(now())));
            $packageId = $packages->keys()->get($i % max($packages->count(), 1)) ?? $template->package_id;
            $price = (float) ($packages[$packageId] ?? $template->price);
            $subscriptionId = $storeIds->keys()->get($i % max($storeIds->count(), 1)) ?? $template->store_subscription_id;

            $transaction = (array) $template;
            unset($transaction['id']);
            DB::table('subscription_transactions')->insert(array_merge($transaction, [
                'package_id' => $packageId,
                'store_subscription_id' => $subscriptionId,
                'store_id' => $storeIds[$subscriptionId] ?? $template->store_id,
                'price' => $price,
                'paid_amount' => $price,
                'plan_type' => $i % 3 === 0 ? 'renew' : 'first_purchased',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]));
        }

        $this->command?->info('Seeded ' . self::SUBSCRIPTION_COUNT . ' paid subscription transactions.');
    }

    private function createdAt(int $daysAgo): Carbon
    {
        return min(now()->subDays($daysAgo)->setTime(rand(8, 21), rand(0, 59), rand(0, 59)), now()->subMinutes(rand(5, 90)));
    }
}
