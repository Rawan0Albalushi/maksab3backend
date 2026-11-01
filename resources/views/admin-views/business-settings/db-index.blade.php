@extends('layouts.admin.app')

@section('title', translate('Clean database'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/db-clean.css') }}">
@endpush

@section('content')
    @php
        $total_records = array_sum($rows);
        $filled_count = count(array_filter($rows));
        $empty_count = count($rows) - $filled_count;
    @endphp

    <div class="content container-fluid tps dbc">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon">
                    <img src="{{ asset('public/assets/admin/img/cloud-database.png') }}" alt="">
                </span>
                <span class="tps-head__text">
                    <h1>{{ translate('Clean database') }}</h1>
                    <p>{{ translate('Permanently delete the records stored in the tables you select.') }}</p>
                </span>
            </div>

            <button type="button" class="tps-help" data-toggle="modal" data-target="#db-clean-help-modal">
                <i class="tio-help-outlined"></i>
                <span>{{ translate('How it works') }}</span>
            </button>
        </div>

        <div class="tps-note tps-note--danger mb-3">
            <i class="tio-warning"></i>
            <div>
                <strong>{{ translate('Note') }}:</strong>
                {{ translate('Clearing a table cannot be undone. Take a full database backup before you continue.') }}
            </div>
        </div>

        <div class="dbc-stats">
            <div class="dbc-stat">
                <span class="dbc-stat__icon"><i class="tio-table"></i></span>
                <span>
                    <span class="dbc-stat__value">{{ number_format(count($tables)) }}</span>
                    <span class="dbc-stat__label">{{ translate('Tables available') }}</span>
                </span>
            </div>
            <div class="dbc-stat">
                <span class="dbc-stat__icon"><i class="tio-layers-outlined"></i></span>
                <span>
                    <span class="dbc-stat__value">{{ number_format($total_records) }}</span>
                    <span class="dbc-stat__label">{{ translate('Records stored') }}</span>
                </span>
            </div>
            <div class="dbc-stat dbc-stat--danger">
                <span class="dbc-stat__icon"><i class="tio-delete-outlined"></i></span>
                <span>
                    <span class="dbc-stat__value" id="dbc-selected-records">0</span>
                    <span class="dbc-stat__label">{{ translate('Records selected') }}</span>
                </span>
            </div>
        </div>

        <form action="{{ route('admin.business-settings.clean-db') }}" method="post" id="db-clean-form">
            @csrf

            <div class="dbc-toolbar">
                <div class="dbc-search">
                    <i class="tio-search dbc-search__icon"></i>
                    <input type="search" class="form-control" id="dbc-search"
                           autocomplete="off" placeholder="{{ translate('Search a table by name') }}"
                           aria-label="{{ translate('Search a table by name') }}">
                    <button type="button" class="dbc-search__clear" id="dbc-search-clear"
                            aria-label="{{ translate('Clear search') }}">
                        <i class="tio-clear"></i>
                    </button>
                </div>

                <div class="dbc-seg" role="group">
                    <input type="radio" name="dbc_scope" id="dbc-scope-all" value="all" checked>
                    <label for="dbc-scope-all">
                        {{ translate('All') }} <span class="dbc-seg__count">{{ count($tables) }}</span>
                    </label>
                    <input type="radio" name="dbc_scope" id="dbc-scope-filled" value="filled">
                    <label for="dbc-scope-filled">
                        {{ translate('With data') }} <span class="dbc-seg__count">{{ $filled_count }}</span>
                    </label>
                    <input type="radio" name="dbc_scope" id="dbc-scope-empty" value="empty">
                    <label for="dbc-scope-empty">
                        {{ translate('Empty') }} <span class="dbc-seg__count">{{ $empty_count }}</span>
                    </label>
                </div>

                <div class="dbc-bulk">
                    <button type="button" class="btn btn--reset" id="dbc-select-shown">
                        <i class="tio-checkmark-circle-outlined"></i> {{ translate('Select all shown') }}
                    </button>
                    <button type="button" class="btn btn--reset" id="dbc-clear-selection">
                        <i class="tio-clear-circle-outlined"></i> {{ translate('Clear selection') }}
                    </button>
                </div>
            </div>

            <div class="dbc-grid">
                @foreach ($tables as $key => $table)
                    <label class="dbc-item {{ $rows[$key] ? '' : 'is-empty' }}"
                           data-table="{{ $table }}"
                           data-rows="{{ $rows[$key] }}"
                           data-empty="{{ $rows[$key] ? 0 : 1 }}"
                           title="{{ $table }}">
                        <input type="checkbox" name="tables[]" value="{{ $table }}" id="{{ $table }}" class="dbc-check">
                        <span class="dbc-item__tick" aria-hidden="true"></span>
                        <span class="dbc-item__body">
                            <span class="dbc-item__name">{{ $table }}</span>
                        </span>
                        <i class="tio-link dbc-item__link"
                           title="{{ translate('Selected automatically because a table it depends on is selected.') }}"></i>
                        <span class="dbc-item__count">{{ number_format($rows[$key]) }}</span>
                    </label>
                @endforeach
            </div>

            <div class="dbc-empty">
                <i class="tio-search"></i>
                {{ translate('No table matches your search.') }}
            </div>

            <div class="dbc-actionbar">
                <div class="dbc-actionbar__text">
                    <p class="dbc-actionbar__summary" id="dbc-summary">
                        {{ translate('Select the tables you want to empty.') }}
                    </p>
                    <p class="dbc-actionbar__hint" id="dbc-hidden-hint"></p>
                </div>

                <div class="dbc-actionbar__buttons">
                    <button type="reset" class="btn btn--reset">
                        <i class="tio-refresh"></i> {{ translate('Reset') }}
                    </button>
                    <button type="{{ getDemoModeFormButton(type: 'button') }}" id="dbc-submit" disabled
                            class="btn btn--danger {{ getDemoModeFormButton(type: 'class') }}">
                        <i class="tio-delete-outlined"></i> {{ translate('Clear selected data') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="db-clean-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Clearing a database') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                            aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('Take a full database backup — nothing on this page can be undone.') }}</li>
                        <li>{{ translate('Use the search box or the filter to find the tables you want to empty.') }}</li>
                        <li>{{ translate('Select a table and everything that references it is selected with it.') }}</li>
                        <li>{{ translate('Check the summary at the bottom of the page, then press clear selected data.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--warn">
                        <i class="tio-info-outined"></i>
                        <div>
                            {{ translate('Selecting zones, stores, vendors or orders also selects the tables that reference them, so no orphaned rows are left behind.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @php
        $dbc_text = [
            'idle' => translate('Select the tables you want to empty.'),
            'tables' => translate('Tables selected'),
            'hidden' => translate('Selected tables hidden by the current filter'),
        ];
    @endphp

    <script>
        "use strict";

        const store_dependent = ['stores', 'store_schedule', 'discounts', 'campaign_store', 'store_configs', 'store_notification_settings', 'store_subscriptions', 'store_wallets', 'disbursements', 'disbursement_details', 'disbursement_withdrawal_methods'];
        const order_dependent = ['order_delivery_histories', 'd_m_reviews', 'delivery_histories', 'track_deliverymen', 'order_details', 'reviews', 'order_transactions', 'offline_payments', 'order_payments', 'order_references', 'refunds', 'cash_back_histories', 'expenses'];
        const zone_dependent = ['stores', 'vendors', 'orders'];

        const DBC_TEXT = @json($dbc_text);

        let $root, $items, $submit;

        function checked_stores(status) {
            store_dependent.forEach(function (value) {
                $('#' + value).prop('checked', status);
            });
            $('#vendors').prop('checked', status);
        }

        function checked_orders(status) {
            order_dependent.forEach(function (value) {
                $('#' + value).prop('checked', status);
            });
            $('#orders').prop('checked', status);
        }

        function check_zone() {
            if ($('#zones').is(':checked')) {
                toastr.warning("{{ translate('messages.Table unchecked warning') }}: zones");
                return true;
            }
            return false;
        }

        function check_orders() {
            if ($('#orders').is(':checked')) {
                toastr.warning("{{ translate('messages.Table unchecked warning') }}: orders");
                return true;
            }
            return false;
        }

        function check_store() {
            if ($('#stores').is(':checked') || $('#vendors').is(':checked')) {
                toastr.warning("{{ translate('messages.Table unchecked warning') }}: stores/vendors");
                return true;
            }
            return false;
        }

        /*
         * Mirrors the three guards above with the row itself excluded: a table is
         * "linked" exactly when unchecking it on its own would be refused. Without
         * the self-exclusion `stores` would report as held down by `stores`.
         */
        function isLinked(id) {
            const zone = id !== 'zones' && $('#zones').is(':checked');
            const store = (id !== 'stores' && $('#stores').is(':checked')) ||
                (id !== 'vendors' && $('#vendors').is(':checked'));
            const order = id !== 'orders' && $('#orders').is(':checked');

            if (store_dependent.includes(id)) return store || zone;
            if (order_dependent.includes(id)) return order || zone;
            if (zone_dependent.includes(id)) return zone;
            return false;
        }

        function applyFilters() {
            const query = $('#dbc-search').val().trim().toLowerCase();
            const scope = $('input[name="dbc_scope"]:checked').val();
            let shown = 0;

            $items.each(function () {
                const $item = $(this);
                const isEmpty = $item.data('empty') === 1;
                const matchesQuery = query === '' || String($item.data('table')).indexOf(query) !== -1;
                const matchesScope = scope === 'all' || (scope === 'empty' ? isEmpty : !isEmpty);
                const visible = matchesQuery && matchesScope;

                $item.toggleClass('is-hidden', !visible);
                if (visible) shown++;
            });

            $root.toggleClass('has-query', query !== '');
            $root.toggleClass('is-no-results', shown === 0);
            refreshSelection();
        }

        function refreshSelection() {
            let tables = 0;
            let records = 0;
            let hidden = 0;

            $items.each(function () {
                const $item = $(this);
                const $box = $item.find('.dbc-check');
                const checked = $box.is(':checked');

                $item.toggleClass('is-linked', checked && isLinked($box.attr('id')));

                if (!checked) return;

                tables++;
                records += parseInt($item.data('rows'), 10) || 0;
                if ($item.hasClass('is-hidden')) hidden++;
            });

            $('#dbc-selected-records').text(records.toLocaleString());
            $('#dbc-summary').text(tables === 0
                ? DBC_TEXT.idle
                : DBC_TEXT.tables + ': ' + tables.toLocaleString());
            $('#dbc-hidden-hint').text(DBC_TEXT.hidden + ': ' + hidden.toLocaleString());

            $root.toggleClass('has-selection', tables > 0);
            $root.toggleClass('has-hidden-selection', hidden > 0);
            $submit.prop('disabled', tables === 0);
        }

        $(document).ready(function () {
            $root = $('.dbc');
            $items = $root.find('.dbc-item');
            $submit = $('#dbc-submit');

            $root.on('change', '.dbc-check', function (event) {
                if ($(this).is(':checked')) {
                    if (event.target.id === 'zones' || event.target.id === 'stores' || event.target.id === 'vendors') {
                        checked_stores(true);
                    }

                    if (event.target.id === 'zones' || event.target.id === 'orders') {
                        checked_orders(true);
                    }
                } else {
                    if (store_dependent.includes(event.target.id)) {
                        if (check_store() || check_zone()) {
                            $(this).prop('checked', true);
                        }
                    } else if (order_dependent.includes(event.target.id)) {
                        if (check_orders() || check_zone()) {
                            $(this).prop('checked', true);
                        }
                    } else if (zone_dependent.includes(event.target.id)) {
                        if (check_zone()) {
                            $(this).prop('checked', true);
                        }
                    }
                }

                refreshSelection();
            });

            $('#dbc-search').on('input', applyFilters);
            $('input[name="dbc_scope"]').on('change', applyFilters);

            $('#dbc-search-clear').on('click', function () {
                $('#dbc-search').val('').trigger('focus');
                applyFilters();
            });

            /* Only the rows the current filter shows — selecting what is hidden is
               how an admin ends up clearing a table they never looked at. The
               cascade is run once here rather than by triggering "change" per
               row, which would re-scan every row for every row. */
            $('#dbc-select-shown').on('click', function () {
                $items.not('.is-hidden').find('.dbc-check').prop('checked', true);

                if ($('#zones').is(':checked') || $('#stores').is(':checked') || $('#vendors').is(':checked')) {
                    checked_stores(true);
                }

                if ($('#zones').is(':checked') || $('#orders').is(':checked')) {
                    checked_orders(true);
                }

                refreshSelection();
            });

            $('#dbc-clear-selection').on('click', function () {
                $items.find('.dbc-check:checked').prop('checked', false);
                refreshSelection();
            });

            $('#db-clean-form').on('reset', function () {
                $('#dbc-search').val('');
                $('#dbc-scope-all').prop('checked', true);
                setTimeout(applyFilters, 0);
            });

            $('#db-clean-form').on('submit', function (e) {
                e.preventDefault();

                const form = this;
                const tables = $items.find('.dbc-check:checked').length;

                if (tables === 0) {
                    toastr.warning(DBC_TEXT.idle);
                    return;
                }

                Swal.fire({
                    title: '{{ translate('Are you sure?') }}',
                    text: "{{ translate('This permanently deletes every record in the selected tables.') }}",
                    type: 'warning',
                    showCancelButton: true,
                    cancelButtonColor: 'default',
                    confirmButtonColor: '#FC6A57',
                    cancelButtonText: '{{ translate('messages.No') }}',
                    confirmButtonText: '{{ translate('messages.Yes') }}',
                    reverseButtons: true
                }).then((result) => {
                    if (result.value) {
                        form.submit();
                    }
                });
            });

            applyFilters();
        });
    </script>
@endpush
