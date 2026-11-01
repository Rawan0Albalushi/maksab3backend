{{-- Withdraw request status, shared by all three approval queues.

     @include('admin-views.withdraw.partials._status-pill', ['approved' => $wr->approved])

     `approved` is a tinyint on `withdraw_requests`: 0 pending, 1 approved,
     2 denied. The label map is literal — translate() may not be handed a
     variable (playbook §8).

     Styles: `withdraw.css` §1. --}}

@php
    $wdr_pill_labels = [
        0 => translate('messages.Pending'),
        1 => translate('messages.Approved'),
        2 => translate('messages.Denied'),
    ];
    $wdr_pill_classes = [
        0 => 'wdr-pill--pending',
        1 => 'wdr-pill--approved',
        2 => 'wdr-pill--denied',
    ];
    $wdr_approved = (int) $approved;
@endphp

<span class="wdr-pill {{ $wdr_pill_classes[$wdr_approved] ?? '' }}">{{ $wdr_pill_labels[$wdr_approved] ?? $wdr_approved }}</span>
