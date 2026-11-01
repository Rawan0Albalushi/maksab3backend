<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParcelTaxReportSeeder extends Seeder
{
    private const TAXES = [
        ['tax_id' => 1, 'tax_name' => 'Service tax', 'tax_rate' => 10],
        ['tax_id' => 2, 'tax_name' => 'Income tax', 'tax_rate' => 5],
    ];

    public function run(): void
    {
        $template = DB::table('orders')
            ->where('order_type', 'parcel')
            ->where('order_status', 'delivered')
            ->whereNotNull('delivery_man_id')
            ->latest('id')
            ->first();

        if (!$template) {
            $this->command?->warn('No delivered parcel order to copy from.');
            return;
        }

        $systemTaxSetupId = DB::table('system_tax_setups')->where('tax_payer', 'parcel')->value('id') ?? 4;
        $statuses = ['delivered', 'delivered', 'delivered', 'delivered', 'refund_requested', 'refund_request_canceled'];
        $taxTypes = ['order_wise', 'order_wise', 'category_wise'];

        DB::transaction(function () use ($template, $systemTaxSetupId, $statuses, $taxTypes) {
            for ($i = 0; $i < 24; $i++) {
                $createdAt = $i < 16
                    ? now()->subDays(rand(0, 6))->setTime(rand(8, 21), rand(0, 59), rand(0, 59))
                    : now()->subDays(rand(7, 30))->setTime(rand(8, 21), rand(0, 59), rand(0, 59));

                $deliveryCharge = round(rand(4000, 25000) / 100, 2);
                $additionalCharge = (float) $template->additional_charge;
                $taxes = $i % 3 === 0 ? self::TAXES : [self::TAXES[0]];
                $totalTax = round(array_sum(array_map(fn ($tax) => $deliveryCharge * $tax['tax_rate'] / 100, $taxes)), 2);
                $status = $statuses[$i % count($statuses)];

                $order = (array) $template;
                unset($order['id']);
                $order = array_merge($order, [
                    'order_status' => $status,
                    'payment_status' => 'paid',
                    'delivery_charge' => $deliveryCharge,
                    'original_delivery_charge' => $deliveryCharge,
                    'total_tax_amount' => $totalTax,
                    'order_amount' => round($deliveryCharge + $additionalCharge + $totalTax, 2),
                    'tax_type' => $taxTypes[$i % count($taxTypes)],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'pending' => $createdAt,
                    'confirmed' => $createdAt->copy()->addMinutes(5),
                    'picked_up' => $createdAt->copy()->addMinutes(20),
                    'delivered' => $createdAt->copy()->addMinutes(55),
                    'refund_requested' => $status === 'refund_requested' ? $createdAt->copy()->addHours(2) : null,
                    'refund_request_canceled' => $status === 'refund_request_canceled' ? $createdAt->copy()->addHours(3) : null,
                ]);
                $order = array_intersect_key($order, array_flip(DB::getSchemaBuilder()->getColumnListing('orders')));

                $orderId = DB::table('orders')->insertGetId($order);

                foreach ($taxes as $tax) {
                    $taxAmount = round($deliveryCharge * $tax['tax_rate'] / 100, 2);
                    DB::table('order_taxes')->insert([
                        'tax_name' => $tax['tax_name'],
                        'tax_type' => 'order_wise',
                        'tax_on' => 'basic',
                        'tax_rate' => $tax['tax_rate'],
                        'tax_amount' => $taxAmount,
                        'before_tax_amount' => $deliveryCharge,
                        'after_tax_amount' => $deliveryCharge + $taxAmount,
                        'tax_payer' => 'parcel',
                        'order_id' => $orderId,
                        'order_type' => 'App\Models\Order',
                        'quantity' => 1,
                        'tax_id' => $tax['tax_id'],
                        'store_type' => 'App\Models\Store',
                        'system_tax_setup_id' => $systemTaxSetupId,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }
        });

        $this->command?->info('Seeded 24 parcel orders with parcel taxes.');
    }
}
