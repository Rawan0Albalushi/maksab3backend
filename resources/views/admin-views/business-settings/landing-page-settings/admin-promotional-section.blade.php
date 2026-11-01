@extends('layouts.admin.app')

@section('title',translate('Admin landing page'))

@section('content')
<div class="content container-fluid">
    <div class="page-header pb-0">
        <div class="d-flex flex-wrap justify-content-between">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/outline/landing.svg')}}" class="w--26" alt="">
                    </span>
                    <span>
                        {{ translate('messages.Admin landing pages') }}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('The promotional blocks on the admin landing page, and the order they appear in.') }}</p>
            </div>
            <div class="text--primary-2 py-1 d-flex flex-wrap align-items-center" type="button" data-toggle="modal" data-target="#how-it-works">
                <strong class="mr-2">{{translate('See how it works')}}</strong>
                <div>
                    <i class="tio-info-outined"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="mb-20 mt-2">
        <div class="js-nav-scroller hs-nav-scroller-horizontal">
            @include('admin-views.business-settings.landing-page-settings.top-menu-links.admin-landing-page-links')
        </div>
    </div>

    @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
    @if($language)
        <ul class="nav nav-tabs mb-4 border-0">
            <li class="nav-item">
                <a class="nav-link lang_link active"
                href="#"
                id="default-link">{{translate('Default')}}</a>
            </li>
            @foreach (json_decode($language) as $lang)
                <li class="nav-item">
                    <a class="nav-link lang_link"
                        href="#"
                        id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                </li>
            @endforeach
        </ul>
    @endif
    <div class="tab-content">
        <div class="tab-pane fade show active">
            <form action="{{ route('admin.business-settings.admin-landing-page-settings-update', 'promotional-section') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card mb-3">
                    <div class="card-body">
                        @if ($language)
                            <div class="row g-3 lang_form" id="default-form">
                                <div class="col-sm-6">
                                    <label for="title" class="form-label">{{translate('Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 20">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span>
                                        <span class="form-label-secondary text-danger"
                                              data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span></label>
                                        <input id="title" required type="text"  maxlength="20" name="title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                </div>
                                <div class="col-sm-6">
                                    <label for="sub_title" class="form-label">{{translate('Sub Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span>
                                        <span class="form-label-secondary text-danger"
                                              data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span></label>
                                        <input id="sub_title" required type="text"  maxlength="80" name="sub_title[]" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                                @foreach(json_decode($language) as $lang)
                                    <div class="row g-3 d-none lang_form" id="{{$lang}}-form">
                                        <div class="col-sm-6">
                                            <label for="title{{$lang}}" class="form-label">{{translate('Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 20">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <input id="title{{$lang}}" type="text"  maxlength="20" name="title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="sub_title{{$lang}}" class="form-label">{{translate('Sub Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <input type="text"  id="sub_title{{$lang}}" maxlength="80" name="sub_title[]" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                        </div>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label  for="title" class="form-label">{{translate('Title')}}</label>
                                        <input id="title" type="text" name="title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="sub_title" class="form-label">{{translate('Sub Title')}}</label>
                                        <input  id="sub_title" type="text" name="sub_title[]" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                            <button type="submit" class="btn btn--primary mb-2"><i class="tio-add-circle"></i> {{translate('Add')}}</button>
                        </div>
                    </div>
                </div>
            </form>
            @php($banners=\App\CentralLogics\Helpers::cached_list(\App\Models\AdminPromotionalBanner::class))
            <div class="card">
                <div class="card-header py-2">
                    <div class="search--button-wrapper">

                        @include('partials._table-head', [
                            'title'    => translate('Promotional List'),
                            'subtitle' => translate('messages.Promotional cards shown in the promotions section of your landing page.'),
                            'count'    => null,
                        ])
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                                class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                                data-hs-datatables-options='{
                                    "order": [],
                                    "orderCellsTop": true,
                                    "paging":false

                                }'>
                            <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{translate('SL')}}</th>
                                <th class="border-0">{{translate('Title')}}</th>
                                <th class="border-0">{{translate('Sub Title')}}</th>
                                <th class="border-0">{{translate('Status')}}</th>
                                <th class="text-center border-0">{{translate('messages.Action')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                                @foreach($banners as $key=>$banner)
                                <tr>
                                    <td>{{ $key+1 }}</td>
                                    <td>
                                        <div class="text--title">
                                        {{ $banner->title }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="d-block font-size-sm text-body">
                                            {{ $banner->sub_title }}
                                         </span>
                                    </td>
                                    <td>
                                        <label class="toggle-switch toggle-switch-sm">
                                            <input type="checkbox"
                                                   data-id="status-{{$banner->id}}"
                                                   data-type="status"
                                                   data-image-on="{{ asset('/public/assets/admin/img/modal/promotional-on.png') }}"
                                                   data-image-off="{{ asset('/public/assets/admin/img/modal/promotional-off.png') }}"
                                                   data-title-on="{{ translate('By Turning ON Promotional Banner Section') }}"
                                                   data-title-off="{{ translate('By Turning OFF Promotional Banner Section') }}"
                                                   data-text-on="<p>{{ translate('Promotional banner will be enabled. You can see promotional activity') }}</p>"
                                                   data-text-off="<p>{{ translate('Promotional banner will be disabled. You will be unable to see promotional activity.') }}</p>"
                                                   class="status toggle-switch-input dynamic-checkbox"
                                                   id="status-{{$banner->id}}" {{$banner->status?'checked':''}}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <form action="{{route('admin.business-settings.promotional-status',[$banner->id,$banner->status?0:1])}}" method="get" id="status-{{$banner->id}}_form">
                                        </form>
                                    </td>

                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit" href="{{route('admin.business-settings.promotional-edit',[$banner['id']])}}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                               data-id="banner-{{$banner['id']}}"
                                               data-message="{{ translate('Want to delete this banner?') }}"
                                               title="{{translate('messages.Delete banner')}}"><i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{route('admin.business-settings.promotional-delete',[$banner['id']])}}" method="post" id="banner-{{$banner['id']}}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>
                @if(count($banners) === 0)
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
    @include('admin-views.business-settings.landing-page-settings.partial.how-it-work')
@endsection
