@extends('layouts.admin.app')

@section('title',translate('Update unit'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    <div class="content container-fluid tps">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--20" alt="">
                </span>
                <span>
                    {{translate('messages.Unit update')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Rename this unit or change the languages it is shown in.') }}</p>
        </div>
        <div class="tps-card">
            {{-- custom-validation + .error-wrapper: the shared jQuery Validate layer,
                 so a cleared default name fails inline. UnitUpdateRequest still
                 enforces it, and the unique rule, server side. --}}
            <form action="{{route('admin.unit.update',[$unit['id']])}}" method="post" class="custom-validation">
                @csrf
                @method('PUT')
                <div class="tps-card__body">
                    <p class="tps-card__subtitle mb-3">
                        {{ translate('Renaming a unit changes it everywhere it is already in use.') }}
                    </p>

                    @if($language)
                        <ul class="nav nav-tabs mb-3 border-0">
                            <li class="nav-item">
                                <a class="nav-link lang_link active"
                                href="#"
                                id="default-link">{{translate('Default')}}</a>
                            </li>
                            @foreach ($language as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link"
                                        href="#"
                                        id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="lang_form" id="default-form">
                            <div class="tps-field">
                                <div class="error-wrapper">
                                    <label class="tps-field__label" for="default_title">
                                        {{translate('Name')}} ({{translate('Default')}})
                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.Required.')}}">*</span>
                                    </label>
                                    <input type="text" name="unit[]" id="default_title" class="form-control"
                                           placeholder="{{translate('messages.Unit name')}}" value="{{$unit?->getRawOriginal('unit')}}"
                                           maxlength="191" required>
                                </div>
                                <small class="tps-field__hint">
                                    {{ translate('Short lowercase names read best in the vendor panel and the customer app.') }}
                                </small>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                        </div>
                        @foreach($language as $lang)
                            <?php
                                if(count($unit['translations'])){
                                    $translate = [];
                                    foreach($unit['translations'] as $t)
                                    {
                                        if($t->locale == $lang && $t->key=="unit"){
                                            $translate[$lang]['unit'] = $t->value;
                                        }
                                    }
                                }
                            ?>
                            <div class="d-none lang_form" id="{{$lang}}-form">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="{{$lang}}_title">
                                        {{translate('Name')}} ({{strtoupper($lang)}})
                                        <span class="tps-opt">{{ translate('Optional') }}</span>
                                    </label>
                                    <input type="text" name="unit[]" id="{{$lang}}_title" class="form-control"
                                           placeholder="{{translate('messages.Unit name')}}" value="{{$translate[$lang]['unit']??''}}"
                                           maxlength="191">
                                    <small class="tps-field__hint">
                                        {{ translate('Leave it empty to fall back to the default name.') }}
                                    </small>
                                </div>
                                <input type="hidden" name="lang[]" value="{{$lang}}">
                            </div>
                        @endforeach
                    @else
                        <div id="default-form">
                            <div class="tps-field">
                                <div class="error-wrapper">
                                    <label class="tps-field__label" for="default_title">
                                        {{translate('Name')}} ({{ translate('Default') }})
                                        <span class="tps-req">*</span>
                                    </label>
                                    <input type="text" name="unit[]" id="default_title" class="form-control"
                                           placeholder="{{translate('messages.Unit name')}}" value="{{$unit['unit']}}"
                                           maxlength="191" required>
                                </div>
                                <small class="tps-field__hint">
                                    {{ translate('Short lowercase names read best in the vendor panel and the customer app.') }}
                                </small>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                        </div>
                    @endif
                </div>

                <div class="tps-card__foot">
                    <a href="{{route('admin.unit.index')}}" class="tps-foot-note text-primary">
                        <i class="tio-chevron-left"></i> {{translate('messages.Back to unit list')}}
                    </a>
                    <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{translate('Update')}}</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('script_2')

@endpush
