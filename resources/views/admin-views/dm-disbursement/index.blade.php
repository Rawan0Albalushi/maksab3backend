@extends('layouts.admin.app')

@section('title',translate('Deliveryman disbursement'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/disbursement.css') }}">
@endpush

@section('content')

@php
    $payout_count = function ($n) {
        return translate('Deliveryman payouts') . ': ' . $n;
    };

    $is_filtered = request()->filled('search') || ($status !== 'all');
@endphp

<div class="content container-fluid sdb">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('/public/assets/admin/img/report/new/disburstment.png')}}" class="w--22" alt="">
            </span>
            <span>
                {{ translate('Deliveryman disbursement') }}
                <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $disbursements->total() }}</span>
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('Payouts on their way to your deliverymen, and the state each one is in.') }}</p>

        @include('admin-views.disbursement.partials._tabs', [
            'tab_route' => 'admin.transactions.dm-disbursement.list',
            'tab_statuses' => ['all', 'pending', 'processing', 'partially_completed', 'completed', 'canceled'],
        ])
    </div>

    {{-- Two grouped queries in the controller, not one count per tile. The
         strip describes the whole ledger, so it deliberately ignores the tab
         and the search box — the badge on the heading tracks those. --}}
    @include('admin-views.disbursement.partials._summary-strip', [
        'lead_value' => $batch_summary->sum(),
        'lead_label' => translate('messages.Payout runs generated so far'),
        'released_label' => translate('Released to deliverymen'),
    ])

    <div class="card">
        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper">
                @include('partials._table-head', [
                    'subtitle' => translate('Scheduled payout runs that release deliveryman earnings. open a run to review and release each payout.'),
                    'count' => null,
                ])

                <form class="search-form">
                    <div class="input--group input-group input-group-merge input-group-flush">
                        <input class="form-control" type="search" name="search" value="{{ request('search') }}"
                               placeholder="{{ translate('Ex') }}: {{ translate('Disbursement') }} # 1024">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                    @if($status !== 'all')
                        <input type="hidden" name="status" value="{{ $status }}">
                    @endif
                </form>

                @if(request()->filled('search'))
                    <a href="{{ route('admin.transactions.dm-disbursement.list', ['status' => $status]) }}" class="btn btn--reset">
                        <i class="tio-refresh"></i> {{ translate('Reset') }}
                    </a>
                @endif
            </div>
        </div>

        <div class="card-body">
            @if(count($disbursements) === 0)
                @include('admin-views.disbursement.partials._empty', [
                    'empty_title' => translate('messages.No disbursement found'),
                    'empty_body' => $is_filtered
                        ? translate('messages.Nothing matches this filter. Try another status or clear the search.')
                        : translate('messages.Payout runs appear here once the disbursement schedule fires. Check the disbursement settings to see when the next one is due.'),
                ])
            @else
                <div class="sdb-batches">
                    @foreach($disbursements as $disbursement)
                        @include('admin-views.disbursement.partials._batch-card', [
                            'details_url' => route('admin.transactions.dm-disbursement.view', ['id' => $disbursement->id]),
                        ])
                    @endforeach
                </div>
            @endif
        </div>

        <div class="page-area">
            {!! $disbursements->links() !!}
        </div>
    </div>
</div>

@endsection
