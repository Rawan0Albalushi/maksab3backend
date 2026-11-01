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
                                        <h3 class="mb-3 mt-0">{{ translate('Trip information') }}</h3>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table class="order-table w-100">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <h3 class="subtitle">{{ translate('Trip summary') }}</h3>
                                                    <span class="d-block">{{ translate('Trip') }}#
                                                        {{ $trip->id }}</span>
                                                        <span class="d-block">{{ \App\CentralLogics\Helpers::time_date_format($trip->schedule_at)	 }}</span>

                                                        <div class="text-break mb-1">
                                                            <span class="opacity-70">{{ translate('Pickup location') }}</span> <span>:</span>
                                                            <span>{{ $trip?->pickup_location['location_name'] }}</span>
                                                        </div>
                                                        <div class="text-break mb-1">
                                                            <span class="opacity-70">{{ translate('messages.Destination location') }}</span> <span>:</span>
                                                            <span>{{ $trip?->destination_location['location_name'] }}</span>
                                                        </div>

                                                    </td>
                                                <td style="max-width:130px">
                                                    <h3 class="subtitle">{{ translate('Customer information') }}</h3>

                                                    @php($address = $trip->user_info)
                                                    @php($subtotal = 0)
                                                    <span
                                                        class="d-block">{{ $trip->customer?->f_name . ' ' . $trip->customer?->l_name ?? $address['contact_person_name'] }}</span>
                                                    <span class="d-block">
                                                        {{ $trip->customer?->phone ?? ($address['contact_person_number'] ?? null) }}
                                                    </span>

                                                </td>
                                            </tr>

                                            <td colspan="2">
                                                <table class="w-100">
                                                    <thead class="bg-section-2">
                                                        <tr>
                                                            <th class="text-left p-1 px-3">#
                                                            </th>
                                                            <th class="text-left p-1 px-3">{{ translate('Vehicle') }}
                                                            </th>
                                                            <th class="text-left p-1 px-3">{{ translate('Hour/Km/Day') }}
                                                            </th>
                                                            <th class="text-right p-1 px-3">{{ translate('Fare') }}
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>

                                                        @foreach ($trip->trip_details as $key => $details)
                                                            <?php
                                                            $subtotal += $details['calculated_price'];
                                                            $item_details = $details->vehicle_details;
                                                            ?>
                                                            <tr>
                                                                <td class="text-left p-1 px-3">
                                                                    {{ $key + 1 }}
                                                                </td>
                                                                <td class="text-left p-2 px-3">
                                                                    <span style="font-size: 14px;">
                                                                        {{ Str::limit($item_details['name'], 40, '...') }}
                                                                    </span>
                                                                    <br>

                                                                    <span>x {{ $details->quantity }}</span>
                                                                </td>

                                                                        <?php
                                                                            if($details->rental_type == 'hourly'){
                                                                                $getPrice=$details->vehicle_details['hourly_price'];
                                                                                $getType=$trip->estimated_hours.' '.'Hrs';
                                                                            }elseif ($details->rental_type == 'day_wise') {
                                                                                $getPrice=$details->vehicle_details['day_wise_price'];
                                                                                $getType=( (int) round($details->estimated_hours/ 24) ).' '.translate('days');
                                                                            } else{
                                                                                $getPrice=$details->vehicle_details['distance_price'];
                                                                                $getType= $trip->distance .' '.'KM';
                                                                            }
                                                                        ?>

                                                                <td class=" p-2 px-3">
                                                                    {{ \App\CentralLogics\Helpers::format_currency($getPrice) }}  x   {{ $getType }}
                                                                </td>


                                                                <td class="text-right p-2 px-3">
                                                                    <h4>
                                                                        {{ \App\CentralLogics\Helpers::format_currency($details['calculated_price']) }}
                                                                    </h4>
                                                                </td>
                                                            </tr>
                                                        @endforeach

                                                        <tr>
                                                            <td colspan="4">
                                                                <hr class="mt-0">
                                                                <table class="w-100">
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">
                                                                            {{ translate('messages.price') }}
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">
                                                                            {{ \App\CentralLogics\Helpers::format_currency($subtotal) }}
                                                                        </td>
                                                                    </tr>

                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">
                                                                            {{ translate('messages.subtotal') }}
                                                                            @if ($trip->tax_status == 'included')
                                                                                ({{ translate('TAX included') }})
                                                                            @endif
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">
                                                                            {{ \App\CentralLogics\Helpers::format_currency($subtotal) }}
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">
                                                                            {{ translate('Discount') }}
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">
                                                                            {{ \App\CentralLogics\Helpers::format_currency($trip->discount_on_trip) }}
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">
                                                                            {{ translate('Coupon discount') }}
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">
                                                                            {{ \App\CentralLogics\Helpers::format_currency($trip->coupon_discount_amount) }}
                                                                        </td>
                                                                    </tr>
                                                                    @if ($trip?->ref_bonus_amount > 0)
                                                                        <tr>
                                                                            <td style="width: 40%"></td>
                                                                            <td class="p-1 px-3">
                                                                                {{ translate('Referral discount') }}
                                                                            </td>
                                                                            <td class="text-right p-1 px-3">
                                                                                {{ \App\CentralLogics\Helpers::format_currency($trip->ref_bonus_amount) }}
                                                                            </td>
                                                                        </tr>
                                                                    @endif

                                                                    @if (($trip->proDiscount?->amount_saved ?? 0) > 0)
                                                                        <tr>
                                                                            <td style="width: 40%"></td>
                                                                            <td class="p-1 px-3">
                                                                                {{ translate('messages.Pro discount') }}
                                                                            </td>
                                                                            <td class="text-right p-1 px-3">
                                                                                {{ \App\CentralLogics\Helpers::format_currency($trip->proDiscount?->amount_saved) }}
                                                                            </td>
                                                                        </tr>
                                                                    @endif

                                                                    @if ($trip->tax_status == 'excluded' || $trip->tax_status == null)
                                                                        <tr>
                                                                            <td style="width: 40%"></td>
                                                                            <td class="p-1 px-3">
                                                                                {{ translate('messages.tax') }}
                                                                            </td>
                                                                            <td class="text-right p-1 px-3">
                                                                                {{ \App\CentralLogics\Helpers::format_currency($trip->tax_amount) }}
                                                                            </td>
                                                                        </tr>
                                                                    @else
                                                                    @endif

                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">
                                                                            {{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??\App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('Additional charge') }}
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">
                                                                            {{ \App\CentralLogics\Helpers::format_currency($trip->additional_charge) }}
                                                                        </td>
                                                                    </tr>

                                                                    <tr>
                                                                        <td style="width: 40%"></td>
                                                                        <td class="p-1 px-3">
                                                                            <h4>{{ translate('messages.Total') }}</h4>
                                                                        </td>
                                                                        <td class="text-right p-1 px-3">
                                                                            <span
                                                                                class="text-base">{{ \App\CentralLogics\Helpers::format_currency($trip->trip_amount) }}</span>
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
