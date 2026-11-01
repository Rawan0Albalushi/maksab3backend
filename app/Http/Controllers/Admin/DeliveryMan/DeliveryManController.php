<?php

namespace App\Http\Controllers\Admin\DeliveryMan;

use App\Exports\DeliveryManReferralEarningExport;
use App\Models\DeliverymanReferralHistory;
use Carbon\Carbon;
use Exception;
use App\Models\Order;
use Illuminate\View\View;
use App\Mail\DmSuspendMail;
use Illuminate\Http\Request;
use App\Mail\DmSelfRegistration;
use Illuminate\Http\JsonResponse;
use App\Models\DisbursementDetails;
use App\Services\DeliveryMan\DeliveryManService;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\RedirectResponse;
use App\Exports\DeliveryManListExport;
use App\Exports\DeliveryManReviewExport;
use App\Http\Controllers\BaseController;
use App\Exports\DeliveryManEarningExport;
use App\Exports\DisbursementHistoryExport;
use Illuminate\Database\Eloquent\Collection;
use App\Exports\SingleDeliveryManReviewExport;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Enums\ExportFileNames\Admin\DeliveryMan;
use App\Http\Requests\Admin\DeliveryManAddRequest;
use App\Http\Requests\Admin\DeliveryManUpdateRequest;
use App\Contracts\Repositories\ZoneRepositoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Contracts\Repositories\DmReviewRepositoryInterface;
use App\Contracts\Repositories\UserInfoRepositoryInterface;
use App\Contracts\Repositories\DeliveryManRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Enums\ViewPaths\Admin\DeliveryMan as DeliveryManViewPath;
use App\Contracts\Repositories\OrderTransactionRepositoryInterface;
use App\Contracts\Repositories\UserNotificationRepositoryInterface;
use App\Exports\DeliveryManWithdrawTransactionExport;
use App\Exports\SingleDeliveryManLoyaltyPointExport;
use App\Mail\WithdrawRequestMail;
use App\Models\DeliverymanLoyaltyPointHistory;
use App\Models\DeliveryManWallet;
use App\Models\WithdrawRequest;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use Illuminate\Support\Facades\Log;

class DeliveryManController extends BaseController
{
    public function __construct(
        protected DeliveryManRepositoryInterface $deliveryManRepo,
        protected ZoneRepositoryInterface $zoneRepo,
        protected TranslationRepositoryInterface $translationRepo,
        protected DmReviewRepositoryInterface $dmReviewRepo,
        protected UserInfoRepositoryInterface $userInfoRepo,
        protected ConversationRepositoryInterface $conversationRepo,
        protected MessageRepositoryInterface $messageRepo,
        protected DeliveryManService $deliveryManService,
        protected OrderTransactionRepositoryInterface $orderTransactionRepo,
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        return $this->getListView($request);
    }
    private function getListView(Request $request): View
    {

        $zoneId = $request->query('zone_id', 'all');
        $deliveryMen = $this->deliveryManRepo->getFilterWiseListWhere(
            zoneId: $zoneId,
            searchValue: $request['search'],
            filters: ['type' => 'zone_wise', 'application_status' => 'approved'],
            additionalFilter: $request['filter'],
            jobType: $request['job_type'],
            relations: ['storage', 'zone', 'wallet', 'rating', 'order_transaction'],
            dataLimit: config('default_pagination')
        );
        $zone = is_numeric($zoneId) ? $this->zoneRepo->getFirstWhere(params: ['id' => $zoneId]) : null;
        return view(DeliveryManViewPath::LIST [VIEW], compact('deliveryMen', 'zone'));
    }

    public function getAddView(): View
    {
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        return view(DeliveryManViewPath::ADD[VIEW], compact('language', 'defaultLang'));
    }

    public function getNewDeliveryManView(Request $request): View
    {
        $searchBy = $request->query('search_by');
        $zoneId = $request->query('zone_id', 'all');
        $deliveryMen = $this->deliveryManRepo->getZoneWiseListWhere(
            zoneId: $zoneId,
            searchValue: $searchBy,
            filters: ['type' => 'zone_wise', 'application_status' => 'pending'],
            relations: ['storage', 'zone'],
            dataLimit: config('default_pagination')
        );
        $zone = is_numeric($zoneId) ? $this->zoneRepo->getFirstWhere(params: ['id' => $zoneId]) : null;
        return view(DeliveryManViewPath::NEW [VIEW], compact('deliveryMen', 'zone', 'searchBy'));
    }

    public function getDeniedDeliveryManView(Request $request): View
    {
        $searchBy = $request->query('search_by');
        $zoneId = $request->query('zone_id', 'all');
        $deliveryMen = $this->deliveryManRepo->getZoneWiseListWhere(
            zoneId: $zoneId,
            searchValue: $searchBy,
            filters: ['type' => 'zone_wise', 'application_status' => 'denied'],
            relations: ['storage', 'zone'],
            dataLimit: config('default_pagination')
        );
        $zone = is_numeric($zoneId) ? $this->zoneRepo->getFirstWhere(params: ['id' => $zoneId]) : null;
        return view(DeliveryManViewPath::DENY[VIEW], compact('deliveryMen', 'zone', 'searchBy'));
    }

    public function getSearchList(Request $request): JsonResponse
    {
        $deliveryMen = $this->deliveryManRepo->getListWhere(
            searchValue: $request['search'],
            filters: ['type' => 'zone_wise', 'application_status' => 'approved'],
        );
        return response()->json([
            'view' => view(DeliveryManViewPath::SEARCH[VIEW], compact('deliveryMen'))->render(),
            'count' => $deliveryMen->count()
        ]);
    }

    public function getActiveSearchList(Request $request): JsonResponse
    {
        $deliveryMen = $this->deliveryManRepo->getFilterWiseListWhere(
            searchValue: $request['search'],
            filters: ['type' => 'zone_wise', 'status' => 1],
        );
        return response()->json([
            'dm' => $deliveryMen
        ]);
    }

    public function add(DeliveryManAddRequest $request): JsonResponse
    {
        $this->deliveryManRepo->add(data: $this->deliveryManService->getAddData($request->all()));
        Toastr::success(translate('Added successfully'));
        return response()->json([
            'message' => translate('Added successfully'),
            'redirect' => route('admin.users.delivery-man.list')
        ], 200);
    }

    public function getUpdateView(string|int $id): View
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        return view(DeliveryManViewPath::UPDATE[VIEW], compact('deliveryMan', 'language', 'defaultLang'));
    }

    public function update(DeliveryManUpdateRequest $request, $id): JsonResponse
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['id' => $id]);

        $deliveryMan = $this->deliveryManRepo->update(id: $id, data: $this->deliveryManService->getUpdateData($request->all(), deliveryMan: $deliveryMan));
        if ($deliveryMan->userinfo) {
            $this->userInfoRepo->update(id: $deliveryMan->userinfo->id, data: [
                'f_name' => $deliveryMan->f_name,
                'l_name' => $deliveryMan->l_name,
                'email' => $deliveryMan->email,
                'image' => $deliveryMan->image,
            ]);
        }

        Toastr::success(translate('Updated successfully'));
        return response()->json([
            'message' => translate('Updated successfully'),
            'redirect' => route('admin.users.delivery-man.list')
        ], 200);
    }

    public function delete(Request $request): RedirectResponse
    {
        $this->deliveryManRepo->delete(id: $request['id']);
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function updateStatus(Request $request, UserNotificationRepositoryInterface $notificationRepo): RedirectResponse
    {
        $deliveryMan = $this->deliveryManRepo->update(id: $request['id'], data: ['status' => $request['status']]);


        if ($request['status'] == 0) {
            $deliveryMan->auth_token = null;

            if (isset($deliveryMan->fcm_token) && SendNotification::channelEnabled('deliveryman', 'deliveryman_account_block', 'push_notification_status')) {
                $data = NotificationMessages::accountSuspended();
                SendNotification::pushToPanel($deliveryMan->fcm_token, $data);

                $notificationRepo->add([
                    'data' => json_encode($data),
                    'delivery_man_id' => $deliveryMan->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                Toastr::warning(translate('messages.Push notification failed'));
            }
        } else {
            if (SendNotification::channelEnabled('deliveryman', 'deliveryman_account_unblock', 'push_notification_status') && isset($deliveryMan->fcm_token)) {
                $data = NotificationMessages::accountActivated();
                SendNotification::pushToDeliveryMan($deliveryMan->id, $deliveryMan->fcm_token, $data);
            }
        }
        try {
            if (config('mail.status') && getWebConfigStatus('suspend_mail_status_dm') == '1' && $request['status'] == 0 && SendNotification::channelEnabled('deliveryman', 'deliveryman_account_block', 'mail_status')) {
                SendNotification::mail($deliveryMan?->getRawOriginal('email'), new DmSuspendMail('suspend', $deliveryMan));
            } elseif (config('mail.status') && getWebConfigStatus('unsuspend_mail_status_dm') == '1' && $request['status'] != 0 && SendNotification::channelEnabled('deliveryman', 'deliveryman_account_unblock', 'mail_status')) {
                SendNotification::mail($deliveryMan?->getRawOriginal('email'), new DmSuspendMail('unsuspend', $deliveryMan));
            }
        } catch (Exception) {
            Toastr::warning(translate('messages.Failed to send mail'));
        }

        Toastr::success(translate('messages.Deliveryman status updated'));
        return back();
    }
    public function updateEarning(Request $request): RedirectResponse
    {
        $this->deliveryManRepo->update(id: $request['id'], data: ['earning' => $request['status']]);
        Toastr::success(translate('messages.Deliveryman type updated'));
        return back();
    }

    public function exportList(Request $request): BinaryFileResponse
    {



        $zoneId = $request->query('zone_id', 'all');
        $deliveryMen = $this->deliveryManRepo->getFilterWiseListWhere(
            zoneId: $zoneId,
            searchValue: $request['search'],
            filters: ['type' => 'zone_wise', 'application_status' => 'approved'],
            additionalFilter: $request['filter'],
            jobType: $request['job_type'],
            relations: ['storage', 'zone', 'wallet', 'vehicle'],
            dataLimit: 'all'
        );


        $zone = is_numeric($zoneId) ? $this->zoneRepo->getFirstWhere(params: ['id' => $zoneId]) : null;

        $data = [
            'delivery_men' => $deliveryMen,
            'search' => $request->search ?? null,
            'zone' => is_numeric($zoneId) ? $zone['name'] : null,
        ];

        if ($request['type'] == 'excel') {
            return Excel::download(new DeliveryManListExport($data), DeliveryMan::EXPORT_XLSX);
        }
        return Excel::download(new DeliveryManListExport($data), DeliveryMan::EXPORT_CSV);
    }

    public function getReviewListView(Request $request): View
    {
        $filter = $request['deliveryman_id'] && is_numeric($request['deliveryman_id']) ? ['delivery_man_id' => $request['deliveryman_id']] : [];
        $orderBy = $request['order_by'] && isset($request['order_by']) && in_array($request['order_by'], ['asc', 'desc']) ? ['col' => 'rating', 'type' => $request['order_by']] : [];
        $reviews = $this->dmReviewRepo->getListWhereOrder(
            searchValue: $request['search'],
            filters: $filter,
            relations: ['delivery_man.storage', 'customer.storage', 'order', 'storage'],
            dataLimit: config('default_pagination'),
            orderBy: $orderBy
        );

        // Built here rather than in the view: reviews-list.blade.php used to import
        // App\Models\DeliveryMan and run the dropdown query inline.
        $deliveryMen = $this->deliveryManRepo->getApprovedFilterOptions();

        return view(DeliveryManViewPath::REVIEW_LIST[VIEW], compact('reviews', 'deliveryMen'));
    }

    public function getReviewSearchList(Request $request): JsonResponse
    {
        $reviews = $this->dmReviewRepo->getListWhere(searchValue: $request['search'], relations: ['delivery_man.storage', 'customer.storage', 'storage']);

        return response()->json([
            'view' => view(DeliveryManViewPath::REVIEW_SEARCH_LIST[VIEW], compact('reviews'))->render(),
            'count' => $reviews->count()
        ]);
    }

    public function getAllReviewExportList(Request $request): BinaryFileResponse
    {
        $filter = $request['deliveryman_id'] && is_numeric($request['deliveryman_id']) ? ['delivery_man_id' => $request['deliveryman_id']] : [];
        $orderBy = $request['order_by'] && isset($request['order_by']) && in_array($request['order_by'], ['asc', 'desc']) ? ['col' => 'rating', 'type' => $request['order_by']] : [];
        $reviews = $this->dmReviewRepo->getListWhereOrder(
            searchValue: $request['search'],
            filters: $filter,
            relations: ['delivery_man.storage', 'customer.storage', 'order.store'],
            dataLimit: "all",
            orderBy: $orderBy
        );


        if($request['order_by'] == 'desc'){
            $orderBy=translate('messages.Top ratings');
        } elseif($request['order_by'] == 'asc'){
            $orderBy=translate('messages.Low ratings');
        } else {
            $orderBy=translate('messages.Latest ratings');
        }

        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['type' => 'zone_wise', 'id' => $request['deliveryman_id']]);
        $data = [
            'delivery_men' => is_numeric($request['deliveryman_id']) ?  $deliveryMan?->full_name : translate('All'),
            'order_by' => $orderBy?? null,
            'reviews' => $reviews,
            'search' => $request->search ?? null,
        ];

        if ($request['type'] == 'excel') {
            return Excel::download(new DeliveryManReviewExport($data), DeliveryMan::REVIEW_EXPORT_XLSX);
        }
        return Excel::download(new DeliveryManReviewExport($data), DeliveryMan::EXPORT_CSV);

    }

    public function updateReviewStatus(Request $request): RedirectResponse
    {
        $this->dmReviewRepo->update(id: $request['id'], data: ['status' => $request['status']]);
        Toastr::success(translate('messages.Review visibility updated'));
        return back();
    }

    public function getReviewExportList(Request $request): BinaryFileResponse
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['type' => 'zone_wise', 'id' => $request['id']], relations: ['reviews']);

        if (!$deliveryMan) {
            abort(404);
        }

        $reviews = $this->dmReviewRepo->getListWhere(searchValue: $request['search'], filters: ['delivery_man_id' => $request['id']]);

        $data = [
            'dm' => $deliveryMan,
            'reviews' => $reviews,
            'search' => $request->search ?? null,
        ];

        if ($request['type'] == 'excel') {
            return Excel::download(new SingleDeliveryManReviewExport($data), DeliveryMan::REVIEW_EXPORT_XLSX);
        }
        return Excel::download(new SingleDeliveryManReviewExport($data), DeliveryMan::EXPORT_CSV);

    }
    public function getLoyaltyPointExportList(Request $request): BinaryFileResponse
    {
        $date = $request->query('dates');

        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['type' => 'zone_wise', 'id' => $request['id']], relations: ['reviews']);

        if (!$deliveryMan) {
            abort(404);
        }

        $loyaltyPointHistory = $this->getLoyaltyHistoryList($request, $deliveryMan, $date)->get();

        $data = [
            'dm' => $deliveryMan,
            'histories' => $loyaltyPointHistory,
            'search' => $request->search ?? null,
        ];

        if ($request['type'] == 'excel') {
            return Excel::download(new SingleDeliveryManLoyaltyPointExport($data), DeliveryMan::LOYALTY_POINT_EXPORT_XLSX);
        }
        return Excel::download(new SingleDeliveryManLoyaltyPointExport($data), DeliveryMan::LOYALTY_POINT_EXPORT_CSV);

    }
    public function getReferralEarnExportList(Request $request): BinaryFileResponse
    {
        $date = $request->query('dates');

        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['type' => 'zone_wise', 'id' => $request['id']], relations: ['reviews']);

        if (!$deliveryMan) {
            abort(404);
        }

        $referralEarnHistory = $this->getReferralHistoryList($request, $deliveryMan, $date)->get();

        $data = [
            'dm' => $deliveryMan,
            'histories' => $referralEarnHistory,
            'search' => $request->search ?? null,
        ];

        if ($request['type'] == 'excel') {
            return Excel::download(new DeliveryManReferralEarningExport($data), DeliveryMan::REFERRAL_EARN_EXPORT_XLSX);
        }
        return Excel::download(new DeliveryManReferralEarningExport($data), DeliveryMan::REFERRAL_EARN_EXPORT_CSV);

    }

    public function getPreview(Request $request, int|string $id, string $tab = 'info'): View
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['type' => 'zone_wise', 'id' => $id], relations: ['storage', 'reviews', 'vehicle.storage', 'zone', 'wallet', 'rating', 'receivedReviews', 'order_transaction']);

        if (! $deliveryMan) {
            abort(404);
        }

        if ($tab == 'info') {
            $reviews = $this->dmReviewRepo->getListWhere(searchValue: $request['search'], filters: ['delivery_man_id' => $id], relations: ['customer.storage'], dataLimit: config('default_pagination'));
            return view(DeliveryManViewPath::INFO[VIEW], compact('deliveryMan', 'reviews'));
        } else if ($tab == 'transaction') {
            $date = $request->query('dates');
            if ($request->has('date_range') && $request->date_range != 'custom') {
                if ($request->date_range == 'this_week') {
                    $date = now()->startOfWeek()->format('Y-m-d') . ' - ' . now()->endOfWeek()->format('Y-m-d');
                } elseif ($request->date_range == 'this_month') {
                    $date = now()->startOfMonth()->format('Y-m-d') . ' - ' . now()->endOfMonth()->format('Y-m-d');
                } elseif ($request->date_range == 'this_year') {
                    $date = now()->startOfYear()->format('Y-m-d') . ' - ' . now()->endOfYear()->format('Y-m-d');
                } elseif ($request->date_range == 'all_time') {
                    $date = null;
                }
            }
            $digital_transaction = $this->orderTransactionRepo->getListWhere(searchValue: $request['search'], filters: ['delivery_man_id' => $id], relations: ['order'], dataLimit: config('default_pagination'), orderBy: ['col' => 'created_at', 'type' => 'desc'], date: $date);
            return view(DeliveryManViewPath::TRANSACTION[VIEW], compact('deliveryMan', 'date', 'digital_transaction'));
        } else if ($tab == 'order_list') {
            $order_lists = Order::with(['customer', 'transaction'])->where('delivery_man_id', $deliveryMan->id)->paginate(config('default_pagination'));
            $orderStats = Order::where('delivery_man_id', $deliveryMan->id)
                ->selectRaw('COUNT(*) as total_orders')
                ->selectRaw("SUM(CASE WHEN order_status IN ('handover', 'picked_up') THEN order_amount ELSE 0 END) as ongoing_amount")
                ->selectRaw("SUM(CASE WHEN order_status = 'delivered' THEN order_amount ELSE 0 END) as delivered_amount")
                ->selectRaw("SUM(CASE WHEN order_status = 'canceled' THEN 1 ELSE 0 END) as canceled_orders")
                ->first();

            return view(DeliveryManViewPath::ORDER_LIST[VIEW], compact('deliveryMan', 'order_lists', 'orderStats'));
        } else if ($tab == 'loyalty-point') {
            $date = $request->query('dates');

            $points = DeliverymanLoyaltyPointHistory::where('delivery_man_id', $deliveryMan->id)
                ->selectRaw('
                    SUM(CASE WHEN point_conversion_type = "credit" THEN point ELSE 0 END) AS total_loyalty_point,
                    SUM(CASE WHEN point_conversion_type = "debit" THEN point ELSE 0 END) AS total_converted_loyalty_point
                ')
                ->first();


            $total_loyalty_point = $points->total_loyalty_point ?? 0;
            $total_converted_loyalty_point = $points->total_converted_loyalty_point ?? 0;
            $loyalty_points = $this->getLoyaltyHistoryList($request, $deliveryMan, $date)->paginate(config('default_pagination'));

            return view('admin-views.delivery-man.view.loyalty-point', compact('deliveryMan', 'date', 'loyalty_points', 'total_loyalty_point', 'total_converted_loyalty_point'));
        } else if ($tab == 'referal-earn') {
            $date = $request->query('dates');

            $stats = DeliverymanReferralHistory::where('delivery_man_id', $deliveryMan->id)
                ->selectRaw("
                    SUM(CASE WHEN refer_type = 'referral' THEN 1 ELSE 0 END) as total_referred,
                    COALESCE(SUM(amount), 0) as total_referral_earning
                ")
                ->first();

            $totalReferred = max($stats?->total_referred, 0) ?? 0;
            $totalReferralEarning = max($stats?->total_referral_earning, 0) ?? 0;
            $referralEarnings = $this->getReferralHistoryList($request, $deliveryMan, $date)->paginate(config('default_pagination'));

            return view('admin-views.delivery-man.view.referral-earn', compact('deliveryMan', 'date', 'totalReferred', 'totalReferralEarning', 'referralEarnings'));
        } else if ($tab == 'disbursement') {
            $key = explode(' ', $request['search'] ?? '');
            $disbursements = DisbursementDetails::with(['delivery_man', 'withdraw_method'])->where('delivery_man_id', $deliveryMan->id)
                ->when($request['search'], function ($q) use ($key) {
                    $q->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('disbursement_id', 'like', "%{$value}%")
                                ->orWhere('status', 'like', "%{$value}%");
                        }
                    });
                })
                ->latest()->paginate(config('default_pagination'));
            return view('admin-views.delivery-man.view.disbursement', compact('deliveryMan', 'disbursements'));
        }

        $user = $this->userInfoRepo->getFirstWhere(params: ['deliveryman_id' => $id]);
        if ($user) {
            $conversations = $this->conversationRepo->getListWithScope(relations: ['sender', 'receiver', 'last_message'], dataLimit: 8, scopes: ['WhereUser' => [$user['id']]], conversation_with: $request?->conversation_with ?? 'customer');
        } else {
            $conversations = [];
        }

        return view(DeliveryManViewPath::CONVERSATION[VIEW], compact('conversations', 'deliveryMan'));

    }
    private function getLoyaltyHistoryList($request, $deliveryMan, $date)
    {
        $key = explode(' ', $request['search'] ?? '');

        $start = null;
        $end = null;
        if (strpos($date, ' - ') !== false) {
            $dates = explode(' - ', $date);
            $start = Carbon::parse($dates[0]);
            $end = Carbon::parse($dates[1]);
        }
        $loyalty_points = DeliverymanLoyaltyPointHistory::where('delivery_man_id', $deliveryMan->id)
            ->when($request['search'], function ($q) use ($key) {
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('transaction_id', 'like', "%{$value}%")
                            ->orWhere('transaction_type', 'like', "%{$value}%");
                    }
                });
            })
            ->when($request->point_conversion_type, function ($q) use ($request) {
                return $q->where('point_conversion_type', $request->point_conversion_type);
            })
            ->applyDateFilter($request->date_range, $start, $end)
            ->latest();

        return $loyalty_points;
    }
    private function getReferralHistoryList($request, $deliveryMan, $date)
    {
        $key = explode(' ', $request['search'] ?? '');

        $start = null;
        $end = null;
        if (strpos($date, ' - ') !== false) {
            $dates = explode(' - ', $date);
            $start = Carbon::parse($dates[0]);
            $end = Carbon::parse($dates[1]);
        }
        $loyalty_points = DeliverymanReferralHistory::where('delivery_man_id', $deliveryMan->id)
            ->when($request['search'], function ($q) use ($key) {
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('transaction_id', 'like', "%{$value}%")
                            ->orWhere('refer_type', 'like', "%{$value}%");
                    }
                });
            })
            ->applyDateFilter($request->date_range, $start, $end)
            ->latest();

        return $loyalty_points;
    }

    public function getEarningListExport(Request $request, OrderTransactionRepositoryInterface $orderTransactionRepo): BinaryFileResponse
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['type' => 'zone_wise', 'id' => $request['id']], relations: ['reviews']);

        if (!$deliveryMan) {
            abort(404);
        }

        $earnings = $orderTransactionRepo->getDmEarningList(request: $request);

        $data = [
            'dm' => $deliveryMan,
            'earnings' => $earnings,
            'date' => $request->date ?? null,
        ];

        if ($request['type'] == 'excel') {
            return Excel::download(new DeliveryManEarningExport($data), 'DeliveryManEarnings.xlsx');
        }
        return Excel::download(new DeliveryManEarningExport($data), 'DeliveryManEarnings.csv');

    }

    public function getDropdownList(Request $request): JsonResponse
    {
        $data = $this->deliveryManRepo->getDropdownList(request: $request);
        return response()->json($data);
    }

    public function getAccountData(Request $request): JsonResponse
    {
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['id' => $request['id']], relations: ['wallet']);
        $wallet = $deliveryMan?->wallet;
        $cashInHand = 0;
        $balance = 0;

        if ($wallet) {
            $cashInHand = $wallet->collected_cash;
            $balance = round($wallet->total_earning - $wallet->total_withdrawn - $wallet->pending_withdraw, config('round_up_to_digit'));
        }
        return response()->json(['cash_in_hand' => $cashInHand, 'earning_balance' => $balance]);

    }

    public function getConversationList(Request $request): JsonResponse
    {
        $user = $this->userInfoRepo->getFirstWhere(params: ['deliveryman_id' => $request['user_id']]);
        $deliveryMan = $this->deliveryManRepo->getFirstWhere(params: ['id' => $request['user_id']]);

        if (! $deliveryMan) {
            return response()->json(['errors' => [['code' => 'deliveryman', 'message' => translate('No data found')]]], 404);
        }

        if ($user) {
            $conversations = $this->conversationRepo->getDmConversationList(request: $request, dataLimit: 8, user: $user->id);
        } else {
            $conversations = [];
        }
        $view = view(DeliveryManViewPath::CONVERSATION_LIST[VIEW], compact('conversations', 'deliveryMan'))->render();

        return response()->json(['html' => $view]);

    }

    public function getConversationView($conversation_id, $user_id): JsonResponse
    {
        $conversations = $this->messageRepo->getListWhere(filters: ['conversation_id' => $conversation_id]);
        $conversation = $this->conversationRepo->getFirstWhere(params: ['id' => $conversation_id], relations: ['receiver', 'sender']);
        $user = $this->userInfoRepo->getFirstWhere(params: ['id' => $user_id]);

        if (! $conversation || ! $user) {
            return response()->json(['errors' => [['code' => 'conversation', 'message' => translate('No data found')]]], 404);
        }

        $receiver = $conversation['receiver'];

        return response()->json([
            'view' => view(DeliveryManViewPath::CONVERSATIONS[VIEW], compact('conversations', 'user', 'receiver'))->render()
        ]);
    }

    public function updateApplication(Request $request): RedirectResponse
    {
        $deliveryMan = $this->deliveryManRepo->update(id: $request['id'], data: ['application_status' => $request['status']]);
        if ($request['status'] == 'approved')
            $this->deliveryManRepo->update(id: $request['id'], data: ['status' => 1]);
        try {
            if ($request['status'] == 'approved') {

                $mail_status = getWebConfigStatus('approve_mail_status_dm');
                if (config('mail.status') && $mail_status == '1' && SendNotification::channelEnabled('deliveryman', 'deliveryman_registration_approval', 'mail_status')) {
                    SendNotification::mail($deliveryMan?->getRawOriginal('email'), new DmSelfRegistration('approved', $deliveryMan));
                }
            } else {

                $mail_status = getWebConfigStatus('deny_mail_status_dm');
                if (config('mail.status') && $mail_status == '1' && SendNotification::channelEnabled('deliveryman', 'deliveryman_registration_deny', 'mail_status')) {
                    SendNotification::mail($deliveryMan?->getRawOriginal('email'), new DmSelfRegistration('denied', $deliveryMan));
                }
            }
        } catch (Exception $ex) {
            Log::error('delivery_man.delivery_man_controller.update_application_failed', [
                'error' => $ex->getMessage(),
                'file' => $ex->getFile().':'.$ex->getLine(),
            ]);
        }
        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function disbursement_export(Request $request, $id, $type)
    {
        $key = explode(' ', $request['search'] ?? '');

        $dm = \App\Models\DeliveryMan::find($id);
        $disbursements = DisbursementDetails::with(['delivery_man', 'withdraw_method'])->where('delivery_man_id', $dm->id)
            ->when($request['search'], function ($q) use ($key) {
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('disbursement_id', 'like', "%{$value}%")
                            ->orWhere('status', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()->get();
        $data = [
            'disbursements' => $disbursements,
            'search' => $request->search ?? null,
            'delivery_man' => $dm->f_name . ' ' . $dm->l_name,
            'type' => 'dm',
        ];

        if ($request->type == 'excel') {
            return Excel::download(new DisbursementHistoryExport($data), 'Disbursementlist.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new DisbursementHistoryExport($data), 'Disbursementlist.csv');
        }
    }

    public function status_filter(Request $request)
    {
        session()->put('withdraw_status_filter', $request['withdraw_status_filter']);
        return response()->json(session('withdraw_status_filter'));
    }


    public function withdraw_list(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];
        // The status used to live in one session key, `withdraw_status_filter`,
        // shared by all three withdraw queues — so filtering the vendor list to
        // "denied" silently filtered the delivery man and rider lists too, and
        // the URL never said which view you were looking at. It is a query
        // parameter now, like every other list screen.
        $status = $request->query('status', 'all');
        $approved_map = ['pending' => 0, 'approved' => 1, 'denied' => 2];

        $withdraw_req = WithdrawRequest::with(['deliveryman.storage', 'deliveryman.wallet', 'method', 'disbursementMethod'])
            ->when(isset($approved_map[$status]), function ($query) use ($approved_map, $status) {
                return $query->where('approved', $approved_map[$status]);
            })
            ->when(isset($request['search']), function ($query) use ($key) {
                return $query->whereHas('deliveryman', function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->where(function ($query) use ($value) {
                            $query->where('f_name', 'like', "%{$value}%")
                                ->orWhere('l_name', 'like', "%{$value}%");
                        });
                    }
                });
            })
            ->where('delivery_man_id', '!=', null)
            ->latest()
            ->paginate(config('default_pagination'))
            ->appends($request->except('page'));

        return view('admin-views.wallet.dm-withdraw', [
            'withdraw_req' => $withdraw_req,
            'status' => $status,
            'summary' => $this->withdrawSummary(),
        ]);
    }

    /**
     * Request counts and money per `approved` value, for the summary strip and
     * the tab counters. One grouped query rather than a count() per tile, and
     * it deliberately ignores the status tab and the search box — those are
     * what the table itself is showing.
     *
     * The base filter matches the list above — every request that names a delivery man, so the tiles always
     * agree with what is on screen.
     */
    private function withdrawSummary()
    {
        return WithdrawRequest::selectRaw('approved, COUNT(*) as requests, SUM(amount) as amount')
            ->where('delivery_man_id', '!=', null)
            ->groupBy('approved')
            ->get()
            ->keyBy('approved');
    }
    public function withdraw_export(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];
        // The status used to live in one session key, `withdraw_status_filter`,
        // shared by all three withdraw queues — so filtering the vendor list to
        // "denied" silently filtered the delivery man and rider lists too, and
        // the URL never said which view you were looking at. It is a query
        // parameter now, like every other list screen.
        $status = $request->query('status', 'all');
        $approved_map = ['pending' => 0, 'approved' => 1, 'denied' => 2];

        $withdraw_req = WithdrawRequest::with(['deliveryman.storage'])
            ->when(isset($approved_map[$status]), function ($query) use ($approved_map, $status) {
                return $query->where('approved', $approved_map[$status]);
            })
            ->when(isset($request['search']), function ($query) use ($key) {
                return $query->whereHas('deliveryman', function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->where('f_name', 'like', "%{$value}%")
                            ->orWhere('l_name', 'like', "%{$value}%");
                    }
                });
            })
            ->where('delivery_man_id', '!=', null)
            ->latest()->get();

        $data = [
            'withdraw_requests' => $withdraw_req,
            'search' => $request->search ?? null,
            'request_status' => $status === 'all' ? null : $status,

        ];

        if ($request->type == 'excel') {
            return Excel::download(new DeliveryManWithdrawTransactionExport($data), 'WithdrawRequests.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new DeliveryManWithdrawTransactionExport($data), 'WithdrawRequests.csv');
        }
    }

    public function getWithdrawDetails(Request $request)
    {
        $withdraw = WithdrawRequest::with(['deliveryman.storage', 'deliveryman.wallet', 'method', 'disbursementMethod'])->where(['id' => $request->withdraw_id])->first();

        if (! $withdraw) {
            return response()->json(['errors' => [['code' => 'withdraw', 'message' => translate('No data found')]]], 404);
        }

        return response()->json([
            'view' => view('admin-views.wallet.dm-partials._side_view', compact('withdraw'))->render(),
        ]);
    }

    public function withdraw_search(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        $withdraw_req = WithdrawRequest::whereNotNull('delivery_man_id')
            ->whereHas('deliveryman', function ($query) use ($key) {
                foreach ($key as $value) {
                    $query->where('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%");
                }
            })->get();
        $total = $withdraw_req->count();
        return response()->json([
            'view' => view('admin-views.wallet.dm-partials._table', compact('withdraw_req'))->render(),
            'total' => $total
        ]);
    }

    public function withdraw_view($withdraw_id, $seller_id)
    {
        $wr = WithdrawRequest::with(['vendor.stores', 'vendor.wallet', 'method'])->where(['id' => $withdraw_id])->first();

        if (! $wr) {
            Toastr::warning(translate('No data found'));

            return back();
        }

        $vendor = $wr->vendor?->stores?->first()?->module_type == 'rental' ? 'Provider' : 'store';

        return view('admin-views.wallet.withdraw-view', compact('wr', 'vendor'));
    }

    public function withdrawStatus(Request $request, $id)
    {
        $request->validate([
            'note' => 'max:200',
        ]);
        $withdraw = WithdrawRequest::findOrFail($id);
        $withdraw->approved = $request->approved;
        $withdraw->transaction_note = $request['note'];

        $wallet = DeliveryManWallet::where('delivery_man_id', $withdraw->delivery_man_id)->first();
        if ((string) $wallet->total_earning < (string) ($wallet->total_withdrawn + $wallet->pending_withdraw)) {
            Toastr::error(translate('messages.Blalnce mismatched total earning is too low'));
            return redirect()->route('admin.transactions.delivery-man.withdraw_list');
        }

        $delivery_man = $withdraw->deliveryman;

        if ($request->approved == 1) {
            $wallet->increment('total_withdrawn', $withdraw->amount);
            $wallet->decrement('pending_withdraw', $withdraw->amount);
            $withdraw->save();
            $push_notification_status = SendNotification::channelEnabled('deliveryman', 'deliveryman_withdraw_approve', 'push_notification_status', $delivery_man->id);
            $push_notification_status = $push_notification_status == 1 && $delivery_man?->fcm_token && $delivery_man?->fcm_token != '@' ? 1 : 0;
            $mail_status = (SendNotification::canSendMail('withdraw_approve_mail_status_dm', 'deliveryman', 'deliveryman_withdraw_approve', $delivery_man->id));
            $this->sentWithdrawRequestNotification($withdraw, $delivery_man->fcm_token, $delivery_man->email, 'approved', $push_notification_status, $mail_status);
            Toastr::success(translate('messages.Deliveryman withdraw request approved'));
            return redirect()->route('admin.transactions.delivery-man.withdraw_list');
        } else if ($request->approved == 2) {
            $wallet->decrement('pending_withdraw', $withdraw->amount);
            $withdraw->save();
            $push_notification_status = SendNotification::channelEnabled('deliveryman', 'deliveryman_withdraw_rejaction', 'push_notification_status', $delivery_man->id);
            $push_notification_status = $push_notification_status == 1 && $delivery_man?->fcm_token ? 1 : 0;
            $mail_status = (SendNotification::canSendMail('withdraw_deny_mail_status_dm', 'deliveryman', 'deliveryman_withdraw_rejaction', $delivery_man->id));
            $this->sentWithdrawRequestNotification($withdraw, $delivery_man->fcm_token, $delivery_man->email, 'denied', $push_notification_status, $mail_status);
            Toastr::info(translate('messages.Deliveryman withdraw request denied'));
            return redirect()->route('admin.transactions.delivery-man.withdraw_list');
        } else {
            Toastr::error(translate('No data found'));
            return back();
        }
    }

    private function sentWithdrawRequestNotification($withdraw, $token, $email, $type = 'approved', $push_notification_status = '1', $mail_status = '1')
    {
        try {
            if ($push_notification_status == 1) {
                $data = NotificationMessages::withdrawRequestProcessed($type);
                SendNotification::pushToDeliveryMan($withdraw->delivery_man_id, $token, $data);
            }

            if ($mail_status == 1) {
                SendNotification::mail($email, new WithdrawRequestMail($type, $withdraw, 'dm'));
            }
        } catch (\Exception $e) {
            Log::error('delivery_man.delivery_man_controller.sent_withdraw_request_notification_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }
        return true;
    }

}
