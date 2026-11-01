@extends('layouts.admin.app')

@section('title', translate('Admin tax report'))

@section('tax_report')
    active
@endsection

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/tax.css') }}">
@endpush

@section('content')

@php
    /* Income sources are a fixed set of platform revenue lines, so they map from
       literal keys — translate($key) would write whatever the query returned
       into the language file (§8). Anything unmapped falls back to a humanised
       key rather than vanishing. */
    $source_labels = [
        'admin_commission' => translate('Order commission'),
        'delivery_commission' => translate('messages.Delivery charge commission'),
        'delivery_fee_comission' => translate('messages.Delivery charge commission'),
        'service_charge' => translate('messages.Service charge'),
        'additional_charge' => translate('Additional charge'),
        'vendor_subscription' => translate('Vendor subscription'),
    ];
@endphp

    <div class="content container-fluid txr">
        {{-- Was `<h2 …>…</h3>` — an h2 closed with an h3 tag, and no panel header
             at all, so this screen was the only one in Finance without the icon
             and landmark every other page carries. --}}
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/report.svg') }}" class="w--26" alt="">
                </span>
                <span>{{ translate('Order tax report') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('The tax collected on orders, ready for your return.') }}</p>
        </div>

            <div class="txr-query">
                <form action="" method="get">
                <div class="txr-query__body">
                @if (addon_published_status('Rental'))
                    <div class="txr-note">
                        <i class="tio-info-outined"></i>
                        <span id="info_for_item">
                            {{ translate('You will get a combined tax report. View the rental tax report separately') }}: <a href="{{ route('admin.transactions.rental.report.getTaxReport') }}">{{ translate('Tax report for rental module') }}</a>
                        </span>
                    </div>
                @endif
                    <div class="txr-fields">
                        {{-- One grid, not two hand-built columns. Half these fields
                             toggle `d-none` with the mode, and the old layout put four
                             in the left stack and three in the right across three
                             separate `.d-flex` groups with an ad-hoc `mt-3`, so the two
                             columns ran on different rhythms and left holes as fields
                             came and went. auto-fit reflows around whatever is on. --}}
                        <div class="txr-field">
                            <label class="form-label" for="date_range_type">{{ translate('Date range type') }}</label>
                            <select name="date_range_type" id="date_range_type" class="form-control js-select2-custom">
                                <option value="">{{ translate('Select date range') }}</option>
                                <option value="this_fiscal_year" {{ $date_range_type == 'this_fiscal_year' ? 'selected' : '' }}>
                                    {{ translate('This fiscal year') }}
                                </option>
                                <option value="custom" {{ $date_range_type == 'custom' ? 'selected' : '' }}>
                                    {{ translate('Custom') }}
                                </option>
                            </select>
                        </div>

                        <div class="txr-field {{ $date_range_type == 'custom' ? '' : 'd-none' }}" id="date_range">
                            <label class="form-label" for="dates">{{ translate('Date range') }}</label>
                            {{-- `h-45` was doing nothing: no such class exists in any admin
                                 sheet, so this input sat shorter than the selects beside it. --}}
                            <div class="txr-field__control txr-field__control--icon">
                                <i class="tio-calendar-month"></i>
                                <input type="text" id="dates" class="form-control" name="dates"
                                       placeholder="{{ translate('Select date') }}">
                            </div>
                        </div>

                        <div class="txr-field">
                            <label class="form-label" for="calculate_tax_on">{{ translate('Select how to calculate tax') }}</label>
                            <select name="calculate_tax_on" id="calculate_tax_on" required class="form-control js-select2-custom">
                                <option disabled selected value="">{{ translate('Select calculate tax') }}</option>
                                <option {{ $calculate_tax_on == 'all_source' ? 'selected' : '' }} value="all_source">
                                    {{ translate('Same tax for all income sources') }}
                                </option>
                                <option {{ $calculate_tax_on == 'individual_source' ? 'selected' : '' }} value="individual_source">
                                    {{ translate('Different tax for different income sources') }}
                                </option>
                            </select>
                        </div>

                        <div class="txr-field {{ $calculate_tax_on == 'individual_source' ? 'd-none' : '' }}" id="calculate_tax_rate">
                            <label class="form-label">{{ translate('Select tax rate') }}</label>
                            <div class="select-class-closest">
                                <select {{ $calculate_tax_on == 'individual_source' ? '' : 'required' }}
                                        name="tax_rate[]" id="select_customer_fiscal-5"
                                        class="form-control js-select2-custom" multiple="multiple"
                                        placeholder="{{ translate('Type & select tax rate') }}"></select>
                            </div>
                        </div>

                        <div class="txr-field {{ $calculate_tax_on == 'individual_source' ? '' : 'd-none' }}" id="calculate_commission_tax">
                            <label class="form-label">{{ translate('Tax on order commission') }}</label>
                            <div class="select-class-closest">
                                <select name="tax_on_order_commission[]" id="select_customer_fiscal1"
                                        class="form-control js-select2-custom" multiple="multiple"
                                        placeholder="{{ translate('Type & select tax rate') }}"></select>
                            </div>
                        </div>

                        <div class="txr-field {{ $calculate_tax_on == 'individual_source' ? '' : 'd-none' }}" id="calculate_delivery_charge_tax">
                            <label class="form-label">{{ translate('Tax on delivery charge commission') }}</label>
                            <div class="select-class-closest">
                                <select name="tax_on_delivery_charge_commission[]" id="select_customer_fiscal2"
                                        class="form-control js-select2-custom" multiple="multiple"
                                        placeholder="{{ translate('Type & select tax rate') }}"></select>
                            </div>
                        </div>

                        <div class="txr-field {{ $calculate_tax_on == 'individual_source' ? '' : 'd-none' }}" id="calculate_service_charge_tax">
                            <label class="form-label">{{ translate('Tax on service charge') }}</label>
                            <div class="select-class-closest">
                                <select name="tax_on_service_charge[]" id="select_customer_fiscal-3"
                                        class="form-control js-select2-custom" multiple="multiple"
                                        placeholder="{{ translate('Type & select tax rate') }}"></select>
                            </div>
                        </div>

                        <div class="txr-field {{ $calculate_tax_on == 'individual_source' ? '' : 'd-none' }}" id="calculate_subscription_tax">
                            <label class="form-label">{{ translate('Tax on subscription') }}</label>
                            <div class="select-class-closest">
                                <select name="tax_on_subscription[]" id="select_customer_fiscal-6"
                                        class="form-control js-select2-custom" multiple="multiple"
                                        placeholder="{{ translate('Type & select tax rate') }}"></select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="txr-query__foot">
                    <button type="reset" id="reset_button_id" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
                    <button type="submit" class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Submit') }}</button>
                </div>
                </form>
            </div>
            @include('admin-views.report.tax-report.partials._summary-strip', ['tiles' => [
                [
                    'icon' => 'tio-money', 'tone' => 'income',
                    'value' => \App\CentralLogics\Helpers::format_currency($totalBase),
                    'label' => translate('Total income'),
                ],
                [
                    'icon' => 'tio-dollar-outlined', 'tone' => 'tax',
                    'value' => \App\CentralLogics\Helpers::format_currency($totalTax),
                    'label' => translate('Total tax'),
                ],
            ]])
            <div class="card">
                <div class="card-header border-0 py-2">
                    <div class="search--button-wrapper">
                        @include('partials._table-head', [
                            'title'    => translate('Tax report'),
                            'subtitle' => translate('messages.Tax collected on platform earnings over the selected period.'),
                            'count'    => count($combinedResults),
                        ])

                        @include('admin-views.report.tax-report.partials._export-dropdown', [
                            'export_route' => 'admin.transactions.report.adminTaxReportExport',
                            'export_target' => 'usersExportDropdown__admin',
                        ])
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive datatable-custom">
                        <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ translate('Income source') }}</th>
                                    <th class="col--numeric">{{ translate('Total income') }}</th>
                                    <th class="col--numeric">{{ translate('Tax rate') }}</th>
                                    <th>{{ translate('Tax amount') }}</th>
                                    <th class="text-center">{{ translate('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($combinedResults as $key => $item)
                                    @php
                                        $row_taxes = collect($item['taxes'] ?? [])
                                            ->flatMap(fn ($taxItems, $taxName) => collect($taxItems)->map(fn ($tax) => [
                                                'name' => $taxName,
                                                'rate' => (float) $tax['tax_rate'],
                                                'amount' => (float) $tax['total_tax_amount'],
                                            ]))
                                            ->values();
                                    @endphp
                                    <tr>
                                        <td>
                                            <a class="font-weight-bold text--title" target="_blank"
                                               href="{{ route('admin.transactions.report.getTaxDetails', array_merge(request()->except(['page']), ['source' => $key])) }}">
                                                {{ $source_labels[$key] ?? ucfirst(str_replace('_', ' ', $key)) }}
                                            </a>
                                        </td>
                                        <td class="col--numeric">
                                            <span class="txr-amount">{{ \App\CentralLogics\Helpers::format_currency($item['total_base_amount']) }}</span>
                                        </td>
                                        <td class="col--numeric">{{ $row_taxes->sum('rate') }}%</td>
                                        <td>
                                            <div class="txr-tax">
                                                <span class="txr-tax__total">{{ \App\CentralLogics\Helpers::format_currency($row_taxes->sum('amount')) }}</span>
                                                @foreach ($row_taxes as $tax)
                                                    <div class="d-flex align-items-baseline gap-2 fs-12">
                                                        <span class="text-muted">{{ $tax['name'] }} ({{ $tax['rate'] }}%)</span>
                                                        <span class="text--title font-weight-bold">{{ \App\CentralLogics\Helpers::format_currency($tax['amount']) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <a class="btn btn-sm mx-auto action-btn action-btn--view" target="_blank"
                                               title="{{ translate('View details') }}"
                                               href="{{ route('admin.transactions.report.getTaxDetails', array_merge(request()->except(['page']), ['source' => $key])) }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    @include('admin-views.report.tax-report.partials._blank', [
                                        'blank_colspan' => 5,
                                        'blank_title' => translate('No tax report generated'),
                                        'blank_body' => translate('messages.To generate your tax report please select & input above field and submit for the result'),
                                    ])
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </div>



@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/view-pages/tax-report-filters.js')}}"></script>
    <script>
        "use strict";

        // `ready`, not `on('ready')`: the panel-wide `.js-select2-custom` pass in
        // admin.js is a `ready` callback, and those run after the `ready` event
        // handlers. Registered the other way round, this card's select2 config —
        // the tax list AJAX included — was built and then immediately replaced by
        // the panel-wide one, which is why the tax rate pickers listed nothing.
        $(document).ready(function() {
            TaxReportFilters.initFixedSelect($('#date_range_type'));
            TaxReportFilters.initFixedSelect($('#calculate_tax_on'));

            function updateUI() {
                if ($('#date_range_type').val() == 'custom') {
                    $('#date_range').removeClass('d-none');
                } else {
                    $('#date_range').addClass('d-none');
                }

                if ($('#calculate_tax_on').val() == 'individual_source') {
                    $('#calculate_commission_tax').removeClass('d-none');
                    $('#calculate_delivery_charge_tax').removeClass('d-none');
                    $('#calculate_service_charge_tax').removeClass('d-none');
                    // $('#calculate_packaging_charge_tax').removeClass('d-none');
                    $('#calculate_subscription_tax').removeClass('d-none');
                    $('#calculate_tax_rate').addClass('d-none').find('select').attr('required', false);
                } else {
                    $('#calculate_tax_rate').removeClass('d-none').find('select').attr('required', true);
                    $('#calculate_commission_tax').addClass('d-none');
                    $('#calculate_delivery_charge_tax').addClass('d-none');
                    $('#calculate_service_charge_tax').addClass('d-none');
                    // $('#calculate_packaging_charge_tax').addClass('d-none');
                    $('#calculate_subscription_tax').addClass('d-none');
                }
            }
            updateUI();
            $('#date_range_type').on('change', updateUI);
            $('#calculate_tax_on').on('change', updateUI);
            $('#reset_button_id').on('click', function() {
                // Only the tax pickers clear: the two fixed-choice selects go
                // back to their default option, which the form's own reset does
                // on the tick after this handler — select2 is re-synced to the
                // value it lands on rather than blanked.
                $('.js-select2-custom[multiple]').val(null).trigger('change');
                setTimeout(() => {
                    $('.js-select2-custom:not([multiple])').trigger('change.select2');
                    updateUI();
                }, 1);
            });
        });


        $(function() {
            $('input[name="dates"]').daterangepicker({
                startDate: moment('{{ $startDate }}'),
                endDate: moment('{{ $endDate }}'),
                maxDate: moment(),
                locale: {
                    format: 'MM/DD/YYYY'
                }
            });
        });


        $(document).ready(function() {
            const selectedTax = @json($selectedTax);
            Object.entries(selectedTax).forEach(([key, taxArray]) => {
                const $select = $(`select[name="${key}[]"]`);
                if (!$select.length || !Array.isArray(taxArray)) return;
                taxArray.forEach(tax => {
                    if (!tax.id || !tax.name) return;

                    const displayText = `${tax.name} (${tax.tax_rate}%)`;
                    const option = new Option(displayText, tax.id, true, true);
                    $select.append(option);
                });
                $select.trigger('change');
                TaxReportFilters.initTaxSelect($select, {
                    placeholder: "{{ translate('Select tax rate') }}",
                    ajax: {
                        url: '{{ route('admin.transactions.report.getTaxList') }}',
                        data: function(params) {
                            return {
                                q: params.term,
                                page: params.page
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data
                            };
                        }
                    }
                });
            });
        });
    </script>
@endpush
