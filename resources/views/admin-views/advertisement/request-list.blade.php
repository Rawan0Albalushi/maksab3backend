@extends('layouts.admin.app')

@section('title',translate('Advertisement requests'))
@section('advertisement')
active
@endsection
@section('advertisement_request')
active
@endsection

@push('css_or_js')

@endpush

@section('content')
@php($isProviderContext = in_array(config('module.current_module_type'), ['rental', 'service'], true))
@php($is_denied_tab = request()?->type === 'denied-requests')
@php($ad_type_labels = [
    'store_promotion' => $isProviderContext ? translate('Provider promotion') : translate('messages.store_promotion'),
    'video_promotion' => translate('Video promotion'),
])
@php($lifecycle_labels = [
    1 => translate('messages.Running'),
    2 => translate('messages.upcoming'),
    0 => translate('messages.Expired'),
])
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon"><img src="{{asset('public/assets/admin/img/outline/advertisement.svg')}}" alt=""></span>
            <span>{{ translate('Advertisement requests') }}
                <span class="badge badge-soft-dark ml-2">{{ $count }}</span>
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('Advertisements stores have submitted and are waiting for you to approve or turn down.') }}</p>
    </div>

    <ul class="nav nav-tabs border-0 nav--tabs nav--pills mb-4">
        <li class="nav-item">
            <a class="nav-link  {{ !request()?->type  ? 'active' : '' }}" href="{{ route('admin.advertisement.requestList') }}">{{ translate('New request') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()?->type == 'update-requests' ? 'active' : '' }} " href="{{ route('admin.advertisement.requestList',['type'=> 'update-requests']) }}">{{ translate('Update request') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()?->type == 'denied-requests' ? 'active' : '' }} " href="{{ route('admin.advertisement.requestList',['type'=> 'denied-requests']) }}">{{ translate('Denied requests') }}</a>
        </li>
    </ul>



    <div class="card">


        <div class="card-header py-2 border-0">
            <div class="search--button-wrapper">
                @include('partials._table-head', [
                    'subtitle' => translate('messages.Advertisement requests from stores waiting on your review.'),
                ])
                <form>
                    <div class="input--group input-group input-group-merge input-group-flush">
                        <input id="datatableSearch" type="search" name="search" value="{{ request()?->search ?? null }}" class="form-control" placeholder="{{ $isProviderContext ? str_replace('store', 'provider', translate('Search by Advertisement ID or store name')) : translate('Search by Advertisement ID or store name') }}" aria-label="{{translate('Search')}}">
                        <input type="hidden" value="{{ request()?->type }}" name='type'>
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>

            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table class="font-size-sm table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table min-h-225px">
                    <thead class="thead-light">
                        <tr>
                            <th>{{translate('Advertisement')}}</th>
                            <th>{{ $isProviderContext ? translate('Provider information') : translate('Store information') }}</th>
                            <th>{{translate('Advertisement type')}}</th>
                            <th>{{translate('Duration')}}</th>
                            <th>{{translate('messages.Submitted')}}</th>
                            @if($is_denied_tab)
                                <th>{{translate('Denied reason')}}</th>
                            @endif
                            <th class="text-center">{{translate('Action')}}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($adds as $add)
                            @php($waiting_days = $add->created_at ? $add->created_at->diffInDays(now()) : 0)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.advertisement.show',[$add->id ,'request_page_type'=> request()?->type ?? 'pending-requests']) }}" class="table-rest-info" title="{{ $add->title }}">
                                        <img class="img--60 rounded onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}"
                                             src="{{ $add->cover_image_full_url ?? $add->profile_image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $add->title }}">
                                        <div class="info max-w-200px">
                                            <div class="text--title line--limit-2">{{ Str::limit($add->title, 25, '...') }}</div>
                                            <div class="font-light">ID:{{ $add->id }}</div>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <a class="media align-items-center text-body" href="{{route('admin.store.view', $add?->store_id)}}">
                                        <img class="avatar avatar-lg mr-3" src="{{ $add->store['logo_full_url'] ?? asset('public/assets/admin/img/100x100/food-default-image.png') }}" alt="">
                                        <div class="media-body">
                                            <h5 class="mb-0">{{ $add?->store?->name }}</h5>
                                            <small class="text-body">{{ $add?->store?->email }}</small>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <span class="d-block text-title">{{ $ad_type_labels[$add?->add_type] ?? $add?->add_type }}</span>
                                    <span class="d-block fs-12 text-muted">{{ $add->is_paid ? translate('messages.paid') : translate('messages.unpaid') }}</span>
                                </td>
                                <td>
                                    <span class="d-block text-title">{{ \App\CentralLogics\Helpers::date_format($add->start_date) }} - {{ \App\CentralLogics\Helpers::date_format($add->end_date) }}</span>
                                    <span class="cell-chips d-block mt-1">
                                        <span class="cell-chip">{{ $lifecycle_labels[$add->active] ?? '' }}</span>
                                    </span>
                                </td>
                                <td data-order="{{ $add->created_at }}">
                                    <span class="table-when{{ $waiting_days >= 3 ? ' table-when--stale' : '' }}">
                                        <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($add->created_at) }}</span>
                                        <span class="table-when__ago" title="{{ \App\CentralLogics\Helpers::time_date_format($add->created_at) }}">
                                            {{ $add->created_at?->diffForHumans() }}
                                        </span>
                                    </span>
                                </td>
                                @if($is_denied_tab)
                                    <td>
                                        @if($add->cancellation_note)
                                            <span class="d-block text-body max-w-200px" title="{{ $add->cancellation_note }}">
                                                {{ Str::limit($add->cancellation_note, 40, '...') }}
                                            </span>
                                        @else
                                            <span class="text-muted font-size-sm">{{ translate('No reason recorded') }}</span>
                                        @endif
                                    </td>
                                @endif

                                <td>
                                    <div class="btn--container justify-content-center">
                                        <div class="dropdown dropdown-2">
                                            <button type="button" class="btn action-btn action-btn--menu" data-toggle="dropdown" aria-expanded="false">
                                                <i class="tio-more-vertical" data-toggle="tooltip" data-placement="bottom"
                                                    data-original-title="{{ translate('messages.menu') }}"></i>
                                            </button>
                                            <ul class="dropdown-menu "dir="ltr">
                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('admin.advertisement.show',[$add->id ,'request_page_type'=> request()?->type ?? 'pending-requests']) }}">
                                                    <i class="tio-visible-outlined"></i>
                                                    {{ translate('View advertisement') }}
                                                </a>

                                                @if ($add->status == 'denied' || $add->active == 0)
                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('admin.advertisement.edit',[$add->id ,'request_page_type'=> request()?->type ?? 'pending-requests']) }}">
                                                    <i class="tio-edit"></i>
                                                    {{ translate('Edit & resubmit advertisement') }}
                                                </a>
                                                @else
                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('admin.advertisement.edit',[$add->id ,'request_page_type'=> request()?->type ?? 'pending-requests']) }}">
                                                    <i class="tio-edit"></i>
                                                    {{ translate('Edit advertisement') }}
                                                </a>
                                                @endif

                                                @if ($add->status == 'pending')
                                                <a class="dropdown-item d-flex gap-2 align-items-center approve_add"
                                                    data-is_expired="{{ $add->active }}"
                                                    data-approve_url={{   route('admin.advertisement.status',['status' => 'approved' ,'id' => $add->id ,'approved' => 1]) }}
                                                    data-edit_url={{  route('admin.advertisement.edit',[$add->id ,'request_page_type'=> isset($request_page_type) ]) }}
                                                    href="#">
                                                    <i class="tio-done"></i>
                                                    {{ translate('Approve') }}
                                                </a>

                                                <a class="dropdown-item d-flex gap-2 align-items-center new-dynamic-submit-model" id="data-add-{{ $add->id }}" data-id="data-add-{{ $add->id }}" data-title="{{translate('Are you sure you want to deny the request?')}}" data-text="<p>{{ $isProviderContext ? str_replace('Store', 'Provider', translate('You will lose the Store ads request.')) : translate('You will lose the Store ads request.') }}</p>" data-image="{{asset('public/assets/admin/img/modal/deny.png')}}" data-type="deny" data-btn_class="btn-primary" data-2nd_btn_text="{{ translate('messages.Cancel') }}" href="#">
                                                    <i class="tio-clear-circle-outlined"></i>
                                                    {{ translate('Cancel advertisement') }}
                                                </a>

                                                <form id="data-add-{{ $add->id }}_form" action="{{ route('admin.advertisement.status',['status' => 'paused' ,'id' => $add->id]) }}" method="get">
                                                    @csrf
                                                    @method('get')
                                                    <input type="hidden" name="cancellation_note" id="data-add-{{ $add?->id }}_note">
                                                    <input type="hidden" name="status" value="denied">
                                                    <input type="hidden" name="id" value="{{ $add->id }}">
                                                </form>
                                                @endif

                                                @if ($add->status != 'pending')
                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('admin.advertisement.destroy',$add->id) }}">
                                                    <i class="tio-delete"></i>
                                                    {{ translate('Delete advertisement') }}
                                                </a>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if(count($adds) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
            <div class="page-area px-4 pb-3">
                <div class="d-flex align-items-center justify-content-end">
                    <div>
                        {!! $adds->withQueryString()->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<div class="modal fade" id="approve-model1">
    <div class="modal-dialog modal-dialog-centered status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto mb-20">
                    <div>
                        <div class="text-center">
                            <img src="{{  asset('public/assets/admin/img/modal/timeout.png') }}" class="mb-20">
                            <h5 class="modal-title"></h5>
                        </div>
                        <div class="text-center" >
                            <h3 > {{ translate('This advertisement is already expired.') }}</h3>
                            <div > <p>{{ translate('After approval this advertisement will automatically show in the expired list as the duration is already over.') }}</h3></p></div>
                        </div>

                        </div>

                    <div class="btn--container justify-content-center">
                            <a href="#" id="edit_url1"  class="btn btn-success min-w-120" ><i class="tio-edit"></i> {{translate('Edit & approve')}}</a>
                            <a href="#" id="approve_url1"  type="button"  class="btn btn--secondary  min-w-120"><i class="tio-checkmark-circle-outlined"></i> {{translate('Only approve')}}</a>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>





<div class="modal fade" id="confirm-approve-model">
    <div class="modal-dialog modal-dialog-centered status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto mb-20">
                    <div>
                        <div class="text-center">
                            <img width="80" src="{{  asset('public/assets/admin/img/modal/tick.png') }}" class="mb-20">
                            <h5 class="modal-title"></h5>
                        </div>
                        <div class="text-center" >
                            <h3 > {{ translate('Are you sure?') }}</h3>
                            <div > <p>{{ translate('After approval this advertisement will show in the user app & websites.') }}</h3></p></div>
                        </div>

                        </div>

                    <div class="btn--container justify-content-center">
                        <button data-dismiss="modal" class="btn btn--secondary min-w-120" ><i class="tio-time"></i> {{translate('Not now')}}</button>
                        <a href="#" id="approve_url" type="button"  class="btn btn-primary min-w-120"><i class="tio-checkmark-circle-outlined"></i> {{translate('Approve')}}</a>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection

@push('script_2')


<script>
    $(document).on("click", ".approve_add", function () {
    const edit_url = $(this).data("edit_url");
    const approve_url = $(this).data("approve_url");
    const is_expired = $(this).data("is_expired");


    if(is_expired !== 0){
        $("#approve_url").attr("href", approve_url);
        $("#confirm-approve-model").modal('show');
    }
    else{
        $("#approve_url1").attr("href", approve_url);
        $("#edit_url1").attr("href", edit_url);
        $("#approve-model1").modal('show');

    }




});
</script>


@endpush
