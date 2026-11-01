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
                <p class="page-header-desc">{{ translate('The headline, logo and contact details that stay the same across the flutter web landing page.') }}</p>
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
    @php($fixed_header_title=($flutterSettings['fixed_header_title'] ?? null))
    @php($fixed_header_sub_title=($flutterSettings['fixed_header_sub_title'] ?? null))
    @php($fixed_header_image=($flutterSettings['fixed_header_image'] ?? null))
    @php($fixed_module_title=($flutterSettings['fixed_module_title'] ?? null))
    @php($fixed_module_sub_title=($flutterSettings['fixed_module_sub_title'] ?? null))
    @php($fixed_location_title=($flutterSettings['fixed_location_title'] ?? null))
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
            <form action="{{ route('admin.business-settings.flutter-landing-page-settings-update', 'fixed-header') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <h5 class="card-title mb-3">
                    <span class="card-header-icon mr-2"><i class="tio-settings-outlined"></i></span> <span>{{translate('messages.Header Section')}}</span>
                </h5>
                <div class="card">
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                @if ($language)
                                <div class="col-md-12 lang_form default-form">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="fixed_header_title" class="form-label">{{translate('Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 20">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_header_title" type="text"  maxlength="20" name="fixed_header_title[]" value="{{ $fixed_header_title?->getRawOriginal('value')??'' }}" class="form-control" placeholder="{{translate('Enter title')}}">
                                        </div>
                                        <div class="col-12">
                                            <label for="fixed_header_sub_title" class="form-label">{{translate('Sub Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 120">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_header_sub_title" type="text"  maxlength="120" name="fixed_header_sub_title[]" value="{{ $fixed_header_sub_title?->getRawOriginal('value')??'' }}" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                                    @foreach(json_decode($language) as $lang)
                                    <?php
                                    if(isset($fixed_header_title->translations)&&count($fixed_header_title->translations)){
                                            $fixed_header_title_translate = [];
                                            foreach($fixed_header_title->translations as $t)
                                            {
                                                if($t->locale == $lang && $t->key=='fixed_header_title'){
                                                    $fixed_header_title_translate[$lang]['value'] = $t->value;
                                                }
                                            }

                                        }
                                    if(isset($fixed_header_sub_title->translations)&&count($fixed_header_sub_title->translations)){
                                            $fixed_header_sub_title_translate = [];
                                            foreach($fixed_header_sub_title->translations as $t)
                                            {
                                                if($t->locale == $lang && $t->key=='fixed_header_sub_title'){
                                                    $fixed_header_sub_title_translate[$lang]['value'] = $t->value;
                                                }
                                            }

                                        }
                                        ?>
                                    <div class="col-md-12 d-none lang_form" id="{{$lang}}-form1">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label for="fixed_header_title{{$lang}}" class="form-label">{{translate('Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 20">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_header_title{{$lang}}" type="text"  maxlength="20" name="fixed_header_title[]" value="{{ $fixed_header_title_translate[$lang]['value']??'' }}" class="form-control" placeholder="{{translate('Enter title')}}">
                                            </div>
                                            <div class="col-12">
                                                <label for="fixed_header_sub_title{{$lang}}" class="form-label">{{translate('Sub Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 120">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input type="text"  id="fixed_header_sub_title{{$lang}}" maxlength="120" name="fixed_header_sub_title[]" value="{{ $fixed_header_sub_title_translate[$lang]['value']??'' }}" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                            </div>
                                        </div>
                                    </div>
                                        <input type="hidden" name="lang[]" value="{{$lang}}">
                                    @endforeach
                                @else
                                <div class="col-md-12">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="fixed_header_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input type="text"  id="fixed_header_title" maxlength="50" name="fixed_header_title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                        </div>
                                        <div class="col-12">
                                            <label for="fixed_header_sub_title" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input type="text" id="fixed_header_sub_title"  maxlength="50" name="fixed_header_sub_title[]" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                        </div>
                                    </div>
                                </div>
                                    <input type="hidden" name="lang[]" value="default">
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label d-block mb-2">
                                    {{ translate('messages.Image') }}  <span class="text--primary">({{ translate('size') }}: 1:1)</span>
                                </label>
                                <label class="upload-img-3 m-0">
                                    <div class="position-relative">
                                    <div class="img">
                                        <img

                                        src="{{\App\CentralLogics\Helpers::get_full_url('fixed_header_image', $fixed_header_image?->value?? '', $fixed_header_image?->storage[0]?->value ?? 'public','aspect_1')}}" data-onerror-image="{{asset('/public/assets/admin/img/aspect-1.png')}}" alt="" class="img__aspect-1 min-w-187px max-w-187px onerror-image">
                                    </div>
                                      <input type="file"  name="image" hidden>
                                        @if (isset($fixed_header_image['value']))
                                            <span id="fixed_header_image" class="remove_image_button remove-image"
                                                  data-id="fixed_header_image"
                                                  data-title="{{translate('warning')}}"
                                                  data-text="<p>{{translate('Are you sure you want to remove this image?')}}</p>"
                                            > <i class="tio-clear"></i></span>

                                            @endif
                                        </div>
                                </label>
                            </div>
                        </div>
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                            <button type="submit"   class="btn btn--primary mb-2"><i class="tio-save"></i> {{translate('Save')}}</button>
                        </div>
                    </div>
                </div>
            </form>
            <form  id="fixed_header_image_form" action="{{ route('admin.remove_image') }}" method="post">
                @csrf
                <input type="hidden" name="id" value="{{  $fixed_header_image?->id}}" >
                <input type="hidden" name="model_name" value="DataSetting" >
                <input type="hidden" name="image_path" value="fixed_header_image" >
                <input type="hidden" name="field_name" value="value" >
            </form>
            <form action="{{ route('admin.business-settings.flutter-landing-page-settings-update', 'fixed-location') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <h5 class="card-title mb-3 mt-3">
                    <span class="card-header-icon mr-2"><i class="tio-settings-outlined"></i></span> <span>{{translate('messages.Location setup')}}</span>
                </h5>
                <div class="card">
                    <div class="card-body">
                        @if ($language)
                            <div class="row g-3 lang_form default-form">
                                <div class="col-sm-12">
                                    <label for="fixed_location_title" class="form-label">{{translate('Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 30">
                                        <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                    </span></label>
                            <input type="text"  id="fixed_location_title" maxlength="30" name="fixed_location_title[]" class="form-control" value="{{$fixed_location_title?->getRawOriginal('value')??''}}" placeholder="{{translate('Enter title')}}">
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                                @foreach(json_decode($language) as $lang)
                                <?php
                                if(isset($fixed_location_title->translations)&&count($fixed_location_title->translations)){
                                        $fixed_location_title_translate = [];
                                        foreach($fixed_location_title->translations as $t)
                                        {
                                            if($t->locale == $lang && $t->key=='fixed_location_title'){
                                                $fixed_location_title_translate[$lang]['value'] = $t->value;
                                            }
                                        }

                                    }
                                    ?>
                                    <div class="row g-3 d-none lang_form" id="{{$lang}}-form2">
                                        <div class="col-sm-12">
                                            <label for="fixed_location_title{{$lang}}" class="form-label">{{translate('Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 30">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_location_title{{$lang}}" type="text"  maxlength="30" name="fixed_location_title[]" class="form-control" value="{{ $fixed_location_title_translate[$lang]['value']?? '' }}" placeholder="{{translate('Enter title')}}">
                                        </div>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="row g-3">
                                    <div class="col-sm-12">
                                        <label for="fixed_location_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 30">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_location_title" type="text"  maxlength="30" name="fixed_location_title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                            <button type="submit"   class="btn btn--primary mb-2"><i class="tio-add-circle"></i> {{translate('Add')}}</button>
                        </div>
                    </div>
                </div>
            </form>
            <form action="{{ route('admin.business-settings.flutter-landing-page-settings-update', 'fixed-module') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <h5 class="card-title mb-3 mt-3">
                    <span class="card-header-icon mr-2"><i class="tio-settings-outlined"></i></span> <span>{{translate('Module setup')}}</span>
                </h5>
                <div class="card">
                    <div class="card-body">

                        @if ($language)
                            <div class="row g-3 lang_form default-form">
                                <div class="col-sm-6">
                                    <label for="fixed_module_title" class="form-label">{{translate('Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 40">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_module_title" type="text"  maxlength="40" name="fixed_module_title[]" class="form-control" value="{{$fixed_module_title?->getRawOriginal('value')??''}}" placeholder="{{translate('Enter title')}}">
                                </div>
                                <div class="col-sm-6">
                                    <label  for="fixed_module_sub_title" class="form-label">{{translate('Sub Title')}} ({{ translate('Default') }})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_module_sub_title" type="text"  maxlength="80" name="fixed_module_sub_title[]" class="form-control" value="{{$fixed_module_sub_title?->getRawOriginal('value')??''}}" placeholder="{{translate('Enter subtitle')}}">
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                                @foreach(json_decode($language) as $lang)
                                <?php
                                if(isset($fixed_module_title->translations)&&count($fixed_module_title->translations)){
                                        $fixed_module_title_translate = [];
                                        foreach($fixed_module_title->translations as $t)
                                        {
                                            if($t->locale == $lang && $t->key=='fixed_module_title'){
                                                $fixed_module_title_translate[$lang]['value'] = $t->value;
                                            }
                                        }

                                    }
                                if(isset($fixed_module_sub_title->translations)&&count($fixed_module_sub_title->translations)){
                                        $fixed_module_sub_title_translate = [];
                                        foreach($fixed_module_sub_title->translations as $t)
                                        {
                                            if($t->locale == $lang && $t->key=='fixed_module_sub_title'){
                                                $fixed_module_sub_title_translate[$lang]['value'] = $t->value;
                                            }
                                        }

                                    }
                                    ?>
                                    <div class="row g-3 d-none lang_form" id="{{$lang}}-form">
                                        <div class="col-sm-6">
                                            <label for="fixed_module_title{{$lang}}" class="form-label">{{translate('Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 40">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_module_title{{$lang}}" type="text"  maxlength="40" name="fixed_module_title[]" class="form-control" value="{{ $fixed_module_title_translate[$lang]['value']?? '' }}" placeholder="{{translate('Enter title')}}">
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="fixed_module_sub_title{{$lang}}" class="form-label">{{translate('Sub Title')}} ({{strtoupper($lang)}})<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_module_sub_title{{$lang}}" type="text"  maxlength="80" name="fixed_module_sub_title[]" class="form-control" value="{{ $fixed_module_sub_title_translate[$lang]['value']?? '' }}" placeholder="{{translate('Enter subtitle')}}">
                                        </div>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                @endforeach
                            @else
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="fixed_module_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 40">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_module_title" type="text"  maxlength="40" name="fixed_module_title[]" class="form-control" placeholder="{{translate('Enter title')}}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="fixed_module_sub_title" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_module_sub_title" type="text"  maxlength="80" name="fixed_module_sub_title[]" class="form-control" placeholder="{{translate('Enter subtitle')}}">
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            @endif
                        <div class="alert alert--primary d-flex mt-4">
                            <div class="alert--icon">
                                <i class="tio-info"></i>
                            </div>
                            <div>
                                {{translate('Modules are added automatically from module setup. You only add the section title and subtitle.')}}
                            </div>
                        </div>
                        <div class="btn--container justify-content-end mt-20">
                            <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                            <button type="submit"   class="btn btn--primary mb-2"><i class="tio-save"></i> {{translate('Save')}}</button>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>

    @include('admin-views.business-settings.landing-page-settings.partial.how-it-work-flutter')
</div>

@endsection

