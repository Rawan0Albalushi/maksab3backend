<div class="sdb-head">
    <span class="sdb-head__icon"><i class="tio-money-vs"></i></span>
    <div class="sdb-head__text">
        <h2 class="sdb-head__title">
            {{ $disbursement->title }}
            @include('admin-views.disbursement.partials._status-pill', ['status' => $disbursement->status])
        </h2>
        <div class="sdb-head__meta">
            <span>
                <i class="tio-date-range"></i>
                {{ translate('Created at') }} {{ \App\CentralLogics\Helpers::time_date_format($disbursement->created_at) }}
            </span>
            <span>
                <i class="tio-hashtag"></i>
                {{ translate('Disbursement ID') }} #{{ $disbursement->id }}
            </span>
            <span>
                <i class="tio-user-outlined"></i>
                {{ $payout_count($total_payouts) }}
            </span>
        </div>
    </div>
    <div class="sdb-head__amount">
        <span class="sdb-head__amount-label">{{ translate('Total amount') }}</span>
        <span class="sdb-head__amount-value">{{ \App\CentralLogics\Helpers::format_currency($disbursement->total_amount) }}</span>
    </div>
</div>
