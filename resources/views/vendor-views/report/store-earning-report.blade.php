@extends('layouts.vendor.app')

@section('title', translate('Store earning report'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div>
                <h1 class="page-header-title text-capitalize">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/outline/report.svg') }}" class="w--26" alt="">
                    </span>
                    <span>
                        {{ translate('Store earning report') }}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('What you earned, and what was taken from it in commission.') }}</p>
            </div>
        </div>

        @include('admin-views.report.partials._store_earning_report_content', [
            'summary_url' => route('vendor.report.store-earning-summary'),
            'breakdown_url' => route('vendor.report.store-earning-breakdown'),
            'expense_url' => route('vendor.report.store-expense-breakdown'),
            'trend_url' => route('vendor.report.store-earning-trend'),
            'reset_url' => route('vendor.report.store-earning-report'),
            'export_url_excel' => route('vendor.report.store-earning-export', array_merge(request()->query(), ['export_type' => 'excel'])),
            'export_url_csv' => route('vendor.report.store-earning-export', array_merge(request()->query(), ['export_type' => 'csv'])),
            'transactions_export_url' => route('vendor.report.store-earning-export'),
            'transactions_url' => route('vendor.report.store-earning-transactions'),
            'show_store_select' => false,
            'stores' => [],
            'store_id' => $store_id,
        ])
    </div>
@endsection
