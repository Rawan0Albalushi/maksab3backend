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
                <p class="page-header-desc">{{ translate('Customer quotes shown on the admin landing page, and the order they rotate in.') }}</p>
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

    @php($testimonial_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','testimonial_title')->first())
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
            <form action="{{ route('admin.business-settings.admin-landing-page-settings-update', 'testimonial-title') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card mb-3">
                    <div class="card-body">
                        @if ($language)
                            <div class="row g-3 lang_form" id="default-form">
                                <div class="col-sm-12">
                                    <label for="testimonial_title" class="form-label">{{translate('Title')}} ({{ translate('Default') }})<span
                                        class="form-label-secondary" data-toggle="tooltip"
                                        data-placement="right"
                                        data-original-title="{{ translate('Character limit') }}: 30">
                                        <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                            alt="">
                                    </span>
                                        <span class="form-label-secondary text-danger"
                                              data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span></label>
                                <input required id="testimonial_title" type="text" maxlength="30" name="testimonial_title[]" class="form-control" value="{{$testimonial_title?->getRawOriginal('value')}}" placeholder="{{translate('Enter title')}}">
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                                @foreach(json_decode($language) as $lang)
                                <?php
                                if(isset($testimonial_title->translations)&&count($testimonial_title->translations)){
                                        $testimonial_title_translate = [];
                                        foreach($testimonial_title->translations as $t)
                                        {
                                            if($t->locale == $lang && $t->key=='testimonial_title'){
                                                $testimonial_title_translate[$lang]['value'] = $t->value;
                                            }
                                        }

                                    }
                                    ?>
                                    <div class="row g-3 d-none lang_form" id="{{$lang}}-form">
                                        <div class="col-sm-12">
                                            <label for="testimonial_title{{$lang}}" class="form-label">{{translate('Title')}} ({{strtoupper($lang)}})<span
                                                class="form-label-secondary" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('Character limit') }}: 40">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                    alt="">
                                            </span></label>
                                        <input id="testimonial_title{{$lang}}" type="text" maxlength="30" name="testimonial_title[]" class="form-control" value="{{ $testimonial_title_translate[$lang]['value']?? '' }}" placeholder="{{translate('Enter title')}}">
                                        </div>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="row g-3">
                                    <div class="col-sm-12">
                                        <label for="testimonial_title" class="form-label">{{translate('Title')}}<span
                                            class="form-label-secondary" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('Character limit') }}: 40">
                                            <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                alt="">
                                        </span></label>
                                    <input type="text" id="testimonial_title" maxlength="30" name="testimonial_title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                            <button type="submit"   class="btn btn--primary mb-2"><i class="tio-save"></i> {{translate('Save')}}</button>
                        </div>
                    </div>
                </div>
            </form>
            <form action="{{ route('admin.business-settings.admin-landing-page-settings-update', 'testimonial-list') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <h5 class="card-title mb-3 mt-3">
                    <span class="card-header-icon mr-2"><i class="tio-settings-outlined"></i></span> <span>{{translate('Testimonial List Section')}}</span>
                </h5>
                <div class="card mb-3">
                    <div class="card-body">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">{{translate('Reviewer Name')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 30">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}" alt="">
                                            </span>
                                            <span class="form-label-secondary text-danger"
                                                  data-toggle="tooltip" data-placement="right"
                                                  data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span>
                                        </label>
                                        <input required id="name" type="text" maxlength="30" name="name" class="form-control" placeholder="{{ translate('Ex') }}: John Doe">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="designation" class="form-label">{{translate('Designation')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 30">
                                                <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}" alt="">
                                            </span>
                                            <span class="form-label-secondary text-danger"
                                                  data-toggle="tooltip" data-placement="right"
                                                  data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span>
                                        </label>
                                        <input required id="designation" type="text" maxlength="30" name="designation" class="form-control" placeholder="{{translate('Ex') . ':  CTO'}}">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="review" class="form-label">{{translate('messages.review')}}<span
                                            class="form-label-secondary" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('Character limit') }}: 250">
                                            <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                alt="">
                                        </span>
                                            <span class="form-label-secondary text-danger"
                                                  data-toggle="tooltip" data-placement="right"
                                                  data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span>
                                        </label>
                                        <textarea required id="review" name="review" maxlength="250" placeholder="{{translate('Very Good Company')}}" class="form-control h92px"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex gap-40px">
                                    <div>
                                        <label class="form-label d-block mb-3">
                                            {{translate('Reviewer Image')}}  <span class="text--primary">(1:1)</span>
                                            <span class="form-label-secondary text-danger"
                                                  data-toggle="tooltip" data-placement="right"
                                                  data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span>
                                            <div class="fs-12 opacity-70">
                                                {{ IMAGE_FORMAT.' ' . 'Less Than 2MB' }}
                                            </div>
                                        </label>
                                        <label class="upload-img-3 m-0 d-block">
                                            <div class="img">
                                                <img src="{{asset("/public/assets/admin/img/aspect-1.png")}}"
                                                data-onerror-image="{{asset("/public/assets/admin/img/aspect-1.png")}}" class="img__aspect-1 min-w-187px max-w-187px onerror-image" alt="">
                                            </div>
                                            <input accept="{{IMAGE_EXTENSION}}" class="upload-file__input single_file_input" type="file"  name="reviewer_image" hidden="">
                                        </label>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <label class="form-label d-block mb-3">
                                            {{translate('Company Logo')}}  <span class="text--primary">(3:1)</span>
                                            <span class="form-label-secondary text-danger"
                                                  data-toggle="tooltip" data-placement="right"
                                                  data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span>
                                            <div class="fs-12 opacity-70">
                                                {{ IMAGE_FORMAT.' ' . 'Less Than 2MB' }}
                                            </div>
                                        </label>
                                        <label class="upload-img-4 m-0 d-block my-auto">
                                            <div class="img">
                                                <img src="{{asset("/public/assets/admin/img/aspect-3-1.png")}}" data-onerror-image="{{asset("/public/assets/admin/img/aspect-3-1.png")}}" class="vertical-img max-w-187px onerror-image" alt="">
                                            </div>
                                            <input accept="{{IMAGE_EXTENSION}}" class="upload-file__input single_file_input" type="file" id="image-upload-2" name="company_image" hidden="">
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                            <button type="submit"   class="btn btn--primary mb-2"><i class="tio-add-circle"></i> {{translate('Add')}}</button>
                        </div>

                    </div>
                    </div>
                </form>
                    @php($reviews=\App\CentralLogics\Helpers::cached_list(\App\Models\AdminTestimonial::class))
                    <div class="card-body p-0">
                        <div class="table-responsive datatable-custom">
                            <table class="table table-borderless table-thead-bordered table-align-middle table-nowrap card-table m-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="border-top-0">{{translate('SL')}}</th>
                                        <th class="border-top-0">{{translate('Reviewer Name')}}</th>
                                        <th class="border-top-0">{{translate('Designation')}}</th>
                                        <th class="border-top-0">{{translate('Reviews')}}</th>
                                        <th class="text-center border-top-0">{{translate('Reviewer Image')}}</th>
                                        <th class="text-center border-top-0">{{translate('Company Image')}}</th>
                                        <th class="text-center border-top-0">{{translate('Status')}}</th>
                                        <th class="text-center border-top-0">{{translate('Action')}}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($reviews as $key=>$review)
                                    <tr>
                                        <td>{{ $key+1 }}</td>
                                        <td>
                                            <div class="text--title">
                                            {{ $review->name }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text--title">
                                            {{ $review->designation }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="word-break max-w-200px text-truncate min-w-220" data-toggle="tooltip" data-placement="top" title="{{ $review->review }}">
                                                {{ Str::limit($review->review, 50) }}
                                            </div>
                                        </td>
                                        <td>
                                            <img   src="{{ $review?->reviewer_image_full_url ?? asset('/public/assets/admin/img/upload-3.png') }}"

                                            data-onerror-image="{{asset('/public/assets/admin/img/upload-3.png')}}" class="__size-105 onerror-image" alt="">
                                        </td>
                                        <td>
                                            <img
                                            src="{{ $review?->company_image_full_url ?? asset('/public/assets/admin/img/upload-3.png') }}"

                                            data-onerror-image="{{asset('/public/assets/admin/img/upload-3.png')}}" class="__size-105 onerror-image" alt="">
                                        </td>
                                        <td>
                                            <label class="toggle-switch toggle-switch-sm">
                                                <input type="checkbox"
                                                       data-id="status-{{$review->id}}"
                                                       data-type="status"
                                                       data-image-on="{{ asset('/public/assets/admin/img/modal/testimonial-on.png') }}"
                                                       data-image-off="{{ asset('/public/assets/admin/img/modal/testimonial-off.png') }}"
                                                       data-title-on="{{ translate('By Turning ON') }}  <strong>{{ translate('This review') }}"
                                                       data-title-off="{{ translate('By Turning OFF') }}  <strong>{{ translate('This review') }}"
                                                       data-text-on="<p>{{ translate('This section will be enabled. You can see this section on your landing page.') }}</p>"
                                                       data-text-off="<p>{{ translate('This section will be disabled. You can enable it in the settings') }}</p>"
                                                       class="status toggle-switch-input dynamic-checkbox"

                                                       id="status-{{$review->id}}" {{$review->status?'checked':''}}>
                                                <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                            <form action="{{route('admin.business-settings.review-status',[$review->id,$review->status?0:1])}}" method="get" id="status-{{$review->id}}_form">
                                            </form>
                                        </td>

                                        <td>
                                            <div class="btn--container justify-content-center">
                                                <a class="btn action-btn action-btn--edit" href="{{route('admin.business-settings.review-edit',[$review['id']])}}">
                                                    <i class="tio-edit"></i>
                                                </a>
                                                <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                                   data-id="review-{{$review['id']}}"
                                                   data-message="{{ translate('Want to delete this review?') }}"><i class="tio-delete-outlined"></i>
                                                </a>
                                                <form action="{{route('admin.business-settings.review-delete',[$review['id']])}}" method="post" id="review-{{$review['id']}}">
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
                    @if(count($reviews) === 0)
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

    @include('admin-views.business-settings.landing-page-settings.partial.how-it-work')
@endsection

