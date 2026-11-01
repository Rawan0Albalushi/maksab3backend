@extends('layouts.admin.app')

@section('title', translate('messages.Withdraw method'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/cash.css') }}">
@endpush

@section('content')

@php
    /* Form control types, matching the options the edit screen offers. Mapped from
       literal keys so 'string' does not become a translation key of its own. */
    $input_type_labels = [
        'string' => translate('Text'),
        'number' => translate('Number'),
        'date' => translate('Date'),
        'email' => translate('Email'),
        'phone' => translate('Phone'),
    ];

    $request_count = function ($n) {
        return translate('Requests') . ': ' . $n;
    };

    $payee_count = function ($n) {
        return translate('messages.Payees') . ': ' . $n;
    };

    $tiles = [
        [
            'icon' => 'tio-credit-card',
            'value' => $summary['total'],
            'label' => translate('messages.Methods set up'),
        ],
        [
            'icon' => 'tio-checkmark-circle-outlined', 'tone' => 'in',
            'value' => $summary['active'],
            'label' => translate('messages.Offered to payees'),
        ],
        [
            'icon' => 'tio-pause-circle-outlined', 'tone' => 'off',
            'value' => $summary['inactive'],
            'label' => translate('messages.Turned off'),
        ],
        [
            'icon' => 'tio-star', 'tone' => 'info',
            'value' => $summary['default'] ?: translate('messages.None'),
            'label' => translate('messages.Default method'),
        ],
    ];
@endphp

<div class="content container-fluid csh">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/wallet.svg') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('messages.withdraw Method List') }}
                    <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $withdrawal_methods->total() }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('The bank and mobile-money details stores and deliverymen can be paid through.') }}</p>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('admin.transactions.withdraw-method.create') }}" class="btn btn--primary">
                <i class="tio-add-circle"></i> {{ translate('messages.Add new method') }}
            </a>
        </div>
    </div>

    @include('admin-views.cash.partials._summary-strip')

    <div class="card">
        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper">
                @include('partials._table-head', [
                    'subtitle' => translate('Ways a vendor, deliveryman or rider can be paid. each method declares the account details they must fill in.'),
                    'count' => null,
                ])

                <form class="search-form theme-style">
                    <div class="input-group input--group">
                        <input id="datatableSearch" name="search" type="search" class="form-control h--40px"
                               placeholder="{{ translate('Ex') }}: {{ translate('messages.method Name') }}"
                               value="{{ request('search') }}" aria-label="{{ translate('Search') }}">
                        <button type="submit" class="btn btn--secondary h--40px"><i class="tio-search"></i></button>
                    </div>
                </form>

                @if(request()->filled('search'))
                    <a href="{{ route('admin.transactions.withdraw-method.list') }}" class="btn btn--reset ml-2">
                        <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                    </a>
                @endif
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table id="datatable" class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.Method ID') }}</th>
                            <th>{{ translate('messages.method Name') }}</th>
                            <th>{{ translate('messages.Method fields') }}</th>
                            <th>{{ translate('messages.In use') }}</th>
                            <th class="text-center">{{ translate('Active status') }}</th>
                            <th class="text-center">{{ translate('messages.Default method') }}</th>
                            <th class="text-center">{{ translate('messages.Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($withdrawal_methods as $withdrawal_method)
                            <tr>
                                <td><span class="csh-id">#{{ $withdrawal_method->id }}</span></td>
                                <td>
                                    <span class="csh-method"><i class="tio-credit-card"></i> {{ $withdrawal_method['method_name'] }}</span>
                                </td>
                                <td>
                                    {{-- One chip per field the payee has to fill in. The old markup
                                         drew these as inline runs split by hand-built 1px divs. --}}
                                    @if(filled($withdrawal_method['method_fields']))
                                        <div class="csh-fields">
                                            @foreach($withdrawal_method['method_fields'] as $method_field)
                                                <span class="csh-field">
                                                    <span class="csh-field__name">{{ $method_field['input_name'] }}</span>
                                                    <span class="csh-field__type">{{ $input_type_labels[$method_field['input_type']] ?? ucfirst($method_field['input_type']) }}</span>
                                                    @if(filled($method_field['placeholder']))
                                                        <span class="csh-field__hint">{{ $method_field['placeholder'] }}</span>
                                                    @endif
                                                    @if($method_field['is_required'])
                                                        <span class="csh-field__req">{{ translate('messages.Required.') }}</span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="csh-fields--none">{{ translate('messages.No fields defined') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($withdrawal_method->requests_count || $withdrawal_method->payees_count)
                                        <span class="csh-usage">
                                            <span class="csh-usage__line">{{ $request_count($withdrawal_method->requests_count) }}</span>
                                            <span class="csh-usage__sub">{{ $payee_count($withdrawal_method->payees_count) }}</span>
                                        </span>
                                    @else
                                        <span class="csh-usage__none">{{ translate('messages.Not used yet') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <label class="toggle-switch toggle-switch-sm">
                                        <input class="toggle-switch-input status featured-status"
                                               data-id="{{ $withdrawal_method->id }}"
                                               type="checkbox" {{ $withdrawal_method->is_active ? 'checked' : '' }}
                                               aria-label="{{ translate('Active status') }}">
                                        <span class="toggle-switch-label mx-auto">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td class="text-center">
                                    <label class="toggle-switch mx-auto toggle-switch-sm">
                                        <input type="checkbox" class="default-method toggle-switch-input"
                                               id="{{ $withdrawal_method->id }}" {{ $withdrawal_method->is_default == 1 ? 'checked' : '' }}
                                               aria-label="{{ translate('messages.Default method') }}">
                                        <span class="toggle-switch-label mx-auto">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a href="{{ route('admin.transactions.withdraw-method.edit', [$withdrawal_method->id]) }}"
                                           class="btn btn-sm action-btn action-btn--edit" title="{{ translate('Edit') }}">
                                            <i class="tio-edit"></i>
                                        </a>

                                        @if(!$withdrawal_method->is_default)
                                            <a class="btn btn-sm action-btn action-btn--delete form-alert" href="javascript:;"
                                               title="{{ translate('messages.Delete') }}" data-id="delete-{{ $withdrawal_method->id }}"
                                               data-message="{{ translate('Want to delete this item?') }}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{ route('admin.transactions.withdraw-method.delete', [$withdrawal_method->id]) }}"
                                                  method="post" id="delete-{{ $withdrawal_method->id }}">
                                                @csrf @method('delete')
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(count($withdrawal_methods) === 0)
                    @include('admin-views.cash.partials._empty', [
                        'empty_title' => translate('messages.No withdraw method found'),
                        'empty_body' => request()->filled('search')
                            ? translate('messages.Nothing matches this search. Try another method name.')
                            : translate('Add a method so vendors, deliverymen and riders have somewhere to be paid.'),
                    ])
                @endif
            </div>
        </div>

        <div class="page-area">
            {!! $withdrawal_methods->links() !!}
        </div>
    </div>
</div>

<div class="modal fade" id="withdrawMethodList" tabindex="-1" role="dialog" aria-labelledby="withdrawMethodListLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div id="data-view"></div>
        </div>
    </div>
</div>

@endsection


@push('script_2')
  <script>
      "use strict";
      $(document).on('change', '.default-method', function () {
          let id = $(this).attr("id");
          let status = $(this).prop("checked") === true ? 1:0;

          $.ajaxSetup({
              headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
              }
          });
          $.ajax({
              url: "{{route('admin.transactions.withdraw-method.default-status-update')}}",
              method: 'POST',
              data: {
                  id: id,
                  status: status
              },
              success: function (data) {
                  if(data.success == true) {
                      toastr.success('{{ translate('Updated successfully')}}');
                      setTimeout(function(){
                          location.reload();
                      }, 1000);
                  }
                  else if(data.success == false) {
                      toastr.error('{{ translate('Default method updated failed.')}}');
                      setTimeout(function(){
                          location.reload();
                      }, 1000);
                  }
              }
          });
      });

      $('.featured-status').on('change', function () {
          let id = $(this).data('id');
          $.ajaxSetup({
              headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
              }
          });
          $.ajax({
              url: "{{route('admin.transactions.withdraw-method.status-update')}}",
              method: 'POST',
              data: {
                  id: id
              },
              success: function (data) {
                  toastr.success('{{ translate('Updated successfully')}}');
              }
          });
      })


      function fetch_data(id) {
            $.ajax({
                url: "{{ route('admin.transactions.withdraw-method.getMethodInfo') }}" + '?id=' + id,
                type: "get",

                beforeSend: function () {
                    $('#data-view').empty();
                    $('#loading').show()
                },
                success: function(data) {
                    $("#withdrawMethodList").modal("show");
                    $("#data-view").append(data.view);
                },
                complete: function () {
                    $('#loading').hide()
                }
            })
        }



        $(document).on('click', '.withdraw-info-show', function () {
            let id = $(this).data('id');
            fetch_data(id)

        })


  </script>
@endpush
