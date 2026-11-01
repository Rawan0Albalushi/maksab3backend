<table>
    <thead>
        <tr>
            <th colspan="6" style="text-align: center;"><h1>{{ translate($data['title']) }}</h1></th>
        </tr>
        <tr>
            <th colspan="3">
                @if(isset($data['store_name']))
                    {{ translate('messages.Store') }} - {{ $data['store_name'] }}
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
                    $breakdown = $t['breakdown'] ?? [];
                    $breakdownLines = [];

                    if ($type === 'subscription') {
                        if (!empty($t['transaction_type'])) {
                            $breakdownLines[] = translate($t['transaction_type']);
                        }
                    } elseif (!empty($breakdown)) {
                        if (array_key_exists('store_amount_without_tax', $breakdown) || array_key_exists('tax_amount', $breakdown)) {
                            $breakdownLines[] = translate('Trip commission') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['store_amount_without_tax'] ?? 0);
                            $breakdownLines[] = translate('Tax amount') . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['tax_amount'] ?? 0);
                        } elseif (array_key_exists('order_commission', $breakdown) || array_key_exists('delivery_fee_comission', $breakdown) || array_key_exists('tax_collected', $breakdown) || array_key_exists('packaging_fee_collected', $breakdown)) {
                            $additionalChargeLabel = translate('Packaging charge');
                            $isParcel = isset($breakdown['is_parcel']) && $breakdown['is_parcel'];
                            $firstEarningLabel = $isParcel ? translate('Delivery fee commission') : translate('Order commission');
                            $breakdownLines[] = $firstEarningLabel . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['order_commission'] ?? 0);
                            
                            if (!$isParcel) {
                                $breakdownLines[] = (isset($breakdown['tax_collected']) ? translate('Tax collected') : translate('Delivery fee commission')) . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['delivery_fee_comission'] ?? $breakdown['tax_collected'] ?? 0);
                            }
                            $breakdownLines[] = $additionalChargeLabel . ': ' . \App\CentralLogics\Helpers::format_currency($breakdown['packaging_fee_collected'] ?? 0);
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
