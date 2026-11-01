@extends('layouts.admin.app')

@section('title', translate('Store earning report'))

@section('store_earning_report')
    active
@endsection
@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div>
                <h1 class="page-header-title text-capitalize">
                    <span class="page-header-icon">
                        <img src="{{ asset('public/assets/admin/img/outline/report.svg') }}" class="w--26" alt="">
                    </span>
                    <span>
                        {{ translate('Vendor earning report') }}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('What each store earned, and what you took from it in commission.') }}</p>
            </div>
        </div>

        <div class="js-nav-scroller hs-nav-scroller-horizontal mb-20 mt-2">
            <ul class="nav mb-0 nav-tabs border-0 nav--tabs nav--pills">
                <li class="nav-item">
                    <a class="nav-link {{ !in_array(request()->tab, ['rental', 'service']) ? 'active' : '' }}"
                        href="{{ route('admin.transactions.report.store-earning-report') }}"
                        aria-disabled="true">{{ translate('Order modules') }}</a>
                </li>
                @if (addon_published_status('Rental'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->tab === 'rental' ? 'active' : '' }}"
                            href="{{ route('admin.transactions.report.store-earning-report', ['tab' => 'rental']) }}"
                            aria-disabled="true">{{ translate('Rental module') }}</a>
                    </li>
                @endif
                @if (addon_published_status('Service'))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->tab === 'service' ? 'active' : '' }}"
                            href="{{ route('admin.transactions.report.store-earning-report', ['tab' => 'service']) }}"
                            aria-disabled="true">{{ translate('Service module') }}</a>
                    </li>
                @endif

            </ul>
        </div>

        @if (request()->tab === 'rental' && addon_published_status('Rental'))
            @include('rental::provider.report.earning-report.content', [
                'report_url' => route('admin.transactions.report.store-earning-report'),
                'summary_url' => route('admin.transactions.rental.report.provider-earning-summary'),
                'breakdown_url' => route('admin.transactions.rental.report.provider-earning-breakdown'),
                'expense_url' => route('admin.transactions.rental.report.provider-expense-breakdown'),
                'trend_url' => route('admin.transactions.rental.report.provider-earning-trend'),
                'reset_url' => route('admin.transactions.report.store-earning-report', ['tab' => 'rental']),
                'transactions_export_url' => route('admin.transactions.rental.report.provider-earning-export'),
                'transactions_url' => route('admin.transactions.rental.report.provider-earning-transactions'),
                'show_store_select' => true,
                'store' => $store,
                'store_id' => $store_id,
                'module_id' => $module_id,
                'tab' => 'rental',
            ])
        @elseif (request()->tab === 'service' && addon_published_status('Service'))
            @include('service::vendor.report.earning-report.content', [
                'report_url' => route('admin.transactions.report.store-earning-report'),
                'summary_url' => route('admin.transactions.service.report.provider-earning-summary'),
                'breakdown_url' => route('admin.transactions.service.report.provider-earning-breakdown'),
                'expense_url' => route('admin.transactions.service.report.provider-expense-breakdown'),
                'trend_url' => route('admin.transactions.service.report.provider-earning-trend'),
                'reset_url' => route('admin.transactions.report.store-earning-report', ['tab' => 'service']),
                'transactions_export_url' => route('admin.transactions.service.report.provider-earning-export'),
                'transactions_url' => route('admin.transactions.service.report.provider-earning-transactions'),
                'show_store_select' => true,
                'store' => $store,
                'store_id' => $store_id,
                'module_id' => $module_id,
                'tab' => 'service',
            ])
        @else
            @include('admin-views.report.partials._store_earning_report_content', [
                'summary_url' => route('admin.transactions.report.store-earning-summary'),
                'breakdown_url' => route('admin.transactions.report.store-earning-breakdown'),
                'expense_url' => route('admin.transactions.report.store-expense-breakdown'),
                'trend_url' => route('admin.transactions.report.store-earning-trend'),
                'reset_url' => route('admin.transactions.report.store-earning-report'),
                'export_url_excel' => route('admin.transactions.report.store-earning-export', array_merge(request()->query(), ['export_type' => 'excel'])),
                'export_url_csv' => route('admin.transactions.report.store-earning-export', array_merge(request()->query(), ['export_type' => 'csv'])),
                'transactions_export_url' => route('admin.transactions.report.store-earning-export'),
                'transactions_url' => route('admin.transactions.report.store-earning-transactions'),
                'show_store_select' => true,
                'store' => $store,
                'store_id' => $store_id,
                'module_id' => $module_id,
            ])
        @endif
    </div>
@endsection
