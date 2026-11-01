@extends('layouts.admin.app')

@section('title',translate('messages.Why Choose Us'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/outline/3rd-party.svg')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('messages.Other Promotional Content Setup')}}
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('The reasons to choose your parcel service, listed in the customer app.') }}</p>
    </div>
    <div class="mb-20 mt-2">
        <div class="js-nav-scroller hs-nav-scroller-horizontal">
            @include('admin-views.other-banners.partial.parcel-links')
        </div>
    </div>
    @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
    <div class="tab-content">
        <div class="tab-pane fade show active">
            <div class="card mb-3">
                <div class="card-body">
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
                        <form action="{{ route('admin.promotional-banner.why-choose-store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-3">
                                @if ($language)
                                <div class="col-6">
                                    <div class="row lang_form default-form">
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label class="form-label">{{translate('Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                            <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                        </span></label>
                                                <input type="text"  maxlength="80" name="title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label class="form-label">{{translate('Short description')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 100">
                                                            <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                        </span></label>
                                                <textarea type="text"  maxlength="100" name="short_description[]" class="form-control" rows="3" {{translate('Enter short description')}}> </textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                    @foreach(json_decode($language) as $lang)
                                    <div class="row d-none lang_form" id="{{$lang}}-form1">

                                        <div class="col-12">
                                            <div class="form-group">
                                                <label class="form-label">{{translate('Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                    <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                </span></label>
                                                <input type="text"  maxlength="80" name="title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label class="form-label">{{translate('Short description')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 100">
                                                    <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                </span></label>
                                                <textarea type="text"  maxlength="100" name="short_description[]" class="form-control" rows="3" {{translate('Enter short description')}}> </textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                    @endforeach

                                </div>

                                @else
                                <div class="col-sm-6">
                                    <label class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input type="text"  maxlength="80" name="title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                </div>
                                    <input type="hidden" name="lang[]" value="default">
                                @endif
                                <div class="col-sm-6">
                                    <div class="ml-5">
                                        <div>

                                            <label class="form-label">image (1:1)</label>
                                        </div>
                                        <label class="upload-img-3 m-0">
                                            <div class="img">
                                                <img src="{{asset('/public/assets/admin/img/aspect-1.png')}}" alt="" class="img__aspect-1 min-w-187px max-w-187px">
                                            </div>
                                              <input type="file"  name="image" hidden>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="btn--container justify-content-end mt-20">
                                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                                <button type="submit" class="btn btn--primary mb-2"><i class="tio-checkmark-circle-outlined"></i> {{translate('Submit')}}</button>
                            </div>
                        </form>
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
                                    <th class="border-0">{{translate('Short description')}}</th>
                                    <th class="border-0">{{translate('Image')}}</th>
                                    <th class="border-0">{{translate('Status')}}</th>
                                    <th class="text-center border-0">{{translate('messages.Action')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                    @foreach($banners as $key=>$banner)
                                    <tr>
                                        <td>{{ $key+1 }}</td>
                                        <td>
                                            <div class="text--title" title="{{$banner->title}}">
                                                {{ strlen($banner->title) > 30 ? substr($banner->title, 0, 30).'...' : $banner->title }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text--title" title="{{$banner->short_description}}">
                                                {{ strlen($banner->short_description) > 40 ? substr($banner->short_description, 0, 40).'...' : $banner->short_description }}
                                            </div>
                                        </td>
                                        <td>
                                            <img  src="{{ $banner->image_full_url ?? asset('/public/assets/admin/img/upload-3.png')}}"
                                            data-onerror-image="{{asset('/public/assets/admin/img/upload-3.png')}}" class="__size-105 onerror-image" alt="">
                                        </td>
                                        <td>
                                            <label class="toggle-switch toggle-switch-sm">
                                                <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                                       data-id="status-{{$banner->id}}"
                                                       data-type="status"
                                                       data-image-on="{{asset('/public/assets/admin/img/modal')}}/this-criteria-on.png"
                                                       data-image-off="{{asset('/public/assets/admin/img/modal')}}/this-criteria-off.png"
                                                       data-title-on="{{translate('messages.Want to enable')}} <strong>{{translate('This feature?')}}"
                                                       data-title-off="{{translate('messages.Want to disable')}} <strong>{{translate('This feature?')}}"
                                                       data-text-on="<p>{{translate('If yes, it will be available on this module.')}}</p>"
                                                       data-text-off="<p>{{translate('If yes, it will be hidden from this module.')}}</p>"
                                                       id="status-{{$banner->id}}" {{$banner->status?'checked':''}}>
                                                <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                            <form action="{{route('admin.promotional-banner.why-choose-status-update',[$banner->id,$banner->status?0:1])}}" method="get" id="status-{{$banner->id}}_form">
                                            </form>
                                        </td>

                                        <td>
                                            <div class="btn--container justify-content-center">
                                                <a class="btn action-btn action-btn--edit" href="{{route('admin.promotional-banner.why-choose-edit',[$banner['id']])}}">
                                                    <i class="tio-edit"></i>
                                                </a>
                                                <a class="btn action-btn action-btn--delete form-alert-title" href="javascript:"
                                                data-id="criteria-{{$banner['id']}}" data-title="{{ translate('Want to delete this feature?') }}" data-message="{{translate('If yes, it will be removed from this list and this module.')}}" title="{{translate('messages.Delete criteria')}}"><i class="tio-delete-outlined"></i>
                                                </a>
                                                <form action="{{route('admin.promotional-banner.why-choose-delete',[$banner['id']])}}" method="post" id="criteria-{{$banner['id']}}">
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
@endsection
@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/other-banners.js"></script>
@endpush
