@extends('layouts.admin.app')

@section('title', translate('Advertisement list'))
@section('advertisement')
active
@endsection
@section('advertisement_list')
active
@endsection

@push('css_or_js')

@endpush

@section('content')
@php($isProviderContext = in_array(config('module.current_module_type'), ['rental', 'service'], true))
@php($ad_type_labels = [
    'store_promotion' => $isProviderContext ? translate('Provider promotion') : translate('messages.store_promotion'),
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
<div class="content container-fluid overflow-hidden">



    @if ($ads_count == 0)




    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon"><img src="{{asset('public/assets/admin/img/outline/advertisement.svg')}}" alt=""></span>
            <span>{{ translate('Advertisement list') }}</span>
        </h1>
        <p class="page-header-desc">{{ translate('Every advertisement running across your modules, with the state each one is in.') }}</p>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="text-center max-w-700 mb-10 mt-10 mx-auto pt-5">
                <img src="{{asset('public/assets/admin/img/advertisement-list.png')}}" class="mw-100 mb-3" alt="">
                <h4 class="mb-2">{{ translate('Advertisement list') }}</h4>
                <p class="mb-4">{{ translate('Create an advertisement for your targeted audience, as none has been created yet.') }}</p>
            </div>
        </div>
    </div>



    @else




    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><img src="{{asset('public/assets/admin/img/outline/advertisement.svg')}}" alt=""></span>
                <span>{{ translate('Advertisement list') }}
                    <span class="badge badge-soft-dark ml-2">{{ $adds->total() }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Every advertisement running across your modules, with the state each one is in.') }}</p>
        </div>
        <a href="{{ route('admin.advertisement.create') }}" class="btn btn-primary">  <i class="tio-add"></i> {{ translate('New advertisement') }}</a>
    </div>


    <div class="card">

        <div class="card-header py-2 border-0">
            <div class="search--button-wrapper">
            @include('partials._table-head', [
                'subtitle' => translate('messages.Paid store promotions running across the customer apps.'),
            ])
            <form >
                <div class="input--group input-group input-group-merge input-group-flush">
                    <input id="datatableSearch" type="search" name="search"  value="{{ request()?->search ?? null }}"  class="form-control" placeholder="{{ $isProviderContext ? str_replace('store', 'provider', translate('Search by Advertisement ID or store name')) : translate('Search by Advertisement ID or store name') }}" aria-label="{{translate('Search')}}">
                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                </div>
            </form>
            <div class="select-item min-w-135px">
                <select name="subscription_list" class="form-control js-select2-custom set-filter"
                data-url="{{url()->full()}}" data-filter="ads_type">
                    <option  value="all">{{translate('All advertisements')}}</option>
                    <option {{ request()?->ads_type =='running'?'selected':''}} value="running">{{translate('Running')}} </option>
                    <option {{request()?->ads_type =='paused'?'selected':''}} value="paused">{{translate('paused')}} </option>
                    <option {{request()?->ads_type =='approved'?'selected':''}} value="approved">{{translate('Approved')}} </option>
                    <option {{request()?->ads_type =='expired'?'selected':''}} value="expired">{{translate('Expired')}} </option>
                </select>
            </div>
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
                            <th>{{translate('Status')}}</th>
                            <th>{{translate('Priority')}}</th>
                            <th class="text-center">{{translate('Action')}}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($adds as $add)
                            @php($ends = $add->end_date ? \Carbon\Carbon::parse($add->end_date) : null)
                            @php($days_left = $ends ? (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($ends->copy()->startOfDay(), false) : null)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.advertisement.show',$add->id) }}" class="table-rest-info" title="{{ $add->title }}">
                                        <img class="img--60 rounded onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}"
                                             src="{{ $add->cover_image_full_url ?? $add->profile_image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="{{ $add->title }}">
                                        <div class="info max-w-200px">
                                            <div class="text--title line--limit-2">{{ Str::limit($add->title, 25, '...') }}</div>
                                            <div class="font-light">ID:{{ $add->id }}</div>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    {{-- `max-w-250` + `cell--truncate`: the table is `table-nowrap`, so a long
                                         store email cannot wrap -- it used to run straight out of this cell and
                                         under the Advertisement type column beside it. Same pairing every other
                                         media cell in the panel uses; the `title`s keep the full value reachable. --}}
                                    <a class="media align-items-center text-body max-w-250" href="{{route('admin.store.view', $add?->store_id)}}">
                                        <img class="avatar avatar-lg mr-3" src="{{ $add->store['logo_full_url'] ?? asset('public/assets/admin/img/100x100/food-default-image.png') }}" alt="">
                                        <div class="media-body cell--truncate">
                                            <h5 class="mb-0" title="{{ $add?->store?->name }}">{{ $add?->store?->name }}</h5>
                                            <small class="text-body d-block" title="{{ $add?->store?->email }}">{{ $add?->store?->email }}</small>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <span class="d-block text-title">{{ $ad_type_labels[$add?->add_type] ?? $add?->add_type }}</span>
                                    <span class="d-block fs-12 text-muted">{{ $add->is_paid ? translate('messages.paid') : translate('messages.unpaid') }}</span>
                                </td>
                                <td data-order="{{ $add->end_date }}">
                                    <span class="d-block text-title">{{ \App\CentralLogics\Helpers::date_format($add->start_date) }} - {{ \App\CentralLogics\Helpers::date_format($add->end_date) }}</span>
                                    @if(! is_null($days_left) && abs($days_left) <= 90)
                                        <span class="d-block fs-12 text-muted">
                                            @if($days_left > 1)
                                                {{ translate('Ends') }} {{ $ends->copy()->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                            @elseif($days_left === 1)
                                                {{ translate('Ends tomorrow') }}
                                            @elseif($days_left === 0)
                                                {{ translate('Ends today') }}
                                            @elseif($days_left === -1)
                                                {{ translate('Ended yesterday') }}
                                            @else
                                                {{ translate('Ended') }} {{ $ends->copy()->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($add->status == 'approved' && $add->active == 1 )
                                    <label class="badge badge-soft-primary rounded-pill">{{ $status_labels['running'] }}</label>
                                    @elseif ($add->status == 'approved' && $add->active == 2 )
                                    <label class="badge badge-soft-success rounded-pill">{{ $status_labels['approved'] }}</label>
                                    @elseif ($add->status == 'paused' && $add->active == 1 )
                                    <label class="badge badge-soft-warning rounded-pill">{{ $status_labels['paused'] }}</label>
                                    @elseif (in_array($add->status ,['denied','expired'] ))
                                    <label class="badge badge-soft-danger rounded-pill">{{ $status_labels[$add->status] ?? $add->status }}</label>
                                    @elseif ($add->active == 0)
                                    <label class="badge badge-soft-secondary rounded-pill">{{ $status_labels['expired'] }}</label>
                                    @else
                                    <label class="badge badge-soft-info rounded-pill">{{ $status_labels[$add->status] ?? $add->status }}</label>
                                    @endif
                                </td>
                                <td>
                                    @if ( in_array($add->status ,['denied','expired']) || $add->active == 0)
                                    <div class="d-flex align-items-center gap-2" data-toggle="tooltip" title="{{ translate('Expired & denied ads has no priority.') }}">
                                        <span>{{  translate('N/A') }}</span> <img src="{{asset('public/assets/admin/img/na.png')}}" alt="">
                                    </div>
                                    @else
                                    <select id="select_option_{{ $add->id }}" data-priority_old_value="{{ $add?->priority }}" data-prority_id="{{ $add->id }}" class="form-control w-70px p-0 h-30px js-select2-custom update-priority">
                                        <option value="{{ $add?->priority == null ||  $add?->priority == 0 ?  '' : $add?->priority }}">{{ $add?->priority == null ||  $add?->priority == 0 ?  translate('N/A') : $add?->priority }} </option>
                                        @for ($i = 1; $i <= $total_adds; $i++)
                                        @if ($add?->priority != $i )
                                        <option value="{{ $i }}">{{ $i }}</option>
                                        @endif
                                        @endfor
                                        @if ( $add?->priority !== null)
                                            <option value="">{{  translate('N/A') }} </option>
                                        @endif
                                    </select>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <div class="dropdown dropdown-2">
                                            <button type="button" class="btn action-btn action-btn--menu" data-toggle="dropdown" aria-expanded="false">
                                                <i class="tio-more-vertical" data-toggle="tooltip" data-placement="bottom"
                                                    data-original-title="{{ translate('messages.menu') }}"></i>
                                            </button>
                                            <ul class="dropdown-menu" dir="ltr">
                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('admin.advertisement.show',$add->id) }}">
                                                    <i class="tio-visible-outlined"></i>
                                                    {{ translate('View advertisement') }}
                                                </a>

                                                @if ($add->active == 0)
                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('admin.advertisement.edit',$add->id) }}">
                                                    <i class="tio-edit"></i>
                                                    {{ translate('Edit & resubmit advertisement') }}
                                                </a>
                                                @else
                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('admin.advertisement.edit',$add->id) }}">
                                                    <i class="tio-edit"></i>
                                                    {{ translate('Edit advertisement') }}
                                                </a>
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

                                                    <form  id="data-add-{{ $add->id }}_form" action="{{ route('admin.advertisement.status',['status' => 'approved' ,'id' => $add->id]) }}" method="get">
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

                                                    <form  id="data-add-{{ $add->id }}_form" action="{{ route('admin.advertisement.status',['status' => 'paused' ,'id' => $add->id]) }}" method="get">
                                                        @csrf
                                                        @method('get')
                                                        <input type="hidden"  name="pause_note" id="data-add-{{ $add?->id }}_note">
                                                        <input type="hidden"  name="status" value="paused">
                                                        <input type="hidden"  name="id" value="{{ $add->id }}">
                                                    </form>
                                                @endif

                                                <a class="dropdown-item d-flex gap-2 align-items-center" href="{{ route('admin.advertisement.copyAdd', $add->id) }}" >
                                                    <i class="tio-copy"></i>
                                                    {{ translate('Copy advertisement') }}
                                                </a>

                                                <a class="dropdown-item d-flex gap-2 align-items-center new-dynamic-submit-model"
                                                id="delete-add-{{ $add->id }}"
                                                    data-id="delete-add-{{ $add->id }}"
                                                    @if ($add->status != 'paused' && $add->active == 1)
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
                                                <form  id="delete-add-{{ $add->id }}_form" action="{{ route('admin.advertisement.destroy',$add->id) }}" method="post">
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


@endif


</div>


<div class="modal fade" id="priority-update-modal">
    <div class="modal-dialog modal-dialog-centered status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <form action="{{ route('admin.advertisement.priority') }}" method="get">
                <div class="max-349 mx-auto mb-20">
                    <div>
                        <div class="text-center">
                            <img src="{{asset('public/assets/admin/img/modal/package-status-disable.png')}}" class="mb-20">
                            <h5 class="modal-title" id="toggle-title"></h5>
                        </div>
                        <div class="text-center" id="toggle-message">
                            <h3 >{{ translate('Are you sure you want to change the priority of this advertisement?') }}</h3>
                        </div>
                        <input id="update_priority_value"   name="priority_value" type="hidden">
                        <input id="update_priority_id" name="priority_id" type="hidden">
                        <input id="update_priority_old_value"  type="hidden">
                        </div>

                    <div class="btn--container justify-content-center mt-3">
                        <button data-dismiss="modal" type="reset" id="reset_btn" class="btn btn--cancel" ><i class="tio-time"></i> {{translate('Not now')}}</button>
                        <button type="sbmit" class="btn btn-primary min-w-120"><i class="tio-checkmark-circle-outlined"></i> {{translate('Yes')}}</button>
                    </div>
                </div>
            </form>
            </div>
        </div>
    </div>
</div>





@endsection

@push('script_2')
<script>
    $(document).ready(function() {


    $(document).on('change', '.update-priority', function() {


        let update_priority_value = $(this).val();
        let update_priority_old_value = $(this).data('priority_old_value');
        let update_priority_id = $(this).data('prority_id');

        $('#update_priority_value').val(update_priority_value)
        $('#update_priority_old_value').val(update_priority_old_value)
        $('#update_priority_id').val(update_priority_id)
        $('#priority-update-modal').modal('show')
    });
    $('#reset_btn').on('click', function() {

        $('#update_priority_id').val()

        $('#select_option_'+$('#update_priority_id').val()).val( $('#update_priority_old_value').val()
        ).trigger('change');

    });




});

</script>
@endpush
