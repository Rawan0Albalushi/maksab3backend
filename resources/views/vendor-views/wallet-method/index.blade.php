@extends('layouts.vendor.app')

@section('title',translate('messages.Store wallet'))

@php
    $wmLang = [
        'hint' => translate('Choose a method above and the details it needs will appear here.'),
        'loading' => translate('Loading method details'),
        'failed' => translate('Could not load the details for this method. Please try again.'),
        'optional' => translate('Optional'),
        'details' => translate('Account details'),
    ];
@endphp

@push('css_or_js')
    <style>
        .wm-modal .modal-header { align-items: flex-start; }
        .wm-modal__sub { margin: .15rem 0 0; font-size: .8rem; color: #7a879b; }
        .wm-fields { margin-top: 1.25rem; }
        .wm-fields__label { display: block; margin-bottom: .55rem; font-size: .68rem; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: #7a879b; }
        .wm-note { display: flex; gap: .6rem; align-items: flex-start; padding: .85rem 1rem; border: 1px dashed #dfe4ec; border-radius: var(--field-radius, 8px); background: #f7f9fb; color: #7a879b; font-size: .82rem; line-height: 1.5; }
        .wm-note i { flex-shrink: 0; font-size: 1rem; }
        .wm-note--error { border-style: solid; border-color: #f3d0d0; background: #fdf5f5; color: #b64b4b; }
        .wm-loading { display: flex; gap: .6rem; align-items: center; justify-content: center; padding: 1.5rem; color: #7a879b; font-size: .82rem; }
        .wm-loading__spinner { width: 18px; height: 18px; border: 2px solid #dfe4ec; border-top-color: var(--primary-clr, #107980); border-radius: 50%; animation: wm-spin .7s linear infinite; }
        @keyframes wm-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .wm-loading__spinner { animation-duration: 2s; } }
    </style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/wallet.svg') }}" class="w--26" alt="">
                        </span>
                        <span>
                            {{translate('messages.Disbursement method setup')}}
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('The bank or mobile-money account your payouts are sent to.') }}</p>
                </div>
            </div>
        </div>
        <div class="card" data-ajax-region data-ajax-url="{{ url()->full() }}"
             data-ajax-links=".page-link" data-ajax-forms=".search-form">
            <div class="card-header py-2">
                <div class="search--button-wrapper">
                    <h3 class="card-title">
                        {{ translate('Disbursement methods') }}<span class="badge badge-soft-secondary"  id="countfoods">{{ $vendor_withdrawal_methods->total() }}</span>
                    </h3>
                    <form class="search-form">
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" name="search" class="form-control" placeholder="{{ translate('Ex') . ' : ' . translate('Search by name') }}"  value="{{ request()?->search ?? null }}" aria-label="Search">

                            <button type="submit" class="btn btn--secondary">
                                <i class="tio-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                &nbsp;
                <div class="p--10px">
                    <a class="btn btn--primary btn-outline-primary w-100" href="javascript:" data-toggle="modal" data-target="#balance-modal"><i class="tio-add-circle"></i> {{translate('messages.Add new method')}}</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="datatable"
                           class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table" data-hs-datatables-options='{
                            "order": [],
                            "orderCellsTop": true,
                            "paging":false
                        }'>
                        <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.SL') }}</th>
                            <th>{{translate('messages.Payment method name')}}</th>
                            <th>{{translate('Payment information')}}</th>
                            <th>{{translate('Default')}}</th>
                            <th class="w-100px text-center">{{translate('messages.Action')}}</th>
                        </tr>
                        </thead>
                        <tbody id="set-rows">
                        @foreach($vendor_withdrawal_methods as $k=>$e)
                            <tr>
                                <th scope="row">{{$k+$vendor_withdrawal_methods->firstItem()}}</th>
                                <td class="text-capitalize text-break text-hover-primary">{{$e['method_name']}}</td>
                                <td>
                                    <div class="col-md-8 mt-2">
                                        @forelse(json_decode($e->method_fields, true) as $key=> $item)
                                            <h5 class="text-capitalize "> {{  translate($key) }}: {{$item}}</h5>
                                        @empty
                                            <h5 class="text-capitalize"> {{translate('No data found')}}</h5>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex">
                                        <div>
                                            <label class="toggle-switch toggle-switch-sm mr-2" data-toggle="tooltip" data-placement="top" title="{{ translate('messages.Make default method') }}" for="statusCheckbox{{$e->id}}">
                                                <input type="checkbox" data-url="{{route('vendor.wallet-method.default',[$e['id'],$e->is_default?0:1])}}" class="toggle-switch-input redirect-url" id="statusCheckbox{{$e->id}}" {{$e->is_default?'checked':''}}>
                                                <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                            </label>
                                        </div>
                                    </div>
                                </td>
                                <td>

                                    @if ($current_employee_id != $e['id'])
                                        <div class="btn--container justify-content-center">
                                            <a class="btn btn-sm action-btn action-btn--delete form-alert" href="javascript:"
                                               data-id="employee-{{$e['id']}}" data-message="{{translate('Want to delete this method information?')}}" title="{{translate('messages.Delete method')}}"><i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{route('vendor.wallet-method.delete',[$e['id']])}}"
                                                  method="post" id="employee-{{$e['id']}}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if(count($vendor_withdrawal_methods) === 0)
                        <div class="empty--data">
                            <img src="{{asset('public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                            <h5>
                                {{translate('No data found')}}
                            </h5>
                        </div>
                    @endif
                </div>
            </div>
            <div class="card-footer">
                <div class="page-area">
                    <table>
                        <tfoot>
                        {!! $vendor_withdrawal_methods->links() !!}
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>


        <div class="modal fade wm-modal" id="balance-modal" tabindex="-1" role="dialog"
             aria-labelledby="balanceModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="balanceModalLabel">{{ translate('messages.Add method') }}</h5>
                            <p class="wm-modal__sub">{{ translate('Pick how you want to be paid, then fill in the account details.') }}</p>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('messages.Close') }}">
                            <span aria-hidden="true" class="btn btn--circle btn-soft-danger text-danger"><i class="tio-clear"></i></span>
                        </button>
                    </div>
                    <form action="{{ route('vendor.wallet-method.store') }}" method="post"
                          id="add-method-form"
                          data-ajax-form
                          data-ajax-refresh="[data-ajax-region]"
                          data-ajax-close="#balance-modal"
                          data-ajax-reset
                          data-ajax-busy-text="{{ translate('Saving') }}">
                        @csrf
                        <div class="modal-body">
                            <div class="form-group mb-0">
                                <label class="form-label" for="withdraw_method">
                                    {{ translate('Disbursement method') }}
                                    <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="withdraw_method" name="withdraw_method" required>
                                    <option value="" selected disabled>{{ translate('Select disburse method') }}</option>
                                    @foreach($withdrawal_methods as $item)
                                        <option value="{{ $item['id'] }}">{{ $item['method_name'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="wm-fields" id="method-fields">
                                <div class="wm-note">
                                    <i class="tio-info-outined"></i>
                                    <span>{{ $wmLang['hint'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer pt-0 border-0">
                            <button type="button" class="btn btn--reset" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
                            <button type="submit" id="submit_button" disabled class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
@push('script_2')
    <script>
        "use strict";

        const wmFieldsUrl = "{{ route('vendor.wallet.method-list') }}";
        const wmLang = @json($wmLang);

        const wmInputTypes = { string: 'text', phone: 'tel' };

        function wmLabel(name) {
            return name.replace(/[_-]+/g, ' ').replace(/\S+/g, function (word) {
                return word.charAt(0).toUpperCase() + word.slice(1);
            });
        }

        function wmNote(text, isError) {
            $('#method-fields').html(
                '<div class="wm-note' + (isError ? ' wm-note--error' : '') + '">'
                + '<i class="' + (isError ? 'tio-error-outlined' : 'tio-info-outined') + '"></i>'
                + '<span></span></div>'
            ).find('.wm-note span').text(text);
        }

        function wmLoading() {
            $('#method-fields').html(
                '<div class="wm-loading"><span class="wm-loading__spinner"></span><span></span></div>'
            ).find('.wm-loading span').last().text(wmLang.loading);
        }

        function wmFields(fields) {
            const $box = $('#method-fields').empty();

            $box.append($('<span class="wm-fields__label"></span>').text(wmLang.details));

            fields.forEach(function (field) {
                const required = Number(field.is_required) === 1;
                const inputId = 'wm-field-' + field.input_name;
                const $label = $('<label class="form-label"></label>')
                    .attr('for', inputId)
                    .text(wmLabel(field.input_name));

                if (required) {
                    $label.append(' ', $('<span class="text-danger">*</span>'));
                } else {
                    $label.append(' ', $('<span class="form-label-secondary"></span>').text('(' + wmLang.optional + ')'));
                }

                const $input = $('<input class="form-control">')
                    .attr({
                        type: wmInputTypes[field.input_type] || field.input_type,
                        id: inputId,
                        name: field.input_name,
                        placeholder: field.placeholder || ''
                    })
                    .prop('required', required);

                $box.append($('<div class="form-group"></div>').append($label, $input));
            });
        }

        $('#withdraw_method').on('change', function () {
            const methodId = this.value;

            $('#submit_button').prop('disabled', true);
            wmLoading();

            $.ajax({
                url: wmFieldsUrl,
                data: { method_id: methodId },
                type: 'get',
                success: function (response) {
                    const fields = (response.content && response.content.method_fields) || [];
                    wmFields(fields);
                    $('#submit_button').prop('disabled', false);
                },
                error: function () {
                    wmNote(wmLang.failed, true);
                }
            });
        });

        $('#balance-modal').on('hidden.bs.modal', function () {
            $('#add-method-form')[0].reset();
            wmNote(wmLang.hint, false);
            $('#submit_button').prop('disabled', true);
        });
    </script>
@endpush
