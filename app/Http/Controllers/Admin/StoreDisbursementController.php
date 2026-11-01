<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Services\System\BusinessSettingService;
use App\Exports\DisbursementExport;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\Store;
use App\Models\Disbursement;
use App\Models\DisbursementDetails;
use App\Models\StoreWallet;
use App\Models\WithdrawRequest;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;

class StoreDisbursementController extends Controller
{
    public function list(Request $request)
    {
        $status = $request->status ?? 'all';
        $key = $request->filled('search') ? explode(' ', $request['search']) : null;

        $disbursements = Disbursement::where('created_for', 'store')
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

        return view('admin-views.store-disbursement.index', [
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
        return Disbursement::where('created_for', 'store')
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
                    ->where('disbursements.created_for', 'store');
            })
            ->selectRaw('disbursement_details.status as status, COUNT(*) as payouts, SUM(disbursement_details.disbursement_amount) as amount')
            ->groupBy('disbursement_details.status')
            ->get()
            ->keyBy('status');
    }

    public function view(Request $request,$id)
    {
        $key = explode(' ', $request['search'] ?? '');
        $store_id = $request->query('store_id', 'all');
        $payment_method_id = $request->query('payment_method_id', 'all');
        $disbursement = Disbursement::where('created_for', 'store')->findOrFail($id);
        $store = is_numeric($store_id) ? Store::withStorage()->findOrFail($store_id) : null;
        $module_id = $request->query('module_id', 'all');


        $disbursements=DisbursementDetails::with('store.storage','store.vendor','store.module','withdraw_method')->where(['disbursement_id'=>$id])
            ->when($request['search'] , function($q) use($key){
                $q->whereHas('store', function ($q) use($key){
                    $q->where(function($query)use ($key){
                        $query->orWhereHas('vendor', function ($q) use ($key) {
                            foreach ($key as $value) {
                                $q->orWhere('f_name', 'like', "%{$value}%")
                                    ->orWhere('l_name', 'like', "%{$value}%")
                                    ->orWhere('email', 'like', "%{$value}%")
                                    ->orWhere('phone', 'like', "%{$value}%");
                            }
                        })
                            ->where(function ($q) use ($key) {
                                foreach ($key as $value) {
                                    $q->orWhere('name', 'like', "%{$value}%")
                                        ->orWhere('email', 'like', "%{$value}%")
                                        ->orWhere('phone', 'like', "%{$value}%");
                                }
                            });
                    });
                });
            })
            ->when((isset($store_id) && is_numeric($store_id)), function ($query) use ($store_id){
                $query->where('store_id', $store_id);
            })
            ->when((isset($module_id) &&  is_numeric($module_id)), function ($query) use ($module_id) {
                return $query->whereHas('store', function ($query) use ($module_id) {
                    $query->where('module_id',$module_id);
                });
            })
            ->when((isset($payment_method_id) && is_numeric($payment_method_id)), function ($query) use ($payment_method_id){
                $query->whereHas('withdraw_method', function ($q) use($payment_method_id){
                    return $q->where('withdrawal_method_id', $payment_method_id);
                });
            })
            ->latest();
        $store_ids = json_encode($disbursements->pluck('store_id')->toArray());
        $disbursement_stores = $disbursements->paginate(config('default_pagination'))
            ->appends($request->except('page'));
        $payout_summary = $this->payoutSummary($id);

        return view('admin-views.store-disbursement.view', compact('disbursement','disbursement_stores','store_ids','store_id','payment_method_id','store','payout_summary'));
    }
    public function export(Request $request,$id, $type = 'excel')
    {
        $key = explode(' ', $request['search'] ?? '');
        $store_id = $request->query('store_id', 'all');
        $payment_method_id = $request->query('payment_method_id', 'all');
        $disbursement = Disbursement::where('created_for', 'store')->findOrFail($id);
        $disbursements=DisbursementDetails::with(['store.vendor','withdraw_method'])->where(['disbursement_id'=>$id])
            ->when($request['search'] , function($q) use($key){
                $q->whereHas('store', function ($q) use($key){
                    $q->where(function($query)use ($key){
                        $query->orWhereHas('vendor', function ($q) use ($key) {
                            foreach ($key as $value) {
                                $q->orWhere('f_name', 'like', "%{$value}%")
                                    ->orWhere('l_name', 'like', "%{$value}%")
                                    ->orWhere('email', 'like', "%{$value}%")
                                    ->orWhere('phone', 'like', "%{$value}%");
                            }
                        })
                            ->where(function ($q) use ($key) {
                                foreach ($key as $value) {
                                    $q->orWhere('name', 'like', "%{$value}%")
                                        ->orWhere('email', 'like', "%{$value}%")
                                        ->orWhere('phone', 'like', "%{$value}%");
                                }
                            });
                    });
                });
            })
            ->when((isset($store_id) && is_numeric($store_id)), function ($query) use ($store_id){
                $query->where('store_id', $store_id);
            })
            ->when((isset($payment_method_id) && is_numeric($payment_method_id)), function ($query) use ($payment_method_id){
                $query->whereHas('withdraw_method', function ($q) use($payment_method_id){
                    return $q->where('withdrawal_method_id', $payment_method_id);
                });
            })
            ->latest()->get();
        $data=[
            'type'=>'store',
            'disbursement' =>$disbursement,
            'disbursements' =>$disbursements,
        ];
        if($type == 'pdf'){
            $logoFullUrl = Helpers::get_full_url(
                'business',
                Helpers::get_business_settings('logo', false),
                app(BusinessSettingService::class)->findStorageDisk('logo')
            );
            $mpdf_view = View::make('admin-views.store-disbursement.pdf', compact('disbursement', 'disbursements', 'logoFullUrl'));
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
                $disbursements = DisbursementDetails::with(['store.vendor', 'withdraw_method'])
                    ->where(['disbursement_id' => $request->disbursement_id])
                    ->whereIn('store_id', $request->store_ids)
                    ->lockForUpdate()
                    ->get();

                foreach ($disbursements as $disbursement) {
                    $this->syncStoreDisbursementStatus($disbursement, $request->status);
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
                $disbursement = DisbursementDetails::with(['store.vendor', 'withdraw_method'])
                    ->lockForUpdate()
                    ->findOrFail($id);

                $this->syncStoreDisbursementStatus($disbursement, $status);
                self::check_status($disbursement->disbursement_id);
            });
            Toastr::success(translate('messages.Status updated'));
            return back();
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());
            return back();
        }
    }

    private function syncStoreDisbursementStatus(DisbursementDetails $disbursement, string $status): void
    {
        $store = $disbursement->store;
        $wallet = StoreWallet::where('vendor_id', $store?->vendor_id)->lockForUpdate()->first();

        if (!$wallet) {
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

            $withdraw = WithdrawRequest::firstOrNew([
                'transaction_note' => $disbursement->id,
                'vendor_id' => $store?->vendor?->id,
            ]);

            $withdraw->amount = $amount;
            $withdraw->withdrawal_method_id = $disbursement->payment_method;
            $withdraw->withdrawal_method_fields = $disbursement->withdraw_method?->method_fields;
            $withdraw->approved = 1;
            $withdraw->type = 'disbursement';
            $withdraw->save();
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

                WithdrawRequest::where('transaction_note', $disbursement->id)
                    ->where('vendor_id', $store->vendor_id)
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
        $stores = Store::where('status', 1)
            ->has('disbursement_method')
            ->with('vendor.wallet', 'disbursement_method')
            ->select(['id', 'vendor_id', 'module_id'])
            ->get();
        $disbursement_details = [];
        $total_amount = 0;

        $lastId = Disbursement::max('id') ?? 999;
        $disbursement = new Disbursement();
        $disbursement->id = $lastId + 1;
        $disbursement->title = 'Disbursement # '.$disbursement->id;
        $minimum_amount = Helpers::get_business_settings('store_disbursement_min_amount', false);
        foreach ($stores as $store){
            if(isset($store->vendor->wallet)){

                $total_earning = $store->vendor->wallet->total_earning;
                $total_withdraw = $store->vendor->wallet->total_withdrawn + $store->vendor->wallet->pending_withdraw;
                $total_cash_in_hand = $store->vendor->wallet->collected_cash;
                $disbursement_amount = ((string) $total_earning > (string) ($total_withdraw+$total_cash_in_hand))
                    ? ($total_earning - ($total_withdraw+$total_cash_in_hand))
                    : 0;

                if ($disbursement_amount > $minimum_amount && isset($store->disbursement_method)){

                    $res_d = [
                        'disbursement_id' => $disbursement->id,
                        'store_id' => $store->id,
                        'disbursement_amount' => $disbursement_amount,
                        'payment_method' => $store->disbursement_method->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                    $disbursement_details[] = $res_d;
                    $total_amount += $res_d['disbursement_amount'];

                    $store->vendor->wallet->pending_withdraw = $store->vendor->wallet->pending_withdraw + $disbursement_amount;
                    $store->vendor->wallet->save();
                }
            }
        }

        if ($total_amount > 0){
            $disbursement->total_amount = $total_amount;
            $disbursement->created_for = 'store';
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
