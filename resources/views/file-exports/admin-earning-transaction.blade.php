<table>
    <thead>
        <tr>
            <th colspan="6" style="text-align: center;"><h1>{{ translate($data['title']) }}</h1></th>
        </tr>
        <tr>
            <th colspan="3">
                @if(($data['type'] ?? 'order') === 'expense')
                    {{ translate('Expense report') }}
                @elseif(($data['type'] ?? 'order') === 'subscription')
                    {{ translate('Subscription earnings') }}
                @elseif(($data['type'] ?? 'order') === 'pro_customer')
                    {{ translate('Pro customer subscription') }}
                @else
                    {{ translate('messages.Earnings') }}
                @endif
            </th>
            <th colspan="3">
                @if(isset($data['search']))
                {{ translate('Search bar content') }} - {{ $data['search'] }}
                @endif
            </th>
        </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('messages.Transaction ID') }}</th>
            <th>{{ translate('messages.Date') }}</th>
            <th>{{ translate('messages.Source') }}</th>
            <th>
                @if(($data['type'] ?? 'order') === 'expense')
                    {{ translate('Expense source') }}
                @elseif(($data['type'] ?? 'order') === 'subscription')
                    {{ translate('Transaction type') }}
                @elseif(($data['type'] ?? 'order') === 'pro_customer')
                    {{ translate('messages.Plan') }}
                @else
                    {{ translate('Earning source') }}
                @endif
            </th>
            <th>{{ translate('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach($data['transactions'] as $key => $t)
        <tr>
            <td>{{ $key + 1 }}</td>
            <td>{{ $t['transaction_id'] }}</td>
            <td>
                @php
                    $date = \Carbon\Carbon::parse($t['date']);
                @endphp
                {{ $date->format('d M Y') }} {{ $date->format('h:i a') }}
            </td>
            <td>
                {{ $t['source'] ?? $t['store'] ?? '' }}
                @if(isset($t['source_type']))
                    ({{ translate($t['source_type']) }})
                @endif
            </td>
            <td>
                @php
                    $type = $data['type'] ?? 'order';
                    $badge = $t['earning_from_badge'] ?? $t['expense_source_badge'] ?? $t['transaction_type'] ?? '';
                    $from = $t['earning_from'] ?? $t['expense_source'] ?? '';
                    $additionalChargeLabel = \App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? translate('Additional charge');
                    $breakdown = $t['breakdown'] ?? [];
                    $breakdownLines = [];

                    if ($type === 'subscription') {
                        if (!empty($t['transaction_type'])) {
                            $breakdownLines[] = translate($t['transaction_type']);
                        }
                    } elseif (!empty($breakdown)) {
                        if (array_key_exists('trip_commission', $breakdown) || array_key_exists('booking_commission', $breakdown) || array_key_exists('additional_charge', $breakdown)) {
                            $isBookingCommission = array_key_exists('booking_commission', $breakdown);
                            $breakdownLines[] = ($isBookingCommission ? translate('Booking commission') : translate('Trip commission')) . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['trip_commission'] ?? $breakdown['booking_commission'] ?? 0);
                            $breakdownLines[] = $additionalChargeLabel . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['additional_charge'] ?? 0);
                        } elseif (array_key_exists('order_commission', $breakdown) || array_key_exists('delivery_fee_comission', $breakdown) || array_key_exists('tax_collected', $breakdown) || array_key_exists('packaging_fee_collected', $breakdown)) {
                            $hideOrderCommission = isset($breakdown['hide_order_commission']) && $breakdown['hide_order_commission'];
                            if (!$hideOrderCommission) {
                                $breakdownLines[] = translate('Order commission') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['order_commission'] ?? 0);
                            }

                            if (array_key_exists('delivery_fee_comission', $breakdown) || array_key_exists('tax_collected', $breakdown)) {
                                $breakdownLines[] = (isset($breakdown['tax_collected']) ? translate('Tax collected') : translate('Delivery fee commission')) . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['delivery_fee_comission'] ?? $breakdown['tax_collected'] ?? 0);
                            }
                            $breakdownLines[] = $additionalChargeLabel . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['packaging_fee_collected'] ?? 0);
                        } elseif (array_key_exists('admin_earning', $breakdown) || array_key_exists('vat_tax', $breakdown)) {
                            $breakdownLines[] = translate('Admin earning') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['admin_earning'] ?? 0);
                            $breakdownLines[] = translate('VAT/tax') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['vat_tax'] ?? 0);
                        } elseif (array_key_exists('discount_amount', $breakdown) || array_key_exists('coupon_amount', $breakdown)) {
                            $breakdownLines[] = translate('Discount amount') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['discount_amount'] ?? 0);
                            $breakdownLines[] = translate('Coupon amount') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['coupon_amount'] ?? 0);
                        } elseif (array_key_exists('general_expense', $breakdown)) {
                            $breakdownLines[] = translate($breakdown['type'] ?? 'General Expense') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['general_expense'] ?? 0);
                        } elseif ($type === 'expense') {
                            $breakdownLines[] = translate('Commission paid') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['admin_commission'] ?? 0);
                            $breakdownLines[] = translate('Discount on item') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['discount_on_item'] ?? 0);
                            $breakdownLines[] = translate('Coupon contribution') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['coupon_contribution'] ?? 0);
                            $breakdownLines[] = translate('Free delivery') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['free_delivery'] ?? 0);
                        }
                    }
                @endphp
                {{ $badge ? translate($badge) : '' }}
                {{ $from ? '('.$from.')' : '' }}
                @if(!empty($breakdownLines))
                    <br>
                    {!! implode('<br>', $breakdownLines) !!}
                @endif
            </td>
            <td>{{ \App\CentralLogics\Helpers::format_currency($t['amount']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
