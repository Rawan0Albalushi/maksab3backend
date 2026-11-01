<!DOCTYPE html>
<?php
$lang = \App\CentralLogics\Helpers::system_default_language();
$site_direction = \App\CentralLogics\Helpers::system_default_direction();
?>
<html lang="{{ $lang }}" class="{{ $site_direction === 'rtl' ? 'active' : '' }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ translate('Email template') }}</title>

    @include('email-templates.partials._style-order')

</head>


<body style="background-color: #e9ecef;padding:15px">

    <table dir="{{ $site_direction }}" class="main-table">
        <tbody>
            <tr>
                <td class="main-table-td">
                    <h2 class="mb-3" id="mail-title">{{ $title ?? translate('Main title or subject of the mail') }}
                    </h2>
                    <div class="mb-1" id="mail-body">{!! $body ?? translate('Hi sabrina,') !!}</div>
                    <span class="d-block text-center mb-3">
                        @if ($data?->button_url)
                            <a type="button" href="{{ $data['button_url'] ?? '#' }}" class="cmn-btn"
                                id="mail-button">{{ $data['button_name'] ?? 'Submit' }}</a>
                        @endif
                    </span>
                    <table class="bg-section p-10 w-100">
                        <tbody>
                            <tr>
                                <td class="p-10">
                                    <span class="d-block text-center">
                                        <img class="mb-2 mail-img-2"
                                            src="{{ $data['logo_full_url'] ?? asset('/public/assets/admin/img/blank1.png') }}"
                                            alt="">
                                        <h3 class="mb-3 mt-0">{{ translate('Order information') }}</h3>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table class="order-table w-100">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <h3 class="subtitle">{{ translate('Order summary') }}</h3>
                                                    <span class="d-block">{{ translate('Order') }}#
                                                        {{ $order->id }}</span>
                                                    <span class="d-block">{{ $order->created_at }}</span>
                                                </td>
                                                <td style="max-width:130px">
                                                    <h3 class="subtitle">{{ translate('Delivery address') }}</h3>
                                                    @if ($order->delivery_address)
                                                        @php($address = json_decode($order->delivery_address, true))
                                                        <span
                                                            class="d-block">{{ $address['contact_person_name'] ?? $order->customer?->f_name . ' ' . $order->customer?->l_name }}</span>
                                                        <span class="d-block">
                                                            {{ $address['contact_person_number'] ?? null }}
                                                        </span>
                                                        <span class="d-block">
                                                            {{ $address['address'] ?? null }}
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                            <?php
                                            $subtotal = 0;
                                            $total = 0;
                                            $sub_total = 0;
                                            $total_tax = 0;
                                            $total_shipping_cost = $order->delivery_charge;
                                            $total_discount_on_product = 0;
                                            $extra_discount = 0;
                                            $total_addon_price = 0;
                                            ?>
                                            <td colspan="2">
                                                <table class="w-100">
                                                    <thead class="bg-section-2">
                                                        <tr>
                                                            <th class="text-left p-1 px-3">{{ translate('Product') }}
                                                            </th>
                                                            <th class="text-right p-1 px-3">{{ translate('price') }}
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if ($order->order_type == 'parcel')
                                                            <tr>
                                                                <td class="text-left p-2 px-3">
                                                                    {{ Str::limit($order->parcel_category ? $order->parcel_category->name : translate('messages.Parcel category not found'), 25, '...') }}
                                                                    @include('partials.parcel-tier-lines', ['order' => $order])
                                                                </td>
                                                                <td class="text-right p-2 px-3">
                                                                    <h4>
                                                                        {{ \App\CentralLogics\Helpers::format_currency(app(\App\Services\Order\OrderService::class)->proDeliveryBreakdown($order)['original_fee']) }}
                                                                    </h4>
                                                                </td>
                                                            </tr>
                                                            @include('partials.pro-delivery-discount-row', ['order' => $order, 'layout' => 'tr'])
                                                        @else
                                                            @foreach ($order->details as $key => $details)
                                                                <?php
                                                                $subtotal = $details['price'] * $details->quantity;
                                                                $item_details = json_decode($details->item_details, true);
                                                                ?>
                                                                <tr>
                                                                    <td class="text-left p-2 px-3">
                                                                        <span style="font-size: 14px;">
                                                                            {{ Str::limit($item_details['name'], 40, '...') }}
                                                                        </span>
                                                                        <br>
                                                                        @if (count(json_decode($details['variation'], true)) > 0)
                                                                            <span style="font-size: 12px;">
                                                                                {{ translate('messages.variation') }} :
                                                                                @foreach (json_decode($details['variation'], true) as $variation)
                                                                                    @if (isset($variation['name']) && isset($variation['values']))
                                                                                        <span
                                                                                            class="d-block text-capitalize">
                                                                                            <strong>{{ $variation['name'] }}
                                                                                                - </strong>
                                                                                            @foreach ($variation['values'] as $value)
                                                                                                {{ $value['label'] }}
                                                                                                @if ($value !== end($variation['values']))
                                                                                                    ,
                                                                                                @endif
                                                                                            @endforeach
                                                                                        </span>
                                                                                    @else
                                                                                        @if (isset(json_decode($details['variation'], true)[0]))
                                                                                            @foreach (json_decode($details['variation'], true)[0] as $key1 => $variation)
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
                                                                            </span>
                                                                        @endif
                                                                        @foreach (json_decode($details['add_ons'], true) as $key2 => $addon)
                                                                            @if ($key2 == 0)
                                                                                <br><span
                                                                                    style="font-size: 12px;"><u>{{ translate('Addons') }}
                                                                                    </u></span>
                                                                            @endif
                                                                            <div style="font-size: 12px;">
                                                                                <span>{{ Str::limit($addon['name'], 20, '...') }}
                                                                                    : </span>
                                                                                <span class="font-weight-bold">
                                                                                    {{ $addon['quantity'] }} x
                                                                                    {{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}
                                                                                </span>
                                                                            </div>
                                                                            @php($total_addon_price += $addon['price'] * $addon['quantity'])
                                                                        @endforeach
                                                                        <span>x {{ $details->quantity }}</span>
                                                                    </td>
                                                                    <td class="text-right p-2 px-3">
                                                                        <h4>
                                                                            {{ \App\CentralLogics\Helpers::format_currency($subtotal) }}
                                                                        </h4>
                                                                    </td>
                                                                </tr>
                                                                <?php
                                                                $sub_total += $details['price'] * $details['quantity'];
                                                                $total_tax += $details['tax'];
                                                                $total_discount_on_product += $details['discount'];
                                                                $total += $subtotal;
                                                                ?>
                                                            @endforeach
                                                        @endif
                                                        <tr>
                                                            <td colspan="2">
                                                                <hr class="mt-0">
                                                                <table class="w-100">
                                                                    @if ($order->order_type != 'parcel')
                                                                        <tr>
                                                                            <td style="width: 40%"></td>
                                                                            <td class="p-1 px-3">
                                                                                {{ translate('Item price') }}
                                                                            </td>
                                                                            <td class="text-right p-1 px-3">
                                                                                {{ \App\CentralLogics\Helpers::format_currency($sub_total) }}
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="width: 40%"></td>
                                                                            <td class="p-1 px-3">
                                                                                {{ translate('messages.Addon cost') }}
                                                                            </td>
                                                                            <td class="text-right p-1 px-3">
                                                                                {{ \App\CentralLogics\Helpers::format_currency($total_addon_price) }}
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="width: 40%"></td>
                                                                            <td class="p-1 px-3">
                                                                                {{ translate('messages.subtotal') }}
                                                                                @if ($order->tax_status == 'included')
                                                                                    ({{ translate('TAX included') }})
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-right p-1 px-3">
                                                                                {{ \App\CentralLogics\Helpers::format_currency($sub_total + $total_addon_price) }}
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="width: 40%"></td>
                                                                            <td class="p-1 px-3">
                                                                                {{ translate('Discount') }}
                                                                            </td>
                                                                            <td class="text-right p-1 px-3">
                                                                                {{ \App\CentralLogics\Helpers::format_currency($order->store_discount_amount) }}
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="width: 40%"></td>
                                                                            <td class="p-1 px-3">
                                                                                {{ translate('Coupon discount') }}
                                                                            </td>
                                                                            <td class="text-right p-1 px-3">
                                                                                {{ \App\CentralLogics\Helpers::format_currency($order->coupon_discount_amount) }}
                                                                            </td>
                                                                        </tr>

                                                                        @if ($order->extra_discount_amount > 0)
                                                                          <tr>
                                                                            <td style="width: 40%"></td>
                                                                            <td class="p-1 px-3">
                                                                                {{ translate('Extra discount') }}
                                                                            </td>
                                                                            <td class="text-right p-1 px-3">
                                                                                {{ \App\CentralLogics\Helpers::format_currency($order->extra_discount_amount) }}
                                                                            </td>
                                                                        </tr>
                                                                        @endif
                                                                        @if ($order?->ref_bonus_amount > 0)
                                                                            <tr>
                                                                                <td style="width: 40%"></td>
                                                                                <td class="p-1 px-3">
                                                                                    {{ translate('Referral discount') }}
                                                                                </td>
                                                                                <td class="text-right p-1 px-3">
                                                                                    {{ \App\CentralLogics\Helpers::format_currency($order->ref_bonus_amount) }}
                                                                                </td>
                                                                            </tr>
                                                                        @endif
                                                                        @if (($order->orderProDiscount?->amount_saved ?? 0) > 0)
                                                                            <tr>
                                                                                <td style="width: 40%"></td>
                                                                                <td class="p-1 px-3">
                                                                                    {{ translate('messages.Pro discount') }}
                                                                                </td>
                                                                                <td class="text-right p-1 px-3">
                                                                                    {{ \App\CentralLogics\Helpers::format_currency($order->orderProDiscount->amount_saved) }}
                                                                                </td>
                                                                            </tr>
                                                                        @endif

                                                                        @if ($order?->extra_packaging_amount > 0)
                                                                            <tr>
                                                                                <td style="width: 40%"></td>
                                                                                <td class="p-1 px-3">
                                                                                    {{ translate('Extra packaging amount') }}
                                                                                </td>
                                                                                <td class="text-right p-1 px-3">
                                                                                    {{ \App\CentralLogics\Helpers::format_currency($order->extra_packaging_amount) }}
                                                                                </td>
                                                                            </tr>
                                                                        @endif
                                                                        @endif
                                                                        @if (($order->tax_status == 'excluded' && $order->total_tax_amount > 0 )|| $order->tax_status == null)
                                                                            <tr>
                                                                                <td style="width: 40%"></td>
                                                                                <td class="p-1 px-3">
                                                                                    {{ translate('messages.tax') }}
                                                                                </td>
                                                                                <td class="text-right p-1 px-3">
                                                                                    {{ \App\CentralLogics\Helpers::format_currency($order->total_tax_amount) }}
                                                                                </td>
                                                                            </tr>
                                                                        @else
                                                                            <tr>
                                                                                <td style="width: 40%"></td>
                                                                                <td class="p-1 px-3">
                                                                                    {{ translate('messages.tax') }}
                                                                                </td>
                                                                                <td class="text-right p-1 px-3">
                                                                                    {{ \App\CentralLogics\Helpers::format_currency($total_tax) }}
                                                                                </td>
                                                                            </tr>
                                                                        @endif
                                                                        @if ($order->order_type != 'parcel')
                                                                        <tr>
                                                                            <td style="width: 40%"></td>
                                                                            <td class="p-1 px-3">
                                                                                {{ translate('Delivery charge') }}
                                                                            </td>
                                                                            <td class="text-right p-1 px-3">
                                                                                {{ \App\CentralLogics\Helpers::format_currency(app(\App\Services\Order\OrderService::class)->proDeliveryBreakdown($order)['original_fee']) }}
                                                                            </td>
                                                                        </tr>
                                                                        @include('partials.pro-delivery-discount-row', ['order' => $order, 'layout' => 'tr3'])
                                                                        @endif


                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3"> {{ translate('Deliveryman tips') }}
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">
                                                                            {{ \App\CentralLogics\Helpers::format_currency($order->dm_tips ?? 0) }}
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">
                                                                            {{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? (\App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? translate('Additional charge')) }}
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">
                                                                            {{ \App\CentralLogics\Helpers::format_currency($order->additional_charge ?? 0) }}
                                                                        </td>
                                                                    </tr>


                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">
                                                                            <h4>{{ translate('messages.Total') }} {{ $order->order_type == 'parcel' && $order->tax_status == 'included' ? '('.translate('TAX included').')'  :'' }}</h4>
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">
                                                                            <span
                                                                                class="text-base">{{ \App\CentralLogics\Helpers::format_currency($order->order_amount) }}</span>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <hr>

                    @isset($url)
                        <div class="mb-2">
                            <a href="{{ $url }}" target="_blank">{{ translate('Download invoice') }}</a>
                        </div>
                    @endisset

                    <div class="mb-2" id="mail-footer">
                        {{ $footer_text ?? 'Please contact us for any queries, we’re always happy to help. ' }}
                    </div>
                    <div>
                        {{ translate('Thanks & regards') }},
                    </div>
                    <div class="mb-4">
                        {{ $company_name }}
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    @include('email-templates.partials._footer')
                </td>
            </tr>
        </tbody>
    </table>


</body>

</html>
