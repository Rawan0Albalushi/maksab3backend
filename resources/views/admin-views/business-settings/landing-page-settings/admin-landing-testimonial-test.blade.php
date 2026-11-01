@extends('layouts.admin.app')

@section('title',translate('Update testimonial'))

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
                <p class="page-header-desc">{{ translate('Customer quotes shown on the admin landing page.') }}</p>
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
    <div class="tab-content">
        <div class="tab-pane fade show active">
            <form action="{{ route('admin.business-settings.review-update',[$review->id]) }}" method="POST" enctype="multipart/form-data">
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
                                        <input required id="name" type="text" maxlength="30" name="name" value="{{ $review->name }}" class="form-control" placeholder="{{ translate('Ex') }}: John Doe">
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
                                        <input required id="designation" type="text" maxlength="30" name="designation" value="{{ $review->designation }}" class="form-control" placeholder="{{translate('Ex') . ':  CTO'}}">
                                    </div>
                                    <div class="col-md-12">
                                        <label for="review" class="form-label">{{translate('messages.review')}}<span
                                            class="form-label-secondary" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('Character limit') }}: 250">
                                            <img src="{{ asset('public/assets/admin/img/info-circle.svg') }}"
                                                alt="">
                                        </span><span class="form-label-secondary text-danger"
                                                     data-toggle="tooltip" data-placement="right"
                                                     data-original-title="{{ translate('messages.Required.')}}"> *
                                                </span>
                                        </label>
                                        <textarea required id="review" name="review" maxlength="250" placeholder="{{translate('Very Good Company')}}" class="form-control h92px">{{ $review->review }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex gap-40px">
                                    <div>
                                        <label class="form-label d-block mb-2">
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
                                            <div class="position-relative">
                                            <div class="img">
                                                <img

                                                src="{{ $review?->reviewer_image_full_url ?? asset('/public/assets/admin/img/aspect-1.png') }}" data-onerror-image="{{asset("/public/assets/admin/img/aspect-1.png")}}" class="img__aspect-1 mw-100 min-w-187px max-w-187px onerror-image" alt="">
                                            </div>
                                            <input accept="{{IMAGE_EXTENSION}}" class="upload-file__input single_file_input" type="file"  name="reviewer_image" hidden="">
                                             @if (isset($review->reviewer_image))
                                                    <span style="right: 53px;top: 2px;!important;"  id="reviewer_image" class="remove_image_button remove-image dynamic-checkbox"
                                                          data-id="reviewer_image"
                                                          data-image-off="{{ asset('/public/assets/admin/img/delete-confirmation.png') }}"
                                                          data-title="{{translate('warning')}}"
                                                          data-text="<p>{{translate('Are you sure you want to remove this image?')}}</p>"
                                                    > <i class="tio-clear"></i></span>
                                            @endif
                                        </div>
                                        </label>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <label class="form-label d-block mb-2">
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
                                            <div class="position-relative">
                                            <div class="img">
                                                <img
                                                src="{{ $review?->company_image_full_url ?? asset('/public/assets/admin/img/aspect-3-1.png') }}" data-onerror-image="{{asset("/public/assets/admin/img/aspect-3-1.png")}}" class="vertical-img max-w-187px onerror-image" alt="">
                                            </div>
                                            <input accept="{{IMAGE_EXTENSION}}" class="upload-file__input single_file_input" type="file" id="image-upload-2" name="company_image" hidden="">
                                            @if (isset($review->company_image))
                                                    <span style="right: 53px;top: 2px;!important;" id="company_image" class="remove_image_button remove-image dynamic-checkbox"
                                                          data-id="company_image"
                                                          data-image-off="{{ asset('/public/assets/admin/img/delete-confirmation.png') }}"
                                                          data-title="{{translate('warning')}}"
                                                          data-text="<p>{{translate('Are you sure you want to remove this image?')}}</p>"
                                                    > <i class="tio-clear"></i></span>
                                            @endif
                                        </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                            <button type="submit"   class="btn btn--primary mb-2"><i class="tio-save"></i> {{translate('Update')}}</button>
                        </div>

                    </div>
                </div>
            </form>
            <form  id="reviewer_image_form" action="{{ route('admin.remove_image') }}" method="post">
                @csrf
                <input type="hidden" name="id" value="{{  $review?->id}}" >
                <input type="hidden" name="model_name" value="AdminTestimonial" >
                <input type="hidden" name="image_path" value="reviewer_image" >
                <input type="hidden" name="field_name" value="reviewer_image" >
            </form>
            <form  id="company_image_form" action="{{ route('admin.remove_image') }}" method="post">
                @csrf
                <input type="hidden" name="id" value="{{  $review?->id}}" >
                <input type="hidden" name="model_name" value="AdminTestimonial" >
                <input type="hidden" name="image_path" value="reviewer_company_image" >
                <input type="hidden" name="field_name" value="company_image" >
            </form>


            <div class="modal fade" id="testimonials-section">
                <div class="modal-dialog modal-lg warning-modal">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="mb-3">
                                <h3 class="modal-title mb-3"> {{translate('Special review')}}</h3>
                            </div>
                            <img src="{{asset('/public/assets/admin/img/zone-instruction.png')}}" alt="admin/img" class="w-100">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="testimonials-status-modal">
                <div class="modal-dialog status-warning-modal">
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
                                        <img src="{{asset('/public/assets/admin/img/modal/this-review-off.png')}}" alt="" class="mb-20">
                                        <h5 class="modal-title">{{translate('By Turning OFF')}}  <strong>{{translate('This review')}}</strong></h5>
                                    </div>
                                    <div class="text-center">
                                        <p>
                                            {{translate('This section will be disabled. You can enable it in the settings')}}
                                        </p>
                                    </div>
                                </div>
                                <div class="btn--container justify-content-center">
                                    <button type="submit" class="btn btn--primary min-w-120" data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{translate('OK')}}</button>
                                    <button id="reset_btn" type="reset" class="btn btn--cancel min-w-120" data-dismiss="modal">
                                        <i class="tio-clear-circle-outlined"></i> {{translate("Cancel")}}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    @include('admin-views.business-settings.landing-page-settings.partial.how-it-work')
@endsection

