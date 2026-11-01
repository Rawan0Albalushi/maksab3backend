@extends('layouts.admin.app')

@section('title', translate('messages.Invoice'))


@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Scoped under `.oiv` on the page wrapper; see the header comment in the file. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/order-invoice.css') }}">

    <style type="text/css" media="print">
        @page {
            size: auto;
            margin: 0;
        }

    </style>
@endpush


@section('content')
    <?php
    // Wording and colour for the two chips are derived once, from literal keys,
    // so nothing is fed to translate() from a variable. Same maps as the order
    // details screen, so a status cannot read one way there and another here.
    $orderStatusLabels = [
        'pending'                 => translate('Pending'),
        'confirmed'               => translate('messages.confirmed'),
        'accepted'                => translate('Accepted'),
        'processing'              => translate('Processing'),
        'handover'                => translate('messages.handover'),
        'picked_up'               => translate('Out for delivery'),
        'delivered'               => translate('Delivered'),
        'canceled'                => translate('Canceled'),
        'failed'                  => translate('Payment failed'),
        'refund_requested'        => translate('messages.Refund requested'),
        'refunded'                => translate('Refunded'),
        'refund_request_canceled' => translate('messages.Refund request canceled'),
    ];
    $orderStatusTones = [
        'pending'                 => 'accent',
        'confirmed'               => 'info',
        'accepted'                => 'info',
        'processing'              => 'warn',
        'handover'                => 'warn',
        'picked_up'               => 'warn',
        'delivered'               => 'ok',
        'canceled'                => 'danger',
        'failed'                  => 'danger',
        'refund_requested'        => 'warn',
        'refunded'                => 'info',
        'refund_request_canceled' => 'neutral',
    ];
    $orderTypeLabels = [
        'delivery'  => translate('messages.delivery'),
        'take_away' => translate('messages.Take away'),
        'parcel'    => translate('messages.Parcel'),
    ];

    $orderStatusLabel = $orderStatusLabels[$order->order_status] ?? ucwords(str_replace('_', ' ', $order->order_status));
    $orderStatusTone  = $orderStatusTones[$order->order_status] ?? 'neutral';
    $orderTypeLabel   = $orderTypeLabels[$order->order_type] ?? ucwords(str_replace('_', ' ', $order->order_type));

    // Payment methods are gateway names, not copy — printed as data, per the
    // translation rules, rather than grown into the language file one gateway
    // at a time.
    $paymentMethodLabel = ucwords(str_replace('_', ' ', $order->payment_method));
    $partiallyPaid = $order->payment_status == 'partially_paid' && $order->payments()->where('payment_status', 'unpaid')->exists();
    ?>
    <div class="content container-fluid oiv">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon"><i class="tio-receipt-outlined"></i></span>
                        <span>{{ translate('messages.Invoice') }}
                            <span class="oiv-id">#{{ $order['id'] }}</span>
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('The printable bill for this order, itemised and ready to hand over.') }}</p>
                </div>

                <div class="col-sm-auto">
                    <div class="oiv-actions">
                        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
                            <i class="tio-arrow-backward"></i> <span>{{ translate('Back') }}</span>
                        </a>
                        <a href="{{ route('admin.order.details', [$order['id']]) }}" class="btn btn-outline-primary">
                            <i class="tio-shopping-basket"></i> <span>{{ translate('Order details') }}</span>
                        </a>
                        <button type="button" class="btn btn-primary print-Div" onclick="printDiv('printableArea')">
                            <i class="tio-print"></i> <span>{{ translate('messages.Print receipt') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Only what the receipt itself does not print: where the order stands, whether
             it is paid, who is carrying it, when it is due. --}}
        <div class="oiv-bar">
            <div class="oiv-bar__facts">
                <span class="oiv-pill oiv-pill--{{ $orderStatusTone }}">{{ $orderStatusLabel }}</span>

                @if ($order->payment_status == 'paid')
                    <span class="oiv-pill oiv-pill--ok">{{ translate('messages.paid') }}</span>
                @elseif ($partiallyPaid)
                    <span class="oiv-pill oiv-pill--warn">{{ translate('messages.Partially paid') }}</span>
                @elseif ($order->payment_status == 'partially_paid')
                    <span class="oiv-pill oiv-pill--ok">{{ translate('messages.paid') }}</span>
                @else
                    <span class="oiv-pill oiv-pill--danger">{{ translate('messages.unpaid') }}</span>
                @endif

                <span class="oiv-fact">
                    <i class="tio-credit-card"></i>
                    <strong>{{ $paymentMethodLabel }}</strong>
                </span>

                <span class="oiv-fact">
                    <i class="tio-shopping-basket"></i>
                    <strong>{{ $orderTypeLabel }}</strong>
                </span>

                @if ($order->delivery_man)
                    <span class="oiv-fact">
                        <i class="tio-bike"></i>
                        <strong>{{ Str::limit($order->delivery_man->f_name . ' ' . $order->delivery_man->l_name, 25, '...') }}</strong>
                    </span>
                @endif

                @if ($order->schedule_at && $order->scheduled)
                    <span class="oiv-fact oiv-fact--warn">
                        <i class="tio-time"></i>
                        {{ translate('Scheduled at') }}
                        <strong>{{ date('d M Y ' . config('timeformat'), strtotime($order['schedule_at'])) }}</strong>
                    </span>
                @endif

                @if ($order->transaction_reference)
                    <span class="oiv-fact">
                        <i class="tio-label"></i>
                        {{ translate('Reference code') }}
                        <strong>{{ $order->transaction_reference }}</strong>
                    </span>
                @endif
            </div>

            <div class="oiv-bar__total">
                <span class="oiv-bar__label">{{ translate('messages.Total') }}</span>
                <span class="oiv-bar__amount">{{ \App\CentralLogics\Helpers::format_currency($order->order_amount) }}</span>
            </div>
        </div>

        <div class="oiv-stage">
            <div class="oiv-stage__cap">
                <span class="oiv-stage__tag">
                    <i class="tio-print"></i>
                    {{ translate('messages.Receipt preview') }}
                    <span>80 mm</span>
                </span>
            </div>

            <div class="oiv-paper">
                @include('admin-views.order.partials._invoice', ['show_actions' => false])
            </div>

            <p class="oiv-stage__note">
                {{ translate('messages.Print opens a printer-ready copy in a new tab. Make sure the thermal printer is ready.') }}
            </p>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";

        function printDiv(divName) {
            window.open('{{ route('admin.order.print-invoice', ['id' => $order->id]) }}', '_blank');
        }
    </script>
@endpush
