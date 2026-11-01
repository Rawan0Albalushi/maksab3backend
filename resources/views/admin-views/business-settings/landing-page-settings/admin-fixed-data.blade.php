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
                <p class="page-header-desc">{{ translate('The headline, logo and contact details that stay the same across the admin landing page.') }}</p>
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
    @php($fixed_header_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','fixed_header_title')->first())
    @php($fixed_header_sub_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','fixed_header_sub_title')->first())
    @php($fixed_module_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','fixed_module_title')->first())
    @php($fixed_module_sub_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','fixed_module_sub_title')->first())
    @php($fixed_referal_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','fixed_referal_title')->first())
    @php($fixed_referal_sub_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','fixed_referal_sub_title')->first())
    @php($fixed_newsletter_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','fixed_newsletter_title')->first())
    @php($fixed_newsletter_sub_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','fixed_newsletter_sub_title')->first())
    @php($fixed_footer_article_title=\App\Models\DataSetting::withoutGlobalScope('translate')->where('type','admin_landing_page')->where('key','fixed_footer_article_title')->first())
    @php($fixed_link = \App\Models\DataSetting::where(['key'=>'fixed_link','type'=>'admin_landing_page'])->first())
    @php($fixed_link = isset($fixed_link->value)?json_decode($fixed_link->value, true):null)
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
            <form action="{{ route('admin.business-settings.admin-landing-page-settings-update', 'fixed-data') }}" method="POST">
                @csrf
                @if ($language)
                <div class="lang_form"  id="default-form">
                    <h5 class="card-title mb-3">
                        <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('messages.Header Section')}} ({{ translate('Default') }})</span>
                    </h5>
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="fixed_header_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_header_title" type="text"  maxlength="50" name="fixed_header_title[]" value="{{$fixed_header_title?->getRawOriginal('value')}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Manage your daily life on one platform')}}">
                                </div>
                                <div class="col-sm-6">
                                    <label for="fixed_header_sub_title" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 100">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_header_sub_title" type="text"  maxlength="100" name="fixed_header_sub_title[]" value="{{$fixed_header_sub_title?->getRawOriginal('value')}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('More than just a reliable eCommerce platform')}}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <h5 class="card-title mb-3 mt-3">
                        <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('messages.Module list section')}} ({{ translate('Default') }})</span>
                    </h5>
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="fixed_module_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_module_title" type="text"  maxlength="50" name="fixed_module_title[]" value="{{$fixed_module_title?->getRawOriginal('value')}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Your eCommerce venture starts here')}}">
                                </div>
                                <div class="col-sm-6">
                                    <label for="fixed_module_sub_title" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 100">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_module_sub_title" type="text"  maxlength="100" name="fixed_module_sub_title[]" value="{{$fixed_module_sub_title?->getRawOriginal('value')}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Enjoy all services in one platform')}}">
                                </div>
                            </div>
                            <div class="alert alert-warning d-flex mt-4 mb-0">
                                <div class="alert--icon">
                                    <i class="tio-info"></i>
                                </div>
                                <div>
                                    {{translate('Modules are added automatically from module setup. You only add the section title and subtitle.')}}
                                </div>
                            </div>
                        </div>
                    </div>
                    <h5 class="card-title mb-3 mt-3">
                        <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('Referral earning')}} ({{ translate('Default') }})</span>
                    </h5>
                    <div class="card">
                        <div class="card-body">

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="fixed_referal_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 40">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_referal_title" type="text"  maxlength="40" name="fixed_referal_title[]" value="{{$fixed_referal_title?->getRawOriginal('value')}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Earn Point')}}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <h5 class="card-title mb-3 mt-3">
                        <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('newsletter')}} ({{ translate('Default') }})</span>
                    </h5>
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label for="fixed_newsletter_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 40">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_newsletter_title" type="text"  maxlength="40" name="fixed_newsletter_title[]" value="{{$fixed_newsletter_title?->getRawOriginal('value')}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Sign up to our newsletter')}}">
                                </div>
                                <div class="col-sm-6">
                                    <label for="fixed_newsletter_sub_title" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_newsletter_sub_title" type="text"  maxlength="80" name="fixed_newsletter_sub_title[]" value="{{$fixed_newsletter_sub_title?->getRawOriginal('value')}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Receive latest news, updates and many other news every week')}}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <h5 class="card-title mb-3 mt-3">
                        <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('Footer Article')}} ({{ translate('Default') }})</span>
                    </h5>
                    <div class="card">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="fixed_footer_article_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 180">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                    <input id="fixed_footer_article_title" type="text"  maxlength="180" name="fixed_footer_article_title[]" value="{{$fixed_footer_article_title?->getRawOriginal('value')}}" class="form-control" placeholder="{{translate('Ex') . ': ' . translate('A complete package! It\'s time to empower your multivendor online business with powerful features!')}}">
                                </div>
                            </div>
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
                    if(isset($fixed_referal_title->translations)&&count($fixed_referal_title->translations)){
                            $fixed_referal_title_translate = [];
                            foreach($fixed_referal_title->translations as $t)
                            {
                                if($t->locale == $lang && $t->key=='fixed_referal_title'){
                                    $fixed_referal_title_translate[$lang]['value'] = $t->value;
                                }
                            }

                        }
                    if(isset($fixed_referal_sub_title->translations)&&count($fixed_referal_sub_title->translations)){
                            $fixed_referal_sub_title_translate = [];
                            foreach($fixed_referal_sub_title->translations as $t)
                            {
                                if($t->locale == $lang && $t->key=='fixed_referal_sub_title'){
                                    $fixed_referal_sub_title_translate[$lang]['value'] = $t->value;
                                }
                            }

                        }
                    if(isset($fixed_newsletter_title->translations)&&count($fixed_newsletter_title->translations)){
                            $fixed_newsletter_title_translate = [];
                            foreach($fixed_newsletter_title->translations as $t)
                            {
                                if($t->locale == $lang && $t->key=='fixed_newsletter_title'){
                                    $fixed_newsletter_title_translate[$lang]['value'] = $t->value;
                                }
                            }

                        }
                    if(isset($fixed_newsletter_sub_title->translations)&&count($fixed_newsletter_sub_title->translations)){
                            $fixed_newsletter_sub_title_translate = [];
                            foreach($fixed_newsletter_sub_title->translations as $t)
                            {
                                if($t->locale == $lang && $t->key=='fixed_newsletter_sub_title'){
                                    $fixed_newsletter_sub_title_translate[$lang]['value'] = $t->value;
                                }
                            }

                        }
                    if(isset($fixed_footer_article_title->translations)&&count($fixed_footer_article_title->translations)){
                        $fixed_footer_article_title_translate = [];
                        foreach($fixed_footer_article_title->translations as $t)
                        {
                            if($t->locale == $lang && $t->key=='fixed_footer_article_title'){
                                $fixed_footer_article_title_translate[$lang]['value'] = $t->value;
                            }
                        }

                    }
                    ?>
                        <div class="d-none lang_form" id="{{$lang}}-form">
                            <h5 class="card-title mb-3">
                                <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('messages.Header Section')}} ({{strtoupper($lang)}})</span>
                            </h5>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <label for="fixed_header_title{{$lang}}" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                            <input id="fixed_header_title{{$lang}}" type="text"  maxlength="50" name="fixed_header_title[]" value="{{$fixed_header_title_translate[$lang]['value']??''}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Manage your daily life on one platform')}}">
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="fixed_header_sub_title{{$lang}}" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 100">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                            <input id="fixed_header_sub_title{{$lang}}" type="text"  maxlength="100" name="fixed_header_sub_title[]" value="{{$fixed_header_sub_title_translate[$lang]['value']??''}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('More than just a reliable eCommerce platform')}}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h5 class="card-title mb-3 mt-3">
                                <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('messages.Module list section')}} ({{strtoupper($lang)}})</span>
                            </h5>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <label for="fixed_module_title{{$lang}}" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                            <input  id="fixed_module_title{{$lang}}" type="text"  maxlength="50" name="fixed_module_title[]" value="{{$fixed_module_title_translate[$lang]['value']??''}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Your eCommerce venture starts here')}}">
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="fixed_module_sub_title{{$lang}}" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 100">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                            <input id="fixed_module_sub_title{{$lang}}" type="text"  maxlength="100" name="fixed_module_sub_title[]" value="{{$fixed_module_sub_title_translate[$lang]['value']??''}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Enjoy all services in one platform')}}">
                                        </div>
                                    </div>
                                    <div class="alert alert-warning d-flex mt-4 mb-0">
                                        <div class="alert--icon">
                                            <i class="tio-info"></i>
                                        </div>
                                        <div>
                                            {{translate('Modules are added automatically from module setup. You only add the section title and subtitle.')}}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h5 class="card-title mb-3 mt-3">
                                <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('Referral earning')}} ({{strtoupper($lang)}})</span>
                            </h5>
                            <div class="card">
                                <div class="card-body">

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="fixed_referal_title{{$lang}}"  class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 40">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                            <input id="fixed_referal_title{{$lang}}"  type="text"  maxlength="40" name="fixed_referal_title[]" value="{{$fixed_referal_title_translate[$lang]['value']??''}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Earn Point')}}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h5 class="card-title mb-3 mt-3">
                                <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('newsletter')}} ({{strtoupper($lang)}})</span>
                            </h5>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <label for="fixed_newsletter_title{{$lang}}" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 40">
                                                        <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                    </span></label>
                                            <input id="fixed_newsletter_title{{$lang}}" type="text"  maxlength="40" name="fixed_newsletter_title[]" value="{{$fixed_newsletter_title_translate[$lang]['value']??''}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Sign up to our newsletter')}}">
                                        </div>
                                        <div class="col-sm-6">
                                            <label for="fixed_newsletter_sub_title{{$lang}}" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 80">
                                                        <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                    </span></label>
                                            <input id="fixed_newsletter_sub_title{{$lang}}" type="text"  maxlength="80" name="fixed_newsletter_sub_title[]" value="{{$fixed_newsletter_sub_title_translate[$lang]['value']??''}}" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Receive latest news, updates and many other news every week')}}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h5 class="card-title mb-3 mt-3">
                                <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('Footer Article')}} ({{strtoupper($lang)}})</span>
                            </h5>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="fixed_footer_article_title{{$lang}}" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 180">
                                                        <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                    </span></label>
                                            <input id="fixed_footer_article_title{{$lang}}" type="text"  maxlength="180" name="fixed_footer_article_title[]" value="{{$fixed_footer_article_title_translate[$lang]['value']??''}}" class="form-control" placeholder="{{translate('Ex') . ': ' . translate('A complete package! It\'s time to empower your multivendor online business with powerful features!')}}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="lang[]" value="{{$lang}}">
                    @endforeach
                @else
                    <div>
                        <h5 class="card-title mb-3">
                            <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('messages.Header Section')}}</span>
                        </h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="fixed_header_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <input  id="fixed_header_title" type="text"  maxlength="50" name="fixed_header_title[]" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Manage your daily life on one platform')}}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="fixed_header_sub_title" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <input id="fixed_header_sub_title" type="text"  maxlength="50" name="fixed_header_sub_title[]" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('More than just a reliable eCommerce platform')}}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h5 class="card-title mb-3 mt-3">
                            <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('messages.Module list section')}}</span>
                        </h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="fixed_module_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <input id="fixed_module_title" type="text"  maxlength="50" name="fixed_module_title[]" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Your eCommerce venture starts here')}}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="fixed_module_sub_title" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <input type="text"  id="fixed_module_sub_title" maxlength="50" name="fixed_module_sub_title[]" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Enjoy all services in one platform')}}">
                                    </div>
                                </div>
                                <div class="alert alert-warning d-flex mt-4 mb-0">
                                    <div class="alert--icon">
                                        <i class="tio-info"></i>
                                    </div>
                                    <div>
                                        {{translate('Modules are added automatically from module setup. You only add the section title and subtitle.')}}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h5 class="card-title mb-3 mt-3">
                            <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('Referral earning')}}</span>
                        </h5>

                        <div class="card">
                            <div class="card-body">

                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="fixed_ref_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <input id="fixed_ref_title" type="text"  maxlength="50" name="fixed_referal_title[]" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Earn Point')}}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="fixed_ref_sub_title" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                            </span></label>
                                        <input id="fixed_ref_sub_title" type="text"  maxlength="50" name="fixed_referal_sub_title[]" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('By referring your friend')}}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h5 class="card-title mb-3 mt-3">
                            <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('newsletter')}} ({{ translate('Default') }})</span>
                        </h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label for="fixed_newsletter_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                    <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                </span></label>
                                        <input id="fixed_newsletter_title" type="text"  maxlength="50" name="fixed_newsletter_title[]" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Sign up to our newsletter')}}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="fixed_newsletter_sub_title" class="form-label">{{translate('Sub Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                    <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                </span></label>
                                        <input id="fixed_newsletter_sub_title" type="text"  maxlength="50" name="fixed_newsletter_sub_title[]"  class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Receive latest news, updates and many other news every week')}}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <h5 class="card-title mb-3 mt-3">
                            <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span> <span>{{translate('Footer Article')}} ({{ translate('Default') }})</span>
                        </h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="fixed_footer_article_title" class="form-label">{{translate('Title')}}<span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Character limit') }}: 50">
                                                    <img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="">
                                                </span></label>
                                        <input id="fixed_footer_article_title" type="text"  maxlength="50" name="fixed_footer_article_title[]"  class="form-control" placeholder="{{translate('Ex') . ': ' . translate('A complete package! It\'s time to empower your multivendor online business with powerful features!')}}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="lang[]" value="default">
                @endif
                <h5 class="card-title card-title mb-3 mt-3">
                    <span class="card-header-icon mr-2"><i class="tio-calendar"></i></span>
                    <span>{{translate('Browse Web Button')}}</span>
                </h5>
                <div class="card">
                    <div class="__bg-F8F9FC-card">
                        <div class="form-group mb-md-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="web_app_url" class="form-label text-capitalize m-0">
                                    {{translate('Web Link')}}

                                </label>
                                <label class="toggle-switch toggle-switch-sm m-0">
                                    <input type="checkbox" name="web_app_url_status" id="apple-dm-status"
                                           data-id="apple-dm-status"
                                           data-type="status"
                                           data-image-on="{{ asset('/public/assets/admin/img/modal/apple-on.png') }}"
                                           data-image-off="{{ asset('/public/assets/admin/img/modal/apple-off.png') }}"
                                           data-title-on="{{ translate('Browse Web Button Enabled for Landing Page') }}"
                                           data-title-off="{{ translate('Browse Web Button Disabled for Landing Page') }}"
                                           data-text-on="<p>{{ translate('Browse Web button is enabled now everyone can use or see the button') }}</p>"
                                           data-text-off="<p>{{ translate('Browse Web button is disabled now no one can use or see the button') }}</p>"
                                           class="status toggle-switch-input dynamic-checkbox"


                                           value="1" {{(isset($fixed_link) && $fixed_link['web_app_url_status'])?'checked':''}}>
                                    <span class="toggle-switch-label text mb-0">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                            <input id="web_app_url" type="text" placeholder="{{translate('Ex') . ': https://6ammart-web.6amtech.com/'}}" class="form-control h--45px" name="web_app_url" value="{{ $fixed_link['web_app_url'] ?? ''}}">
                        </div>
                    </div>
                </div>
                <div class="btn--container justify-content-end mt-20">
                    <button type="reset" class="btn btn--reset mb-2"><i class="tio-refresh"></i> {{translate('Reset')}}</button>
                    <button type="submit" class="btn btn--primary mb-2"><i class="tio-save"></i> {{translate('Save information')}}</button>
                </div>
            </form>
            <div class="modal fade" id="section-view">
                <div class="modal-dialog modal-lg warning-modal">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="mb-3">
                                <h3 class="modal-title mb-3">{{translate('Referral earning')}}</h3>
                            </div>
                            <img src="{{asset('/public/assets/admin/img/zone-instruction.png')}}" alt="admin/img" class="w-100">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    @include('admin-views.business-settings.landing-page-settings.partial.how-it-work')
@endsection
