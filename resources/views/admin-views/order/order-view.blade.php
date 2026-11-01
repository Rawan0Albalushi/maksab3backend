@extends('layouts.admin.app')

@section('title', translate('Order details'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/order-edit-offcanvas.css') }}">
    {{-- Scoped under `.odv` on the page wrapper; see the header comment in the file. --}}
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/order-details.css') }}">
@endpush




@section('content')
    <?php
    $campaign_order = isset($order?->details[0]?->item_campaign_id )  ? true : false;
    $reasons=\App\CentralLogics\Helpers::cached_list(\App\Models\OrderCancelReason::class, ['status' => 1, 'user_type' => 'admin']);

    // The status chip's wording and colour are derived once here rather than in
    // the chain of @elseif blocks the header used to carry, so the badge in the
    // header, the label on the status dropdown and the edit offcanvas cannot
    // drift apart. Literal keys per the translation rules — nothing is fed to
    // translate() from a variable except the fallback for a status not listed.
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
    $orderStatusLabel = $orderStatusLabels[$order->order_status] ?? translate(str_replace('_', ' ', $order->order_status));
    $orderStatusTone  = $orderStatusTones[$order->order_status] ?? 'neutral';
    ?>
    <div class="content container-fluid odv">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('/public/assets/admin/img/shopping-basket.png') }}" class="w--20"
                                 alt="">
                        </span>
                        <span>{{ translate('Order details') }}</span>
                        <input type="hidden" value="{{ $order?->distance }}" name="distance">
                    </h1>
                    <p class="page-header-desc">{{ translate('Everything on this order, from the items to the payment and the deliveryman.') }}</p>
                </div>

                <div class="col-sm-auto">
                    <div class="odv-pager">
                        <a class="btn-icon btn-sm rounded-circle"
                           href="{{ route('admin.order.details', [$order['id'] - 1]) }}" data-toggle="tooltip"
                           data-placement="top" title="{{ translate('Previous order') }}">
                            <i class="tio-chevron-left"></i>
                        </a>
                        <a class="btn-icon btn-sm rounded-circle"
                           href="{{ route('admin.order.details', [$order['id'] + 1]) }}" data-toggle="tooltip"
                           data-placement="top" title="{{ translate('Next order') }}">
                            <i class="tio-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>



        @php
            $refund_amount = $order->order_amount - $order->delivery_charge - $order->dm_tips;
        @endphp
        <div class="row flex-xl-nowrap" id="printableArea">
            <div class="col-lg-8 order-print-area-left">
                <div class="card mb-3 mb-lg-5">
                    <div class="card-header odv-head">
                        <div class="odv-head__top">
                            <div class="odv-head__ident">
                                <h2 class="odv-head__order">
                                    {{ translate('messages.Order') }} <span class="odv-hash">#</span>{{ $order['id'] }}
                                </h2>
                                <span class="odv-pill odv-pill--{{ $orderStatusTone }}">{{ $orderStatusLabel }}</span>
                                @if ($campaign_order)
                                    <span class="odv-flag">{{ translate('messages.Campaign order') }}</span>
                                @endif
                                @if ($order->edited)
                                    <span class="odv-flag">{{ translate('messages.Edited') }}</span>
                                @endif
                                @if ($order->orderEditLogs && $order->orderEditLogs->count() > 0)
                                    <button type="button" class="odv-loglink offcanvas-trigger" data-target="#offcanvas__history_log">
                                        {{ translate('Edit history log') }}
                                    </button>
                                @endif
                            </div>

                            <div class="odv-head__actions">
                                @if (in_array($order->order_status, ['pending']) &&
                                        isset($order->store) && !$campaign_order &&
                                        $order->prescription_order == 0 && count($order?->payments) == 0 && $order?->ref_bonus_amount == 0 && $order?->flash_admin_discount_amount == 0 && ($order->payment_method == 'cash_on_delivery') &&
                                        ($canEditOrder ?? true))
                                    @if ($editing)
                                        <button type="button" class="btn btn-outline-primary reopen-edit-offcanvas">
                                            <i class="tio-edit"></i> <span>{{ translate('Edit') }}</span>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-outline-primary" data-toggle="modal"
                                            data-target="#edit_order_confirmation-btn">
                                            <i class="tio-edit"></i> <span>{{ translate('Edit') }}</span>
                                        </button>
                                    @endif
                                @endif
                                <a class="btn btn--primary"
                                   href="{{ route('admin.order.generate-invoice', [$order['id']]) }}">
                                    <i class="tio-print"></i> <span>{{ translate('messages.Print invoice') }}</span>
                                </a>
                            </div>
                        </div>

                        <div class="odv-head__facts">
                            <span class="odv-fact">
                                <i class="tio-date-range"></i>
                                {{ translate('Placed on') }}
                                <strong>{{ date('d M Y ' . config('timeformat'), strtotime($order['created_at'])) }}</strong>
                            </span>
                            <span class="odv-fact">
                                <i class="tio-shop"></i>
                                <strong>{{ Str::limit($order->store ? $order->store->name : translate('messages.Store deleted'), 25, '...') }}</strong>
                                @include('partials._verified_store_badge', ['store' => $order->store])
                            </span>
                            @if ($order->schedule_at && $order->scheduled)
                                <span class="odv-fact odv-fact--warn">
                                    <i class="tio-time"></i>
                                    {{ translate('Scheduled at') }}
                                    <strong>{{ date('d M Y ' . config('timeformat'), strtotime($order['schedule_at'])) }}</strong>
                                </span>
                            @endif
                            @if (!empty($eta))
                                <span class="odv-fact">
                                    <i class="tio-time"></i>
                                    {{ translate('Estimated delivery') }}
                                    <strong>{{ $etaWindow }}</strong>
                                    <small class="text-muted">({{ $eta['text'] }})</small>
                                </span>
                            @endif
                            @if ($order->coupon)
                                <span class="odv-fact odv-fact--accent">
                                    <i class="tio-ticket"></i>
                                    <strong>{{ $order->coupon_code }}</strong>
                                    ({{ translate('messages.' . $order->coupon->coupon_type) }})
                                </span>
                            @endif
                            <button type="button" class="odv-fact" data-toggle="modal" data-target="#locationModal">
                                <i class="tio-poi"></i> {{ translate('messages.Show locations on map') }}
                            </button>
                        </div>
                    </div>

                    <div class="odv-meta">
                        <div class="odv-meta__item">
                            <span class="odv-meta__label">{{ translate('messages.Payment method') }}</span>
                            <span class="odv-meta__value">{{ payment_method_label($order['payment_method']) }}</span>
                        </div>

                        <div class="odv-meta__item">
                            <span class="odv-meta__label">{{ translate('Payment status') }}</span>
                            <span class="odv-meta__value">
                                @if ($order['payment_status'] == 'paid')
                                    <span class="odv-pill odv-pill--ok">{{ translate('messages.paid') }}</span>
                                @elseif ($order['payment_status'] == 'partially_paid')
                                    @if ($order->payments()->where('payment_status','unpaid')->exists())
                                        <span class="odv-pill odv-pill--warn">{{ translate('messages.Partially paid') }}</span>
                                    @else
                                        <span class="odv-pill odv-pill--ok">{{ translate('messages.paid') }}</span>
                                    @endif
                                @else
                                    <span class="odv-pill odv-pill--danger">{{ translate('messages.unpaid') }}</span>
                                @endif
                            </span>
                        </div>

                        <div class="odv-meta__item">
                            <span class="odv-meta__label">{{ translate('Order type') }}</span>
                            <span class="odv-meta__value">{{ translate(str_replace('_', ' ', $order['order_type'])) }}</span>
                        </div>

                        <div class="odv-meta__item">
                            <span class="odv-meta__label">{{ translate('Reference code') }}</span>
                            <span class="odv-meta__value {{ $order['transaction_reference'] ? 'odv-meta__value--mono' : '' }}">
                                @if ($order['transaction_reference'] == null)
                                    <button class="btn btn-outline-primary" data-toggle="modal"
                                            data-target=".bd-example-modal-sm">
                                        <i class="tio-add"></i> {{ translate('Add') }}
                                    </button>
                                @else
                                    {{ $order['transaction_reference'] }}
                                @endif
                            </span>
                        </div>

                        @if ($order?->offline_payments)
                            <div class="odv-meta__item">
                                <span class="odv-meta__label">{{ translate('Payment verification') }}</span>
                                <span class="odv-meta__value">
                                    @if ($order?->offline_payments?->status == 'pending')
                                        <span class="odv-pill odv-pill--warn">{{ translate('Pending') }}</span>
                                    @elseif ($order?->offline_payments?->status == 'verified')
                                        <span class="odv-pill odv-pill--ok">{{ translate('messages.verified') }}</span>
                                    @elseif ($order?->offline_payments?->status == 'denied')
                                        <span class="odv-pill odv-pill--danger">{{ translate('Denied') }}</span>
                                    @endif
                                </span>
                            </div>

                            @foreach (json_decode($order->offline_payments->payment_info) as $key => $item)
                                @if ($key != 'method_id')
                                    <div class="odv-meta__item">
                                        <span class="odv-meta__label">{{ translate($key) }}</span>
                                        <span class="odv-meta__value">{{ $item }}</span>
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        @if ($order->store && $order->store->module->module_type == 'food')
                            <div class="odv-meta__item">
                                <span class="odv-meta__label">{{ translate('cutlery') }}</span>
                                <span class="odv-meta__value">
                                    @if ($order['cutlery'] == '1')
                                        <span class="odv-pill odv-pill--ok">{{ translate('messages.Yes') }}</span>
                                    @else
                                        <span class="odv-pill odv-pill--neutral">{{ translate('messages.No') }}</span>
                                    @endif
                                </span>
                            </div>
                        @endif
                    </div>

                    @if ($order['cancellation_reason'] || $order['unavailable_item_note'] || $order['delivery_instruction'] || $order['order_note'] || $order['bring_change_amount'] > 0)
                        <div class="odv-notes">
                            @if ($order['cancellation_reason'])
                                <div class="odv-note odv-note--danger">
                                    <i class="tio-clear-circle-outlined"></i>
                                    <div class="odv-note__body">
                                        <span class="odv-note__label">{{ translate('Order cancellation reason') }}</span>
                                        {{ $order['cancellation_reason'] }}
                                        @if ($order['canceled_by'])
                                            — {{ translate('Canceled by') }} {{ $order['canceled_by'] }}
                                        @endif
                                    </div>
                                </div>
                            @endif
                            @if ($order['unavailable_item_note'])
                                <div class="odv-note odv-note--warn">
                                    <i class="tio-warning-outlined"></i>
                                    <div class="odv-note__body">
                                        <span class="odv-note__label">{{ translate('messages.Order unavailable item note') }}</span>
                                        {{ $order['unavailable_item_note'] }}
                                    </div>
                                </div>
                            @endif
                            @if ($order['delivery_instruction'])
                                <div class="odv-note odv-note--warn">
                                    <i class="tio-info-outined"></i>
                                    <div class="odv-note__body">
                                        <span class="odv-note__label">{{ translate('messages.Order delivery instruction') }}</span>
                                        {{ $order['delivery_instruction'] }}
                                    </div>
                                </div>
                            @endif
                            @if ($order['order_note'])
                                <div class="odv-note">
                                    <i class="tio-comment-text-outlined"></i>
                                    <div class="odv-note__body">
                                        <span class="odv-note__label">{{ translate('messages.Order note') }}</span>
                                        {{ $order['order_note'] }}
                                    </div>
                                </div>
                            @endif
                            @if ($order['bring_change_amount'] > 0)
                                <div class="odv-note odv-note--info">
                                    <i class="tio-money-vs"></i>
                                    <div class="odv-note__body">
                                        {{ translate('Please bring change when making the delivery') }}: {{ \App\CentralLogics\Helpers::format_currency($order['bring_change_amount']) }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($order->order_attachment)
                        @php
                            $order_images = json_decode($order->order_attachment,true) ?? [];
                        @endphp
                    <div class="px-20">
                        <h4 class="fs-14 mb-10px">{{ translate('messages.prescription') }}</h4>
                        <div class="tabs-slide-wrap tabs-slide-wrap-prescription position-relative">
                            <div class="tabs-inner d-flex align-items-center gap-xxl-20 gap-3">

                                @foreach ($order_images as $key => $item)
                                            <?php $item = is_array($item)?$item:['img'=>$item,'storage'=>'public']; ?>

                                              <div class="tabs-slide_items">
                                                    <div class="prescription-thumb h-100px aspect-ratio-1 overflow-hidden rounded" data-toggle="modal"
                                                                                data-target="#prescriptionimagemodal{{ $key }}">
                                                                <img src="{{\App\CentralLogics\Helpers::get_full_url('order', $item['img'], $item['storage']??'public') }}" alt="img" class="w-100">
                                                    </div>
                                                </div>
                                            <div class="modal fade" id="prescriptionimagemodal{{ $key }}" tabindex="-1"
                                                 role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h4 class="modal-title" id="myModalLabel">
                                                                {{ translate('messages.prescription') }}</h4>
                                                            <button type="button" class="close"
                                                                    data-dismiss="modal"><span
                                                                    aria-hidden="true">&times;</span><span
                                                                    class="sr-only">{{ translate('messages.Cancel') }}</span></button>
                                                        </div>
                                                        <div class="modal-body scroll-bar">
                                                            <img  src="{{\App\CentralLogics\Helpers::get_full_url('order', $item['img'], $item['storage']??'public') }}"
                                                                  class="initial--22 w-100">
                                                        </div>
                                                        <?php $storage = $item['storage']??'public'; ?>
                                                        <?php $file = $storage == 's3'?base64_encode('order/' . $item['img']):base64_encode('public/order/' . $item['img']); ?>
                                                        <div class="modal-footer">
                                                            <a class="btn btn-primary"
                                                               href="{{ route('admin.business-settings.file-manager.download', [$file,$storage]) }}"><i
                                                                    class="tio-download"></i>
                                                                {{ translate('messages.Download') }}
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach


                            </div>
                            <div class="arrow-area">
                                <div class="button-prev align-items-center">
                                    <button type="button"
                                        class="btn btn-click-prev mr-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                                        <i class="tio-chevron-left fs-24"></i>
                                    </button>
                                </div>
                                <div class="button-next align-items-center">
                                    <button type="button"
                                        class="btn btn-click-next ml-auto border-0 btn-primary rounded-circle fs-12 p-2 d-center">
                                        <i class="tio-chevron-right fs-24"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                     @endif


                    <div class="card-body px-0">
                                <?php

                                $total_addon_price = 0;
                                $product_price = 0;
                                $delivery_fee_info = app(\App\Services\Order\OrderService::class)->adjustedFeeForOrder($order);
                                if ($order->prescription_order == 1) {
                                    $product_price = $order['order_amount'] - $delivery_fee_info['adjusted'] - $order['total_tax_amount'] - $order['dm_tips'] - $order['additional_charge'] + $order['store_discount_amount'];
                                    if($order->tax_status == 'included'){
                                        $product_price += $order['total_tax_amount'];
                                    }
                                }



                                // A bundle is stored as one row per member but bought as one thing, so the
                                // table lists the ordinary lines first and then one row per bundle.
                                $bogoGroups = app(\App\Services\Promotion\BogoOrderService::class)
                                    ->orderGroups($order->details, (int) $order->store_id);
                                $bundleGroups = app(\App\Services\Promotion\BundleOrderService::class)
                                    ->orderGroups($bogoGroups['plain']);
                                $details = $bundleGroups['plain'];
                                foreach ($details as $key => $item) {
                                    $details[$key]->status = true;
                                }
                                ?>
                            <div class="odv-section-head">
                                <h2 class="odv-section-head__title">{{ translate('messages.Order items') }}</h2>
                                <span class="odv-count">{{ $order->details->count() }}</span>
                            </div>
                            <div class="table-responsive datatable-custom pb-0">
                                <table
                                    class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table dataTable no-footer mb-0 odv-items">
                                    <thead class="thead-light">
                                    <tr>
                                        <th class="border-0">#</th>
                                        <th class="border-0">{{ translate('Item details') }}</th>
                                        @if ($order->store && $order->store->module->module_type == 'food')
                                            <th class="border-0">{{ translate('Addons') }}</th>
                                        @endif
                                        <th class="border-0 text-center">{{ translate('messages.QTY') }}</th>
                                        <th class="text-right  border-0">{{ translate('messages.price') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($details as $key => $detail)
                                        @if (isset($detail->item_id) && $detail->status)
                                                <?php
                                                // item_details is the snapshot taken at order time; $detail->item is the
                                                // live record the controller already eager-loaded. Keep them in separate
                                                // variables so the relation survives for the partials rendered below.
                                                $itemSnapshot = json_decode($detail->item_details, true);
                                                $product = $detail->item;
                                                ?>

                                            <tr>
                                                <td class="odv-items__sl">
                                                    {{ $key + 1 }}
                                                </td>
                                                <td>
                                                    <div class="odv-item">
                                                        <a class="odv-item__thumb"
                                                           href="{{ route('admin.item.view', [$itemSnapshot['id'],'module_id' => $order->module_id]) }}">
                                                            <img class="onerror-image"
                                                                 src="{{ $product?->image_full_url ?? asset('public/assets/admin/img/100x100/2.png') }}"
                                                                 data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                                                                 alt="{{ $itemSnapshot['name'] }}">
                                                        </a>
                                                        <div class="odv-item__body">
                                                            <div>
                                                                <strong class="odv-item__name">
                                                                    {{ $itemSnapshot['name'] }}</strong>
                                                                <?php $unitPrice = $detail['price']; ?>
                                                                <span class="odv-item__unit">
                                                                    {{ \App\CentralLogics\Helpers::format_currency($unitPrice) }}
                                                                    {{ translate('messages.each') }}
                                                                </span>
                                                                <div class="odv-item__opts">
                                                                @if ($order->store && $order->store->module->module_type == 'food')
                                                                    @if (isset($detail['variation']) ? json_decode($detail['variation'], true) : [])
                                                                        @foreach (json_decode($detail['variation'], true) as $variation)
                                                                            @if (isset($variation['name']) && isset($variation['values']))
                                                                                <span class="d-block text-capitalize">
                                                                                        <strong>
                                                                                            {{ $variation['name'] }} -
                                                                                        </strong>
                                                                                    </span>
                                                                                @foreach ($variation['values'] as $value)
                                                                                    <span
                                                                                        class="d-block text-capitalize">
                                                                                            &nbsp; &nbsp;
                                                                                            {{ $value['label'] }} :
                                                                                            <strong>{{ \App\CentralLogics\Helpers::format_currency($value['optionPrice']) }}</strong>
                                                                                        </span>
                                                                                @endforeach
                                                                            @else
                                                                                @if (isset(json_decode($detail['variation'], true)[0]))
                                                                                    <strong><u>
                                                                                            {{ translate('messages.variation') }}
                                                                                            : </u></strong>
                                                                                    @foreach (json_decode($detail['variation'], true)[0] as $key1 => $variation)
                                                                                        <div
                                                                                            class="font-size-sm text-body">
                                                                                                <span>{{ $key1 }}
                                                                                                    : </span>
                                                                                            <span
                                                                                                class="font-weight-bold">{{ $variation }}</span>
                                                                                        </div>
                                                                                    @endforeach
                                                                                @endif
                                                                            @endif
                                                                        @endforeach
                                                                    @endif
                                                                @else
                                                                    @if (count(json_decode($detail['variation'], true)) > 0)
                                                                        <strong><u>{{ translate('messages.variation') }}
                                                                                :
                                                                            </u></strong>
                                                                    <?php
                                                                        $detailsVariation = isset(json_decode($detail['variation'], true)[0]) ? json_decode($detail['variation'], true)[0] : json_decode($detail['variation'], true);
                                                                    ?>
                                                                        @foreach ($detailsVariation as $key1 => $variation)
                                                                            @if ($key1 != 'stock')
                                                                                <div class="font-size-sm text-body">
                                                                                        <span>{{ $key1 }} :
                                                                                        </span>
                                                                                    <span class="font-weight-bold">
                                                                                        {{ Str::limit(implode(', ', (array) $variation), 15, '...') }}
                                                                                    </span>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    @endif
                                                                @endif
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                @if ($order->store && $order->store->module->module_type == 'food')
                                                    <td>
                                                        <div class="odv-item__opts">
                                                            @foreach (json_decode($detail['add_ons'], true) as $key2 => $addon)
                                                                @if ($key2 == 0)
                                                                    <span class="odv-item__opts-title">{{ translate('Addons') }}</span>
                                                                @endif
                                                                <div>
                                                                        <span>{{ Str::limit($addon['name'], 20, '...') }}
                                                                            : </span>
                                                                    <span class="font-weight-bold">
                                                                            {{ $addon['quantity'] }} x
                                                                            {{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}
                                                                        </span>
                                                                </div>
                                                                <?php $total_addon_price += $addon['price'] * $addon['quantity']; ?>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                @endif
                                                <td class="odv-qty">
                                                    &times;{{ $detail['quantity'] }}
                                                </td>
                                                <td class="odv-line-total">
                                                    <?php
                                                        $amount = $detail['price'] * $detail['quantity'];
                                                        $lineTotal = $unitPrice * $detail['quantity'];
                                                    ?>
                                                    {{ \App\CentralLogics\Helpers::format_currency($lineTotal) }}
                                                </td>
                                            </tr>

                                            <?php $product_price += $amount; ?>



                                        @elseif(isset($detail->item_campaign_id) && $detail->status)
                                                <?php
                                                // Same split as the item branch above: snapshot in a local, live record
                                                // from the relation the controller already eager-loaded.
                                                $campaignSnapshot = json_decode($detail->item_details, true);
                                                $campaign = $detail->campaign;
                                                ?>
                                            <tr>
                                                <td class="odv-items__sl">
                                                    {{ $key + 1 }}
                                                </td>
                                                <td>
                                                    <div class="odv-item">
                                                        <a class="odv-item__thumb"
                                                            href="{{ route('admin.campaign.view', ['item', $campaignSnapshot['id']]) }}">
                                                            <img class="onerror-image"
                                                                src="{{ $campaign?->image_full_url ?? asset('public/assets/admin/img/900x400/img1.jpg') }}"
                                                                data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                                                alt="{{ $campaignSnapshot['title'] ?? ($campaign?->title ?? '') }}">
                                                        </a>

                                                        <div class="odv-item__body">
                                                            <div>
                                                                <strong
                                                                    class="odv-item__name">{{ Str::limit($campaignSnapshot['title'] ?? ($campaign?->title ?? ''), 20, '...') }}</strong>

                                                                <?php $unitPrice = $detail['price']; ?>
                                                                <span class="odv-item__unit">
                                                                    {{ \App\CentralLogics\Helpers::format_currency($unitPrice) }}
                                                                    {{ translate('messages.each') }}
                                                                </span>
                                                                <div class="odv-item__opts">
                                                                @if ($order->store && $order->store->module->module_type == 'food')
                                                                    @if (isset($detail['variation']) ? json_decode($detail['variation'], true) : [])
                                                                        @foreach (json_decode($detail['variation'], true) as $variation)
                                                                            @if (isset($variation['name']) && isset($variation['values']))
                                                                                <span class="d-block text-capitalize">
                                                                                        <strong>
                                                                                            {{ $variation['name'] }} -
                                                                                        </strong>
                                                                                    </span>
                                                                                @foreach ($variation['values'] as $value)
                                                                                    <span
                                                                                        class="d-block text-capitalize">
                                                                                            &nbsp; &nbsp;
                                                                                            {{ $value['label'] }} :
                                                                                            <strong>{{ \App\CentralLogics\Helpers::format_currency($value['optionPrice']) }}</strong>
                                                                                        </span>
                                                                                @endforeach
                                                                            @else
                                                                                @if (isset(json_decode($detail['variation'], true)[0]))
                                                                                    <strong><u>
                                                                                            {{ translate('messages.variation') }}
                                                                                            : </u></strong>
                                                                                    @foreach (json_decode($detail['variation'], true)[0] as $key1 => $variation)
                                                                                        <div
                                                                                            class="font-size-sm text-body">
                                                                                                <span>{{ $key1 }}
                                                                                                    : </span>
                                                                                            <span
                                                                                                class="font-weight-bold">{{ $variation }}</span>
                                                                                        </div>
                                                                                    @endforeach
                                                                                @endif
                                                                            @endif
                                                                        @endforeach
                                                                    @endif
                                                                @else
                                                                    @if (count(json_decode($detail['variation'], true)) > 0)
                                                                        <strong><u>{{ translate('messages.variation') }}
                                                                                :</u></strong>
                                                                        @foreach (json_decode($detail['variation'], true)[0] as $key1 => $variation)
                                                                            @if ($key1 != 'stock')
                                                                                <div class="font-size-sm text-body">
                                                                                        <span>{{ $key1 }} :
                                                                                        </span>
                                                                                    <span
                                                                                        class="font-weight-bold">{{ Str::limit($variation, 15, '...') }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    @endif
                                                                @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                @if ($order->store && $order->store->module->module_type == 'food')
                                                    <td>
                                                        <div class="odv-item__opts">
                                                            @foreach (json_decode($detail['add_ons'], true) as $key2 => $addon)
                                                                @if ($key2 == 0)
                                                                    <span class="odv-item__opts-title">{{ translate('Addons') }}</span>
                                                                @endif
                                                                <div>
                                                                        <span>{{ Str::limit($addon['name'], 20, '...') }}
                                                                            : </span>
                                                                    <span class="font-weight-bold">
                                                                            {{ $addon['quantity'] }} x
                                                                            {{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}
                                                                        </span>
                                                                </div>
                                                                <?php $total_addon_price += $addon['price'] * $addon['quantity']; ?>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                @endif
                                                <td class="odv-qty">
                                                    &times;{{ $detail['quantity'] }}
                                                </td>
                                                <td class="odv-line-total">
                                                    <?php
                                                        $amount = $detail['price'] * $detail['quantity'];
                                                        $lineTotal = $unitPrice * $detail['quantity'];
                                                    ?>
                                                    {{ \App\CentralLogics\Helpers::format_currency($lineTotal) }}
                                                </td>
                                            </tr>

                                            <?php $product_price += $amount; ?>


                                        @endif
                                    @endforeach
                                    {{-- One row per bundle, after the ordinary items. A bundle is
                                         one thing the customer chose, so it is one line here; its
                                         total joins the same running sum the summary below uses. --}}
                                    @foreach ($bogoGroups['bundles'] as $bogo)
                                        @include('partials.promotion._bogo_order_row', [
                                            'bogo' => $bogo,
                                            'sl' => $details->count() + $loop->iteration,
                                            'hasAddons' => $order->store && $order->store->module->module_type == 'food',
                                            'hasQty' => true,
                                        ])
                                        <?php $product_price += $bogo['total']; ?>
                                    @endforeach
                                    @foreach ($bundleGroups['bundles'] as $bundle)
                                        @include('partials.bundle._order_row', [
                                            'bundle' => $bundle,
                                            'sl' => $details->count() + count($bogoGroups['bundles']) + $loop->iteration,
                                            'hasAddons' => $order->store && $order->store->module->module_type == 'food',
                                            'hasQty' => true,
                                        ])
                                        <?php $product_price += $bundle['total']; ?>
                                    @endforeach
                                    </tbody>

                                </table>
                    @include('partials.promotion._bogo_order_modals', ['bogoGroups' => $bogoGroups])
                    @include('partials.bundle._order_modals', ['bundleGroups' => $bundleGroups])
                            </div>
                                <?php

                                $total_tax_amount = $order['total_tax_amount'];
                                if($order->tax_status == 'included'){
                                    $total_tax_amount=0;
                                }

                                ?>

                        <div class="odv-summary-wrap">
                            <dl class="odv-summary">

                                    <dt class="col-6">{{ translate('messages.Items price') }}</dt>
                                    <dd class="col-6">
                                        {{ \App\CentralLogics\Helpers::format_currency($product_price) }}</dd>
                                    @if ($order->store && $order->store->module->module_type == 'food')
                                        <dt class="col-6">{{ translate('messages.Addon cost') }}</dt>
                                        <dd class="col-6">
                                            {{ \App\CentralLogics\Helpers::format_currency($total_addon_price) }}
                                        </dd>
                                    @endif

                                    <dt class="col-6">{{ translate('messages.subtotal') }}
                                        @if ($order->tax_status == 'included')
                                            ({{ translate('TAX included') }})
                                        @endif</dt>
                                    <dd class="col-6">
                                        {{ \App\CentralLogics\Helpers::format_currency($product_price + $total_addon_price) }}
                                    </dd>
                                    {{-- The tooltip names the promotion, so it reads the accessors rather than
                                         store_discount_amount: that column also carries the items' own discounts
                                         and the bundle reduction, so testing it > 0 labelled plain item discounts
                                         and bundle-only orders "store discount applied". --}}
                                    <dt class="col-6">{{ translate('Discount') }}
                                        @if ($order->is_happy_hour)
                                            <i class="tio-info-outined" data-toggle="tooltip"
                                               title="{{ translate('messages.happy_hour_discount_applied') }}"></i>
                                        @elseif ($order->is_store_discount)
                                            <i class="tio-info-outined" data-toggle="tooltip"
                                               title="{{ translate('messages.store_discount_applied') }}"></i>
                                        @endif</dt>
                                    <dd class="col-6">
                                        - {{ \App\CentralLogics\Helpers::format_currency($order['store_discount_amount'] + $order['flash_admin_discount_amount']  + $order['flash_store_discount_amount']) }}
                                    </dd>



                                    <dt class="col-6">{{ translate('Coupon discount') }}
                                        @if ($order['coupon_discount_amount'] > 0 && $order->orderProDiscount && $order->orderProDiscount->benefit_type === 'coupon')
                                            <i class="tio-info-outined" data-toggle="tooltip"
                                               title="{{ translate('Pro customer coupon applied.') }}"></i>
                                        @endif</dt>
                                    <dd class="col-6">
                                        - {{ \App\CentralLogics\Helpers::format_currency($order['coupon_discount_amount']) }}
                                    </dd>



                                     @if ($order->extra_discount_amount > 0)
                                    <dt class="col-6">{{ translate('Extra discount') }}</dt>
                                    <dd class="col-6">
                                        - {{ \App\CentralLogics\Helpers::format_currency($order->extra_discount_amount) }}
                                    </dd>

                                    @endif
                                        @if ($order['ref_bonus_amount'] > 0)
                                            <dt class="col-6">{{ translate('Referral discount') }}</dt>
                                            <dd class="col-6">
                                                - {{ \App\CentralLogics\Helpers::format_currency($order['ref_bonus_amount']) }}
                                            </dd>
                                        @endif
                                        @if (($order->orderProDiscount?->amount_saved ?? 0) > 0)
                                            <dt class="col-6">{{ translate('messages.Pro discount') }}</dt>
                                            <dd class="col-6">
                                                - {{ \App\CentralLogics\Helpers::format_currency($order->orderProDiscount->amount_saved) }}
                                            </dd>
                                        @endif
                                        @include('partials.pro-delivery-discount-row', ['order' => $order, 'layout' => 'dl', 'dtClass' => '', 'ddClass' => ''])
                                        @if ($order->tax_status == 'excluded' && $total_tax_amount > 0 || $order->tax_status == null  )
                                            <dt class="col-6">{{ translate('VAT/tax') }}</dt>
                                            <dd class="col-6">
                                                +
                                                {{ \App\CentralLogics\Helpers::format_currency($total_tax_amount) }}
                                            </dd>

                                        @endif

                                    @if ($order['extra_packaging_amount'] > 0)
                                        <dt class="col-6">{{ translate('messages.Extra Packaging Amount') }}</dt>
                                        <dd class="col-6">
                                            + {{ \App\CentralLogics\Helpers::format_currency($order['extra_packaging_amount']) }}
                                        </dd>
                                    @endif

                                         <dt class="col-6">{{ translate('Delivery fee') }}
                                             @if (
                                                 $order->orderProDiscount
                                                 && $order->orderProDiscount->benefit_type === 'delivery_fee'
                                                 && ($order->orderProDiscount->delivery_fee_reduction_amount ?? 0) > 0
                                             )
                                                 @if ($order->orderProDiscount->delivery_offer_type === 'full_free')
                                                     <i class="tio-info-outined" data-toggle="tooltip"
                                                        title="{{ translate('Pro customer free delivery has been applied to this order.') }}"></i>
                                                 @else
                                                     <i class="tio-info-outined" data-toggle="tooltip"
                                                        title="{{ translate('Pro customer partial delivery discount has been applied to this order') }} ({{ (float) ($order->orderProDiscount->delivery_charge_discount_percentage ?? 0) }}%)"></i>
                                                 @endif
                                             @elseif ($order->free_delivery_by == 'admin')
                                                 <i class="tio-info-outined" data-toggle="tooltip" title="{{ translate('Delivery fee is applicable and will be covered by the admin.') }}"></i>
                                             @elseif ($order->free_delivery_by == 'vendor')
                                                 <i class="tio-info-outined" data-toggle="tooltip" title="{{ translate('Delivery fee is applicable and will be covered by the vendor.') }}"></i>
                                             @elseif ($order->surge_amount > 0)
                                                 <i class="tio-info-outined" data-toggle="tooltip"
                                                    title="{{ translate('messages.surge_price') }} {{ \App\CentralLogics\Helpers::format_currency($order->surge_amount) }}"></i>
                                             @endif
                                                 :</dt>
                                         <dd class="col-6 text-dark fs-14">
                                             + {{ \App\CentralLogics\Helpers::format_currency(app(\App\Services\Order\OrderService::class)->proDeliveryBreakdown($order)['original_fee']) }}

                                         </dd>
                                         @include('partials.delivery-type-row', ['order' => $order, 'layout' => 'dl', 'dtClass' => '', 'ddClass' => ''])
                                    <dt class="col-6">{{ translate('Deliveryman tips') }}</dt>
                                    <dd class="col-6">
                                        + {{ \App\CentralLogics\Helpers::format_currency($order['dm_tips']) }}</dd>
                                    <dt class="col-6">{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? translate('Additional charge') }}</dt>

                                    <dd class="col-6">
                                        + {{ \App\CentralLogics\Helpers::format_currency($order['additional_charge']) }}</dd>

                                    @if ($order['extra_packaging_amount'] > 0)
                                        <dt class="col-6">{{ translate('Extra packaging amount') }}</dt>
                                        <dd class="col-6">
                                            + {{ \App\CentralLogics\Helpers::format_currency($order['extra_packaging_amount']) }}
                                        </dd>
                                    @endif

                                    <div class="odv-summary__rule"></div>
                                    <dt class="col-6 odv-summary__total-label">{{ translate('messages.Total') }} {{ $order->tax_status == 'included' ? '('.translate('TAX included').')'  :'' }}</dt>
                                    <dd class="col-6 odv-summary__total-value">
                                        {{ \App\CentralLogics\Helpers::format_currency($order->order_amount )  }}
                                    </dd>
                                    @if ($order?->payments)
                                        @foreach ($order?->payments as $payment)
                                            @if ($payment->payment_status == 'paid')
                                                @if ( $payment->payment_method == 'cash_on_delivery')

                                                    <dt class="col-6">{{ translate('messages.Paid with Cash') }} ({{  translate('COD')}})</dt>
                                                @else

                                                    <dt class="col-6">{{ translate('Paid by') }} {{  payment_method_label($payment->payment_method)}}</dt>
                                                @endif
                                            @else

                                                <dt class="col-6">{{ translate('Due amount') }} ({{  $payment->payment_method == 'cash_on_delivery' ?  translate('messages.COD') : payment_method_label($payment->payment_method) }})</dt>
                                            @endif
                                            <dd class="col-6">
                                                {{ \App\CentralLogics\Helpers::format_currency($payment->amount) }}
                                            </dd>
                                        @endforeach
                                    @endif
                            </dl>
                        </div>
                        @if ($order->edited)
                            <div class="odv-summary__note">
                                <div class="odv-note odv-note--warn">
                                    <i class="tio-info"></i>
                                    <div class="odv-note__body">
                                        {{translate('Total bill has been updated after the edits.')}}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4 order-print-area-right odv-side">
                @if ($order->order_status == 'canceled')

                    <div class="card">
                        <div class="card-body">
                            <div class="odv-sec__head">
                                <span class="odv-sec__icon"><i class="tio-clear-circle-outlined"></i></span>
                                <h2 class="odv-sec__title">{{ translate('Canceled') }}</h2>
                            </div>

                            <div class="odv-kv">
                                <div class="odv-kv__row">
                                    <span class="odv-kv__label">{{ translate('Cancel reason') }}</span>
                                    <span class="odv-kv__value">{{ $order->cancellation_reason ?? translate('messages.N/A') }}</span>
                                </div>
                                <div class="odv-kv__row">
                                    <span class="odv-kv__label">{{ translate('Cancel note') }}</span>
                                    <span class="odv-kv__value">{{ $order->cancellation_note ?? translate('messages.N/A') }}</span>
                                </div>
                                <div class="odv-kv__row">
                                    <span class="odv-kv__label">{{ translate('Canceled by') }}</span>
                                    <span class="odv-kv__value">{{ translate($order->canceled_by) }}</span>
                                </div>
                                @if ($order->payment_status == 'paid' || $order->payment_status == 'partially_paid' )
                                    @if ( $order?->payments)
                                        <?php $pay_infos =$order->payments()->where('payment_status','paid')->get(); ?>
                                        @foreach ($pay_infos as $pay_info)
                                            <div class="odv-kv__row">
                                                <span class="odv-kv__label">{{ translate('Amount paid by') }} {{ payment_method_label($pay_info->payment_method) }}</span>
                                                <span class="odv-kv__value">{{ \App\CentralLogics\Helpers::format_currency($pay_info->amount)  }}</span>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="odv-kv__row">
                                            <span class="odv-kv__label">{{ translate('Amount paid by') }} {{ payment_method_label($order->payment_method) }}</span>
                                            <span class="odv-kv__value">{{ \App\CentralLogics\Helpers::format_currency($order->order_amount)  }}</span>
                                        </div>
                                    @endif

                                    @if ( $order?->payments)
                                        <?php $amount =$order->payments()->where('payment_status','paid')->sum('amount'); ?>
                                        <div class="odv-kv__row">
                                            <span class="odv-kv__label">{{ translate('Amount returned to wallet') }}</span>
                                            <span class="odv-kv__value">{{ \App\CentralLogics\Helpers::format_currency($amount)  }}</span>
                                        </div>
                                    @else
                                        <div class="odv-kv__row">
                                            <span class="odv-kv__label">{{ translate('Amount returned to wallet') }}</span>
                                            <span class="odv-kv__value">{{ \App\CentralLogics\Helpers::format_currency($order->order_amount)  }}</span>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                @endif
                <?php $refund = \App\Models\BusinessSetting::where(['key' => 'refund_active_status'])->first(); ?>

                @if (!empty($order->refund))
                    @if (
                        $order->order_status == 'refund_requested' ||
                            $order->order_status == 'refunded' ||
                            $order->order_status == 'refund_request_canceled')
                        <div class="card">
                            <div class="card-body">
                                <div class="odv-sec__head">
                                    <span class="odv-sec__icon"><i class="tio-money"></i></span>
                                    <h2 class="odv-sec__title">{{ translate('Refund request') }}</h2>
                                    @if ($order->order_status == 'refund_requested')
                                        <span class="odv-pill odv-pill--warn odv-sec__aside">{{ translate('Pending') }}</span>
                                    @elseif($order->order_status == 'refunded')
                                        <span class="odv-pill odv-pill--info odv-sec__aside">{{ translate('Refunded') }}</span>
                                    @elseif($order->refund->order_status == 'refund_request_canceled')
                                        <span class="odv-pill odv-pill--danger odv-sec__aside">{{ translate('messages.rejected') }}</span>
                                    @endif
                                </div>

                                <div class="odv-kv mb-3">
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('messages.Requested at') }}</span>
                                        <span class="odv-kv__value">{{ date('d M Y ' . config('timeformat'), strtotime($order->refund->created_at)) }}</span>
                                    </div>
                                </div>

                                <div class="odv-thumbs">
                                    <?php $data = isset($order->refund->image) ? json_decode($order->refund->image, true) : 0; ?>
                                    @if ($data)
                                        @foreach ($data as $key => $img)
                                            <?php $img = is_array($img)?$img:['img'=>$img,'storage'=>'public']; ?>
                                            <img class="odv-thumb onerror-image" data-toggle="modal"
                                                 data-target="#imagemodal{{ $key }}"
                                                 data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                                 src="{{ \App\CentralLogics\Helpers::get_full_url('refund',$img['img'],$img['storage']) }}"
                                                 alt="{{ translate('Refund Image') }}">
                                            <div class="modal fade" id="imagemodal{{ $key }}" tabindex="-1"
                                                 role="dialog" aria-labelledby="myModalLabel{{ $key }}"
                                                 aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h4 class="modal-title"
                                                                id="myModalLabel{{ $key }}">
                                                                {{ translate('Refund Image') }}</h4>
                                                            <button type="button" class="close"
                                                                    data-dismiss="modal"><span
                                                                    aria-hidden="true">&times;</span><span
                                                                    class="sr-only">{{ translate('messages.Cancel') }}</span></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <img
                                                                src="{{ \App\CentralLogics\Helpers::get_full_url('refund',$img['img'],$img['storage']) }}"

                                                                class="initial--22 w-100">
                                                        </div>
                                                        <?php $storage = $img['storage']??'public'; ?>
                                                        <?php $file = $storage == 's3'?base64_encode('refund/' . $img['img']):base64_encode('public/refund/' . $img['img']); ?>
                                                        <div class="modal-footer">
                                                            <a class="btn btn-primary"
                                                               href="{{ route('admin.business-settings.file-manager.download', [$file,$storage]) }}"><i
                                                                    class="tio-download"></i>
                                                                {{ translate('messages.Download') }}
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <img class="odv-thumb onerror-image"
                                             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                             src="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                             alt="{{ translate('Refund Image') }}">
                                    @endif
                                </div>

                                <div class="odv-kv mt-3">
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('Reason') }}</span>
                                        <span class="odv-kv__value">{{ $order->refund->customer_reason }}</span>
                                    </div>
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('Amount') }}</span>
                                        <span class="odv-kv__value">{{ $order->refund->refund_amount }}</span>
                                    </div>
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('method') }}</span>
                                        <span class="odv-kv__value">{{ $order->refund->refund_method }}</span>
                                    </div>
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('Status') }}</span>
                                        <span class="odv-kv__value">{{ $order->refund->refund_status }}</span>
                                    </div>
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('Admin Note') }}</span>
                                        <span class="odv-kv__value">{{ $order->refund->admin_note ?? translate('messages.N/A') }}</span>
                                    </div>
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('Customer note') }}</span>
                                        <span class="odv-kv__value">{{ $order->refund->customer_note ?? translate('messages.N/A') }}</span>
                                    </div>
                                </div>
                                @if ($order->store)
                                    <div class="btn--container refund--btn mt-3">
                                        @if (
                                            (($refund && $refund->value == true) || $order->order_status == 'refund_requested') &&
                                                $order->payment_status == 'paid' &&
                                                $order->order_status != 'refunded')
                                            <button class="btn btn--primary btn--sm route-alert"
                                                    data-url="{{ route('admin.order.status', ['id' => $order['id'],'order_status' => 'refunded',
                                            ]) }}" data-message="{{ translate('messages.You want to refund this order') . ' ' . translate('Refund amount') . ': ' . \App\CentralLogics\Helpers::format_currency($refund_amount) }}" data-title="{{ translate('Are you sure want to refund?') }}"
                                            ><i
                                                    class="tio-money"></i> <span
                                                    class="ml-1">{{ translate('messages.Refund') }}</span> </button>
                                        @endif
                                        @if ($order->order_status == 'refund_requested' )
                                            <button type="button" class="btn btn--danger btn-outline-danger"
                                                    data-toggle="modal" data-target="#refund_cancelation_note">
                                                <i class="tio-money"></i> <span
                                                    class="ml-1">{{ translate('messages.Cancel Refund') }}</span> </button>
                                        @endif
                                    </div>

                                @endif
                            </div>
                        </div>
                    @endif
                @endif



                @if ( !in_array($order->order_status, ['refund_requested', 'refunded', 'refund_request_canceled', 'delivered','canceled']) )
                    <div class="card">
                        <div class="card-body">
                            <div class="odv-sec__head">
                                <span class="odv-sec__icon">
                                    <img class="svg" src="{{asset('public/assets/admin/img/icons/shop-bag.svg')}}" alt="">
                                </span>
                                <h2 class="odv-sec__title">{{ translate('Order setup') }}</h2>
                            </div>
                            @if ($order?->offline_payments?->status == 'denied')
                                <div class="odv-note odv-note--danger mb-3">
                                    <i class="tio-clear-circle-outlined"></i>
                                    <div class="odv-note__body">
                                        <span class="odv-note__label">{{ translate('Denied note') }}</span>
                                        {{ $order?->offline_payments?->note }}
                                    </div>
                                </div>
                            @endif
                            {{-- No wrapper here. There used to be an empty `<div class="">` whose
                                 closing tag sat inside the @else branch, so an unpaid order left it
                                 open and the rest of the sidebar nested inside this card. --}}
                                @if($order->is_unpaid_order)
                                    <div class="odv-panel">
                                        <h3 class="odv-panel__title text-danger">{{ translate('Payment failed') }}</h3>
                                        <?php $isCashOnDelivery = App\CentralLogics\Helpers::get_business_settings('cash_on_delivery')['status'] ?? false; ?>
                                        <?php $isZoneCashOnDelivery = $order?->zone->cash_on_delivery; ?>
                                        @if($isCashOnDelivery && $isZoneCashOnDelivery)
                                            <p class="odv-panel__text">{{ translate('messages.The customer\'s payment couldn\'t be processed. Please switch to COD.') }}</p>
                                        @endif
                                        <div class="btn--container justify-content-center">
                                            @if($isCashOnDelivery && $isZoneCashOnDelivery)
                                            <button type="button" class="btn btn--primary btn-sm form-alert"
                                                    data-id="order-{{$order['id']}}"
                                                    data-cancel-btn="{{ translate('messages.Cancel') }}"
                                                    data-confirm-btn="{{ translate('messages.Confirm') }}"
                                                    data-image-url="{{ asset('public/assets/admin/img/tughrik.png') }}"
                                                    data-title="{{ translate('Switch to Cash on Delivery?') }}"
                                                    data-message="{{ translate('The customer\'s digital payment failed. Confirm the issue with them before switching this order to cash on delivery.') }}">
                                                <i class="tio-sync"></i> {{ translate('messages.Switch to COD') }}</button>
                                            <form action="{{route('admin.order.switch_to_cod',[$order['id']])}}"
                                              method="post" id="order-{{$order['id']}}">
                                            @csrf
                                            </form>
                                            @endif
                                            <button type="button" data-toggle="modal" data-target="#offline_payment_cancel_orders" class="btn btn-outline-secondary"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel order') }}</button>

                                        </div>

                                    </div>
                                @else
                                    @if($order?->payment_method == 'offline_payment' && !in_array($order->order_status, ['canceled']))
                                        {{-- The `pending` branch used to close this panel early and leave a
                                             stray </div> behind; the branches all end at the same depth now. --}}
                                        <div class="odv-panel">
                                            <h3 class="odv-panel__title">
                                                {{ $order?->offline_payments?->status == 'verified'?translate('Payment Verified'):translate('Payment verification') }}
                                            </h3>

                                            @if ($order?->offline_payments?->status == 'pending')
                                                <p class="odv-panel__text text-danger">{{ translate('Please verify the payment before confirming the order.') }}</p>
                                                <div class="btn--container justify-content-center">
                                                    <button  type="button" class="btn btn--primary btn-sm" data-toggle="modal" data-target="#verifyViewModal" ><i class="tio-verified-outlined"></i> {{ translate('Verify payment') }}</button>

                                                    <button type="button" data-toggle="modal" data-target="#offline_payment_cancel_orders" class="btn btn-outline-secondary"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel order') }}</button>
                                                </div>
                                            @elseif($order?->offline_payments?->status == 'verified')
                                                <div class="btn--container justify-content-center">
                                                    <button  type="button" class="btn btn--primary btn-sm" data-toggle="modal" data-target="#verifyViewModal" ><i class="tio-receipt-outlined"></i> {{ translate('messages.Payment Details') }}</button>
                                                </div>
                                            @elseif($order?->offline_payments?->status == 'denied')
                                                <div class="btn--container justify-content-center">
                                                    <button  type="button" class="btn btn--primary btn-sm" data-toggle="modal" data-target="#verifyViewModal" ><i class="tio-verified-outlined"></i> {{ translate('messages.Recheck Verification') }}</button>
                                                    <button type="button" data-toggle="modal" data-target="#offline_payment_cancel_orders" class="btn btn-outline-secondary"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel order') }}</button>

                                                </div>
                                            @elseif(!$order?->offline_payments)
                                                <p class="odv-panel__text text-danger">{{ translate('Please verify the payment before confirming the order.') }}</p>
                                                <div class="btn--container justify-content-center">
                                                    <button  type="button" class="btn btn--primary btn-sm" data-toggle="modal" data-target="#verifyViewModal" ><i class="tio-verified-outlined"></i> {{ translate('Verify payment') }}</button>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($order->payment_method != 'offline_payment' || ($order?->offline_payments && $order?->offline_payments?->status == 'verified'))
                                        @if ( !in_array($order->order_status, [ 'refunded', 'refund_request_canceled']))
                                            <div class="hs-unfold w-100 odv-status-select">
                                                <label for="dropdownMenuButton">{{ translate('Order status') }}</label>
                                                <div class="dropdown">
                                                    <button
                                                        class="form-control h--45px dropdown-toggle d-flex justify-content-between align-items-center w-100"
                                                        type="button" id="dropdownMenuButton" data-toggle="dropdown"
                                                        aria-haspopup="true" aria-expanded="false">
                                                        {{ $orderStatusLabels[$order['order_status']] ?? translate('messages.Status') }}
                                                    </button>
                                                    <?php $order_delivery_verification = (bool) \App\CentralLogics\Helpers::get_business_settings('order_delivery_verification', false); ?>
                                                    <div class="dropdown-menu text-capitalize" aria-labelledby="dropdownMenuButton">
                                                        <a class="dropdown-item {{ $order['order_status'] == 'pending' ? 'active' : '' }} route-alert"
                                                        data-url="{{ route('admin.order.status', ['id' => $order['id'], 'order_status' => 'pending']) }}" data-message="{{ translate('Change status to pending?') }}"
                                                        href="javascript:">{{ translate('Pending') }}</a>
                                                        <a class="dropdown-item {{ $order['order_status'] == 'confirmed' ? 'active' : '' }} route-alert"
                                                        data-url="{{ route('admin.order.status', ['id' => $order['id'], 'order_status' => 'confirmed']) }}" data-message="{{ translate('Change status to confirmed?') }}"
                                                        href="javascript:">{{ translate('messages.confirmed') }}</a>
                                                        @if ($order->order_type != 'parcel')
                                                            @if ($order->store && $order->store->module->module_type == 'food')
                                                                <a href="javascript:" class="dropdown-item {{ $order['order_status'] == 'processing' ? 'active' : '' }} order_status_change_alert" data-url="{{ route('admin.order.status', ['id' => $order['id'], 'order_status' => 'processing']) }}" data-message="{{ translate('Change status to cooking?') }}" data-processing="{{ $order->processing_time ?? 30 }}">{{ translate('Processing') }}</a>
                                                            @else
                                                                <a class="dropdown-item {{ $order['order_status'] == 'processing' ? 'active' : '' }} route-alert"
                                                                data-url="{{ route('admin.order.status', ['id' => $order['id'], 'order_status' => 'processing']) }}" data-message="{{ translate('Change status to processing?') }}"
                                                                href="javascript:">{{ translate('Processing') }}</a>
                                                            @endif
                                                            <a class="dropdown-item {{ $order['order_status'] == 'handover' ? 'active' : '' }} route-alert"
                                                            data-url="{{ route('admin.order.status', ['id' => $order['id'], 'order_status' => 'handover']) }}" data-message="{{ translate('Change status to handover?') }}"
                                                            href="javascript:">{{ translate('messages.handover') }}</a>
                                                        @endif
                                                        <a class="dropdown-item {{ $order['order_status'] == 'picked_up' ? 'active' : '' }} route-alert"
                                                        data-url="{{ route('admin.order.status', ['id' => $order['id'], 'order_status' => 'picked_up']) }}" data-message="{{ translate('Change status to out for delivery?') }}"
                                                        href="javascript:">{{ translate('Out for delivery') }}</a>
                                                        <a class="dropdown-item {{ $order['order_status'] == 'delivered' ? 'active' : '' }} route-alert"
                                                        data-url="{{ route('admin.order.status', ['id' => $order['id'], 'order_status' => 'delivered']) }}" data-message="{{ translate('Change status to delivered (payment status will be paid if not)?') }}"
                                                        href="javascript:">{{ translate('Delivered') }}</a>
                                                        <a class="dropdown-item {{ $order['order_status'] == 'canceled' ? 'active' : '' }}" data-toggle="modal" data-target="#offline_payment_cancel_orders">{{ translate('Canceled') }}</a>
                                                    </div>

                                                </div>
                                            </div>
                                        @endif
                                        @if (!in_array($order->order_status, [ 'refunded','delivered', 'canceled']) &&  ( !$order->delivery_man && $order['order_type'] != 'take_away' && (($order->store && !$order->wasSelfDelivery()))))
                                            <div class="w-100 text-center odv-assign-btn">
                                                <button type="button" class="btn btn--primary w-100" data-toggle="modal"
                                                        data-target="#myModal" data-lat='21.03' data-lng='105.85'>
                                                    <i class="tio-user-add"></i> {{ translate('messages.Assign delivery man manually') }}
                                                </button>
                                            </div>
                                        @endif
                                    @endif
                                @endif
                        </div>
                    </div>
                @endif

                @if ($order->delivery_man && $order['order_type'] != 'take_away' && $order->store)
                    <div class="card">
                        <div class="card-body">
                            <div class="odv-sec__head">
                                <span class="odv-sec__icon"><i class="tio-bike"></i></span>
                                <h2 class="odv-sec__title">
                                    {{ translate('Deliveryman') }}
                                    @if ($order->wasSelfDelivery())
                                        ({{ translate('messages.Store') }})
                                    @endif
                                </h2>
                                @if (!isset($order->delivered) && !$order->wasSelfDelivery())
                                    <a type="button" href="#myModal" class="odv-sec__aside cursor-pointer"
                                       data-toggle="modal" data-target="#myModal">
                                        {{ translate('messages.change') }}
                                    </a>
                                @endif
                            </div>

                            <a class="odv-party"
                               href="{{ !$order->wasSelfDelivery() ?  route('admin.users.delivery-man.preview', [$order->delivery_man['id']]) : '#' }}">
                                <div class="odv-party__avatar">
                                    <img class="onerror-image"
                                         data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                         src="{{ $order->delivery_man?->image_full_url ?? asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                         alt="{{ $order->delivery_man['f_name'] }}">
                                </div>
                                <div class="odv-party__body">
                                    <span class="odv-party__name">{{ $order->delivery_man['f_name'] . ' ' . $order->delivery_man['l_name'] }}</span>
                                    <div class="odv-party__meta">
                                        <span>
                                            <i class="tio-shopping-basket-outlined"></i>
                                            {{ $order->delivery_man->orders_count }}
                                            {{ translate('messages.Orders delivered') }}
                                        </span>
                                        <span><i class="tio-call-talking-quiet"></i>{{ $order->delivery_man['phone'] }}</span>
                                        <span><i class="tio-email-outlined"></i>{{ $order->delivery_man['email'] }}</span>
                                    </div>
                                </div>
                            </a>

                            <?php $address = $order->dm_last_location; ?>
                            <div class="odv-kv mt-3">
                                <div class="odv-kv__row">
                                    <span class="odv-kv__label">{{ translate('messages.Last location') }}</span>
                                    <span class="odv-kv__value">
                                        @if (isset($address))
                                            <a target="_blank"
                                               href="http://maps.google.com/maps?z=12&t=m&q=loc:{{ $address['latitude'] }}+{{ $address['longitude'] }}">
                                                {{ $address['location'] }}
                                            </a>
                                        @else
                                            {{ translate('messages.Location not found') }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-body">
                        <div class="odv-sec__head">
                            <span class="odv-sec__icon"><i class="tio-user"></i></span>
                            <h2 class="odv-sec__title">{{ translate('Customer information') }}</h2>
                        </div>
                        @if ($order->customer && $order->is_guest == 0)
                            <a class="odv-party"
                               href="{{ route('admin.users.customer.view', [$order->customer['id']]) }}">
                                @include('partials._user-avatar', [
                                    'imageUrl'     => $order->customer->image_full_url,
                                    'proStatus'    => $order->customer->pro_status ?? false,
                                    'size'         => 44,
                                    'wrapperClass' => 'odv-party__avatar',
                                    'alt'          => $order->customer['f_name'],
                                ])
                                <div class="odv-party__body">
                                    <span class="odv-party__name">
                                        {{ $order->customer['f_name'] . ' ' . $order->customer['l_name'] }}
                                    </span>
                                    <div class="odv-party__meta">
                                        <span><i class="tio-shopping-basket-outlined"></i>{{ $order->customer->orders_count }} {{ translate('messages.Orders') }}</span>
                                        <span><i class="tio-call-talking-quiet"></i>{{ $order->customer['phone'] }}</span>
                                        <span><i class="tio-email"></i>{{ $order->customer['email'] }}</span>
                                    </div>
                                </div>
                            </a>
                        @elseif($order->is_guest)
                            <div class="odv-empty">{{ translate('Guest user') }}</div>
                        @else
                            <div class="odv-empty">{{ translate('No data found') }}</div>
                        @endif

                        @if ($order->receiver_details)
                            <?php $receiver_details = $order->receiver_details; ?>
                            @if (isset($receiver_details))
                                <div class="odv-sec__head mt-4">
                                    <span class="odv-sec__icon"><i class="tio-user-big-outlined"></i></span>
                                    <h2 class="odv-sec__title">{{ translate('Receiver information') }}</h2>
                                </div>
                                <div class="odv-kv">
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('Name') }}</span>
                                        <span class="odv-kv__value">{{ $receiver_details['contact_person_name'] }}</span>
                                    </div>
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('Contact') }}</span>
                                        <span class="odv-kv__value">
                                            <a class="deco-none" href="tel:{{ $receiver_details['contact_person_number'] }}">{{ $receiver_details['contact_person_number'] }}</a>
                                        </span>
                                    </div>
                                    <?php
                                        // sanitize_client_string(), not a bare != '' test: orders
                                        // placed before the address was cleaned at the source hold
                                        // the literal text "undefined" in these fields, which is
                                        // not empty and so printed as if it were a real floor or
                                        // house number. See the delivery block below.
                                        $receiverHfr = [];
                                        if (sanitize_client_string(data_get($receiver_details,'house')) !== '') $receiverHfr[] = ['label' => translate('House'), 'value' => sanitize_client_string(data_get($receiver_details,'house'))];
                                        if (sanitize_client_string(data_get($receiver_details,'floor')) !== '') $receiverHfr[] = ['label' => translate('Floor'), 'value' => sanitize_client_string(data_get($receiver_details,'floor'))];
                                        if (sanitize_client_string(data_get($receiver_details,'road'))  !== '') $receiverHfr[] = ['label' => translate('Road'),  'value' => sanitize_client_string(data_get($receiver_details,'road'))];
                                    ?>
                                    @if (count($receiverHfr))
                                        <div class="odv-kv__row odv-kv__inline">
                                            @foreach ($receiverHfr as $item)
                                                <span class="odv-kv__pair">
                                                    <span class="odv-kv__label">{{ $item['label'] }}</span>
                                                    <span class="odv-kv__value">{{ $item['value'] }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if (isset($receiver_details['address']))
                                        <div class="odv-kv__row">
                                            <span class="odv-kv__label">{{ translate('Location') }}</span>
                                            <span class="odv-kv__value">
                                                @if (isset($receiver_details['latitude']) && isset($receiver_details['longitude']))
                                                    <a target="_blank"
                                                       href="http://maps.google.com/maps?z=12&t=m&q=loc:{{ $receiver_details['latitude'] }}+{{ $receiver_details['longitude'] }}">
                                                        {{ $receiver_details['address'] }}
                                                    </a>
                                                @else
                                                    {{ $receiver_details['address'] }}
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endif

                        @if ($order->delivery_address)
                            <?php $address = json_decode($order->delivery_address, true); ?>
                            <div class="odv-sec__head mt-4">
                                <span class="odv-sec__icon"><i class="tio-truck"></i></span>
                                <h2 class="odv-sec__title">{{ translate('Delivery information') }}</h2>
                                @if ($order->order_status != 'delivered' && $order['partially_paid_amount'] == 0 && isset($address))
                                    <a class="odv-sec__aside" data-toggle="modal" data-target="#shipping-address-modal"
                                       href="javascript:"><i class="tio-edit"></i> {{ translate('Edit') }}</a>
                                @endif
                            </div>
                            @if (isset($address))
                                <?php
                                    // A client that interpolated an unset variable posted the
                                    // literal text "undefined" here, and placement stored it
                                    // verbatim -- `!= ''` is true for it, so the row rendered
                                    // "House: undefined  Floor: undefined  Road: undefined" at
                                    // staff as though the customer had typed it. New orders are
                                    // cleaned at the source now (PlaceNewOrderTrait), but the
                                    // ones already placed still carry it, and a receipt has to
                                    // keep reading correctly.
                                    $hfr = [];
                                    if (sanitize_client_string(data_get($address,'house')) !== '') $hfr[] = ['label' => translate('House'), 'value' => sanitize_client_string(data_get($address,'house'))];
                                    if (sanitize_client_string(data_get($address,'floor')) !== '') $hfr[] = ['label' => translate('Floor'), 'value' => sanitize_client_string(data_get($address,'floor'))];
                                    if (sanitize_client_string(data_get($address,'road'))  !== '') $hfr[] = ['label' => translate('Road'),  'value' => sanitize_client_string(data_get($address,'road'))];
                                ?>
                                <div class="odv-kv">
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('Name') }}</span>
                                        <span class="odv-kv__value">
                                            {{ sanitize_client_string(data_get($address,'contact_person_name'), translate('messages.N/A')) }}
                                            @php($contactNumber = sanitize_client_string(data_get($address,'contact_person_number')))
                                            @if ($contactNumber !== '')
                                                <a class="deco-none" href="tel:{{ $contactNumber }}">({{ $contactNumber }})</a>
                                            @endif
                                        </span>
                                    </div>

                                    @if (count($hfr))
                                        <div class="odv-kv__row odv-kv__inline">
                                            @foreach ($hfr as $item)
                                                <span class="odv-kv__pair">
                                                    <span class="odv-kv__label">{{ $item['label'] }}</span>
                                                    <span class="odv-kv__value">{{ $item['value'] }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if (isset($address['address']))
                                        <div class="odv-kv__row">
                                            <span class="odv-kv__label">{{ translate('Location') }}</span>
                                            <span class="odv-kv__value">
                                                @if (data_get($address,'latitude') && data_get($address,'longitude'))
                                                    <a target="_blank" class="deco-none"
                                                       href="http://maps.google.com/maps?z=12&t=m&q=loc:{{ $address['latitude'] }}+{{ $address['longitude'] }}">
                                                        {{ $address['address'] }}
                                                    </a>
                                                @else
                                                    {{ $address['address'] }}
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
                <?php $data = isset($order->order_proof) ? json_decode($order->order_proof, true) : []; ?>
                @if ( in_array($order->order_status, [ 'handover', 'delivered', 'picked_up']) || ($data != null && count($data) > 0) )
                    <div class="card">
                        <div class="card-body">
                            <div class="odv-sec__head">
                                <span class="odv-sec__icon"><i class="tio-photo-gallery-outlined"></i></span>
                                <h2 class="odv-sec__title">{{ translate('messages.Delivery proof') }}</h2>
                                @if ( in_array($order->order_status, [ 'handover', 'delivered', 'picked_up']) )
                                    <button class="btn btn-outline-primary btn-sm odv-sec__aside" data-toggle="modal" data-target=".order-proof-modal">
                                        <i class="tio-add"></i> {{ translate('Add') }}
                                    </button>
                                @endif
                            </div>
                            @if (!$data)
                                <div class="odv-empty">{{ translate('messages.No image added yet') }}</div>
                            @endif
                            @if ($data)
                                <div class="odv-thumbs">
                                    @foreach ($data as $key => $img)
                                        <?php $img = is_array($img)?$img:['img'=>$img,'storage'=>'public']; ?>
                                        <img class="odv-thumb onerror-image" data-toggle="modal"
                                             data-target="#imagemodal{{ $key }}"
                                             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                             src="{{\App\CentralLogics\Helpers::get_full_url('order',$img['img'],$img['storage']) }}"
                                             alt="{{ translate('Order proof image') }}">
                                        <div class="modal fade" id="imagemodal{{ $key }}" tabindex="-1"
                                             role="dialog" aria-labelledby="order_proof_{{ $key }}"
                                             aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title"
                                                            id="order_proof_{{ $key }}">
                                                            {{ translate('Order proof image') }}</h4>
                                                        <button type="button" class="close"
                                                                data-dismiss="modal"><span
                                                                aria-hidden="true">&times;</span><span
                                                                class="sr-only">{{ translate('messages.Cancel') }}</span></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <img src="{{\App\CentralLogics\Helpers::get_full_url('order',$img['img'],$img['storage']) }}"
                                                             class="initial--22 w-100">
                                                    </div>
                                                    <?php $storage = $img['storage'] ?? 'public'; ?>
                                                    <?php $file = $storage == 's3'?base64_encode('order/' . $img['img']):base64_encode('public/order/' . $img['img']); ?>
                                                    <div class="modal-footer">
                                                        <a class="btn btn-primary"
                                                           href="{{ route('admin.business-settings.file-manager.download', [$file,$storage]) }}"><i
                                                                class="tio-download"></i>
                                                            {{ translate('messages.Download') }}
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($order->store)
                    <div class="card">
                        <div class="card-body">
                            <div class="odv-sec__head">
                                <span class="odv-sec__icon"><i class="tio-shop"></i></span>
                                <h2 class="odv-sec__title">{{ translate('Store information') }}</h2>
                            </div>
                            <a class="odv-party odv-party--store"
                               href="{{ route('admin.store.view', [$order->store['id'],'module_id' => $order->module_id]) }}">
                                <div class="odv-party__avatar">
                                    <img class="onerror-image"
                                         data-onerror-image="{{ asset('public/assets/admin/img/100x100/1.png') }}"
                                         src="{{$order?->store?->logo_full_url ?? asset('public/assets/admin/img/100x100/1.png')  }}"
                                         alt="{{ $order->store['name'] }}">
                                </div>
                                <div class="odv-party__body">
                                    <span class="odv-party__name">
                                        {{ $order->store['name'] }}
                                        @include('partials._verified_store_badge', ['store' => $order->store])
                                    </span>
                                    <div class="odv-party__meta">
                                        <span><i class="tio-shopping-basket-outlined"></i>{{ $order->store->orders_count }} {{ translate('messages.Orders') }}</span>
                                        <span><i class="tio-call-talking-quiet"></i>{{ $order->store['phone'] }}</span>
                                        <span><i class="tio-email"></i>{{ $order->store['email'] }}</span>
                                    </div>
                                </div>
                            </a>
                            <div class="odv-kv mt-3">
                                <div class="odv-kv__row">
                                    <span class="odv-kv__label">{{ translate('messages.Address') }}</span>
                                    <span class="odv-kv__value">
                                        <a target="_blank" class="deco-none" href="http://maps.google.com/maps?z=12&t=m&q=loc:{{ $order->store['latitude'] }}+{{ $order->store['longitude'] }}">
                                            {{ $order->store['address'] }}
                                        </a>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="refund_cancelation_note" tabindex="-1" role="dialog"
         aria-labelledby="refund_cancelation_note_l" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="refund_cancelation_note_l">{{ translate('Add order rejection note') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('admin.refund.order_refund_rejection') }}" method="post">
                        @method('PUT')
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                        <input type="text" class="form-control" name="admin_note" value="{{ old('admin_note') }}"
                               placeholder="Fake Order">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="tio-clear"></i> {{  translate('Close') }}</button>
                    <button type="submit" class="btn btn-danger"><i class="tio-clear-circle-outlined"></i> {{ translate('Confirm order rejection') }} </button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade bd-example-modal-sm" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel"
         aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h4" id="mySmallModalLabel">{{ translate('messages.Reference code add') }}</h5>
                    <button type="button" class="btn btn-xs btn-icon btn-ghost-secondary" data-dismiss="modal"
                            aria-label="Close">
                        <i class="tio-clear tio-lg"></i>
                    </button>
                </div>

                <form action="{{ route('admin.order.add-payment-ref-code', [$order['id']]) }}" method="post">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <input type="text" name="transaction_reference" class="form-control"
                                   placeholder="{{ translate('messages.Ex') }}: Code123" required>
                        </div>
                        <div class="text-right">
                            <button class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
    <div class="modal fade order-proof-modal" tabindex="-1" role="dialog" aria-labelledby="mySmallModalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h4" id="mySmallModalLabel">{{ translate('messages.Add delivery proof') }}</h5>
                    <button type="button" class="btn btn-xs btn-icon btn-ghost-secondary" data-dismiss="modal"
                            aria-label="Close">
                        <i class="tio-clear tio-lg"></i>
                    </button>
                </div>

                <form action="{{ route('admin.order.add-order-proof', [$order['id']]) }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="flex-grow-1 mx-auto">
                            <div class="d-flex flex-wrap __gap-12px __new-coba" id="coba">
                                <?php $proof = isset($order->order_proof) ? json_decode($order->order_proof, true) : 0; ?>
                                @if ($proof)

                                    @foreach ($proof as $key => $photo)
                                        <?php $photo = is_array($photo)?$photo:['img'=>$photo,'storage'=>'public']; ?>
                                        <div class="spartan_item_wrapper min-w-176px max-w-176px">
                                            <img class="img--square"
                                                 src="{{\App\CentralLogics\Helpers::get_full_url('order',$photo['img'],$photo['storage']) }}"
                                                 alt="order image">
                                            <div class="pen spartan_remove_row"><i class="tio-edit"></i></div>
                                            <a href="{{ route('admin.order.remove-proof-image', ['id' => $order['id'], 'name' => $photo['img']]) }}"
                                               class="spartan_remove_row"><i class="tio-add-to-trash"></i></a>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        <div class="text-right mt-2">
                            <button class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <div id="shipping-address-modal" class="modal fade" tabindex="-1" role="dialog"
         aria-labelledby="exampleModalTopCoverTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-top-cover bg-dark text-center">
                    <figure class="position-absolute right-0 bottom-0 left-0 mb--1">
                        <svg preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px"
                             viewBox="0 0 1920 100.1">
                            <path fill="#fff" d="M0,0c0,0,934.4,93.4,1920,0v100.1H0L0,0z" />
                        </svg>
                    </figure>

                    <div class="modal-close">
                        <button type="button" class="btn btn-icon btn-sm btn-ghost-light" data-dismiss="modal"
                                aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                                <path fill="currentColor"
                                      d="M11.5,9.5l5-5c0.2-0.2,0.2-0.6-0.1-0.9l-1-1c-0.3-0.3-0.7-0.3-0.9-0.1l-5,5l-5-5C4.3,2.3,3.9,2.4,3.6,2.6l-1,1 C2.4,3.9,2.3,4.3,2.5,4.5l5,5l-5,5c-0.2,0.2-0.2,0.6,0.1,0.9l1,1c0.3,0.3,0.7,0.3,0.9,0.1l5-5l5,5c0.2,0.2,0.6,0.2,0.9-0.1l1-1 c0.3-0.3,0.3-0.7,0.1-0.9L11.5,9.5z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="modal-top-cover-icon">
                    <span class="icon icon-lg icon-light icon-circle icon-centered shadow-soft">
                        <i class="tio-location-search"></i>
                    </span>
                </div>

                @if (isset($address))
                    <form action="{{ route('admin.order.update-shipping', [$order['id']]) }}" method="post">
                        @csrf
                        <div class="modal-body">
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{ translate('Type') }}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="address_type"
                                           value="{{ $address['address_type'] }}" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{ translate('Contact') }}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="contact_person_number"
                                           value="{{ $address['contact_person_number'] }}" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{ translate('Name') }}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="contact_person_name"
                                           value="{{ $address['contact_person_name'] }}" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{ translate('House') }}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="house"
                                           value="{{ isset($address['house']) ? $address['house'] : '' }}" >
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{ translate('Floor') }}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="floor"
                                           value="{{ isset($address['floor']) ? $address['floor'] : '' }}" >
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{ translate('Road') }}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="road"
                                           value="{{ isset($address['road']) ? $address['road'] : '' }}" >
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{ translate('messages.Address') }}
                                </label>
                                <div class="col-md-10 js-form-message">
                                    <input type="text" class="form-control" name="address"
                                           value="{{ $address['address'] }}">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{ translate('messages.latitude') }}
                                </label>
                                <div class="col-md-4 js-form-message">
                                    <input type="text" class="form-control" name="latitude" id="latitude"
                                           value="{{ $address['latitude'] }}">
                                </div>
                                <label for="requiredLabel" class="col-md-2 col-form-label input-label text-md-right">
                                    {{ translate('messages.longitude') }}
                                </label>
                                <div class="col-md-4 js-form-message">
                                    <input type="text" class="form-control" name="longitude" id="longitude"
                                           value="{{ $address['longitude'] }}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <input id="pac-input" class="controls rounded initial-8"
                                       title="{{ translate('Search your location') }}" type="text"
                                       placeholder="{{ translate('Search') }}" />
                                <div class="mb-2 h-200px" id="map"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn--reset"
                                    data-dismiss="modal"><i class="tio-clear"></i> {{ translate('messages.Close') }}</button>
                            <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('messages.Save changes') }}</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="myModalLabel">{{ translate('messages.Assign deliveryman') }}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-5 my-2">
                            <ul class="list-group overflow-auto initial--23">
                                @foreach ($deliveryMen as $dm)
                                    <li class="list-group-item">
                                        <span class="dm_list" role='button' data-id="{{ $dm['id'] }}">
                                            <img class="avatar avatar-sm avatar-circle mr-1 onerror-image"
                                                 data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                                 src="{{$dm['image_full_url'] }}"
                                                 alt="{{ $dm['name'] }}">
                                            {{ $dm['name'] }}
                                        </span>

                                        <a class="btn btn-primary btn-xs float-right add-delivery-man" data-id="{{ $dm['id'] }}"><i class="tio-user-add"></i> {{ $order->delivery_man ? translate('messages.reassign') : translate('messages.assign') }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-md-7 modal_body_map">
                            <div class="location-map" id="dmassign-map">
                                <div class="initial--24" id="map_canvas"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="locationModal" tabindex="-1" role="dialog" aria-labelledby="locationModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="locationModalLabel">{{ translate('messages.Location data') }}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 modal_body_map">
                            <div class="location-map" id="location-map">
                                <div class="initial--25" id="location_map_canvas"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="quick-view" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" id="quick-view-modal">

            </div>
        </div>
    </div>



    @if ($order?->payment_method == 'offline_payment')
        <div class="modal fade" id="verifyViewModal" tabindex="-1" aria-labelledby="verifyViewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header d-flex justify-content-end  border-0 pt-3 px-3">
                        <button type="button" class="close border rounded-circle bg-modal-btn" data-dismiss="modal">
                            <span aria-hidden="true" class="tio-clear"></span>
                        </button>
                    </div>
                    <div class="modal-body pt-0">
                        <div class="d-flex align-items-center flex-column gap-1 mb-xxl-5 mb-4 text-center">

                            <h2 class="mb-0">
                                {{ translate('Payment verification') }}

                                @if(optional($order->offline_payments)->status === 'verified')
                                    <span class="badge badge-soft-success mt-3 mb-3">
                                        {{ translate('messages.verified') }}
                                    </span>
                                @endif
                            </h2>

                            @unless(optional($order->offline_payments)->status === 'verified')
                                <p class="text-danger mb-0 mt-0">
                                    {{ translate('Please check and verify the payment information before confirming the order.') }}
                                </p>
                            @endunless

                        </div>


                        <div class="card border-0">
                            <div class="bg-light2 p-xxl-20 p-3 rounded">
                                <div class="adjust-information-payment flex-md-nowrap flex-wrap">
                                    <div class="bg-white p-3 rounded h-100 w-100">
                                        <h4 class="mb-3 fs-16">{{ translate('Customer information') }}</h4>
                                        <div class="d-flex flex-column gap-2">
                                            @if($order->is_guest)
                                                <?php $customer_details = json_decode($order['delivery_address'],true); ?>

                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="customer-namekey">{{translate('Name')}}</span>:
                                                    <span class="text-dark"> {{$customer_details['contact_person_name']}}</span>
                                                </div>

                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="customer-namekey">{{translate('Phone')}}</span>:
                                                    <span class="text-dark">  {{$customer_details['contact_person_number']}}</span>
                                                </div>

                                            @elseif($order->customer)
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="customer-namekey">{{translate('Name')}}</span>:
                                                    <span class="text-dark"> <a class="text-dark text text-capitalize" href="{{route('admin.users.customer.view',[$order['user_id']])}}"> {{$order->customer['f_name'].' '.$order->customer['l_name']}}  </a>  </span>
                                                </div>

                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="customer-namekey">{{translate('Phone')}}</span>:
                                                    <span class="text-dark">{{$order->customer['phone']}}  </span>
                                                </div>

                                            @else
                                                <label class="badge badge-danger">{{translate('messages.Invalid customer data')}}</label>
                                            @endif

                                        </div>
                                    </div>
                                    @if($order?->offline_payments)
                                    <div class="bg-white p-3 rounded h-100 w-100">
                                        <div class="">
                                            <h4 class="mb-3 fs-16">{{ translate('Payment information') }}</h4>
                                            <div class="row g-1">
                                                @foreach (json_decode($order?->offline_payments?->payment_info ?? '[]') as $key=>$item)
                                                    @if ($key != 'method_id')
                                                        <div class="col-sm-12">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="namekey"> {{translate($key)}}</span>:
                                                                <span class="text-dark text-break">{{ $item }}</span>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>

                                            <div class="d-flex flex-column gap-2 mt-4">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="namekey">{{translate('Customer note')}}</span>:
                                                    <span class="text-dark text-break">{{$order->offline_payments?->customer_note ?? translate('messages.N/A')}} </span>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    @else
                                        <div class="bg-white p-3 rounded h-100 w-100">
                                            <h4 class="mb-3 fs-16">{{ translate('Payment information') }}</h4>
                                            <div class="row g-1">
                                                <div class="col-sm-12">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="namekey"> {{translate('Payment method')}}</span>:
                                                        <span class="text-dark text-break">{{translate('messages.N/A')}} </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if ($order?->offline_payments?->status != 'verified')
                            <div class="btn--container justify-content-end mt-xxl-5 mt-4 pt-xxl-1">
                                @if ($order?->offline_payments?->status != 'denied')
                                    <button type="button" class="btn btn--reset offline_payment_cancelation_note" data-toggle="modal" data-target="#offline_payment_cancelation_note" data-id="{{ $order['id'] }}" class="btn btn--reset"><i class="tio-clear-circle-outlined"></i> {{translate('Payment didn\'t Receive')}}</button>
                                @elseif ($order?->offline_payments?->status == 'denied')
                                    <button type="button" data-url="{{ route('admin.order.offline_payment', [ 'id' => $order['id'], 'verify' => 'switched_to_cod', ]) }}" data-message="{{ translate('messages.Make the payment switched to cod for this order') }}" class="btn btn--reset route-alert"><i class="tio-sync"></i> {{translate('Switched to COD')}}</button>
                                @endif
                                @if($order?->offline_payments)
                                    <button type="button" data-url="{{ route('admin.order.offline_payment', [ 'id' => $order['id'], 'verify' => 'yes', ]) }}" data-message="{{ translate('messages.Make the payment verified for this order') }}" class="btn btn--primary route-alert"><i class="tio-checkmark-circle-outlined"></i> {{translate('Yes, payment received')}}</button>
                                @else
                                        <button type="button" class="btn btn--primary btn-sm form-alert"
                                                data-id="order-{{$order['id']}}"
                                                data-cancel-btn="{{ translate('messages.Cancel') }}"
                                                data-confirm-btn="{{ translate('messages.Confirm') }}"
                                                data-image-url="{{ asset('public/assets/admin/img/tughrik.png') }}"
                                                data-title="{{ translate('Switch to Cash on Delivery?') }}"
                                                data-message="{{ translate('The customer’s offline payment has failed. Before switching this order to Cash on Delivery (COD), please confirm the payment issue with the customer to avoid any misunderstandings.') }}">
                                            <i class="tio-sync"></i> {{ translate('messages.Switch to COD') }}
                                        </button>
                                    <form action="{{route('admin.order.switch_to_cod',[$order['id']])}}"
                                          method="post" id="order-{{$order['id']}}">
                                        @csrf
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="offline_payment_cancelation_note" tabindex="-1" role="dialog"
             aria-labelledby="offline_payment_cancelation_note_l" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-600" role="document">
                <div class="modal-content">
                    <div class="modal-header px-2 pt-2">
                        <button type="button" class="close min-w-28 rounded-circle border bg-modal-btn" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action="{{ route('admin.order.offline_payment') }}" method="get">
                        <div class="modal-body">
                            <div class="cont mb-4 text-center pb-xxl-1">
                                <img width="60px" height="60px" src="{{asset('/public/assets/admin/img/delete-confirmation.png')}}" alt="public" class="mb-20">
                                <h3 class="mb-xl-2 mb-1">
                                    {{translate('Are you sure the payment was not received?')}}
                                </h3>
                                <p class="mb-0 fs-14 max-w-420 mx-auto">
                                    {{translate('Please insert a denied note for this payment request to inform the customer')}}
                                </p>
                            </div>
                            <div class="bg-light2 p-3 rounded">
                                <label class="form-label">
                                    {{translate('Denied note')}}
                                    <span class="custom-tooltip" data-title="payment request to inform the customer ">
                                        <i class="tio-info text-muted"></i>
                                    </span>
                                </label>
                                <input type="hidden" name="id" value="{{ $order->id }}">
                                <textarea type="text" rows="1" maxlength="100" required class="form-control" name="note" value="{{ old('note') }}"
                                    placeholder="{{ translate('Transaction id mismatched') }}"></textarea>
                                <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-2">
                            <button type="button" class="btn btn--reset h-40px min-w-120px py-2 fs-14" data-dismiss="modal"><i class="tio-clear"></i> {{  translate('Close') }}</button>
                            <button type="submit" class="btn btn-primary h-40px min-w-120px py-2 fs-14"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Confirm Rejection') }} </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @endif

        <div class="modal fade" id="offline_payment_cancel_orders" tabindex="-1" role="dialog"
             aria-labelledby="offline_payment_cancel_orders" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-600" role="document">
                <div class="modal-content">
                    <div class="modal-header px-2 pt-2">
                        <button type="button" class="close min-w-28 rounded-circle border bg-modal-btn" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action="{{ route('admin.order.status') }}" method="get">
                        <input type="hidden" name="id" value="{{ $order['id'] }}">
                        <input type="hidden" name="order_status" value="canceled">
                        <div class="modal-body">
                            <div class="cont mb-4 text-center pb-xxl-1">
                                <img width="60px" height="60px" src="{{asset('/public/assets/admin/img/offlice-cancel-orders.png')}}" alt="public" class="mb-20">
                                <h3 class="mb-xl-2 mb-1">
                                    {{translate('Cancel this Order?')}}
                                </h3>
                                <p class="mb-0 fs-14 max-w-420 mx-auto">
                                    {{translate('Please contact the customer before canceling this order permanently.')}}
                                </p>
                            </div>
                            <div class="bg-light2 p-3 rounded">
                                <label class="form-label">
                                    {{translate('Select Cancel Reason')}}
                                </label><br>
                                <select name="reason" class="bg-white custom-select" id="">
                                    <option value="">{{ translate('Select reason') }}</option>
                                    @foreach ($reasons as $r)
                                        <option value="{{ $r->reason }}">{{ $r->reason }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer d-flex gap-3 flex-nowrap pb-4 mb-2 justify-content-center border-0 pt-2">
                            <button type="button" class="btn btn--reset h-40px min-w-120px w-100 py-2 fs-14" data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{  translate('Keep Order') }}</button>
                            <button type="submit" class="btn btn-primary h-40px min-w-120px w-100 py-2 fs-14"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Yes, Cancel Order') }} </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


    <div id="offcanvas__order_edit" class="custom-offcanvas d-flex flex-column justify-content-between" style="--offcanvas-width: 750px !important;">
        <div>
            <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center">
                <div class="px-3 py-3 d-flex justify-content-between w-100">
                    <div>
                        <h2 class="mb-1">{{ translate('Edit item') }}</h2>
                        <div class="d-flex flex-wrap align-items-center gap-4">
                            <h3 class="page-header-title mb-0 d-flex align-items-center gap-2">
                                <span class="font--max-sm fs-14">{{ translate('Order') }} #{{ $order['id'] }}</span>
                                <?php
                                $statusBadge = match($order->order_status) {
                                    'pending'    => 'badge-soft-info',
                                    'confirmed','accepted' => 'badge-soft-success',
                                    'processing' => 'badge-soft-warning',
                                    'handover','picked_up' => 'badge-soft-primary',
                                    'delivered'  => 'badge-soft-success',
                                    'canceled'   => 'badge-soft-danger',
                                    default      => 'badge-soft-secondary',
                                };
                                ?>
                                <span class="badge {{ $statusBadge }} font-regular m-0">{{ translate(str_replace('_', ' ', $order->order_status)) }}</span>
                            </h3>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fs-14 font-regular d-block text-dark">{{ translate('Order placed') }} :</span>
                                <span class="fs-14 font-semibold d-block text-dark">{{ date('d M Y ' . config('timeformat'), strtotime($order['created_at'])) }}</span>
                            </div>
                        </div>
                    </div>
                    <button type="button"
                        class="btn-close h-32px min-w-32 border rounded-circle d-center bg--secondary location-reload offcanvas-close fz-15px p-0"
                        aria-label="Close">&times;
                    </button>
                </div>
            </div>
            <div class="custom-offcanvas-body p-20">
                <div class="mb-20 position-relative edit-search-form">
                    <div class="form-control position-relative bg-white d-flex align-items-center gap-2">
                        <i class="tio-search"></i>
                        <input id="food_search" type="search" class="h-100 fs-12 bg-transparent w-100 border-0 rounded-0" placeholder="{{ translate('Search by food name') }}" autocomplete="off" data-store-id="{{ $order->store_id }}">
                        <div class="search-wrap-manage w-100 z-index-99" id="search-dropdown" style="display:none;">
                            <div class="search-items-wrap p-sm-3 p-2 rounded bg-white d-flex flex-column gap-2">
                                <div id="food-search-result"></div>
                                <div id="food-search-no-data" class="d-none">
                                    <h6 class="text-center bg-light py-5 px-3 rounded">{{ translate('No data found') }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <div id="data-view" class="pb-5 mb-5">
                    @include('admin-views.order.partials._edit_cart_list', ['cart' => $cart, 'editing' => $editing, 'order' => $order])
                </div>

            </div>
            <div class="offcanvas-footer position-absolute bottom-0 start-0 w-100 bg-white p-3 d-flex align-items-center justify-content-end gap-3">
                <button type="button" class="btn min-w-120 btn--reset location-reload reset"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
                <button type="button" class="btn min-w-120 btn--primary submit-edit-order"><i class="tio-shopping-cart"></i> {{ translate('Update cart') }}</button>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay_fixed" class="offcanvasOverlay_fixed"></div>


     <div id="offcanvas__history_log" class="custom-offcanvas d-flex flex-column justify-content-between" style="--offcanvas-width: 570px">
        <div>
            <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center">
                <div class="px-3 py-3 d-flex justify-content-between w-100">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h2 class="mb-0 fs-18 font-medium">{{ translate('Edit history log') }}</h2>
                        <h3 class="page-header-title mb-0 d-flex align-items-center gap-2">
                            <span class="font--max-sm fs-14 font-normal fs-14">(# {{ $order['id'] }})</span>
                        </h3>
                    </div>
                    <button type="button"
                        class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                        aria-label="Close">&times;
                    </button>
                </div>
            </div>
            <div class="custom-offcanvas-body p-20">
                <div class="card p-10px">
                    <div class="table-responsive pt-0">
                        <div class="p-1">
                            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table dataTable no-footer mb-0">
                                <thead class="border-0 initial-94 bg-light">
                                    <tr>
                                        <th class="border-0 text-dark text-nowrap">{{ translate('messages.SL') }}</th>
                                        <th class="border-0 text-dark text-nowrap">{{ translate('Date & time') }}</th>
                                        <th class="border-0 text-dark text-nowrap">{{ translate('messages.remark') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $logRemarkLabels = [
                                        'edited_item_quantity' => translate('messages.Edited item quantity'),
                                        'add_new_item'         => translate('messages.Added new item'),
                                        'delete_item'          => translate('messages.Removed item'),
                                    ];
                                    ?>
                                    @forelse ($order->orderEditLogs as $logKey => $editLog)
                                        <tr>
                                            <td>
                                                <div>{{ $logKey + 1 }}</div>
                                            </td>
                                            <td class="fs-14">
                                                <span class="d-block text-dark">
                                                    {{ $editLog->created_at?->format('d M Y') }}
                                                </span>
                                                <span class="text-muted">
                                                    {{ $editLog->created_at?->format(config('timeformat') == 'h:i A' ? 'h:i A' : config('timeformat', 'h:i a')) }}
                                                </span>
                                            </td>
                                            <td class="fs-14">
                                                <div class="mb-0 text-dark fs-14 lh-1 line--limit-2 min-w-120">
                                                    {{ $logRemarkLabels[$editLog->log] ?? translate(str_replace('_', ' ', $editLog->log ?? 'edited')) }}
                                                </div>
                                                <div class="text-info fs-12">
                                                    {{ translate('messages.Edit by') }} {{ translate('messages.' . ($editLog->edited_by ?? 'admin')) }}
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-3">
                                                {{ translate('messages.No edit history') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    <div class="modal shedule-modal fade" id="edit_order_confirmation-btn" tabindex="-1" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pb-5 max-w-500">
                <div class="modal-header">
                    <button type="button"
                        class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-1">
                        <img src="{{asset('public/assets/admin/img/delete-confirmation.png')}}" alt="icon" class="mb-3">
                        <h3 class="mb-2">{{ translate('Are you sure you want to edit this order?') }}</h3>
                        <p class="mb-0">{{ translate('messages.If you edit this order, some product details will be updated, which may affect the total price.') }} </p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.No') }}</button>
                    <a href="{{ route('admin.order.edit', $order->id) }}" class="btn min-w-120px btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Yes') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade z-1051" id="food_list_delete" tabindex="-1" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pb-5 max-w-500">
                <div class="modal-header">
                    <button type="button"
                        class="close bg-modal-btn w-30px h-30 rounded-circle position-absolute right-0 top-0 m-2 z-2"
                        data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-1">
                        <img src="{{asset('public/assets/admin/img/delete-confirmation.png')}}" alt="icon" class="mb-3">
                        @if ($order->store && $order->store->module && $order->store->module->module_type == 'food')
                            <h3 class="mb-2">{{ translate('Are you sure you want to delete this food?') }}</h3>
                            <p class="mb-0">{{ translate('messages.Deleting removes this food item from the list. Search the food list to add it again.') }}</p>
                        @else
                            <h3 class="mb-2">{{ translate('Are you sure you want to delete this item?') }}</h3>
                            <p class="mb-0">{{ translate('messages.Deleting removes this item from the list. Search the item list to add it again.') }}</p>
                        @endif
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.No') }}</button>
                    <button type="button" class="btn min-w-120px btn--primary" id="confirm-remove-cart-item" data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Yes') }}</button>
                </div>
            </div>
        </div>
    </div>

    <?php
        $defaultLocation = App\CentralLogics\Helpers::get_business_settings('default_location');
        $mapApiKey = \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) ?? '';
        $orderStoreData = $order->store ? [
            'latitude'  => $order->store->latitude,
            'longitude' => $order->store->longitude,
            'name_short'=> Str::limit($order?->store?->name, 15, '...'),
            'address'   => $order->store->address,
            'zone_id'   => $order->store->zone_id,
            'logo_url'  => $order?->store?->logo_full_url ?? asset('public/assets/admin/img/160x160/img1.jpg'),
        ] : null;
        $orderCustomerData = $order->customer ? [
            'f_name'    => $order->customer->f_name,
            'l_name'    => $order->customer->l_name,
            'image_url' => $order?->customer?->image_full_url ?? asset('public/assets/admin/img/160x160/img1.jpg'),
        ] : null;
        $orderDmData = $order->delivery_man ? [
            'f_name'    => $order->delivery_man->f_name,
            'l_name'    => $order->delivery_man->l_name,
            'image_url' => $order?->delivery_man?->image_full_url ?? asset('public/assets/admin/img/160x160/img1.jpg'),
        ] : null;
        $orderDmLastLocation = ($order->delivery_man && $order->dm_last_location) ? [
            'latitude'  => $order->dm_last_location['latitude'],
            'longitude' => $order->dm_last_location['longitude'],
            'location'  => $order->dm_last_location['location'],
        ] : null;
        $orderAddressData = isset($address) ? [
            'latitude'  => $address['latitude'] ?? null,
            'longitude' => $address['longitude'] ?? null,
            'address'   => $address['address'] ?? '',
        ] : null;
    ?>

    <?php
    $pageRoutes = [
        'orderStatus'         => route('admin.order.status') . '?id=' . $order->id . '&order_status=canceled',
        'quickViewCartItem'   => route('admin.order.quick-view-cart-item'),
        'quickView'           => route('admin.order.quick-view'),
        'variantPrice'        => route('admin.item.variant-price'),
        'addToCart'           => route('admin.order.add-to-cart'),
        'removeFromCart'      => route('admin.order.remove-from-cart'),
        'orderUpdate'         => route('admin.order.update', $order->id),
        'searchItems'         => route('admin.order.search-items'),
        'cartList'            => route('admin.order.cart-list'),
        'updateCartQuantity'  => route('admin.order.update-cart-quantity'),
        'addDeliveryManBase'  => url('/admin/order/add-delivery-man/' . $order->id) . '/',
        'zoneCoordinatesBase' => url('/admin/zone/get-coordinates') . '/',
    ];
    $pageTranslations = [
        'self_delivery_disable'       => translate('Self delivery is disable'),
        'are_you_sure'                => translate('messages.Are you sure?'),
        'are_you_sure_q'              => translate('messages.Are you sure?'),
        'change_status_canceled'      => translate('messages.Change status to canceled?'),
        'no'                          => translate('messages.No'),
        'yes'                         => translate('messages.Yes'),
        'no_cap'                      => translate('messages.No'),
        'yes_cap'                     => translate('messages.Yes'),
        'submit'                      => translate('messages.Submit'),
        'cancel'                      => translate('messages.Cancel'),
        'enter_verification_code'     => translate('Enter order verification code'),
        'enter_processing_time'       => translate('Enter processing time'),
        'enter_processing_time_label' => translate('Enter processing time in minutes'),
        'select_reason'               => translate('Select reason'),
        'invalid_image_type'          => translate('Please upload a file in a supported format') . ': PNG, JPG',
        'file_too_big'                => translate('messages.File size too big'),
        'already_in_cart'             => translate('messages.Product already added in cart'),
        'added_to_cart'               => translate('messages.Product has been added in cart'),
        'order_updated'               => translate('Updated successfully'),
        'remove_item_confirm'         => translate('messages.You want to remove this order item'),
        'item_removed'                => translate('messages.Item has been removed from cart'),
        'submit_all_confirm'          => translate('messages.You want to submit all changes for this order'),
        'cart_empty'                  => translate('messages.Cart is empty'),
        'update_failed'               => translate('messages.Order update failed'),
        'delivery_man_added'          => translate('Added successfully'),
        'last_location_warning'       => translate('Only available when order is out for delivery!'),
        'out_of_coverage'             => translate('messages.Out of coverage'),
        'not_available_now'           => translate('messages.Not available'),
        'stock_qty'                   => translate('Stock quantity'),
        'out_of_stock'                => translate('Out of stock'),
        'stock_limit_exceeded'        => translate('messages.Requested quantity exceeds stock'),
        'unavailable'                 => translate('Unavailable'),
        'veg'                         => translate('Veg'),
        'non_veg'                     => translate('Non veg'),
        'halal'                       => translate('messages.Halal'),
        'price'                       => translate('messages.price'),
        'add_to_cart'                 => translate('messages.Add to cart'),
        'update_cart'                 => translate('Update cart'),
    ];
    $mapAddress = null;
    if (!empty($order->delivery_address)) {
        $decoded = is_array($order->delivery_address) ? $order->delivery_address : json_decode($order->delivery_address, true);
        if (is_array($decoded)) {
            $mapAddress = [
                'latitude'  => $decoded['latitude'] ?? 0,
                'longitude' => $decoded['longitude'] ?? 0,
                'address'   => $decoded['address'] ?? '',
            ];
        }
    }
    $mapStore = $order->store ? [
        'latitude'   => $order->store->latitude,
        'longitude'  => $order->store->longitude,
        'name_short' => Str::limit($order->store->name ?? '', 20, '...'),
        'logo_url'   => $order->store->logo_full_url ?? '',
        'address'    => $order->store->address ?? '',
        'zone_id'    => $order->store->zone_id ?? null,
    ] : null;
    $mapCustomer = $order->customer ? [
        'f_name'    => $order->customer->f_name ?? '',
        'l_name'    => $order->customer->l_name ?? '',
        'image_url' => $order->customer->image_full_url ?? '',
    ] : null;
    $mapDeliveryMan = $order->delivery_man ? [
        'f_name'    => $order->delivery_man->f_name ?? '',
        'l_name'    => $order->delivery_man->l_name ?? '',
        'image_url' => $order->delivery_man->image_full_url ?? '',
    ] : null;
    // dm_last_location() delegates to delivery_man->last_location(), but caches on the order.
    // Reading it the same way as the panel above keeps this to one delivery_histories query.
    $mapDmLastLocation = ($order->delivery_man && $order->dm_last_location) ? [
        'latitude'  => $order->dm_last_location->latitude,
        'longitude' => $order->dm_last_location->longitude,
        'location'  => $order->dm_last_location->location ?? '',
    ] : null;
    $pageMapConfig = [
        'mapApiKey'       => \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) ?? '',
        'orderType'       => $order->order_type,
        'defaultLocation' => ['lat' => 23.757989, 'lng' => 90.360587],
        'store'           => $mapStore,
        'customer'        => $mapCustomer,
        'deliveryMan'     => $mapDeliveryMan,
        'dmLastLocation'  => $mapDmLastLocation,
        'address'         => $mapAddress,
        'markerIcons'     => [
            'restaurant'  => asset('public/assets/admin/img/restaurant_map.png'),
            'deliveryBoy' => asset('public/assets/admin/img/delivery_boy_map.png'),
            'customer'    => asset('public/assets/admin/img/customer_location.png'),
        ],
        'fallbackImages'  => [
            'store'       => asset('public/assets/admin/img/160x160/img1.jpg'),
            'storeAlt'    => asset('public/assets/admin/img/100x100/1.png'),
            'customer'    => asset('public/assets/admin/img/160x160/img1.jpg'),
            'deliveryMan' => asset('public/assets/admin/img/160x160/img1.jpg'),
        ],
    ];
    ?>
    <div id="order-page-config"
         hidden
         data-order-id="{{ $order->id }}"
         data-order-proof-count="{{ ($order->order_proof && is_array($order->order_proof)) ? count(json_decode($order->order_proof)) : 0 }}"
         data-open-edit-offcanvas="{{ (isset($editing) && $editing && session()->pull('open_edit_offcanvas')) ? 1 : 0 }}"
         data-img-upload="{{ asset('public/assets/admin/img/upload-img.png') }}"
         data-img-placeholder="{{ asset('public/assets/admin/img/100x100/2.png') }}"
         data-delivery-men='@json($deliveryMen)'
         data-routes='@json($pageRoutes)'
         data-translations='@json($pageTranslations)'
         data-map='@json($pageMapConfig)'></div>

@endsection

@push('script_2')
    <script src="https://maps.googleapis.com/maps/api/js?key={{ \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) }}&libraries=places,marker,geometry&v=3.61"></script>
    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/view-pages/order-edit-offcanvas.js') }}"></script>
@endpush
