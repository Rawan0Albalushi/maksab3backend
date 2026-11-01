<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RentalTaxReportSeeder extends Seeder
{
    private const TRIP_COUNT = 30;

    private const SCALED_TRANSACTION_COLUMNS = [
        'trip_amount', 'store_amount', 'admin_commission', 'tax', 'admin_expense', 'store_expense',
        'discount_amount_by_store', 'admin_net_income',
    ];

    private const SCALED_TRIP_COLUMNS = [
        'trip_amount', 'discount_on_trip', 'tax_amount',
    ];

    private const SCALED_DETAIL_COLUMNS = [
        'price', 'original_price', 'calculated_price', 'discount_on_trip', 'tax_amount',
    ];

    public function run(): void
    {
        $templates = DB::table('trip_transactions')
            ->join('trips', 'trips.id', '=', 'trip_transactions.trip_id')
            ->whereNull('trip_transactions.status')
            ->where('trips.trip_status', 'completed')
            ->select('trip_transactions.*')
            ->orderBy('trip_transactions.id')
            ->get();

        if ($templates->isEmpty()) {
            $this->command?->warn('No completed trip transaction to copy from.');
            return;
        }

        $tripColumns = array_flip(Schema::getColumnListing('trips'));

        DB::transaction(function () use ($templates, $tripColumns) {
            for ($i = 0; $i < self::TRIP_COUNT; $i++) {
                $template = $templates[$i % $templates->count()];
                $createdAt = $this->createdAt($i < 20 ? rand(0, 6) : rand(7, 60));
                $factor = rand(70, 180) / 100;

                $trip = (array) DB::table('trips')->where('id', $template->trip_id)->first();
                unset($trip['id']);
                $trip = array_intersect_key(array_merge($this->scale($trip, self::SCALED_TRIP_COLUMNS, $factor), [
                    'additional_charge' => [5, 10, 15][$i % 3],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'schedule_at' => $createdAt,
                    'pending' => $createdAt,
                    'confirmed' => $createdAt->copy()->addMinutes(10),
                    'ongoing' => $createdAt->copy()->addMinutes(40),
                    'completed' => $createdAt->copy()->addHours(3),
                ]), $tripColumns);
                $tripId = DB::table('trips')->insertGetId($trip);

                foreach (DB::table('trip_details')->where('trip_id', $template->trip_id)->get() as $detail) {
                    $detail = (array) $detail;
                    $oldDetailId = $detail['id'];
                    unset($detail['id']);
                    $detailId = DB::table('trip_details')->insertGetId(array_merge($this->scale($detail, self::SCALED_DETAIL_COLUMNS, $factor), [
                        'trip_id' => $tripId,
                        'schedule_at' => $createdAt,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]));

                    foreach (DB::table('trip_vehicle_details')->where('trip_details_id', $oldDetailId)->get() as $vehicle) {
                        $vehicle = (array) $vehicle;
                        unset($vehicle['id']);
                        DB::table('trip_vehicle_details')->insert(array_merge($vehicle, [
                            'trip_id' => $tripId,
                            'trip_details_id' => $detailId,
                            'is_completed' => 1,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ]));
                    }
                }

                $transaction = (array) $template;
                unset($transaction['id']);
                DB::table('trip_transactions')->insert(array_merge($this->scale($transaction, self::SCALED_TRANSACTION_COLUMNS, $factor), [
                    'trip_id' => $tripId,
                    'additional_charge' => $trip['additional_charge'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]));
            }
        });

        $this->command?->info('Seeded ' . self::TRIP_COUNT . ' completed trips with trip transactions.');
    }

    private function scale(array $row, array $columns, float $factor): array
    {
        foreach ($columns as $column) {
            if (isset($row[$column])) {
                $row[$column] = round((float) $row[$column] * $factor, 2);
            }
        }
        return $row;
    }

    private function createdAt(int $daysAgo): Carbon
    {
        return min(now()->subDays($daysAgo)->setTime(rand(8, 21), rand(0, 59), rand(0, 59)), now()->subMinutes(rand(5, 90)));
    }
}
