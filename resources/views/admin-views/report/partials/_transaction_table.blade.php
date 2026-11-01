@php
    $hide_source_column = $hide_source_column ?? false;
    $row_type = $type ?? 'order';
    $is_subscription_like = in_array($row_type, ['subscription', 'pro_customer'], true);
    $is_expense = $row_type === 'expense';
    $is_order_row = $row_type === 'order' || $row_type === '';
    $column_count = 4 + ($hide_source_column ? 0 : 1);
    $use_additional_charge_name_in_breakdown = $use_additional_charge_name_in_breakdown ?? false;
    $additionalChargeLabelForAdmin = \App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? translate('Additional charge');
    $breakdown_additional_charge_label = $use_additional_charge_name_in_breakdown
        ? $additionalChargeLabelForAdmin
        : translate('Packaging charge');
    $source_type_labels = [
        'Store' => translate('messages.Store'),
        'Restaurant' => translate('messages.Restaurant'),
        'Delivery Man' => translate('Deliveryman'),
        'Customer' => translate('messages.Customer'),
        'Tax Office' => translate('Tax office'),
        'Admin' => translate('messages.admin'),
    ];
    $badge_labels = [
        'discount_on_item' => translate('Discount on item'),
        'discount_on_product' => translate('Discount on item'),
        'commission_paid' => translate('Commission paid'),
        'coupon_discount' => translate('Coupon discount'),
        'free_delivery' => translate('Free delivery'),
        'flash_sale_discount' => translate('messages.flash_sale_discount'),
        'bogo_discount' => translate('BOGO discount'),
        'happy_hour_discount' => translate('Happy hour discount'),
        'bundle_discount' => translate('Bundle discount'),
        'extra_discount' => translate('Extra discount'),
        'cashback' => translate('messages.CashBack'),
        'add_fund_bonus' => translate('messages.add_fund_bonus'),
        'referral_discount' => translate('Referral discount'),
        'tax' => translate('messages.tax'),
        'delivery_commission' => translate('Delivery commission'),
    ];
    $plan_type_labels = [
        'Renew Subscription' => translate('Renew subscription'),
        'Migrate to New Plan' => translate('Migrate to new plan'),
        'First Purchased' => translate('First purchased'),
        'Free Trial' => translate('Free trial'),
    ];
@endphp

@if(count($transactions) > 0)
<div class="table-responsive datatable-custom mt-4 z-index-2">
    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table text-dark">
        <thead class="thead-light">
            <tr>
                <th class="border-0">{{ translate('messages.Transaction') }}</th>
                <th class="border-0">{{ translate('messages.Date') }}</th>
                @if(!$hide_source_column)
                    <th class="border-0">
                        @if($row_type === 'subscription')
                            {{ translate('messages.Store') }}
                        @elseif($row_type === 'pro_customer')
                            {{ translate('messages.Customer') }}
                        @else
                            {{ translate('messages.Source') }}
                        @endif
                    </th>
                @endif
                @if($is_subscription_like)
                    <th class="border-0">{{ $row_type === 'pro_customer' ? translate('messages.Plan') : translate('Transaction type') }}</th>
                @else
                    <th class="border-0">{{ $is_expense ? translate('Expense source') : translate('Earning source') }}</th>
                @endif
                <th class="border-0 col--numeric">{{ translate('Amount') }}</th>
            </tr>
        </thead>
        <tbody id="set-rows">
            @foreach($transactions as $t)
                @php($has_breakdown = isset($t['breakdown']) && count($t['breakdown']) > 0)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @if($has_breakdown)
                                <button type="button" class="btn action-btn collapse-next-tr" aria-expanded="false" title="{{ translate('messages.Show breakdown') }}">
                                    <i class="tio-chevron-down"></i>
                                </button>
                            @endif
                            <span class="font-medium">{{ is_numeric($t['transaction_id']) ? '#'.$t['transaction_id'] : $t['transaction_id'] }}</span>
                        </div>
                    </td>
                    <td>
                        <span class="table-when">
                            <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($t['date']) }}</span>
                            <span class="table-when__ago text-uppercase">{{ \App\CentralLogics\Helpers::time_format($t['date']) }}</span>
                        </span>
                    </td>
                    @if(!$hide_source_column)
                        <td>
                            <span class="d-block text--title">{{ $t['source'] ?? $t['store'] ?? '' }}</span>
                            @if(isset($t['source_type']))
                                <span class="cell-chips mt-1"><span class="cell-chip">{{ $source_type_labels[$t['source_type']] ?? $t['source_type'] }}</span></span>
                            @endif
                        </td>
                    @endif
                    @if($is_subscription_like)
                        <td>
                            @if(isset($t['transaction_type']))
                                <span class="badge rounded-lg font-medium px-2" style="{{ $t['transaction_type_badge_style'] ?? 'background-color: #F4F5F7; color: #4B5563;' }}">{{ $row_type === 'pro_customer' ? $t['transaction_type'] : ($plan_type_labels[$t['transaction_type']] ?? $t['transaction_type']) }}</span>
                            @endif
                        </td>
                    @else
                        @php($source_label = $is_expense ? ($t['expense_source'] ?? '') : ($t['earning_from'] ?? $t['expense_source'] ?? ''))
                        @php($badge = $is_expense ? ($t['expense_source_badge'] ?? $t['transaction_type'] ?? null) : ($t['earning_from_badge'] ?? null))
                        <td>
                            @if(!empty($t['order_id']) && $source_label !== '')
                                <a class="font-medium" href="{{ request()->is('admin/*') ? route('admin.order.details', ['id' => $t['order_id']]) : route('vendor.order.details', ['id' => $t['order_id']]) }}">{{ $source_label }}</a>
                            @elseif($source_label !== '')
                                <span class="font-medium">{{ $source_label }}</span>
                            @endif
                            @if($badge)
                                @php($badge_key = strtolower(str_replace([' ', '-'], '_', trim($badge))))
                                <span class="cell-chips mt-1"><span class="cell-chip">{{ $badge_labels[$badge_key] ?? $badge }}</span></span>
                            @endif
                        </td>
                    @endif
                    <td class="col--numeric">
                        <span class="font-medium">{{ \App\CentralLogics\Helpers::format_currency($t['amount']) }}</span>
                    </td>
                </tr>
                @if($has_breakdown)
                    @php($breakdown = $t['breakdown'])
                    @php($express_charge = (float) ($breakdown['express_charge'] ?? 0))
                    @php($second_label = isset($breakdown['tax_collected']) ? translate('Tax collected') : translate('Delivery fee commission'))
                    @php($second_amount = $breakdown['delivery_fee_comission'] ?? $breakdown['tax_collected'] ?? 0)
                    @php($lines = [])
                    @if($is_order_row)
                        @if($hide_source_column)
                            @php($lines[] = [translate('Order sales'), $breakdown['order_commission'] ?? 0])
                            @php($lines[] = [$second_label, $second_amount])
                        @else
                            @if(empty($breakdown['hide_order_commission']))
                                @php($lines[] = [translate('Order commission'), $breakdown['order_commission'] ?? 0])
                            @endif
                            @if(array_key_exists('delivery_fee_comission', $breakdown) || array_key_exists('tax_collected', $breakdown))
                                @php($lines[] = [$second_label, $second_amount])
                            @endif
                        @endif
                        @php($lines[] = [$breakdown_additional_charge_label, $breakdown['packaging_fee_collected'] ?? 0])
                        @if($express_charge > 0)
                            @php($lines[] = [translate('Express delivery charge'), $express_charge])
                        @endif
                    @else
                        @php($lines[] = [translate('Commission paid'), $breakdown['admin_commission'] ?? 0])
                        @php($lines[] = [translate('Discount on item'), $breakdown['discount_on_item'] ?? 0])
                        @php($lines[] = [translate('Coupon contribution'), $breakdown['coupon_contribution'] ?? 0])
                        @php($lines[] = [translate('Free delivery'), $breakdown['free_delivery'] ?? 0])
                    @endif
                    <tr class="collapsing-tr d-none bg-light2">
                        <td colspan="{{ $column_count - 1 }}" class="pl-5">
                            @foreach($lines as $line)
                                <div class="{{ $loop->last ? '' : 'mb-2' }}">{{ $line[0] }}</div>
                            @endforeach
                        </td>
                        <td class="col--numeric">
                            @foreach($lines as $line)
                                <div class="{{ $loop->last ? '' : 'mb-2' }}">{{ $loop->first ? '' : '+ ' }}{{ \App\CentralLogics\Helpers::format_currency($line[1]) }}</div>
                            @endforeach
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
<div class="page-area px-4 pb-3">
    {!! $transactions->links() !!}
</div>
@else
    <div class="empty--data py-5 w-100">
        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
        <h5>{{ translate('messages.No transactions match this period or search.') }}</h5>
    </div>
@endif
