@extends('layouts.admin.app')

@section('title',translate('Contact messages'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/email.png')}}" class="w--26" alt="">
                </span>
                <span>{{translate('messages.All message lists')}}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Messages sent through your contact form, and which ones you have read.') }}</p>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper">
                            @include('partials._table-head', [
                                'title'    => translate('messages.Message lists'),
                                'subtitle' => translate('messages.Messages submitted through the contact form on your landing page.'),
                                'count'    => $contacts->total(),
                                'count_id' => 'itemCount',
                            ])
                            <form class="search-form">
                                <div class="input-group input--group">
                                    <input  type="search" name="search" class="form-control"
                                    placeholder="{{translate('Ex') . ' : ' . translate('search by name, email, or subject')}}" aria-label="{{translate('messages.Search')}}" value="{{request()?->search}}" >
                                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                </div>
                            </form>
                           @if(request()->input('search'))
                                <button type="reset" class="btn btn--primary ml-2 location-reload-to-base" data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                                @endif


                            <div class="hs-unfold mr-2">
                                <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                   href="javascript:"
                                   data-hs-unfold-options='{
                                                        "target": "#usersExportDropdown",
                                                        "type": "css-animation"
                                                    }'>
                                    <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                                </a>

                                <div id="usersExportDropdown"
                                     class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                    <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                    <a id="export-excel" class="dropdown-item"
                                       href="{{route('admin.users.contact.exportList', ['type'=>'excel',request()->getQueryString()])}}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                             src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                             alt="Image Description">
                                        Excel
                                    </a>
                                    <a id="export-csv" class="dropdown-item"
                                       href="{{route('admin.users.contact.exportList', ['type'=>'csv',request()->getQueryString()])}}">
                                        <img class="avatar avatar-xss avatar-4by3 mr-2"
                                             src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}"
                                             alt="Image Description">
                                        CSV
                                    </a>
                                </div>
                            </div>


                        </div>


                    </div>
                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                               class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                               data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true,
                                 "paging":false,
                                 "columnDefs":[{"targets":[-1],"orderable":false}]
                               }'>
                            <thead class="thead-light">
                            <tr class="text-center">
                                <th class="border-0">{{translate('messages.SL')}}</th>
                                <th class="border-0">{{translate('Name')}}</th>
                                <th class="border-0">{{translate('messages.email')}}</th>
                                <th class="border-0">{{translate('messages.Subject')}}</th>
                                <th class="border-0">{{translate('messages.Seen/Unseen')}}</th>
                                <th class="border-0">{{translate('messages.Action')}}</th>
                            </tr>

                            </thead>

                            <tbody id="set-rows">
                            @foreach($contacts as $key=>$contact)
                                <tr>
                                    <td class="text-center">
                                        <span class="mr-3">
                                            {{$key+1}}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="font-size-sm text-body mr-3">
                                            {{Str::limit($contact['name'],20,'...')}}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="font-size-sm text-body mr-3">
                                            {{$contact['email']}}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="font-size-sm text-body mr-3 white--space-initial max-w-180px mx-auto">
                                            {{Str::limit($contact['subject'],40,'...')}}
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="font-size-sm text-body mr-3">
                                            @if($contact->seen==1)
                                            <label class="badge badge-soft-success mb-0">{{translate('messages.Seen')}}</label>
                                        @else
                                            <label class="badge badge-soft-info mb-0">{{translate('messages.Not Seen Yet')}}</label>
                                        @endif
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--view" href="{{route('admin.users.contact.contact-view',[$contact['id']])}}" title="{{translate('Edit')}}"><i class="tio-visible-outlined"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="contact-{{$contact['id']}}" data-message="{{ translate('messages.Want to delete this message?') }}" title="{{translate('messages.Delete')}}"><i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{route('admin.users.contact.contact-delete',[$contact['id']])}}"
                                                    method="post" id="contact-{{$contact['id']}}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($contacts) !== 0)
                    <hr>
                    @endif
                    <div class="page-area">
                        {!! $contacts->links() !!}
                    </div>
                    @if(count($contacts) === 0)
                    <div class="empty--data">
                        <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                        <h5>
                            {{translate('No data found')}}
                        </h5>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/contact-index.js"></script>
@endpush
