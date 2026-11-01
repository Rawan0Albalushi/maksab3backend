<?php

namespace App\Services\Order;

use App\Models\OrderTransaction;
use App\Services\BaseService;
use App\Traits\Order\OrderCommissionTrait;
use App\Traits\Order\OrderRefundTrait;
use App\Traits\Order\OrderTransactionsTrait;
use App\Traits\Report\ReportGeneratorTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Fluent;

class OrderTransactionService extends BaseService
{
    use OrderCommissionTrait;
    use OrderRefundTrait;
    use OrderTransactionsTrait;
    use ReportGeneratorTrait;

    private const EARNING_COLUMNS = [
        'id', 'order_id', 'delivery_man_id', 'dm_tips', 'original_delivery_charge', 'delivery_fee_comission', 'created_at',
    ];

    public function deliveryManEarningTotals(mixed $deliveryManId): array
    {
        $row = OrderTransaction::where('delivery_man_id', $deliveryManId)
            ->toBase()
            ->selectRaw('SUM(original_delivery_charge) as total_charge, SUM(dm_tips) as total_tips')
            ->selectRaw('SUM(CASE WHEN DATE(created_at) = ? THEN original_delivery_charge ELSE 0 END) as today_charge', [Carbon::now()->toDateString()])
            ->selectRaw('SUM(CASE WHEN DATE(created_at) = ? THEN dm_tips ELSE 0 END) as today_tips', [Carbon::now()->toDateString()])
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN original_delivery_charge ELSE 0 END) as week_charge', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->selectRaw('SUM(CASE WHEN created_at BETWEEN ? AND ? THEN dm_tips ELSE 0 END) as week_tips', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->selectRaw('SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN original_delivery_charge ELSE 0 END) as month_charge', [date('m'), date('Y')])
            ->selectRaw('SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN dm_tips ELSE 0 END) as month_tips', [date('m'), date('Y')])
            ->first();

        return array_map(fn ($value) => (float) $value, (array) $row);
    }

    public function earningSummaryForVendor(mixed $vendorId): array
    {
        return [
            'monthely_earning' => (float) OrderTransaction::whereMonth('created_at', date('m'))->NotRefunded()->where('vendor_id', $vendorId)->sum('store_amount'),
            'weekly_earning' => (float) OrderTransaction::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->NotRefunded()->where('vendor_id', $vendorId)->sum('store_amount'),
            'daily_earning' => (float) OrderTransaction::whereDate('created_at', now())->NotRefunded()->where('vendor_id', $vendorId)->sum('store_amount'),
        ];
    }

    public function deliveryManTotals(array $filters = []): array
    {
        $row = $this->deliveryManEarningQuery($filters)
            ->toBase()
            ->selectRaw('SUM(dm_tips) as tips, SUM(original_delivery_charge) as delivery_charge, SUM(delivery_fee_comission) as admin_commission')
            ->first();

        return [
            'total_dm_tips' => (float) $row->tips,
            'total_delivery_charge' => (float) $row->delivery_charge,
            'total_admin_commission' => (float) $row->admin_commission,
        ];
    }

    public function getDeliveryManEarningList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->deliveryManEarningQuery($filters)
            ->select(self::EARNING_COLUMNS)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getIncomeStatement(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return OrderTransaction::where('delivery_man_id', $filters['delivery_man_id'] ?? null)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function deliveryManEarningSummary(array $filters = []): array
    {
        $summary = $this->getDeliveryManEarningSummaryData(...$this->deliveryManReportArgs($filters));

        unset($summary['breakdown']['admin_commission']);

        return $summary;
    }

    public function deliveryManEarningTrend(array $filters = []): mixed
    {
        return $this->getDeliveryManEarningTrendData(...$this->deliveryManReportArgs($filters));
    }

    public function storeEarningSummary(array $filters = []): array
    {
        return $this->getStoreEarningSummaryData(...$this->storeReportArgs($filters));
    }

    public function storeEarningTrend(array $filters = []): mixed
    {
        return $this->getStoreEarningTrendData(...$this->storeReportArgs($filters));
    }

    public function storeEarningTransactions(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->getStoreEarningTransactions(...$this->storeTransactionArgs($filters, $paginate));
    }

    public function storeExpenseTransactions(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->getStoreExpenseTransactions(...$this->storeTransactionArgs($filters, $paginate));
    }

    public function storeSubscriptionTransactions(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->getStoreSubscriptionTransactions(...$this->storeTransactionArgs($filters, $paginate, withOrderTypes: false));
    }

    public function deliveryManEarningTransactions(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $perPage = $this->pageSize($paginate);
        $page = $this->pageNumber($paginate);

        $result = $this->getDeliveryManEarningTransactions(
            request: new Fluent(['search' => $filters['search'] ?? null, 'limit' => $perPage, 'offset' => $page]),
            delivery_man_id: $filters['delivery_man_id'] ?? null,
            filter: $filters['filter'] ?? 'all_time',
            from: $filters['from'] ?? null,
            to: $filters['to'] ?? null,
            order_types: $filters['order_types'] ?? null
        );

        return new LengthAwarePaginator($result['data'], $result['total_size'], $perPage, $page);
    }

    public function insertMany(array $rows): void
    {
        OrderTransaction::insert($rows);
    }

    public function query(): mixed
    {
        return OrderTransaction::query();
    }

    private function reportRequest(array $filters, array $paginate): object
    {
        return new class($filters, $this->pageNumber($paginate))
        {
            public function __construct(private array $filters, private int $page) {}

            public function __get(string $key): mixed
            {
                return $this->filters[$key] ?? null;
            }

            public function query(string $key, mixed $default = null): mixed
            {
                return $this->filters[$key] ?? $default;
            }

            public function input(string $key, mixed $default = null): mixed
            {
                return $key === 'page' ? $this->page : ($this->filters[$key] ?? $default);
            }
        };
    }

    private function reportWindow(array $filters, bool $withOrderTypes = true): array
    {
        $window = [
            'filter' => $filters['filter'] ?? 'all_time',
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
        ];

        return $withOrderTypes ? $window + ['order_types' => $filters['order_types'] ?? null] : $window;
    }

    private function deliveryManReportArgs(array $filters): array
    {
        return ['delivery_man_id' => $filters['delivery_man_id'] ?? null] + $this->reportWindow($filters);
    }

    private function storeReportArgs(array $filters): array
    {
        return ['store_id' => $filters['store_id'] ?? null] + $this->reportWindow($filters);
    }

    private function storeTransactionArgs(array $filters, array $paginate, bool $withOrderTypes = true): array
    {
        return [
            'request' => $this->reportRequest($filters, $paginate),
            'store_id' => $filters['store_id'] ?? null,
            'nopaginate' => false,
            'limit' => $this->pageSize($paginate),
            'offset' => $this->pageNumber($paginate),
        ] + $this->reportWindow($filters, $withOrderTypes);
    }

    private function deliveryManEarningQuery(array $filters): Builder
    {
        $type = $filters['type'] ?? 'all';

        return OrderTransaction::with(['order:id,payment_method', 'order.module'])
            ->where('delivery_man_id', $filters['delivery_man_id'] ?? null)
            ->where(fn ($query) => $query->where('original_delivery_charge', '>', 0)->orWhere('dm_tips', '>', 0))
            ->applyDateFilter($filters['date_range'] ?? null, $filters['start_date'] ?? null, $filters['end_date'] ?? null)
            ->when($type === 'delivery_fee', fn ($query) => $query->where('original_delivery_charge', '>', 0))
            ->when($type === 'delivery_tips', fn ($query) => $query->where('dm_tips', '>', 0));
    }
}
