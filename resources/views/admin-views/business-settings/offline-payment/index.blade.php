@extends('layouts.admin.app')
@section('title', translate('offline Payment Method'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                {{translate('Payment Methods Setup')}}
            </h2>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
            <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2">
                <ul class="nav nav-tabs border-0 nav--tabs nav--pills">
                    <li class="nav-item">
                        <a class="nav-link   {{ Request::is('admin/business-settings/third-party/payment-method') ? 'active' : '' }}" href="{{ route('admin.business-settings.third-party.payment-method') }}"   aria-disabled="true">{{translate('Digital payment')}}</a>
                    </li>
                    @if (\App\CentralLogics\Helpers::get_business_settings('offline_payment_status'))
                    <li class="nav-item">
                        <a class="nav-link {{ !request()->has('status') ? 'active':'' }}" href="{{route('admin.business-settings.offline')}}">{{ translate('Offline payment') }}</a>
                    </li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="fs-12 text-dark px-3 py-2 rounded bg-warning bg-opacity-10 mb-20">
            <div class="d-flex gap-2 ">
                <span class="text-warning lh-1 fs-14">
                    <i class="tio-info"></i>
                </span>
                <span>
                    {{ translate('In this section, you can add offline payment methods to make them available as offline payment options for the customers') }}
                </span>
            </div>
            <ul class="mb-0">
                <li class="color-656565">
                    {{ translate('To make available these payment options, you must enable the Offline payment option from') }} <strong class="font-semibold text-primary"><a style="color: #245BD1;" href="{{route('admin.business-settings.business-setup')}}" target="_blank" rel="noopener noreferrer">{{ translate('Business information') }}</a></strong> {{ translate('Page') }}
                </li>
                <li>
                    {{ translate('To use offline payments, you need to set up at least one offline payment method') }}
                </li>
            </ul>
        </div>

        <div class="tab-content" id="nav-tabContent">
            <div class="tab-pane fade show active" id="nav-all" role="tabpanel" aria-labelledby="nav-all-tab">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-20">
                            <h4 class="mb-0 flex-grow-1">{{ translate('Offline Payment Methods List') }}</h4>
                            <div class="d-flex align-items-stretch flex-wrap gap-3">
                                <div class="flex-grow-1">
                                    <form action="{{ url()->current() }}" method="GET">
                                        <div class="input--group input-group input-group-merge input-group-flush w-340">
                                            <input id="datatableSearch_" type="search" name="search" class="form-control" placeholder="{{ translate('Search by payment method name') }}" aria-label="Search by payment method name" value="{{ request('search') }}" required="">
                                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                        </div>
                                    </form>
                                </div>
                                <a href="{{route('admin.business-settings.offline.new')}}" class="btn btn--primary"><i class="tio-add-circle"></i> {{ translate('Add new method') }}</a>

                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table w-100 text-title">
                                <thead class="thead-light thead-50 text-capitalize">
                                    <tr>
                                        <th>{{ translate('SL') }}</th>
                                        <th>{{ translate('Payment method name') }}</th>
                                        <th>{{ translate('Payment information') }}</th>
                                        <th>{{ translate('Required Information from Customer') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th class="text-center">{{ translate('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($methods as $key => $method)
                                        <tr>
                                            <td>{{$key+$methods->firstItem()}}</td>
                                            <td>{{ $method->method_name }}</td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    @foreach ($method->method_fields as $item)
                                                        <div>{{ ucwords(str_replace('_',' ',$item['input_name'])) }} : {{ $item['input_data'] }}</div>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td>
                                                @foreach ($method->method_informations as $info_key=>$item)
                                                    {{ ucwords(str_replace('_',' ',$item['customer_input'])) }}
                                                    {{ count($method->method_informations) > ($info_key+1) ?'|':'' }}
                                                @endforeach
                                            </td>

                                            <td>
                                                <label class="toggle-switch toggle-switch-sm">
                                                    <input type="checkbox"
                                                           data-id="status-{{$method->id}}"
                                                           data-type="status"
                                                           data-image-on="{{asset('/public/assets/admin/img/modal/payment-on.png')}}"
                                                           data-image-off=" {{asset('/public/assets/admin/img/modal/wallet-off.png')}}"
                                                           data-title-on="{{ translate('Turn on payment method') }}: {{ ucfirst(str_replace('_', ' ', $method->method_name)) }}"
                                                           data-title-off="{{ translate('Turn off payment method') }}: {{ ucfirst(str_replace('_', ' ', $method->method_name)) }}"
                                                           data-text-on="<p>{{ translate('By enabling this payment method, customers can securely pay with their account.') }}</p>"
                                                           data-text-off="<p>{{ translate('By disabling this payment method, customers cannot use it to pay.') }}</p>"
                                                           class="status toggle-switch-input dynamic-checkbox"
                                                           id="status-{{$method->id}}" {{$method->status?'checked':''}}>
                                                    <span class="toggle-switch-label">
                                                        <span class="toggle-switch-indicator"></span>
                                                    </span>
                                                </label>
                                                <form action="{{route('admin.business-settings.offline.status',['id'=>$method->id])}}" method="get" id="status-{{$method->id}}_form">
                                                </form>
                                            </td>

                                            <td>
                                                <div class="btn--container justify-content-center">
                                                    <a class="btn action-btn action-btn--edit" title="Edit" href="{{route('admin.business-settings.offline.edit', ['id'=>$method->id])}}">
                                                        <i class="tio-edit"></i>
                                                    </a>
                                                    <button class="btn action-btn action-btn--delete" title="Delete"
                                                            data-toggle="modal" data-target="#delete-modal"
                                                            onclick="set_delete_data('{{ $method->id }}')">
                                                        <i class="tio-delete-outlined"></i>
                                                    </button>

                                                    <form action="{{route('admin.business-settings.offline.delete')}}" method="post" id="delete-method_name-{{ $method->id }}">
                                                        @csrf
                                                        <input type="hidden" value="{{ $method->id }}" name="id" required>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @if ($methods->count() > 0)
                                <div class="p-3 d-flex justify-content-end">
                                    {!! $methods->appends(request()->all())->links() !!}
                                </div>
                            @else
                            <div class="empty--data">
                                <img width="64" class="mb-2" src="{{asset('/public/assets/admin/svg/illustrations/no-data.svg')}}" alt="public">
                                <p class="fs-16 mb-20">
                                    {{translate('No Payment Method List')}}
                                </p>
                                <a href="{{route('admin.business-settings.offline.new')}}" class="btn btn--primary"><i class="tio-add-circle"></i> {{ translate('Add new method') }}</a>
                            </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="delete-modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header px-2 pt-2">
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div class="mx-auto mb-20">
                        <div class="text-center mb-30">
                            <img width="64" height="64" src="{{asset('public/assets/admin/img/modal/delete.png')}}" alt="" class="mb-20 initial--10">
                            <h3 class="mb-3">
                                {{ translate('Do you want to delete this payment method?') }}
                            </h3>
                            <p class="mb-0">
                                {{ translate('Deleting this payment method will permanently remove its data.') }} <br>
                                {{ translate('This cannot be undone.') }}
                            </p>
                        </div>
                        <div class="btn--container justify-content-center">
                            <button type="button" class="btn btn-danger min-w-120" id="confirm-delete-btn">
                                <i class="tio-delete-outlined"></i> {{ translate('Yes, delete') }}
                            </button>
                            <button type="button" class="btn btn--reset min-w-120" data-dismiss="modal" ><i class="tio-clear-circle-outlined"></i> {{ translate('No') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>  
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";



        let deleteId = null;
        function set_delete_data(id) {
            deleteId = id;
        }
        $('#confirm-delete-btn').on('click', function() {
            $('#delete-method_name-' + deleteId).submit();
        });
    </script>
@endpush

