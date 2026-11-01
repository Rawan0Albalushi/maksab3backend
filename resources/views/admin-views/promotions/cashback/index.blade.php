@extends('layouts.admin.app')

@section('title',translate('Cashback offer'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/cashback.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('Create cashback offer')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Return part of what a customer spends to their wallet after they order.') }}</p>
        </div>

        <div class="row g-2">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body" id="form_data">
                        <form id="cashback-submit" action="{{route('admin.users.cashback.store')}}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-12">
                                    @if ($language)
                                    <ul class="nav nav-tabs mb-3 border-0">
                                        <li class="nav-item">
                                            <a class="nav-link lang_link active"
                                            href="#"
                                            id="default-link">{{translate('Default')}}</a>
                                        </li>
                                        @foreach ($language as $lang)
                                            <li class="nav-item">
                                                <a class="nav-link lang_link"
                                                    href="#"
                                                    id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                                </li>
                                                @endforeach
                                            </ul>
                                </div>

                                <div class="col-md-4 col-lg-4 col-sm-6">
                                    <div class="lang_form" id="default-form">
                                        <div class="form-group">
                                            <label class="input-label"
                                                for="default_title">{{ translate('messages.Title') }}
                                                ({{ translate('Default') }})
                                                <span class="form-label-secondary text-danger"
                                                      data-toggle="tooltip" data-placement="right"
                                                      data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span>
                                            </label>
                                            <input  type="text" value="{{ old('title.0') }}" name="title[]" maxlength="254" id="default_title"
                                                class="form-control" placeholder="{{ translate('Eid dhamaka') }}" >
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                    </div>
                                        @foreach ($language as $key => $lang)
                                            <div class="d-none lang_form"
                                                id="{{ $lang }}-form">
                                                <div class="form-group">
                                                    <label class="input-label"
                                                        for="{{ $lang }}_title">{{ translate('messages.Title') }}
                                                        ({{ strtoupper($lang) }})
                                                    </label>
                                                    <input type="text" name="title[]" maxlength="254"  value="{{ old('title.'.$key+1) }}" id="{{ $lang }}_title"
                                                        class="form-control" placeholder="{{ translate('Eid dhamaka') }}"
                                                         >
                                                </div>
                                                <input type="hidden" name="lang[]" value="{{ $lang }}">
                                            </div>
                                        @endforeach
                                    @else
                                        <div id="default-form">
                                            <div class="form-group">
                                                <label class="input-label"
                                                    for="exampleFormControlInput1">{{ translate('messages.Title') }} ({{ translate('Default') }})</label>
                                                <input type="text" name="title[]" maxlength="254" class="form-control"
                                                    placeholder="{{ translate('Eid dhamaka') }}">
                                            </div>
                                            <input type="hidden" name="lang[]" value="default">
                                        </div>
                                    @endif
                                </div>

                                <div class="col-md-4 col-lg-4 col-sm-6" id="customer_wise">
                                      <div class="form-group pickup-zone-tag error-wrapper">
                                        <label class="input-label" for="select_customer">{{translate('Select customer')}}</label>
                                        <select name="customer_id[]" id="select_customer" required
                                            class="form-control  multiple-select2" multiple="multiple" data-ajax-url="{{ route('admin.users.customer.select-list') }}" data-placeholder="{{translate('Select customer')}}">
                                            <option  value="all">{{translate('All')}} </option>
                                            @foreach($selected_customers as $user)
                                            <option class="select_customer_option" value="{{$user->id}}" selected>{{$user->f_name.' '.$user->l_name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>



                                <div class="col-md-4 col-lg-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('Cashback type')}} <span class="form-label-secondary text-danger"
                                            data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span></label>
                                        <select name="cashback_type" class="form-control" id="cashback_type" required>
                                            <option {{ old('cashback_type')  == 'percentage' ? "selected": '' }} value="percentage">{{translate('messages.percentage')}} (%)</option>
                                            <option {{ old('cashback_type')  == 'amount' ? "selected": '' }}  value="amount">{{translate('Amount')}} {{ \App\CentralLogics\Helpers::currency_symbol() }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('Cashback amount')}}

                                            <span class="{{ old('cashback_type')  == 'percentage' ||  old('cashback_type') == null  ? '': 'd-none' }} " id="percentage">(%)</span>
                                            <span  class=" {{ old('cashback_type')  == 'amount' && old('cashback_type') !== null ? '': 'd-none' }} " id='cuttency_symbol'>({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                            </span>

                                            <span
                                            class="input-label-secondary text--title" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('Set the value of cashback percentage/ amount which will transfer to the customer wallet when the order is completed.') }}">
                                            <i class="tio-info-outined"></i>
                                        </span>
                                        <span class="form-label-secondary text-danger"
                                        data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('messages.Required.')}}"> *
                                        </span>

                                        </label>
                                        <input type="number" value="{{  old('cashback_amount') }}" step="0.01" min="1" max="100"  placeholder="{{ translate('messages.Ex') . ': 100' }}"  name="cashback_amount" id="Cash_back_amount" class="form-control" required>
                                    </div>
                                </div>

                                <div class="col-md-4 col-lg-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.Minimum Purchase')}} ({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                            <span class="form-label-secondary text-danger"
                                                  data-toggle="tooltip" data-placement="right"
                                                  data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span></label>
                                        <input type="number" step="0.01" id="min_purchase" value="{{  old('min_purchase') }}" required name="min_purchase" value="0" min="0" max="999999999999.99" class="form-control"
                                             placeholder="{{ translate('messages.Ex') . ': 100' }}">
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label" for="max_discount">{{translate('messages.Max Cashback')}} ({{ \App\CentralLogics\Helpers::currency_symbol() }})</label>
                                        <input type="number"   placeholder="{{ translate('messages.Ex') . ': 100' }}" step="0.01" min="0" value="{{  old('cashback_type')  == 'percentage' ?  old('max_discount') : null }}" max="999999999999.99" name="max_discount" id="max_discount" class="form-control">
                                    </div>
                                </div>

                                <div class="col-md-4 col-lg-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('Start date')}}
                                            <span class="form-label-secondary text-danger"
                                                  data-toggle="tooltip" data-placement="right"
                                                  data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span></label>
                                        <input type="date" name="start_date" value="{{  old('start_date') }}" class="form-control" id="date_from" required>
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('End date')}}
                                            <span class="form-label-secondary text-danger"
                                                  data-toggle="tooltip" data-placement="right"
                                                  data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span></label>
                                        <input type="date" name="end_date"  value="{{  old('end_date') }}" class="form-control" id="date_to" required>
                                    </div>
                                </div>
                                <div class="col-md-4 col-lg-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('Limit for same user')}}
                                            <span class="form-label-secondary text-danger"
                                                  data-toggle="tooltip" data-placement="right"
                                                  data-original-title="{{ translate('messages.Required.')}}"> *
                                            </span></label>
                                        <input type="number" step="1" required  value="{{  old('same_user_limit') }}" name="same_user_limit" value="0" min="0" max="9999999" class="form-control"
                                             placeholder="{{ translate('messages.Ex') . ': 5' }}">
                                    </div>
                                </div>

                            </div>
                            <div class="btn--container justify-content-end">
                                <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                                <button type="submit" class="btn btn--primary cashback-submit"><i class="tio-checkmark-circle-outlined"></i> {{translate('messages.Submit')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper">
                            @include('partials._table-head', [
                                'title'    => translate('Cashback list'),
                                'subtitle' => translate('messages.Cashback offers that return part of an order value to the customer.'),
                                'count'    => $cashbacks->total(),
                                'count_id' => 'itemCount',
                            ])
                            <form  class="search-form min--270">
                                <div class="input-group input--group">
                                    <input id="datatableSearch" type="search" name="search" value="{{ request()?->search }}" class="form-control" placeholder="{{ translate('messages.Ex') . ' : ' . translate('messages.Search by title') }}" aria-label="{{translate('Search')}}">
                                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="table-responsive datatable-custom" id="table-div">
                        <table id="columnSearchDatatable"
                               class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                               data-hs-datatables-options='{
                                "order": [],
                                "orderCellsTop": true,

                                "entries": "#datatableEntries",
                                "isResponsive": false,
                                "isShowPaging": false,
                                "paging":false
                               }'>
                            <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{translate('SL')}}</th>
                                <th class="border-0">{{translate('Name')}}</th>
                                <th class="border-0">{{translate('Cashback type')}}</th>
                                <th class="border-0">{{translate('Amount')}}</th>
                                <th class="border-0">{{translate('messages.Duration')}}</th>
                                <th class="border-0 text-center">{{translate('Total used')}}</th>
                                <th class="border-0">{{translate('messages.Status')}}</th>
                                <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                            </tr>
                            </thead>

                            <tbody id="set-rows">
                            @foreach($cashbacks as $key=>$bonus)
                                <tr>
                                    <td>{{$key+$cashbacks->firstItem()}}</td>
                                    <td>
                                    <span class="d-block font-size-sm text-body" title="{{ $bonus['title'] }}">
                                    {{Str::limit($bonus['title'],25,'...')}}
                                    </span>
                                    </td>


                                    <td>{{ translate($bonus['cashback_type']) }}</td>
                                    <td> {{  $bonus['cashback_type'] == 'amount' ? \App\CentralLogics\Helpers::format_currency($bonus['cashback_amount']) : $bonus['cashback_amount'] .' %' }}</td>
                                    <td> {{\App\CentralLogics\Helpers::date_format($bonus->start_date)}} -  {{\App\CentralLogics\Helpers::date_format($bonus->end_date)  }}</td>

                                    <td class="text-center">{{ $bonus['total_used']  }}</td>
                                    <td>
                                        <label class="toggle-switch toggle-switch-sm" for="bonusCheckbox{{$bonus->id}}">
                                            <input type="checkbox" data-url="{{route('admin.users.cashback.status',[$bonus['id'],$bonus->status?0:1])}}" class="toggle-switch-input redirect-url" id="bonusCheckbox{{$bonus->id}}" {{$bonus->status?'checked':''}}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">

                                            <a class="btn action-btn action-btn--edit" href="{{route('admin.users.cashback.update',[$bonus['id']])}}" title="{{translate('messages.Edit cashback')}}"><i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="bonus-{{$bonus['id']}}" data-message="{{ translate('Want to delete this cashback?') }}" title="{{translate('messages.Delete bonus')}}"><i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{route('admin.users.cashback.delete',[$bonus['id']])}}"
                                            method="post" id="bonus-{{$bonus['id']}}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        @if(count($cashbacks) !== 0)
                        <hr>
                        @endif
                        @if(count($cashbacks) === 0)
                        <div class="empty--data">
                            <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                            <h5>
                                {{translate('No data found')}}
                            </h5>
                        </div>
                        @endif
                    </div>
                    <div class="page-area">
                        {!! $cashbacks->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="how-it-works">
        <div class="modal-dialog status-warning-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear"></span>
                    </button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div class="single-item-slider owl-carousel">
                        <div class="item">
                            <div class="mb-20">
                                <div class="text-center">
                                    <img src="{{asset('/public/assets/admin/img/image_127.png')}}" alt="" class="mb-20">
                                    <h5 class="modal-title">{{translate('Wallet bonus is only applicable when a customer add fund to wallet via outside payment gateway!')}}</h5>
                                </div>
                                <ul>
                                    <li>
                                        {{ translate('Customers get a bonus on top of what they add, paid from the admin wallet.') }}
                                    </li>
                                </ul>
                            </div>
                        </div>

                    </div>
                    <div class="d-flex justify-content-center">
                        <div class="slide-counter"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
<script src="{{asset('public/assets/admin')}}/js/view-pages/cashback-index.js"></script>
<script>


</script>
@endpush
