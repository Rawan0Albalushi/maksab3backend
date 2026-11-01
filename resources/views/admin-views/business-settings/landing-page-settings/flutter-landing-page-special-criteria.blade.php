@extends('layouts.admin.app')

@section('title',translate('messages.Flutter web landing page'))

@section('content')

<div class="content container-fluid">
    <div class="page-header pb-0">
        <div class="d-flex flex-wrap justify-content-between">
            <div>
                <h1 class="page-header-title">
                    <span class="page-header-icon">
                        <img src="{{asset('public/assets/admin/img/outline/flutter.svg')}}" class="w--26" alt="">
                    </span>
                    <span>
                        {{ translate('messages.Flutter web landing page') }}
                    </span>
                </h1>
                <p class="page-header-desc">{{ translate('The selling points listed on the flutter web landing page.') }}</p>
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
            @include('admin-views.business-settings.landing-page-settings.top-menu-links.flutter-landing-page-links')
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
                <form action="{{ route('admin.business-settings.flutter-landing-page-settings-update', 'special-criteria-list') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                <h5 class="card-title mb-3 mt-3">
                    <span class="card-header-icon mr-2"><i class="tio-settings-outlined"></i></span> <span>{{translate('Special Feature List Section')}} </span>
                </h5>
                <div class="card mb-3">
                    <div class="card-body">

                            <div class="row g-3">
                                @if ($language)
                                <div class="col-sm-6 lang_form default-form">
                                    <label for="title" class="form-label">{{translate('Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 30">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="title" type="text"  maxlength="30" name="title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                                    @foreach(json_decode($language) as $lang)
                                    <div class="col-sm-6 d-none lang_form" id="{{$lang}}-form1">
                                        <label for="title{{$lang}}" class="form-label">{{translate('Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 30">
                                            <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                        </span></label>
                                <input type="text" id="title{{$lang}}" maxlength="30" name="title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                    </div>
                                        <input type="hidden" name="lang[]" value="{{$lang}}">
                                    @endforeach
                                @else
                                <div class="col-sm-6">
                                    <label for="title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 30">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input type="text" id="title" maxlength="30" name="title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                </div>
                                    <input type="hidden" name="lang[]" value="default">
                                @endif
                                <div class="col-sm-6">
                                    <div>

                                        <label class="form-label">{{translate('Criteria Icon/ Image')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="Icon ratio (1:1) and max size 2 MB.">
                                            <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                        </span></label>
                                    </div>
                                    <label class="upload-img-3 m-0">
                                        <div class="img">
                                            <img src="{{asset('/public/assets/admin/img/aspect-1.png')}}" alt="" class="img__aspect-1 min-w-187px max-w-187px">
                                        </div>
                                          <input type="file"  name="image" hidden>
                                    </label>
                                </div>
                            </div>
                            <div class="btn--container justify-content-end mt-20">
                                <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                                <button type="submit"   class="btn btn--primary mb-2"><i class="tio-add-circle"></i> {{translate('Add')}}</button>
                            </div>
                        </div>
                        </div>
                    </form>
                    @php($criterias=\App\CentralLogics\Helpers::cached_list(\App\Models\FlutterSpecialCriteria::class))
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
                                    <th class="border-0">{{translate('Image')}}</th>
                                    <th class="border-0">{{translate('Status')}}</th>
                                    <th class="text-center border-0">{{translate('messages.Action')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                    @foreach($criterias as $key=>$criteria)
                                    <tr>
                                        <td>{{ $key+1 }}</td>
                                        <td>
                                            <div class="text--title">
                                            {{ $criteria->title }}
                                            </div>
                                        </td>
                                        <td>
                                            <img
                                            src="{{ $criteria->image_full_url ?? asset('/public/assets/admin/img/upload-3.png') }}" 
                                            data-onerror-image="{{asset('/public/assets/admin/img/upload-3.png')}}" class="__size-105 onerror-image" alt="">
                                        </td>
                                        <td>
                                            <label class="toggle-switch toggle-switch-sm">
                                                <input type="checkbox"
                                                       data-id="status-{{$criteria->id}}"
                                                       data-type="status"
                                                       data-image-on="{{ asset('/public/assets/admin/img/modal/this-criteria-on.png') }}"
                                                       data-image-off="{{ asset('/public/assets/admin/img/modal/this-criteria-off.png') }}"
                                                       data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('This feature?') }}"
                                                       data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('This feature?') }}"
                                                       data-text-on="<p>{{ translate('If yes, it will be available on the landing page.') }}</p>"
                                                       data-text-off="<p>{{ translate('If yes, it will be hidden from the landing page.') }}</p>"
                                                       class="status toggle-switch-input dynamic-checkbox"

                                                       id="status-{{$criteria->id}}" {{$criteria->status?'checked':''}}>
                                                <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                            <form action="{{route('admin.business-settings.flutter-criteria-status',[$criteria->id,$criteria->status?0:1])}}" method="get" id="status-{{$criteria->id}}_form">
                                            </form>
                                        </td>

                                        <td>
                                            <div class="btn--container justify-content-center">
                                                <a class="btn action-btn action-btn--edit" href="{{route('admin.business-settings.flutter-criteria-edit',[$criteria['id']])}}">
                                                    <i class="tio-edit"></i>
                                                </a>
                                                <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                                   data-id="criteria-{{$criteria['id']}}"
                                                   data-message="{{ translate('Want to delete this feature?') }}"
                                                   data-test={{translate('If yes, it will be removed from this list and the landing page.')}}""
                                               title="{{translate('messages.Delete criteria')}}"><i class="tio-delete-outlined"></i>
                                                </a>
                                                <form action="{{route('admin.business-settings.flutter-criteria-delete',[$criteria['id']])}}" method="post" id="criteria-{{$criteria['id']}}">
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
                    @if(count($criterias) === 0)
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

    @include('admin-views.business-settings.landing-page-settings.partial.how-it-work-flutter')
@endsection

