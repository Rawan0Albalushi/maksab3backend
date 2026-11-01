<?php

namespace App\Http\Controllers\Admin;

use App\Services\Payment\WalletTransactionService;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\WalletTransaction;
use App\Exports\CustomerWalletTransactionExport;
use App\Http\Controllers\Controller;
use App\Library\AjaxResponse;
use App\Models\User;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\Log;


class CustomerWalletController extends Controller
{
    public function add_fund_view()
    {
        if (Helpers::get_business_settings('wallet_status', false) != 1) {
            Toastr::error(trans('messages.Customer wallet disable warning admin'));
            return back();
        }
        return view('admin-views.customer.wallet.add_fund');
    }

    public function add_fund(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id'=>'required|exists:users,id',
            'amount'=>'required|numeric|min:.01',
            'reference'=>'nullable|string|max:191',
        ]);

        if ($validator->fails()) {
            return AjaxResponse::invalid($validator->errors()->messages());
        }

        $wallet_transaction = app(WalletTransactionService::class)->recordWalletTransaction($request->customer_id, $request->amount, 'add_fund_by_admin',$request->reference);

        if($wallet_transaction)
        {
            try{
                Helpers::add_fund_push_notification($request->customer_id);
                if(SendNotification::canSendMail('add_fund_mail_status_user', 'customer', 'customer_add_fund_to_wallet') ) {
                    SendNotification::mail($wallet_transaction->user?->getRawOriginal('email'), new \App\Mail\AddFundToWallet($wallet_transaction));
                }
            }catch(\Exception $ex)
            {
                Log::error('admin.customer_wallet_controller.add_fund_failed', [
                    'error' => $ex->getMessage(),
                    'file' => $ex->getFile().':'.$ex->getLine(),
                ]);
            }

            return AjaxResponse::success(translate('Fund added') . '. ' . translate('Wallet balance') . ': ' . Helpers::format_currency($wallet_transaction->balance));
        }

        return AjaxResponse::fail(translate('messages.Failed to create transaction'));
    }

    public function report(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $filter = $request->query('filter', 'all_time');
        $key = [];
        if ($request->search) {
            $key = explode(' ', $request['search'] ?? '');
        }
        $data = WalletTransaction::selectRaw('sum(credit+admin_bonus) as total_credit, sum(debit) as total_debit,
         SUM(IF(transaction_type = "add_fund_by_admin", credit + admin_bonus, 0)) as add_fund_total,
         SUM(IF(transaction_type = "add_fund", credit + admin_bonus, 0)) as add_fund,
         SUM(IF(transaction_type = "order_refund", credit, 0)) as order_refund_total,
         SUM(IF(transaction_type = "loyalty_point", credit, 0)) as loyalty_point_total,
         SUM(IF(transaction_type = "CashBack", credit + admin_bonus, 0)) as CashBack,
         SUM(IF(transaction_type = "referrer", credit + admin_bonus, 0)) as referrer,
         SUM(IF(transaction_type = "order_place", credit, 0)) as order_place_total')
            ->when(($request->from && $request->to),function($query)use($request){
                $query->whereBetween('created_at', [$request->from.' 00:00:00', $request->to.' 23:59:59']);
            })
            ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                return $query->whereBetween('created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->when(isset($filter) && $filter == 'this_year', function ($query) {
                return $query->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('created_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })
            ->when(isset($request->transaction_type) && ($request->transaction_type != 'all'), function($query)use($request){
                $query->where('transaction_type',$request->transaction_type);
            })
            ->when(isset($request->customer_id) && is_numeric($request->customer_id), function($query)use($request){
                $query->where('user_id',$request->customer_id);
            })
        ->when(count($key) > 0, function($query) use($key){
            $query->wherehas('user',    function ($query) use ($key) {
                foreach ($key as $value) {
                    $query->where(function($query) use($value){
                        $query->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%");
                    });
                };
            });
       })
        ->get();

        $transactions = WalletTransaction::with('user')->
            when(($request->from && $request->to),function($query)use($request){
                $query->whereBetween('created_at', [$request->from.' 00:00:00', $request->to.' 23:59:59']);
            })
            ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                return $query->whereBetween('created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->when(isset($filter) && $filter == 'this_year', function ($query) {
                return $query->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('created_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })
            ->when(isset($request->transaction_type) && ($request->transaction_type != 'all'), function($query)use($request){
                $query->where('transaction_type',$request->transaction_type);
            })
            ->when(isset($request->customer_id) && is_numeric($request->customer_id), function($query)use($request){
                $query->where('user_id',$request->customer_id);
            })
        ->when(count($key) > 0, function($query) use($key){
            $query->wherehas('user',    function ($query) use ($key) {
                foreach ($key as $value) {
                    $query->where(function($query) use($value){
                        $query->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%");
                    });
                };
            });
       })
        ->latest()
        ->paginate(config('default_pagination'));

        return view('admin-views.customer.wallet.report', compact('data','transactions','filter'));
    }

    public function export(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $filter = $request->query('filter', 'all_time');
        $key = [];
        if ($request->search) {
            $key = explode(' ', $request['search'] ?? '');
        }

        $data = WalletTransaction::selectRaw('sum(credit) as total_credit, sum(debit) as total_debit')
            ->when(($request->from && $request->to),function($query)use($request){
                $query->whereBetween('created_at', [$request->from.' 00:00:00', $request->to.' 23:59:59']);
            })
            ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                return $query->whereBetween('created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->when(isset($filter) && $filter == 'this_year', function ($query) {
                return $query->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('created_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })
            ->when(isset($request->transaction_type) && ($request->transaction_type != 'all'), function($query)use($request){
                $query->where('transaction_type',$request->transaction_type);
            })
            ->when(isset($request->customer_id) && is_numeric($request->customer_id), function($query)use($request){
                $query->where('user_id',$request->customer_id);
            })
        ->when(count($key) > 0, function($query) use($key){
            $query->wherehas('user',    function ($query) use ($key) {
                foreach ($key as $value) {
                    $query->where(function($query) use($value){
                        $query->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%");
                    });
                };
            });
       })
       ->get();

        $transactions = WalletTransaction::
            when(($request->from && $request->to),function($query)use($request){
                $query->whereBetween('created_at', [$request->from.' 00:00:00', $request->to.' 23:59:59']);
            })
            ->when(isset($from) && isset($to) && $from != null && $to != null && $filter == 'custom', function ($query) use ($from, $to) {
                return $query->whereBetween('created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->when(isset($filter) && $filter == 'this_year', function ($query) {
                return $query->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'this_month', function ($query) {
                return $query->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            })
            ->when(isset($filter) && $filter == 'previous_year', function ($query) {
                return $query->whereYear('created_at', date('Y') - 1);
            })
            ->when(isset($filter) && $filter == 'this_week', function ($query) {
                return $query->whereBetween('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            })
            ->when(isset($request->transaction_type) && ($request->transaction_type != 'all'), function($query)use($request){
                $query->where('transaction_type',$request->transaction_type);
            })
            ->when(isset($request->customer_id) && is_numeric($request->customer_id), function($query)use($request){
                $query->where('user_id',$request->customer_id);
            })
        ->when(count($key) > 0, function($query) use($key){
            $query->wherehas('user',    function ($query) use ($key) {
                foreach ($key as $value) {
                    $query->where(function($query) use($value){
                        $query->orWhere('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%");
                    });
                };
            });
       })
        ->latest()
        ->get();

        $data = [
            'transactions'=>$transactions,
            'data'=>$data,
            'from'=>$request->from??null,
            'to'=>$request->to??null,
            'transaction_type'=>$request->transaction_type??null,
            'customer'=>$request->customer_id?Helpers::get_customer_name($request->customer_id):$request['search']?? null,

        ];

        if ($request->type == 'excel') {
            return Excel::download(new CustomerWalletTransactionExport($data), 'CustomerWalletTransactions.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new CustomerWalletTransactionExport($data), 'CustomerWalletTransactions.csv');
        }
    }

    public function set_date(Request $request)
    {
        session()->put('from_date', date('Y-m-d', strtotime($request['from'])));
        session()->put('to_date', date('Y-m-d', strtotime($request['to'])));
        return back();
    }


    public function getUserWallet(Request $request){

        $user = $request->customer_id ? User::withStorage()->find($request->customer_id) : null;

        if(!$user){
            return response()->json(['found' => false], 200);
        }

        return response()->json([
            'found' => true,
            'name' => $user->full_name,
            'phone' => $user->phone,
            'email' => $user->email,
            'image' => $user->image_full_url,
            'balance' => (float) $user->wallet_balance,
            'balance_formatted' => Helpers::format_currency($user->wallet_balance),
            'order_count' => (int) $user->order_count,
            'loyalty_point' => (int) $user->loyalty_point,
            'member_since' => Helpers::date_format($user->created_at),
            'wallet_available' => !storefront_wallet_disabled_for_user($user->id),
            'profile_url' => Helpers::module_permission_check('customer_management')
                ? route('admin.users.customer.view', [$user->id])
                : null,
        ], 200);
    }



}
