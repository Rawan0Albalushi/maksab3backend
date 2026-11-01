<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Services\System\BusinessSettingService;
use App\Exports\DisbursementExport;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\DeliveryMan;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\ProvideDMEarning;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;

class DeliveryManDisbursementController extends Controller
{
    public function list(Request $request)
    {
        $status = $request->status ?? 'all';
        $key = $request->filled('search') ? explode(' ', $request['search']) : null;

        $disbursements = Disbursement::where('created_for', 'delivery_man')
            ->when($status != 'all', function ($q) use ($status) {
                return $q->where('status', $status);
            })
            ->when($key, function ($q) use ($key) {
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('title', 'like', "%{$value}%");
                    }
                });
            })
            ->withCount([
                'details',
                // "Settled" on the card is everything no longer pending — a
                // canceled payout is resolved too, it just was not paid.
                'details as pending_details_count' => function ($q) {
                    $q->where('status', 'pending');
                },
            ])
            ->latest()
            ->paginate(config('default_pagination'))
            ->appends($request->except('page'));

        return view('admin-views.dm-disbursement.index', [
            'disbursements' => $disbursements,
            'status' => $status,
            'batch_summary' => $this->batchSummary(),
            'payout_summary' => $this->payoutSummary(),
        ]);
    }

    /**
     * Batch counts per status, for the tab counters. One grouped query rather
     * than a count() per tab.
     */
    private function batchSummary()
    {
        return Disbursement::where('created_for', 'delivery_man')
            ->selectRaw('status, COUNT(*) as batches')
            ->groupBy('status')
            ->pluck('batches', 'status');
    }

    /**
     * Money is summed per payout, not per batch: a partially completed batch
     * has part of its `total_amount` already paid, so that column cannot
     * answer "how much is still owed".
     *
     * Pass a batch id for one run's breakdown, or nothing for the ledger.
     */
    private function payoutSummary($disbursement_id = null)
    {
        return DisbursementDetails::query()
            ->when($disbursement_id, function ($q) use ($disbursement_id) {
                $q->where('disbursement_id', $disbursement_id);
            }, function ($q) {
                $q->join('disbursements', 'disbursements.id', '=', 'disbursement_details.disbursement_id')
                    ->where('disbursements.created_for', 'delivery_man');
            })
            ->selectRaw('disbursement_details.status as status, COUNT(*) as payouts, SUM(disbursement_details.disbursement_amount) as amount')
            ->groupBy('disbursement_details.status')
            ->get()
            ->keyBy('status');
    }

    public function view(Request $request,$id)
    {
        $key = explode(' ', $request['search'] ?? '');
        $delivery_man_id = $request->query('delivery_man_id', 'all');
        $payment_method_id = $request->query('payment_method_id', 'all');
        $disbursement = Disbursement::where('created_for', 'delivery_man')->findOrFail($id);
        $disbursements=DisbursementDetails::with('delivery_man.storage','delivery_man.zone','withdraw_method')->where(['disbursement_id'=>$id])
            ->when($request['search'] , function($q) use($key){
                $q->whereHas('delivery_man', function ($q) use($key){
                    $q->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('f_name', 'like', "%{$value}%")
                                ->orWhere('l_name', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%")
                                ->orWhere('phone', 'like', "%{$value}%");
                        }
                    });
                });
            })
            ->when((isset($delivery_man_id) && is_numeric($delivery_man_id)), function ($query) use ($delivery_man_id){
                $query->where('delivery_man_id', $delivery_man_id);
            })
            ->when((isset($payment_method_id) && is_numeric($payment_method_id)), function ($query) use ($payment_method_id){
                $query->whereHas('withdraw_method', function ($q) use($payment_method_id){
                    return $q->where('withdrawal_method_id', $payment_method_id);
                });
            })
            ->latest();
        $dm_ids = json_encode($disbursements->pluck('delivery_man_id')->toArray());
        $disbursement_delivery_mans = $disbursements->paginate(config('default_pagination'))
            ->appends($request->except('page'));
        $delivery_men = DeliveryMan::where('type', 'zone_wise')->where('earning', 1)->active()->get(['id', 'f_name', 'l_name']);
        $payout_summary = $this->payoutSummary($id);

        return view('admin-views.dm-disbursement.view', compact('disbursement','disbursement_delivery_mans','delivery_man_id','dm_ids','payment_method_id','delivery_men','payout_summary'));
    }
    public function export(Request $request,$id,$type='excel')
    {
        $key = explode(' ', $request['search'] ?? '');
        $delivery_man_id = $request->query('delivery_man_id', 'all');
        $payment_method_id = $request->query('payment_method_id', 'all');
        $disbursement = Disbursement::where('created_for', 'delivery_man')->findOrFail($id);
        $disbursements=DisbursementDetails::with(['delivery_man','withdraw_method'])->where(['disbursement_id'=>$id])
            ->when($request['search'] , function($q) use($key){
                $q->whereHas('delivery_man', function ($q) use($key){
                    $q->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('f_name', 'like', "%{$value}%")
                                ->orWhere('l_name', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%")
                                ->orWhere('phone', 'like', "%{$value}%");
                        }
                    });
                });
            })
            ->when((isset($delivery_man_id) && is_numeric($delivery_man_id)), function ($query) use ($delivery_man_id){
                $query->where('delivery_man_id', $delivery_man_id);
            })
            ->when((isset($payment_method_id) && is_numeric($payment_method_id)), function ($query) use ($payment_method_id){
                $query->whereHas('withdraw_method', function ($q) use($payment_method_id){
                    return $q->where('withdrawal_method_id', $payment_method_id);
                });
            })
            ->latest()->get();
        $data=[
            'type'=>'dm',
            'disbursement' =>$disbursement,
            'disbursements' =>$disbursements,
        ];
        if($type == 'pdf'){
            $logoFullUrl = Helpers::get_full_url(
                'business',
                Helpers::get_business_settings('logo', false),
                app(BusinessSettingService::class)->findStorageDisk('logo')
            );
            $mpdf_view = View::make('admin-views.dm-disbursement.pdf', compact('disbursement', 'disbursements', 'logoFullUrl'));
            Helpers::gen_mpdf(view: $mpdf_view,file_prefix: 'Disbursement',file_postfix: $id);
        }elseif($type == 'csv'){
            return Excel::download(new DisbursementExport($data), 'Disbursement.csv');
        }
        return Excel::download(new DisbursementExport($data), 'Disbursement.xlsx');
    }

    public function status(Request $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $disbursements = DisbursementDetails::with(['delivery_man.wallet', 'withdraw_method'])
                    ->where(['disbursement_id' => $request->disbursement_id])
                    ->whereIn('delivery_man_id', $request->delivery_man_ids)
                    ->lockForUpdate()
                    ->get();

                foreach ($disbursements as $disbursement) {
                    $this->syncDeliveryManDisbursementStatus($disbursement, $request->status);
                }

                self::check_status($request->disbursement_id);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => translate('messages.Status updated')
        ]);
    }

    public function statusById($id, $status)
    {
        try {
            DB::transaction(function () use ($id, $status) {
                $disbursement = DisbursementDetails::with(['delivery_man.wallet', 'withdraw_method'])
                    ->lockForUpdate()
                    ->findOrFail($id);

                $this->syncDeliveryManDisbursementStatus($disbursement, $status);
                self::check_status($disbursement->disbursement_id);
            });
            Toastr::success(translate('messages.Status updated'));
            return back();
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());
            return back();
        }
    }

    private function syncDeliveryManDisbursementStatus(DisbursementDetails $disbursement, string $status): void
    {
        $deliveryMan = $disbursement->delivery_man;
        $wallet = $deliveryMan?->wallet;

        if (!$deliveryMan || !$wallet) {
            throw new \RuntimeException(translate('messages.wallet_not_found'));
        }

        $amount = (float) $disbursement->disbursement_amount;
        $currentStatus = $disbursement->status;
        $totalEarning = (float) $wallet->total_earning;
        $totalWithdrawn = (float) $wallet->total_withdrawn;
        $pendingWithdraw = (float) $wallet->pending_withdraw;
        $cashInHand = (float) ($wallet->collected_cash ?? 0);

        if (($totalEarning - ($totalWithdrawn + $pendingWithdraw + $cashInHand)) < 0) {
            throw new \RuntimeException(translate('messages.Balance mismatched total earning is too low'));
        }

        if ($currentStatus === $status) {
            return;
        }

        if ($status === 'completed') {
            if ($currentStatus === 'pending') {
                if ($pendingWithdraw < $amount) {
                    throw new \RuntimeException(translate('messages.Pending withdraw is lower than disbursement amount'));
                }

                $wallet->pending_withdraw = $pendingWithdraw - $amount;
                $wallet->total_withdrawn = $totalWithdrawn + $amount;
            } elseif ($currentStatus === 'canceled') {
                $wallet->total_withdrawn = $totalWithdrawn + $amount;
            }

            $provideDmEarning = ProvideDMEarning::firstOrNew([
                'ref' => $disbursement->id,
                'delivery_man_id' => $disbursement->delivery_man_id,
            ]);

            $provideDmEarning->method = $disbursement?->withdraw_method?->method_name;
            $provideDmEarning->amount = $amount;
            $provideDmEarning->save();
        } elseif ($status === 'canceled') {
            if ($currentStatus === 'completed') {
                throw new \RuntimeException(translate('Cannot cancel completed disbursement, uncheck completed disbursements'));
            }

            if ($currentStatus === 'pending') {
                if ($pendingWithdraw < $amount) {
                    throw new \RuntimeException(translate('messages.Pending withdraw is lower than disbursement amount'));
                }

                $wallet->pending_withdraw = $pendingWithdraw - $amount;
            }
        } elseif ($status === 'pending') {
            if ($currentStatus === 'completed') {
                if ($totalWithdrawn < $amount) {
                    throw new \RuntimeException(translate('messages.Total withdrawn is lower than disbursement amount'));
                }

                ProvideDMEarning::where('ref', $disbursement->id)
                    ->where('delivery_man_id', $disbursement->delivery_man_id)
                    ->delete();

                $wallet->total_withdrawn = $totalWithdrawn - $amount;
                $wallet->pending_withdraw = $pendingWithdraw + $amount;
            } elseif ($currentStatus === 'canceled') {
                $wallet->pending_withdraw = $pendingWithdraw + $amount;
            }
        }

        $newBalance = (float) $wallet->total_earning
            - (
                (float) $wallet->total_withdrawn
                + (float) $wallet->pending_withdraw
                + (float) ($wallet->collected_cash ?? 0)
            );

        if ($newBalance < 0) {
            throw new \RuntimeException(translate('messages.Balance would become negative after this status change'));
        }

        $wallet->save();
        $disbursement->status = $status;
        $disbursement->save();
    }
    public function generate_disbursement()
    {
        $delivery_mans = DeliveryMan::has('disbursement_method')->with('wallet', 'disbursement_method')->where('type' ,'zone_wise')->where('earning',1)->select(['id'])->get();
        $disbursement_details = [];
        $total_amount = 0;

        $lastId = Disbursement::max('id') ?? 999;
        $disbursement = new Disbursement();
        $disbursement->id = $lastId + 1;
        $disbursement->title = 'Disbursement # '.$disbursement->id;
        $minimum_amount = Helpers::get_business_settings('dm_disbursement_min_amount', false);
        foreach ($delivery_mans as $delivery_man){
            if(isset($delivery_man->wallet)){

                $total_earning = $delivery_man->wallet?$delivery_man->wallet->total_earning:0;
                $total_withdraw = ($delivery_man->wallet?$delivery_man->wallet->total_withdrawn:0) + ($delivery_man->wallet?$delivery_man->wallet->pending_withdraw:0);
                $total_cash_in_hand = $delivery_man->wallet?$delivery_man->wallet->collected_cash:0;
                $disbursement_amount = ( (string) $total_earning >  (string)  ($total_withdraw+$total_cash_in_hand))?  ($total_earning - ($total_withdraw+$total_cash_in_hand)):0;

                if ($disbursement_amount>$minimum_amount && $delivery_man->disbursement_method){
                    $res_d = [
                        'disbursement_id' => $disbursement->id,
                        'delivery_man_id' => $delivery_man->id,
                        'disbursement_amount' => $disbursement_amount,
                        'payment_method' => $delivery_man->disbursement_method->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $disbursement_details[] = $res_d;
                    $total_amount += $res_d['disbursement_amount'];
                    $delivery_man->wallet->pending_withdraw = $delivery_man->wallet->pending_withdraw + $disbursement_amount;
                    $delivery_man->wallet->save();
                }
            }

        }

        if ($total_amount > 0){
            $disbursement->total_amount = $total_amount;
            $disbursement->created_for = 'delivery_man';
            $disbursement->save();

            DisbursementDetails::insert($disbursement_details);
        }

        return true;

    }

    public function check_status($id) {
        $disbursements = DisbursementDetails::where(['disbursement_id' => $id])->get();
        $statusCounts = $disbursements->countBy('status');

        $disbursement = Disbursement::find($id);

        if (isset($statusCounts['pending']) && ($statusCounts['pending'] == count($disbursements))) {
            $disbursement->status = 'pending';
        } elseif (isset($statusCounts['canceled']) && ($statusCounts['canceled'] == count($disbursements))) {
            $disbursement->status = 'canceled';
        } elseif (isset($statusCounts['completed']) && ($statusCounts['completed'] == count($disbursements))) {
            $disbursement->status = 'completed';
        } else {
            $disbursement->status = 'partially_completed';
        }

        return $disbursement->save();
    }
}
