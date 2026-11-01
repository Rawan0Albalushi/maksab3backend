@extends('layouts.vendor.app')

@section('title', request()?->type == 'pending' ?  translate('Advertisement pending list') : translate('Advertisement list'))
@section('advertisement')
active
@endsection

@if (request()?->type == 'pending')

@section('advertisement_pending_list')

@else
@section('advertisement_list')

@endif


active
@endsection

@push('css_or_js')

@endpush

@section('content')
@php($is_pending_list = request()?->type == 'pending')
@php($is_provider_context = in_array(\App\CentralLogics\Helpers::get_store_data()?->module?->module_type, ['rental', 'service'], true))
@php($ad_type_labels = [
    'store_promotion' => $is_provider_context ? translate('Provider promotion') : translate('messages.store_promotion'),
    'video_promotion' => translate('Video promotion'),
])
@php($status_labels = [
    'running' => translate('Running'),
    'approved' => translate('Approved'),
    'paused' => translate('messages.paused'),
    'denied' => translate('Denied'),
    'expired' => translate('Expired'),
    'pending' => translate('Pending'),
])
<div class="content container-fluid">



@if ($total_adds == 0)




<div class="page-header">
    <h1 class="page-header-title">
        <span class="page-header-icon"><img src="{{asset('public/assets/admin/img/outline/advertisement.svg')}}" alt=""></span>
        <span>{{ translate('Advertisement list') }}</span>
    </h1>
    <p class="page-header-desc">{{ translate('Every advertisement you have run, with the dates and state of each.') }}</p>
</div>

<div class="card">
    <div class="card-body">
        <div class="text-center max-w-700 mx-auto pt-5">
            <img src="{{asset('public/assets/admin/img/advertisement-list.png')}}" class="mw-100 mb-3" alt="">
            <h4 class="mb-2">{{ translate('Advertisement list') }}</h4>
            <p class="mb-4">{{ translate('Uh oh! You didn\'t created any advertisement yet') }}!</p>
            <div class="pb-4">
                <a href="{{ route('vendor.advertisement.create') }}" class="btn btn--primary"><i class="tio-add-circle"></i> {{ translate('Create advertisement') }}</a>
            </div>
            <hr>
            <div class="max-w-471 mx-auto fs-12 py-4">
                {{ translate('Create an advertisement to showcase your items or store to a wider audience through targeted ad campaigns.') }}
            </div>
        </div>
    </div>
</div>



@else



    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><img src="{{asset('public/assets/admin/img/outline/advertisement.svg')}}" alt=""></span>
                <span>{{ request()?->type == 'pending' ? translate('Advertisement pending list') : translate('Advertisement list') }}
                    <span class="badge badge-soft-dark ml-2">{{ $adds->total() }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Every advertisement you have run, with the dates and state of each.') }}</p>
        </div>
        <a href="{{ route('vendor.advertisement.create') }}" class="btn btn-primary">  <i class="tio-add"></i> {{ translate('New advertisement') }}</a>
    </div>


    <div class="card">

        <div class="card-header py-2 border-0">
            <div class="search--button-wrapper">
            @include('partials._table-head', [
                'subtitle' => translate('messages.Your paid promotions and where each one stands in review.'),
            ])
            <form >
                @if (request()?->type == 'pending')
                <input type="hidden" name="type" value="pending">
                @endif
                <div class="input--group input-group input-group-merge input-group-flush">

                    <input id="datatableSearch" type="search" name="search"  value="{{ request()?->search ?? null }}"  class="form-control" placeholder="{{ translate('Search by advertisement ID') }}" aria-label="{{translate('Search')}}">
                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                </div>
            </form>
            @if (request()?->type != 'pending')
            <div class="select-item min-250">
                <select name="subscription_list" class="form-control js-select2-custom set-filter"
                data-url="{{url()->full()}}" data-filter="ads_type">
                    <option  value="all">{{translate('All advertisements')}}</option>
                    <option {{ request()?->ads_type =='running'?'selected':''}} value="running">{{translate('Running')}} </option>
                    <option {{request()?->ads_type =='approved'?'selected':''}} value="approved">{{translate('Approved')}} </option>
                    <option {{request()?->ads_type =='expired'?'selected':''}} value="expired">{{translate('Expired')}} </option>
                    <option {{request()?->ads_type =='denied'?'selected':''}} value="denied">{{translate('Denied')}} </option>
                </select>
            </div>
            @endif
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive datatable-custom">
                <table class="font-size-sm table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table min-h-225px">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Advertisement') }}</th>
                            <th>{{ translate('Advertisement type') }}</th>
                            <th>{{ translate('Duration') }}</th>
                            <th>{{ translate('Submitted') }}</th>
                            @if (!$is_pending_list)
                                <th>{{ translate('Status') }}</th>
                            @endif
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($adds as $add)
                            @php($today = \Carbon\Carbon::now()->startOfDay())
                            @php($days_to_start = (int) $today->diffInDays(\Carbon\Carbon::parse($add->start_date)->startOfDay(), false))
                            @php($days_left = (int) $today->diffInDays(\Carbon\Carbon::parse($add->end_date)->startOfDay(), false))
                            @php($waiting_days = (int) \Carbon\Carbon::parse($add->created_at)->startOfDay()->diffInDays($today))
                            @php($state_note = $add->status == 'denied' ? $add->cancellation_note : ($add->status == 'paused' ? $add->pause_note : null))
                            <tr>
                                <td>
                                    <a class="table-rest-info" href="{{ route('vendor.advertisement.show', $add->id) }}" title="{{ $add->title }}">
                                        <img class="img--60 rounded onerror-image" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                             src="{{ $add->cover_image_full_url ?? $add->profile_image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $add->title }}">
                                        <div class="info max-w-200px">
                                            <div class="text--title line--limit-2">{{ Str::limit($add->title, 25, '...') }}</div>
                                            <div class="font-light">ID:{{ $add->id }}</div>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <span class="d-block text--title">{{ $ad_type_labels[$add->add_type] ?? $add->add_type }}</span>
                                    <span class="d-block fs-12 text-muted">{{ $add->is_paid ? translate('messages.paid') : translate('messages.unpaid') }}</span>
                                </td>
                                <td>
                                    <span class="d-block text--title">{{ \App\CentralLogics\Helpers::date_format($add->start_date) }} - {{ \App\CentralLogics\Helpers::date_format($add->end_date) }}</span>
                                    @if ($add->active == 2 && $days_to_start <= 90)
                                        <span class="d-block fs-12 text-muted">{{ $days_to_start > 1 ? translate('Starts') . ' ' . \Carbon\Carbon::parse($add->start_date)->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) : translate('Starts tomorrow') }}</span>
                                    @elseif ($add->active != 2 && abs($days_left) <= 90)
                                        <span class="d-block fs-12 text-muted">
                                            @if ($days_left > 1)
                                                {{ translate('Ends') }} {{ \Carbon\Carbon::parse($add->end_date)->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                            @elseif ($days_left === 1)
                                                {{ translate('Ends tomorrow') }}
                                            @elseif ($days_left === 0)
                                                {{ translate('Ends today') }}
                                            @elseif ($days_left === -1)
                                                {{ translate('Ended yesterday') }}
                                            @else
                                                {{ translate('Ended') }} {{ \Carbon\Carbon::parse($add->end_date)->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="table-when @if ($is_pending_list && $waiting_days > 3) table-when--stale @endif">
                                        <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($add->created_at) }}</span>
                                        <span class="table-when__ago" title="{{ \App\CentralLogics\Helpers::time_date_format($add->created_at) }}">{{ \Carbon\Carbon::parse($add->created_at)->diffForHumans() }}</span>
                                    </span>
                                </td>
                                @if (!$is_pending_list)
                                    <td>
                                        @if ($add->status == 'approved' && $add->active == 1)
                                            <label class="badge badge-soft-primary rounded-pill">{{ $status_labels['running'] }}</label>
                                        @elseif ($add->status == 'approved' && $add->active == 2)
                                            <label class="badge badge-soft-success rounded-pill">{{ $status_labels['approved'] }}</label>
                                        @elseif ($add->status == 'paused' && $add->active == 1)
                                            <label class="badge badge-soft-warning rounded-pill">{{ $status_labels['paused'] }}</label>
                                        @elseif (in_array($add->status, ['denied', 'expired']))
                                            <label class="badge badge-soft-danger rounded-pill">{{ $status_labels[$add->status] ?? $add->status }}</label>
                                        @elseif ($add->active == 0)
                                            <label class="badge badge-soft-secondary rounded-pill">{{ $status_labels['expired'] }}</label>
                                        @else
                                            <label class="badge badge-soft-info rounded-pill">{{ $status_labels[$add->status] ?? $add->status }}</label>
                                        @endif
                                        @if ($state_note)
                                            <span class="fs-12 text-muted line--limit-2 max-w-200px mt-1" title="{{ $state_note }}">{{ $state_note }}</span>
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
                                            <ul class="dropdown-menu" dir="ltr">
                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('vendor.advertisement.show',$add->id) }}">
                                                    <i class="tio-visible-outlined"></i>
                                                    {{ translate('View advertisement') }}
                                                </a>

                                                @if ($add->active == 0 || in_array($add->status ,['pending']))
                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('vendor.advertisement.edit',$add->id) }}">
                                                    <i class="tio-edit"></i>
                                                    {{ translate('Edit & resubmit advertisement') }}
                                                    </a>

                                                    @else
                                                    <a class="dropdown-item d-flex gap-2 align-items-center new-dynamic-submit-model" href="#"

                                                        id="data-edit-{{ $add->id }}"
                                                        data-id="data-edit-{{ $add->id }}"

                                                        data-title="{{translate('Do you want to edit?')}}"
                                                        data-text="<p>{{translate('Editing a running ad sends it back for admin approval before it resumes.')}}</p>"
                                                        data-image="{{asset('public/assets/admin/img/modal/package-status-disable.png')}}"
                                                        data-type="resume"
                                                        data-btn_class = "btn-primary"
                                                        data-success_btn_text = "{{ translate('Yes, edit') }}"

                                                        >
                                                        <i class="tio-edit"></i>
                                                        {{ translate('Edit advertisement') }}
                                                    </a>
                                                    <form  id="data-edit-{{ $add->id }}_form" action="{{ route('vendor.advertisement.edit',$add->id) }}" method="get">
                                                    </form>
                                                @endif

                                                @if($add->status == 'paused')
                                                    <a class="dropdown-item d-flex gap-2 align-items-center new-dynamic-submit-model"

                                                    id="data-add-{{ $add->id }}"
                                                    data-id="data-add-{{ $add->id }}"

                                                    data-title="{{translate('Are you sure you want to resume the request?')}}"
                                                    data-text="<p>{{translate('This ad will be run again and will show in the user app & websites.')}}</p>"
                                                    data-image="{{asset('public/assets/admin/img/modal/resume.png')}}"
                                                    data-type="resume"
                                                    data-btn_class = "btn-primary"

                                                    href="#">
                                                        <i class="tio-pause-circle"></i>
                                                        {{ translate('Resume advertisement') }}
                                                    </a>

                                                    <form  id="data-add-{{ $add->id }}_form" action="{{ route('vendor.advertisement.status',['status' => 'approved' ,'id' => $add->id]) }}" method="get">
                                                        @csrf
                                                        @method('get')
                                                        <input type="hidden"  name="status" value="approved">
                                                        <input type="hidden"  name="id" value="{{ $add->id }}">
                                                    </form>

                                                @elseif($add->status == 'approved' && $add->active == 1)
                                                <a class="dropdown-item d-flex gap-2 align-items-center new-dynamic-submit-model"
                                                id="data-add-{{ $add->id }}"
                                                data-id="data-add-{{ $add->id }}"
                                                data-title="{{translate('Are you sure you want to pause the request?')}}"
                                                data-text="<p>{{translate('This ad will be pause and not show in the user app & websites.')}}</p>"
                                                data-image="{{asset('public/assets/admin/img/modal/pause.png')}}"
                                                data-type="pause"

                                                href="#">
                                                    <i class="tio-pause-circle"></i>
                                                    {{ translate('Pause advertisement') }}
                                                    </a>

                                                    <form  id="data-add-{{ $add->id }}_form" action="{{ route('vendor.advertisement.status',['status' => 'paused' ,'id' => $add->id]) }}" method="get">
                                                        @csrf
                                                        @method('get')
                                                        <input type="hidden"  name="pause_note" id="data-add-{{ $add?->id }}_note">
                                                        <input type="hidden"  name="status" value="paused">
                                                        <input type="hidden"  name="id" value="{{ $add->id }}">
                                                    </form>
                                                    @endif

                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('vendor.advertisement.copyAdd', $add->id) }}" >
                                                    <i class="tio-copy"></i>
                                                    {{ translate('Copy advertisement') }}
                                                    </a>

                                                <a class="dropdown-item d-flex gap-2 align-items-center new-dynamic-submit-model"
                                                id="delete-add-{{ $add->id }}"
                                                    data-id="delete-add-{{ $add->id }}"
                                                    @if ($add->status == 'approved' && $add->active == 1)
                                                        data-title="{{translate('You can\'t delete the ad')}}"
                                                        data-text="<p>{{translate('This ad is running. Change its status first, then you can delete it.')}}</p>"
                                                        data-image="{{asset('public/assets/admin/img/modal/package-status-disable.png')}}"
                                                        data-type="warning"
                                                    @else
                                                        data-type="delete"
                                                        data-title="{{translate('Confirm advertisement deletion')}}"
                                                        data-text="<p>{{translate('Deleting this ad will remove it permanently. Are you sure you want to proceed?')}}</p>"
                                                        data-image="{{asset('public/assets/admin/img/modal/delete-icon.png')}}"
                                                    @endif
                                                    >
                                                    <i class="tio-delete"></i>
                                                    {{ translate('Delete advertisement') }}
                                                    </a>
                                                    <form  id="delete-add-{{ $add->id }}_form" action="{{ route('vendor.advertisement.destroy',$add->id) }}" method="post">
                                                        @csrf
                                                        @method('delete')
                                                    </form>

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
                        {!! $adds->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="created-sucessful-modal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <i class="tio-clear fs-24"></i>
                </button>
            </div>
            <div class="modal-body pt-0">
                <div class="text-center max-w-700 mx-auto">
                    <img src="{{asset('public/assets/admin/img/created.png')}}" class="mw-100 mb-4" alt="">
                    <h4 class="mb-2">{{ translate('Added successfully') }}</h4>
                    <p class="mb-4 fs-12 mx-auto max-w-520">{{ translate('Congratulations on creating your ad! It\'s now awaiting approval. To finalize the process & make payment arrangements, please contact our')}} <a class="text--underline" href="mailto:{{ $admin_email_address }}">{{ translate('Admin directly.') }}</a>
                    {{   translate('We look forward to helping you boost your visibility & reach more customers') }}</p>
                    <div class="pb-4">
                        <a href="#" data-dismiss="modal"  class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Okay') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif



@endsection

@push('script_2')
<script>
    @if (request()?->has('new_ad'))
    $('#created-sucessful-modal').modal('show')
        var url = new URL(window.location.href);
        var searchParams = new URLSearchParams(url.search);
        searchParams.delete('new_ad');
        var newUrl = url.origin + url.pathname + '?' + searchParams.toString();
        if (!searchParams.toString()) {
            newUrl = url.origin + url.pathname;
        }
        window.history.replaceState(null, '', newUrl);
    @endif

</script>
@endpush
