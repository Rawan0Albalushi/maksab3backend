<!DOCTYPE html>
<?php
    $lang = \App\CentralLogics\Helpers::system_default_language();
    $site_direction = \App\CentralLogics\Helpers::system_default_direction();
?>
<html lang="{{ $lang }}" class="{{ $site_direction === 'rtl'?'active':'' }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ translate('Email template') }}</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;1,400&display=swap');

        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            font-size: 13px;
            line-height: 21px;
            color: #737883;
            background: #f7fbff;
            padding: 0;
            display: flex;align-items: center;justify-content: center;
            min-height: 100vh;
        }
        h1,h2,h3,h4,h5,h6 {
            color: #334257;
            margin: 0;
        }
        * {
            box-sizing: border-box
        }

        :root {
            --base: #006161
        }

        .main-table {
            width: 500px;
            background: #FFFFFF;
            margin: 0 auto;
            padding: 40px;
        }
        .main-table-td {
        }
        img {
            max-width: 100%;
        }
        .cmn-btn{
            background: var(--base);
            color: #fff;
            padding: 8px 20px;
            display: inline-block;
            text-decoration: none;
        }
        .mb-1 {
            margin-bottom: 5px;
        }
        .mb-2 {
            margin-bottom: 10px;
        }
        .mb-3 {
            margin-bottom: 15px;
        }
        .mb-4 {
            margin-bottom: 20px;
        }
        .mb-5 {
            margin-bottom: 25px;
        }
        hr {
            border-color : rgba(0, 170, 109, 0.3);
            margin: 16px 0
        }
        .border-top {
            border-top: 1px solid rgba(0, 170, 109, 0.3);
            padding: 15px 0 10px;
            display: block;
        }
        .d-block {
            display: block;
        }
        .privacy {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
}
.privacy a {
    text-decoration: none;
    color: #334257;
    position: relative;
    margin-left: auto;
    margin-right: auto;
}
.privacy a span {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #334257;
    display: inline-block;
    margin: 0 7px;
}
        .social {
            margin: 15px 0 8px;
            display: block;
        }
        .copyright{
            text-align: center;
            display: block;
        }
        div {
            display: block;
        }
        .text-center {
            text-align: center;
        }
        .text-base {
            color: var(--base);
font-weight: 700
        }
        .font-medium {
            font-family: 500;
        }
        .font-bold {
            font-family: 700;
        }
        a {
            text-decoration: none;
        }
        .bg-section {
            background: #E3F5F1;
        }
        .p-10 {
            padding: 10px;
        }
        .mt-0{
            margin-top: 0;
        }
        .w-100 {
            width: 100%;
        }
        .order-table {
            padding: 10px;
            background: #fff;
        }
        .order-table tr td {
            vertical-align: top
        }
        .order-table .subtitle {
            margin: 0;
            margin-bottom: 10px;
        }
        .text-left {
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .bg-section-2 {
            background: #F8F9FB;
        }
        .p-1 {
            padding: 5px;
        }
        .p-2 {
            padding: 10px;
        }
        .px-3 {
            padding-inline: 15px
        }
        .mb-0 {
            margin-bottom: 0;
        }
        .m-0 {
            margin: 0;
        }
        .text-base {
            color: var(--base);
font-weight: 700
        }
        .mail-img-1 {
            width: 140px;
            height: 60px;
            object-fit: contain
        }
        .mail-img-2 {
            width: 130px;
            height: 45px;
            object-fit: contain
        }
        .mail-img-3 {
            width: 100%;
            height: 172px;
            object-fit: cover
        }
.social img {
width: 24px;
}
    </style>

</head>


<body style="background-color: #e9ecef;padding:15px">

    <table dir="{{ $site_direction }}" class="main-table">
        <tbody>
            <tr>
                <td class="main-table-td">
                    <h2 class="mb-3" id="mail-title">{{ $title?? translate('Main title or subject of the mail') }}</h2>
                    <div class="mb-1" id="mail-body">{!! $body?? translate('Hi sabrina,') !!}</div>
                    <table class="bg-section p-10 w-100">
                        <tbody>
                            <tr>
                                <td class="p-10">
                                    <span class="d-block text-center">
                                        <img class="mb-2 mail-img-2"
                                        src="{{ $data['image_full_url'] ?? asset('/public/assets/admin/img/blank2.png') }}"
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
                                                    <span class="d-block">{{ translate('Order') }}# {{ $order->id }}</span>
                                                    <span class="d-block">{{ $order->created_at  }}</span>
                                                </td>
                                                <td style="max-width:130px">
                                                    <h3 class="subtitle">{{ translate('Delivery address') }}</h3>
                                                    @if ($order->delivery_address)
                                                    @php($address = json_decode($order->delivery_address, true))
                                                    <span class="d-block">{{ $address['contact_person_name']  ?? $order->customer['f_name'] . ' ' . $order->customer['l_name'] }}</span>
                                                    <span  class="d-block">
                                                    {{ $address['contact_person_number'] ?? null }}
                                                    </span>
                                                    <span class="d-block" >
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
                                                            <th class="text-left p-1 px-3">{{ translate('Product') }}</th>
                                                            <th class="text-right p-1 px-3">{{ translate('price') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if ($order->order_type=='parcel')
                                                        <tr>
                                                            <td class="text-left p-2 px-3">
                                                                {{ Str::limit($order->parcel_category?$order->parcel_category->name:translate('messages.Parcel category not found'), 25, '...') }}
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
                                                                    @foreach(json_decode($details['variation'],true) as  $variation)
                                                                    @if ( isset($variation['name'])  && isset($variation['values']))
                                                                        <span class="d-block text-capitalize">
                                                                                <strong>{{  $variation['name']}} - </strong>
                                                                            @foreach ($variation['values'] as $value)
                                                                                        {{ $value['label']}}
                                                                                        @if ($value !== end($variation['values']))
                                                                                            ,
                                                                                        @endif
                                                                            @endforeach
                                                                        </span>
                                                                    @else
                                                                        @if (isset(json_decode($details['variation'],true)[0]))
                                                                            @foreach(json_decode($details['variation'],true)[0] as $key1 =>$variation)
                                                                                <div class="font-size-sm text-body">
                                                                                    <span>{{$key1}} :  </span>
                                                                                    <span class="font-weight-bold">{{$variation}}</span>
                                                                                </div>
                                                                            @endforeach
                                                                        @endif
                                                                    @endif
                                                                    @endforeach
                                                                </span>
                                                                @endif
                                                                @foreach (json_decode($details['add_ons'], true) as $key2 => $addon)
                                                                    @if ($key2 == 0)
                                                                        <br><span style="font-size: 12px;"><u>{{ translate('Addons') }}
                                                                            </u></span>
                                                                    @endif
                                                                    <div style="font-size: 12px;">
                                                                        <span>{{ Str::limit($addon['name'], 20, '...') }} : </span>
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
                                                                    @if ($order->order_type !='parcel')
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('Item price') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($sub_total) }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('messages.Addon cost') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($total_addon_price) }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('messages.subtotal') }}
                                                                            @if ($order->tax_status == 'included' )
                                                                            ({{ translate('TAX included') }})
                                                                            @endif
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($sub_total + $total_addon_price) }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('messages.subtotal') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($sub_total + $total_addon_price) }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('Discount') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($order->store_discount_amount) }}</td>
                                                                    </tr>

                                                                    @if ($order?->ref_bonus_amount > 0 )
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('Referral discount') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($order->ref_bonus_amount) }}</td>
                                                                    </tr>
                                                                    @endif
                                                                    @if (($order->orderProDiscount?->amount_saved ?? 0) > 0)
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('messages.Pro discount') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($order->orderProDiscount->amount_saved) }}</td>
                                                                    </tr>
                                                                    @endif
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('Coupon discount') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($order->coupon_discount_amount) }}</td>
                                                                    </tr>

                                                                    @if ($order?->extra_packaging_amount > 0 )
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('Extra packaging amount') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($order->extra_packaging_amount) }}</td>
                                                                    </tr>
                                                                    @endif

                                                                    @if ($order->tax_status == 'excluded' || $order->tax_status == null  )
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('messages.tax') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($order->total_tax_amount) }}</td>
                                                                    </tr>
                                                                    @endif
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('messages.tax') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency($total_tax) }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">{{ translate('Delivery charge') }}</td>
                                                                        <td class="text-right p-1 px-3">{{ \App\CentralLogics\Helpers::format_currency(app(\App\Services\Order\OrderService::class)->proDeliveryBreakdown($order)['original_fee']) }}</td>
                                                                    </tr>
                                                                    @include('partials.pro-delivery-discount-row', ['order' => $order, 'layout' => 'tr3'])
                                                                    @endif
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">
                                                                            <h4>{{ translate('messages.Total') }}</h4>
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">
                                                                            <span class="text-base">{{ \App\CentralLogics\Helpers::format_currency($order->order_amount) }}</span>
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
                    <div class="mb-2" id="mail-footer">
                        {{ $footer_text??'Please contact us for any queries, we’re always happy to help. ' }}
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
