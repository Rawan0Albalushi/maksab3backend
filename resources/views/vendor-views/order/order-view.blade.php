@use('App\Support\Settings\BusinessRules')
@extends('layouts.vendor.app')

@section('title', translate('Order details'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/order-edit-offcanvas.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/order-details.css') }}">
@endpush

@section('content')
    <?php
    $campaign_order = isset($order?->details[0]?->item_campaign_id) ? true : false;
    $max_processing_time = explode('-', $order['store']['delivery_time'])[0];
    $is_food_module = $order->store && $order->store->module->module_type == 'food';

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
                    </h1>
                    <p class="page-header-desc">{{ translate('Everything on this order, from the items to the payment and the deliveryman.') }}</p>
                </div>

                <div class="col-sm-auto">
                    <div class="odv-pager">
                        <a class="btn-icon btn-sm rounded-circle"
                           href="{{ route('vendor.order.details', [$order['id'] - 1]) }}" data-toggle="tooltip"
                           data-placement="top" title="{{ translate('Previous order') }}">
                            <i class="tio-chevron-left"></i>
                        </a>
                        <a class="btn-icon btn-sm rounded-circle"
                           href="{{ route('vendor.order.details', [$order['id'] + 1]) }}" data-toggle="tooltip"
                           data-placement="top" title="{{ translate('Next order') }}">
                            <i class="tio-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

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
                                @if ($canEditOrder && in_array($order->order_status, ['pending']) && isset($order->store) && !$campaign_order && $order->prescription_order == 0 && count($order?->payments) == 0 && $order?->ref_bonus_amount == 0 && $order?->flash_admin_discount_amount == 0 && ($order->payment_method == 'cash_on_delivery'))
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
                                   href="{{ route('vendor.order.generate-invoice', [$order['id']]) }}">
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
                                    @if ($has_unpaid_payment)
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

                        @if ($is_food_module)
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
                                        {{ translate('Please bring change when making the delivery.') }} {{ translate('Change amount') }}: {{ \App\CentralLogics\Helpers::format_currency($order['bring_change_amount']) }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($order->order_attachment)
                        @php
                            $order_images = json_decode($order->order_attachment, true) ?? [];
                        @endphp
                        <div class="px-20">
                            <h4 class="fs-14 mb-10px">{{ translate('messages.prescription') }}</h4>
                            <div class="tabs-slide-wrap tabs-slide-wrap-prescription position-relative">
                                <div class="tabs-inner d-flex align-items-center gap-xxl-20 gap-3">
                                    @foreach ($order_images as $key => $item)
                                        <?php $item = is_array($item) ? $item : ['img' => $item, 'storage' => 'public']; ?>
                                        <div class="tabs-slide_items">
                                            <div class="prescription-thumb h-100px aspect-ratio-1 overflow-hidden rounded"
                                                 data-toggle="modal" data-target="#prescriptionimagemodal{{ $key }}">
                                                <img src="{{ \App\CentralLogics\Helpers::get_full_url('order', $item['img'], $item['storage'] ?? 'public') }}"
                                                     alt="img" class="w-100">
                                            </div>
                                        </div>
                                        <div class="modal fade" id="prescriptionimagemodal{{ $key }}" tabindex="-1"
                                             role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title" id="myModalLabel">
                                                            {{ translate('messages.prescription') }}</h4>
                                                        <button type="button" class="close" data-dismiss="modal"><span
                                                                aria-hidden="true">&times;</span><span
                                                                class="sr-only">{{ translate('messages.Cancel') }}</span></button>
                                                    </div>
                                                    <div class="modal-body scroll-bar">
                                                        <img src="{{ \App\CentralLogics\Helpers::get_full_url('order', $item['img'], $item['storage'] ?? 'public') }}"
                                                             class="initial--22 w-100">
                                                    </div>
                                                    <?php $storage = $item['storage'] ?? 'public'; ?>
                                                    <?php $file = $storage == 's3' ? base64_encode('order/' . $item['img']) : base64_encode('public/order/' . $item['img']); ?>
                                                    <div class="modal-footer">
                                                        <a class="btn btn-primary"
                                                           href="{{ route('admin.business-settings.file-manager.download', [$file, $storage]) }}"><i
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
                        $bogoGroups = app(\App\Services\Promotion\BogoOrderService::class)
                            ->orderGroups($order->details, (int) $order->store_id);
                        $bundleGroups = app(\App\Services\Promotion\BundleOrderService::class)
                            ->orderGroups($bogoGroups['plain']);
                        $store_discount_amount = 0;
                        $delivery_fee_info = app(\App\Services\Order\OrderService::class)->adjustedFeeForOrder($order);

                        if ($order->prescription_order == 1) {
                            $product_price = $order['order_amount'] - $delivery_fee_info['adjusted'] - $order['total_tax_amount'] - $order['dm_tips'] - $order['additional_charge'] + $order['store_discount_amount'];
                            if ($order->tax_status == 'included') {
                                $product_price += $order['total_tax_amount'];
                            }
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
                                        @if ($is_food_module)
                                            <th class="border-0">{{ translate('Addons') }}</th>
                                        @endif
                                        <th class="border-0 text-center">{{ translate('messages.QTY') }}</th>
                                        <th class="text-right border-0">{{ translate('messages.price') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($bundleGroups['plain'] as $key => $detail)
                                        <?php
                                        $detailVariations = json_decode($detail['variation'] ?? '', true) ?: [];
                                        $detailAddons = json_decode($detail['add_ons'] ?? '', true) ?: [];
                                        ?>
                                        @if (isset($detail->item_id))
                                            <?php
                                            $product = $detail->item;
                                            $itemSnapshot = json_decode($detail->item_details, true) ?: [];
                                            ?>
                                            <tr>
                                                <td class="odv-items__sl">{{ $key + 1 }}</td>
                                                <td>
                                                    <div class="odv-item">
                                                        <a class="odv-item__thumb"
                                                           href="{{ route('vendor.item.view', $itemSnapshot['id'] ?? $detail->item_id) }}">
                                                            <img class="onerror-image"
                                                                 src="{{ $product?->image_full_url ?? asset('public/assets/admin/img/100x100/2.png') }}"
                                                                 data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                                                                 alt="{{ $itemSnapshot['name'] ?? ($product?->name ?? '') }}">
                                                        </a>
                                                        <div class="odv-item__body">
                                                            <div>
                                                                <strong class="odv-item__name">{{ $itemSnapshot['name'] ?? ($product?->name ?? '') }}</strong>
                                                                <?php $unitPrice = $detail['price']; ?>
                                                                <span class="odv-item__unit">
                                                                    {{ \App\CentralLogics\Helpers::format_currency($unitPrice) }}
                                                                    {{ translate('messages.each') }}
                                                                </span>
                                                                <div class="odv-item__opts">
                                                                    @if ($is_food_module)
                                                                        @if ($detailVariations)
                                                                            @foreach ($detailVariations as $variation)
                                                                                @if (isset($variation['name']) && isset($variation['values']))
                                                                                    <span class="d-block text-capitalize">
                                                                                        <strong>{{ $variation['name'] }} -</strong>
                                                                                    </span>
                                                                                    @foreach ($variation['values'] as $value)
                                                                                        <span class="d-block text-capitalize">
                                                                                            &nbsp; &nbsp;
                                                                                            {{ $value['label'] }} :
                                                                                            <strong>{{ \App\CentralLogics\Helpers::format_currency($value['optionPrice']) }}</strong>
                                                                                        </span>
                                                                                    @endforeach
                                                                                @else
                                                                                    @if (isset($detailVariations[0]))
                                                                                        <strong><u>{{ translate('messages.variation') }} : </u></strong>
                                                                                        @foreach ($detailVariations[0] as $key1 => $variation)
                                                                                            <div class="font-size-sm text-body">
                                                                                                <span>{{ $key1 }} : </span>
                                                                                                <span class="font-weight-bold">{{ $variation }}</span>
                                                                                            </div>
                                                                                        @endforeach
                                                                                    @endif
                                                                                @endif
                                                                            @endforeach
                                                                        @endif
                                                                    @else
                                                                        @if (count($detailVariations) > 0)
                                                                            <strong><u>{{ translate('messages.variation') }} : </u></strong>
                                                                            <?php $detailsVariation = $detailVariations[0] ?? $detailVariations; ?>
                                                                            @foreach ($detailsVariation as $key1 => $variation)
                                                                                @if ($key1 != 'stock')
                                                                                    <div class="font-size-sm text-body">
                                                                                        <span>{{ $key1 }} : </span>
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
                                                @if ($is_food_module)
                                                    <td>
                                                        <div class="odv-item__opts">
                                                            @foreach ($detailAddons as $key2 => $addon)
                                                                @if ($key2 == 0)
                                                                    <span class="odv-item__opts-title">{{ translate('Addons') }}</span>
                                                                @endif
                                                                <div>
                                                                    <span>{{ Str::limit($addon['name'], 20, '...') }} : </span>
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
                                                <td class="odv-qty">&times;{{ $detail['quantity'] }}</td>
                                                <td class="odv-line-total">
                                                    <?php
                                                    $amount = $detail['price'] * $detail['quantity'];
                                                    $lineTotal = $unitPrice * $detail['quantity'];
                                                    ?>
                                                    {{ \App\CentralLogics\Helpers::format_currency($lineTotal) }}
                                                </td>
                                            </tr>
                                            <?php $product_price += $amount; ?>
                                            <?php $store_discount_amount += $detail['discount_on_item'] * $detail['quantity']; ?>
                                        @elseif(isset($detail->item_campaign_id))
                                            <?php
                                            $campaign = $detail->campaign;
                                            $campaignSnapshot = json_decode($detail->item_details, true) ?: [];
                                            ?>
                                            <tr>
                                                <td class="odv-items__sl">{{ $key + 1 }}</td>
                                                <td>
                                                    <div class="odv-item">
                                                        <div class="odv-item__thumb">
                                                            <img class="onerror-image"
                                                                 src="{{ $campaign?->image_full_url ?? asset('public/assets/admin/img/100x100/2.png') }}"
                                                                 data-onerror-image="{{ asset('public/assets/admin/img/100x100/2.png') }}"
                                                                 alt="{{ $campaignSnapshot['name'] ?? ($campaign?->title ?? '') }}">
                                                        </div>
                                                        <div class="odv-item__body">
                                                            <div>
                                                                <strong class="odv-item__name">{{ $campaignSnapshot['name'] ?? ($campaign?->title ?? '') }}</strong>
                                                                <?php $unitPrice = $detail['price']; ?>
                                                                <span class="odv-item__unit">
                                                                    {{ \App\CentralLogics\Helpers::format_currency($unitPrice) }}
                                                                    {{ translate('messages.each') }}
                                                                </span>
                                                                <div class="odv-item__opts">
                                                                    @if (count($detailVariations) > 0)
                                                                        <strong><u>{{ translate('messages.variation') }} : </u></strong>
                                                                        @foreach ($detailVariations[0] as $key1 => $variation)
                                                                            @if ($key1 != 'stock')
                                                                                <div class="font-size-sm text-body">
                                                                                    <span>{{ $key1 }} : </span>
                                                                                    <span class="font-weight-bold">{{ Str::limit($variation, 25, '...') }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                @if ($is_food_module)
                                                    <td>
                                                        <div class="odv-item__opts">
                                                            @foreach ($detailAddons as $key2 => $addon)
                                                                @if ($key2 == 0)
                                                                    <span class="odv-item__opts-title">{{ translate('Addons') }}</span>
                                                                @endif
                                                                <div>
                                                                    <span>{{ Str::limit($addon['name'], 20, '...') }} : </span>
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
                                                <td class="odv-qty">&times;{{ $detail['quantity'] }}</td>
                                                <td class="odv-line-total">
                                                    <?php
                                                    $amount = $detail['price'] * $detail['quantity'];
                                                    $lineTotal = $unitPrice * $detail['quantity'];
                                                    ?>
                                                    {{ \App\CentralLogics\Helpers::format_currency($lineTotal) }}
                                                </td>
                                            </tr>
                                            <?php $product_price += $amount; ?>
                                            <?php $store_discount_amount += $detail['discount_on_item'] * $detail['quantity']; ?>
                                        @endif
                                    @endforeach
                                    @foreach ($bogoGroups['bundles'] as $bogo)
                                        @include('partials.promotion._bogo_order_row', [
                                            'bogo' => $bogo,
                                            'sl' => $bundleGroups['plain']->count() + $loop->iteration,
                                            'hasAddons' => $is_food_module,
                                            'hasQty' => true,
                                        ])
                                        <?php $product_price += $bogo['total']; ?>
                                    @endforeach
                                    @foreach ($bundleGroups['bundles'] as $bundle)
                                        @include('partials.bundle._order_row', [
                                            'bundle' => $bundle,
                                            'sl' => $bundleGroups['plain']->count() + count($bogoGroups['bundles']) + $loop->iteration,
                                            'hasAddons' => $is_food_module,
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
                        if ($order->tax_status == 'included') {
                            $total_tax_amount = 0;
                        }
                        $store_discount_amount = $order['store_discount_amount'];
                        $prescription_editable = $order->prescription_order == 1 && in_array($order['order_status'], ['pending', 'confirmed', 'processing', 'accepted']);
                        ?>

                        <div class="odv-summary-wrap">
                            <dl class="odv-summary">
                                <dt class="col-6">{{ translate('messages.Items price') }}</dt>
                                <dd class="col-6">{{ \App\CentralLogics\Helpers::format_currency($product_price) }}</dd>

                                @if ($is_food_module)
                                    <dt class="col-6">{{ translate('messages.Addon cost') }}</dt>
                                    <dd class="col-6">{{ \App\CentralLogics\Helpers::format_currency($total_addon_price) }}</dd>
                                @endif

                                <dt class="col-6">{{ translate('messages.subtotal') }}
                                    @if ($order->tax_status == 'included' || $tax_included == 1)
                                        ({{ translate('TAX included') }})
                                    @endif
                                </dt>
                                <dd class="col-6">
                                    @if ($prescription_editable)
                                        <button class="btn btn-sm" type="button" data-toggle="modal"
                                                data-target="#edit-order-amount"><i class="tio-edit"></i></button>
                                    @endif
                                    {{ \App\CentralLogics\Helpers::format_currency($product_price + $total_addon_price) }}
                                </dd>

                                {{-- Accessors, not $store_discount_amount: that figure also carries the
                                     items' own discounts and the bundle reduction, so testing it > 0
                                     labelled plain item discounts and bundle-only orders "store discount
                                     applied". --}}
                                <dt class="col-6">{{ translate('Discount') }}
                                    @if ($order->is_happy_hour)
                                        <i class="tio-info-outined" data-toggle="tooltip"
                                           title="{{ translate('messages.happy_hour_discount_applied') }}"></i>
                                    @elseif ($order->is_store_discount)
                                        <i class="tio-info-outined" data-toggle="tooltip"
                                           title="{{ translate('messages.store_discount_applied') }}"></i>
                                    @endif
                                </dt>
                                <dd class="col-6">
                                    @if ($prescription_editable)
                                        <button class="btn btn-sm" type="button" data-toggle="modal"
                                                data-target="#edit-discount-amount"><i class="tio-edit"></i></button>
                                    @endif
                                    - {{ \App\CentralLogics\Helpers::format_currency($store_discount_amount + $order['flash_admin_discount_amount'] + $order['flash_store_discount_amount']) }}
                                </dd>

                                <dt class="col-6">{{ translate('Coupon discount') }}
                                    @if ($order->orderProDiscount && $order->orderProDiscount->benefit_type === 'coupon')
                                        <i class="tio-info-outined" data-toggle="tooltip"
                                           title="{{ translate('Pro customer coupon applied.') }}"></i>
                                    @endif
                                </dt>
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

                                @if ($order->tax_status == 'excluded' || $order->tax_status == null)
                                    <dt class="col-6">{{ translate('VAT/tax') }}</dt>
                                    <dd class="col-6">
                                        + {{ \App\CentralLogics\Helpers::format_currency($total_tax_amount) }}
                                    </dd>
                                @endif

                                <dt class="col-6">{{ translate('Deliveryman tips') }}</dt>
                                <dd class="col-6">+ {{ \App\CentralLogics\Helpers::format_currency($order->dm_tips) }}</dd>

                                <dt class="col-6">{{ translate('Delivery fee') }}
                                    @if (
                                        $order->orderProDiscount &&
                                            $order->orderProDiscount->benefit_type === 'delivery_fee' &&
                                            ($order->orderProDiscount->delivery_fee_reduction_amount ?? 0) > 0)
                                        @if ($order->orderProDiscount->delivery_offer_type === 'full_free')
                                            <i class="tio-info-outined" data-toggle="tooltip"
                                               title="{{ translate('Pro customer free delivery has been applied to this order.') }}"></i>
                                        @else
                                            <i class="tio-info-outined" data-toggle="tooltip"
                                               title="{{ translate('Pro customer partial delivery discount has been applied to this order') }} ({{ (float) ($order->orderProDiscount->delivery_charge_discount_percentage ?? 0) }}%)"></i>
                                        @endif
                                    @elseif ($order->free_delivery_by == 'admin')
                                        <i class="tio-info-outined" data-toggle="tooltip"
                                           title="{{ translate('Delivery fee is applicable and will be covered by the admin.') }}"></i>
                                    @elseif ($order->free_delivery_by == 'vendor')
                                        <i class="tio-info-outined" data-toggle="tooltip"
                                           title="{{ translate('Delivery fee is applicable and will be covered by the vendor.') }}"></i>
                                    @elseif ($order->surge_amount > 0)
                                        <i class="tio-info-outined" data-toggle="tooltip"
                                           title="{{ translate('messages.surge_price') }} {{ \App\CentralLogics\Helpers::format_currency($order->surge_amount) }}"></i>
                                    @endif
                                </dt>
                                <dd class="col-6">
                                    + {{ \App\CentralLogics\Helpers::format_currency(app(\App\Services\Order\OrderService::class)->proDeliveryBreakdown($order)['original_fee']) }}
                                </dd>
                                @include('partials.pro-delivery-discount-row', ['order' => $order, 'layout' => 'dl', 'dtClass' => '', 'ddClass' => ''])
                                @include('partials.delivery-type-row', ['order' => $order, 'layout' => 'dl', 'dtClass' => '', 'ddClass' => ''])

                                <dt class="col-6">{{ $additional_charge_name ?? translate('Additional charge') }}</dt>
                                <dd class="col-6">
                                    + {{ \App\CentralLogics\Helpers::format_currency($order['additional_charge']) }}
                                </dd>

                                @if ($order['extra_packaging_amount'] > 0)
                                    <dt class="col-6">{{ translate('Extra packaging amount') }}</dt>
                                    <dd class="col-6">
                                        + {{ \App\CentralLogics\Helpers::format_currency($order['extra_packaging_amount']) }}
                                    </dd>
                                @endif

                                @if ($order['partially_paid_amount'] > 0)
                                    <dt class="col-6">{{ translate('messages.Partially paid amount') }}</dt>
                                    <dd class="col-6">
                                        {{ \App\CentralLogics\Helpers::format_currency($order['partially_paid_amount']) }}
                                    </dd>
                                    <dt class="col-6">{{ translate('Due amount') }}</dt>
                                    @if ($order['payment_method'] == 'partial_payment')
                                        <dd class="col-6">
                                            {{ \App\CentralLogics\Helpers::format_currency($order['partially_paid_amount']) }}
                                        </dd>
                                    @else
                                        <dd class="col-6">{{ \App\CentralLogics\Helpers::format_currency(0) }}</dd>
                                    @endif
                                @endif

                                <div class="odv-summary__rule"></div>
                                <dt class="col-6 odv-summary__total-label">{{ translate('messages.Total') }} {{ $order->tax_status == 'included' ? '(' . translate('TAX included') . ')' : '' }}</dt>
                                <dd class="col-6 odv-summary__total-value">
                                    {{ \App\CentralLogics\Helpers::format_currency($product_price + $delivery_fee_info['adjusted'] + $total_tax_amount + $total_addon_price + $order['additional_charge'] - $order['coupon_discount_amount'] - $store_discount_amount - $order['flash_admin_discount_amount'] - $order['ref_bonus_amount'] - ($order->orderProDiscount?->amount_saved ?? 0) + $order['extra_packaging_amount'] - $order['flash_store_discount_amount'] + $order->dm_tips - $order->extra_discount_amount) }}
                                </dd>

                                @if ($order?->payments)
                                    @foreach ($order?->payments as $payment)
                                        @if ($payment->payment_status == 'paid')
                                            @if ($payment->payment_method == 'cash_on_delivery')
                                                <dt class="col-6">{{ translate('messages.Paid with Cash') }} ({{ translate('COD') }})</dt>
                                            @else
                                                <dt class="col-6">{{ translate('Paid by') }} {{ payment_method_label($payment->payment_method) }}</dt>
                                            @endif
                                        @else
                                            <dt class="col-6">{{ translate('Due amount') }} ({{ $payment->payment_method == 'cash_on_delivery' ? translate('messages.COD') : payment_method_label($payment->payment_method) }})</dt>
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
                                        {{ translate('Total bill has been updated after the edits.') }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4 order-print-area-right odv-side">
                <?php
                $has_order_setup_actions = in_array($order->order_status, ['pending', 'confirmed', 'accepted', 'processing', 'handover'])
                    || ($order->order_status == 'picked_up' && $order->store->sub_self_delivery == 1);
                ?>
                @if ($has_order_setup_actions)
                    <div class="card">
                        <div class="card-body">
                            <div class="odv-sec__head">
                                <span class="odv-sec__icon">
                                    <img class="svg" src="{{ asset('public/assets/admin/img/icons/shop-bag.svg') }}" alt="">
                                </span>
                                <h2 class="odv-sec__title">{{ translate('messages.Order setup') }}</h2>
                            </div>

                            <div class="odv-panel d-flex flex-column gap-2">
                                @if ($order['order_status'] == 'pending')
                                    <div class="row g-1">
                                        <div class="{{ BusinessRules::canceledByStore() ? 'col-6' : 'col-12' }}">
                                            <a class="btn btn--primary w-100 fz--13 px-2 route-alert"
                                               data-url="{{ route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'confirmed']) }}"
                                               data-message="{{ translate('messages.Confirm this order?') }}"
                                               href="javascript:"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Confirm this order?') }}</a>
                                        </div>
                                        @if (BusinessRules::canceledByStore())
                                            <div class="col-6">
                                                <a class="btn btn--danger w-100 fz--13 px-2 cancelled-status"><i
                                                        class="tio-clear-circle-outlined"></i> {{ translate('Cancel order') }}</a>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                @if (in_array($order['order_status'], ['confirmed', 'accepted']))
                                    @if ($is_food_module)
                                        <a class="btn btn--primary w-100 order-status-change-alert"
                                           data-url="{{ route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'processing']) }}"
                                           data-message="{{ translate('Change status to cooking?') }}"
                                           data-verification="false"
                                           data-processing-time="{{ $max_processing_time }}"
                                           href="javascript:"><i class="tio-arrow-forward"></i> {{ translate('messages.Proceed for processing') }}</a>
                                    @else
                                        <a class="btn btn--primary w-100 route-alert"
                                           data-url="{{ route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'processing']) }}"
                                           data-message="{{ translate('messages.Proceed for processing') }}"
                                           href="javascript:"><i class="tio-arrow-forward"></i> {{ translate('messages.Proceed for processing') }}</a>
                                    @endif
                                @endif

                                @if ($order['order_status'] == 'processing')
                                    <a class="btn btn--primary w-100 route-alert"
                                       data-url="{{ route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'handover']) }}"
                                       data-message="{{ translate('messages.Make ready for handover') }}"
                                       href="javascript:"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Make ready for handover') }}</a>
                                @endif

                                @if ($order['order_status'] == 'handover' || ($order['order_status'] == 'picked_up' && $order->store->sub_self_delivery == 1))
                                    <a class="btn w-100 {{ $order['order_type'] == 'take_away' || $order->store->sub_self_delivery == 1 || $order->is_pos ? 'btn--primary order-status-change-alert' : 'btn--secondary self-delivery-warning' }}"
                                       data-url="{{ route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'delivered']) }}"
                                       data-message="{{ translate('messages.Change status to delivered (payment status will be paid if not)?') }}"
                                       data-verification="{{ $order_delivery_verification ? 'true' : 'false' }}"
                                       href="javascript:"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Make delivered') }}</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

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
                                @if ($order->payment_status == 'paid' || $order->payment_status == 'partially_paid')
                                    @if ($order?->payments)
                                        @foreach ($paid_payments as $pay_info)
                                            <div class="odv-kv__row">
                                                <span class="odv-kv__label">{{ translate('Amount paid by') }} {{ payment_method_label($pay_info->payment_method) }}</span>
                                                <span class="odv-kv__value">{{ \App\CentralLogics\Helpers::format_currency($pay_info->amount) }}</span>
                                            </div>
                                        @endforeach
                                        <div class="odv-kv__row">
                                            <span class="odv-kv__label">{{ translate('Amount returned to wallet') }}</span>
                                            <span class="odv-kv__value">{{ \App\CentralLogics\Helpers::format_currency($paid_payments_amount) }}</span>
                                        </div>
                                    @else
                                        <div class="odv-kv__row">
                                            <span class="odv-kv__label">{{ translate('Amount paid by') }} {{ payment_method_label($order->payment_method) }}</span>
                                            <span class="odv-kv__value">{{ \App\CentralLogics\Helpers::format_currency($order->order_amount) }}</span>
                                        </div>
                                        <div class="odv-kv__row">
                                            <span class="odv-kv__label">{{ translate('Amount returned to wallet') }}</span>
                                            <span class="odv-kv__value">{{ \App\CentralLogics\Helpers::format_currency($order->order_amount) }}</span>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @if ($order['order_type'] != 'take_away' && $order->store->sub_self_delivery == 1)
                    <div class="card">
                        <div class="card-body">
                            <div class="odv-sec__head">
                                <span class="odv-sec__icon"><i class="tio-bike"></i></span>
                                <h2 class="odv-sec__title">{{ translate('Deliveryman') }}</h2>
                            </div>

                            @if ($order->delivery_man)
                                <div class="odv-party">
                                    <div class="odv-party__avatar">
                                        <img class="onerror-image"
                                             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                             src="{{ $order->delivery_man->image_full_url }}"
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
                                </div>

                                <?php $address = $order->delivery_man->last_location; ?>
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
                            @else
                                <div class="odv-empty">{{ translate('No data found') }}</div>
                            @endif
                        </div>
                    </div>
                @endif

                <?php $data = isset($order->order_proof) ? json_decode($order->order_proof, true) : []; ?>
                @if (in_array($order->order_status, ['handover', 'delivered', 'picked_up']) || ($data != null && count($data) > 0))
                    <div class="card">
                        <div class="card-body">
                            <div class="odv-sec__head">
                                <span class="odv-sec__icon"><i class="tio-photo-gallery-outlined"></i></span>
                                <h2 class="odv-sec__title">{{ translate('messages.Delivery proof') }}</h2>
                                @if ($order['store']['sub_self_delivery'])
                                    <button class="btn btn-outline-primary btn-sm odv-sec__aside" data-toggle="modal"
                                            data-target=".order-proof-modal">
                                        <i class="tio-add"></i> {{ translate('Add') }}
                                    </button>
                                @endif
                            </div>

                            @if (!$data)
                                <div class="odv-empty">{{ translate('messages.No image added yet') }}</div>
                            @else
                                <div class="odv-thumbs">
                                    @foreach ($data as $key => $img)
                                        <?php $img = is_array($img) ? $img : ['img' => $img, 'storage' => 'public']; ?>
                                        <img class="odv-thumb onerror-image" data-toggle="modal"
                                             data-target="#imagemodal{{ $key }}"
                                             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                             src="{{ \App\CentralLogics\Helpers::get_full_url('order', $img['img'], $img['storage']) }}"
                                             alt="{{ translate('Order proof image') }}">
                                        <div class="modal fade" id="imagemodal{{ $key }}" tabindex="-1" role="dialog"
                                             aria-labelledby="order_proof_{{ $key }}" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title" id="order_proof_{{ $key }}">
                                                            {{ translate('Order proof image') }}</h4>
                                                        <button type="button" class="close" data-dismiss="modal"><span
                                                                aria-hidden="true">&times;</span><span
                                                                class="sr-only">{{ translate('messages.Cancel') }}</span></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <img src="{{ \App\CentralLogics\Helpers::get_full_url('order', $img['img'], $img['storage']) }}"
                                                             class="initial--22 w-100" alt="img">
                                                    </div>
                                                    <?php $storage = $img['storage'] ?? 'public'; ?>
                                                    <?php $file = $storage == 's3' ? base64_encode('order/' . $img['img']) : base64_encode('public/order/' . $img['img']); ?>
                                                    <div class="modal-footer">
                                                        <a class="btn btn-primary"
                                                           href="{{ route('admin.business-settings.file-manager.download', [$file, $storage]) }}"><i
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

                <div class="card">
                    <div class="card-body">
                        <div class="odv-sec__head">
                            <span class="odv-sec__icon"><i class="tio-user"></i></span>
                            <h2 class="odv-sec__title">{{ translate('Customer information') }}</h2>
                        </div>

                        @if ($order->customer)
                            <div class="odv-party">
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
                                        <span><i class="tio-email-outlined"></i>{{ $order->customer['email'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @elseif($order->is_guest)
                            <div class="odv-empty">{{ translate('Guest user') }}</div>
                        @else
                            <div class="odv-empty">{{ translate('No data found') }}</div>
                        @endif

                        @if ($order->delivery_address)
                            <?php
                            $address = json_decode($order->delivery_address, true);
                            $hfr = [];
                            if (data_get($address, 'house') != '') $hfr[] = ['label' => translate('House'), 'value' => data_get($address, 'house')];
                            if (data_get($address, 'floor') != '') $hfr[] = ['label' => translate('Floor'), 'value' => data_get($address, 'floor')];
                            if (data_get($address, 'road') != '')  $hfr[] = ['label' => translate('Road'),  'value' => data_get($address, 'road')];
                            ?>
                            <div class="odv-sec__head mt-4">
                                <span class="odv-sec__icon"><i class="tio-truck"></i></span>
                                <h2 class="odv-sec__title">{{ translate('Delivery information') }}</h2>
                            </div>
                            @if (isset($address))
                                <div class="odv-kv">
                                    <div class="odv-kv__row">
                                        <span class="odv-kv__label">{{ translate('Name') }}</span>
                                        <span class="odv-kv__value">
                                            {{ data_get($address, 'contact_person_name', translate('messages.N/A')) }}
                                            @if (data_get($address, 'contact_person_number'))
                                                <a class="deco-none" href="tel:{{ data_get($address, 'contact_person_number') }}">({{ data_get($address, 'contact_person_number') }})</a>
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

                                    @if ($order['order_type'] != 'take_away' && isset($address['address']))
                                        <div class="odv-kv__row">
                                            <span class="odv-kv__label">{{ translate('Location') }}</span>
                                            <span class="odv-kv__value">
                                                @if (data_get($address, 'latitude') && data_get($address, 'longitude'))
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

                <form action="{{ route('vendor.order.add-payment-ref-code', [$order['id']]) }}" method="post">
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

                <form action="{{ route('vendor.order.add-order-proof', [$order['id']]) }}" method="post"
                      enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="flex-grow-1 mx-auto">
                            <div class="d-flex flex-wrap __gap-12px __new-coba" id="coba">
                                <?php $proof = isset($order->order_proof) ? json_decode($order->order_proof, true) : 0; ?>
                                @if ($proof)
                                    @foreach ($proof as $key => $photo)
                                        <?php $photo = is_array($photo) ? $photo : ['img' => $photo, 'storage' => 'public']; ?>
                                        <div class="spartan_item_wrapper min-w-176px max-w-176px">
                                            <img class="img--square"
                                                 src="{{ \App\CentralLogics\Helpers::get_full_url('order', $photo['img'], $photo['storage']) }}"
                                                 alt="order image">
                                            <div class="pen spartan_remove_row"><i class="tio-edit"></i></div>
                                            <a href="{{ route('vendor.order.remove-proof-image', ['id' => $order['id'], 'name' => $photo['img']]) }}"
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

    <div class="modal fade" id="edit-order-amount" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('messages.Update order amount') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('vendor.order.update-order-amount') }}" method="POST" class="row">
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                        <div class="form-group col-12">
                            <label for="order_amount">{{ translate('Order amount') }}</label>
                            <input id="order_amount" type="number" class="form-control" name="order_amount" min="0"
                                   value="{{ round($order['order_amount'] - $order['total_tax_amount'] - $order['additional_charge'] - $order['delivery_charge'] + $order['store_discount_amount'] - $order['dm_tips'], 6) }}"
                                   step=".01">
                        </div>

                        <div class="form-group col-sm-12">
                            <button class="btn btn-sm btn-primary" type="submit"><i
                                    class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="edit-discount-amount" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('messages.Update discount amount') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('vendor.order.update-discount-amount') }}" method="POST" class="row">
                        @csrf
                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                        <div class="form-group col-12">
                            <label for="discount_amount">{{ translate('Discount amount') }}</label>
                            <input type="number" id="discount_amount" class="form-control" name="discount_amount"
                                   min="0" value="{{ $order['store_discount_amount'] }}" step=".01">
                        </div>

                        <div class="form-group col-sm-12">
                            <button class="btn btn-sm btn-primary" type="submit"><i
                                    class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}</button>
                        </div>
                    </form>
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
                                $statusBadge = match ($order->order_status) {
                                    'pending' => 'badge-soft-info',
                                    'confirmed', 'accepted' => 'badge-soft-success',
                                    'processing' => 'badge-soft-warning',
                                    'handover', 'picked_up' => 'badge-soft-primary',
                                    'delivered' => 'badge-soft-success',
                                    'canceled' => 'badge-soft-danger',
                                    default => 'badge-soft-secondary',
                                };
                                ?>
                                <span class="badge {{ $statusBadge }} font-regular m-0">{{ $orderStatusLabel }}</span>
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
                        <input id="food_search" type="search" class="h-100 fs-12 bg-transparent w-100 border-0 rounded-0"
                               placeholder="{{ translate('Search by food name') }}" autocomplete="off"
                               data-store-id="{{ $order->store_id }}">
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

    <div class="modal shedule-modal fade" id="edit_order_confirmation-btn" tabindex="-1"
         aria-labelledby="exampleModalLabel" aria-hidden="true">
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
                        <img src="{{ asset('public/assets/admin/img/delete-confirmation.png') }}" alt="icon" class="mb-3">
                        <h3 class="mb-2">{{ translate('Are you sure you want to edit this order?') }}</h3>
                        <p class="mb-0">{{ translate('messages.If you edit this order, some product details will be updated, which may affect the total price.') }}</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 gap-2">
                    <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.No') }}</button>
                    <a href="{{ route('vendor.order.edit', $order->id) }}" class="btn min-w-120px btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Yes') }}</a>
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
                        <img src="{{ asset('public/assets/admin/img/delete-confirmation.png') }}" alt="icon" class="mb-3">
                        @if ($is_food_module)
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
    $pageRoutes = [
        'orderStatus'        => route('vendor.order.status', ['id' => $order['id'], 'order_status' => 'canceled']),
        'quickViewCartItem'  => route('vendor.order.quick-view-cart-item'),
        'quickView'          => route('vendor.order.quick-view'),
        'variantPrice'       => route('vendor.item.variant-price'),
        'addToCart'          => route('vendor.order.add-to-cart'),
        'removeFromCart'     => route('vendor.order.remove-from-cart'),
        'orderUpdate'        => route('vendor.order.update', $order->id),
        'searchItems'        => route('vendor.order.search-items'),
        'cartList'           => route('vendor.order.cart-list'),
        'updateCartQuantity' => route('vendor.order.update-cart-quantity'),
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
    ?>
    <div id="order-page-config"
         hidden
         data-order-id="{{ $order->id }}"
         data-order-proof-count="{{ ($order->order_proof && is_array($order->order_proof)) ? count(json_decode($order->order_proof)) : 0 }}"
         data-open-edit-offcanvas="{{ $open_edit_offcanvas }}"
         data-img-upload="{{ asset('public/assets/admin/img/upload-img.png') }}"
         data-img-placeholder="{{ asset('public/assets/admin/img/100x100/2.png') }}"
         data-routes='@json($pageRoutes)'
         data-translations='@json($pageTranslations)'></div>

    <template id="cancel-reasons-template">@foreach ($reasons as $r)<option value="{{ $r->reason }}">{{ $r->reason }}</option>@endforeach</template>

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/spartan-multi-image-picker.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/view-pages/order-edit-offcanvas.js') }}"></script>
@endpush
