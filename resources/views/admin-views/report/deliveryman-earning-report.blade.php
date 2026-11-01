@extends('layouts.admin.app')

@section('title', translate('Deliveryman earning report'))

@section('deliveryman_earning_report')
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
                        {{ translate('Deliveryman earning report') }}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('What each deliveryman earned, from delivery charges to tips.') }}</p>
            </div>
        </div>

        <div class="js-nav-scroller hs-nav-scroller-horizontal mb-20 mt-2">
            <ul class="nav mb-0 nav-tabs border-0 nav--tabs nav--pills">
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('admin.transactions.report.deliveryman-earning-report') }}" aria-disabled="true">
                        {{ translate('Deliveryman') }}
                    </a>
                </li>
                @if (addon_published_status('RideShare'))
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.transactions.ride-share.report.rider-earning-report') }}" aria-disabled="true">
                            {{ translate('Ride share') }} {{ translate('Rider') }}
                        </a>
                    </li>
                @endif
            </ul>
        </div>

        @include('admin-views.report.partials._deliveryman_earning_report_content', [
            'summary_url' => route('admin.transactions.report.deliveryman-earning-summary'),
            'breakdown_url' => route('admin.transactions.report.deliveryman-earning-breakdown'),
            'expense_url' => route('admin.transactions.report.deliveryman-expense-breakdown'),
            'trend_url' => route('admin.transactions.report.deliveryman-earning-trend'),
            'reset_url' => route('admin.transactions.report.deliveryman-earning-report'),
            'export_url_excel' => route('admin.transactions.report.admin-deliveryman-earning-export', array_merge(request()->query(), ['export_type' => 'excel'])),
            'export_url_csv' => route('admin.transactions.report.admin-deliveryman-earning-export', array_merge(request()->query(), ['export_type' => 'csv'])),
            'delivery_men' => $delivery_men,
            'delivery_man_id' => $delivery_man_id,
        ])
    </div>
@endsection
