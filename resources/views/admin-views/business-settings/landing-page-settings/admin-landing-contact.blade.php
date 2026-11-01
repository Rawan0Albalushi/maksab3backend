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
                <p class="page-header-desc">{{ translate('The address, phone number and email shown on the admin landing page.') }}</p>
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
    @php($contact_us_image=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','contact_us_image')->first())
    <div class="tab-content">
        <div class="tab-pane fade show active">
            <form action="{{ route('admin.business-settings.admin-landing-page-settings-update', 'contact-us-section') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <h5 class="card-title mb-3 mt-3">
                    <span class="card-header-icon mr-2"><i class="tio-poi"></i></span> <span>{{translate('Office Opening & Closing')}}</span>
                </h5>
                <div class="card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6 col-lg-3">
                                @php($opening_time = \App\Models\BusinessSetting::where('key', 'opening_time')->first())
                                <label for="opening_time" class="form-label">{{translate('Start time')}}</label>
                                <input  type="time" value="{{ $opening_time ? $opening_time->value: '' }}" name="opening_time" class="form-control" id="opening_time">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                 @php($closing_time = \App\Models\BusinessSetting::where('key', 'closing_time')->first())
                                <label for="closing_time" class="form-label">{{translate('End time')}}</label>
                                <input type="time" value="{{ $closing_time ? $closing_time->value: '' }}" name="closing_time" class="form-control" id="closing_time">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                @php($opening_day = \App\CentralLogics\Helpers::get_business_settings('opening_day', false) ?? '')
                                <label for="opening_day" class="form-label">{{translate('Start Day')}}</label>
                                <select id="opening_day" name="opening_day" class="form-control">
                                    <option value="saturday" {{ $opening_day == 'saturday' ? 'selected' : '' }}>
                                        Saturday
                                    </option>
                                    <option value="sunday" {{ $opening_day == 'sunday' ? 'selected' : '' }}>
                                        Sunday
                                    </option>
                                    <option value="monday" {{ $opening_day == 'monday' ? 'selected' : '' }}>
                                        Monday
                                    </option>
                                    <option value="tuesday" {{ $opening_day == 'tuesday' ? 'selected' : '' }}>
                                        Tuesday
                                    </option>
                                    <option value="wednesday" {{ $opening_day == 'wednesday' ? 'selected' : '' }}>
                                        Wednesday
                                    </option>
                                    <option value="thursday" {{ in_array($opening_day, ['thursday', 'thrusday'], true) ? 'selected' : '' }}>
                                        Thursday
                                    </option>
                                    <option value="friday" {{ $opening_day == 'friday' ? 'selected' : '' }}>
                                        Friday
                                    </option>
                                </select>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                @php($closing_day = \App\CentralLogics\Helpers::get_business_settings('closing_day', false) ?? '')
                                <label for="closing_day" class="form-label">{{translate('End Day')}}</label>
                                <select id="closing_day" name="closing_day" class="form-control">
                                    <option value="saturday" {{ $closing_day == 'saturday' ? 'selected' : '' }}>
                                        Saturday
                                    </option>
                                    <option value="sunday" {{ $closing_day == 'sunday' ? 'selected' : '' }}>
                                        Sunday
                                    </option>
                                    <option value="monday" {{ $closing_day == 'monday' ? 'selected' : '' }}>
                                        Monday
                                    </option>
                                    <option value="tuesday" {{ $closing_day == 'tuesday' ? 'selected' : '' }}>
                                        Tuesday
                                    </option>
                                    <option value="wednesday" {{ $closing_day == 'wednesday' ? 'selected' : '' }}>
                                        Wednesday
                                    </option>
                                    <option value="thursday" {{ in_array($closing_day, ['thursday', 'thrusday'], true) ? 'selected' : '' }}>
                                        Thursday
                                    </option>
                                    <option value="friday" {{ $closing_day == 'friday' ? 'selected' : '' }}>
                                        Friday
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="btn--container justify-content-end mt-20">
                    <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                    <button type="submit"   class="btn btn--primary mb-2"><i class="tio-save"></i> {{translate('Save information')}}</button>
                </div>
            </form>
            <form  id="contact_image_form" action="{{ route('admin.remove_image') }}" method="post">
                @csrf
                <input type="hidden" name="id" value="{{  $contact_us_image?->id}}" >
                <input type="hidden" name="model_name" value="DataSetting" >
                <input type="hidden" name="image_path" value="contact_us_image" >
                <input type="hidden" name="field_name" value="value" >
            </form>

        </div>
    </div>
</div>
    @include('admin-views.business-settings.landing-page-settings.partial.how-it-work')
@endsection
