@php
    $sdb_rails = [
        'pending' => 'sdb-batch--pending',
        'processing' => 'sdb-batch--partial',
        'completed' => 'sdb-batch--completed',
        'partially_completed' => 'sdb-batch--partial',
        'canceled' => 'sdb-batch--canceled',
    ];

    $sdb_total = $disbursement->details_count;
    $sdb_settled = $sdb_total - $disbursement->pending_details_count;
    $sdb_progress = $sdb_total > 0 ? round(($sdb_settled / $sdb_total) * 100) : 0;
@endphp

<div class="sdb-batch {{ $sdb_rails[$disbursement->status] ?? '' }}">
    <div class="sdb-batch__main">
        <h3 class="sdb-batch__title">
            <a href="{{ $details_url }}">{{ $disbursement->title }}</a>
            @include('admin-views.disbursement.partials._status-pill', ['status' => $disbursement->status])
        </h3>
        <div class="sdb-batch__meta">
            <span>
                <i class="tio-date-range"></i>
                {{ translate('Created at') }} {{ \App\CentralLogics\Helpers::time_date_format($disbursement->created_at) }}
            </span>
            <span>
                <i class="tio-user-outlined"></i>
                {{ $payout_count($sdb_total) }}
            </span>
            <span>
                <i class="tio-hashtag"></i>
                ID #{{ $disbursement->id }}
            </span>
            @if($disbursement->status !== 'pending' && $disbursement->updated_at)
                <span>
                    <i class="tio-checkmark-circle-outlined"></i>
                    {{ translate('messages.Processed') }}: {{ \App\CentralLogics\Helpers::time_date_format($disbursement->updated_at) }}
                </span>
            @endif
        </div>
    </div>

    <div class="sdb-batch__progress">
        <div class="sdb-batch__progress-head">
            <span>{{ translate('Settled') }}</span>
            <strong>{{ $sdb_settled }} / {{ $sdb_total }}</strong>
        </div>
        <div class="sdb-batch__progress-track" role="progressbar"
             aria-valuenow="{{ $sdb_progress }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ translate('Settled') }}">
            <span class="sdb-batch__progress-bar" style="width: {{ $sdb_progress }}%"></span>
        </div>
    </div>

    <div class="sdb-batch__aside">
        <div class="sdb-batch__amount">
            <span class="sdb-batch__amount-label">{{ translate('Total amount') }}</span>
            <span class="sdb-batch__amount-value">{{ \App\CentralLogics\Helpers::format_currency($disbursement->total_amount) }}</span>
        </div>
        <a href="{{ $details_url }}" class="btn btn--primary">
            <i class="tio-visible-outlined"></i> {{ translate('View details') }}
        </a>
    </div>
</div>
