@extends('layouts.admin.app')

@section('title',translate('Update attribute'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    <div class="content container-fluid tps">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/edit.png') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('messages.Attribute update') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Rename this attribute or change the languages it is shown in.') }}</p>
        </div>

        <div class="tps-card">
            <form action="{{ route('admin.attribute.update', [$attribute['id']]) }}" method="post"
                class="custom-validation" data-ajax-form>
                @csrf

                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-tune-horizontal"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">{{ $attribute['name'] }}</h2>
                        <p class="tps-card__subtitle">
                            {{ translate('Renaming an attribute changes it everywhere it is already used — the values vendors set on their items are kept.') }}
                        </p>
                    </div>
                    <div class="tps-card__aside">
                        <a class="btn btn-sm btn-white" href="{{ route('admin.attribute.add-new') }}"><i
                                class="tio-arrow-backward"></i> {{ translate('messages.Back') }}</a>
                    </div>
                </div>

                <div class="tps-card__body">
                    @if ($language)
                        <ul class="nav nav-tabs mb-3 border-0">
                            <li class="nav-item">
                                <a class="nav-link lang_link active" href="#"
                                    id="default-link">{{ translate('Default') }}</a>
                            </li>
                            @foreach ($language as $lang)
                                <li class="nav-item">
                                    <a class="nav-link lang_link" href="#"
                                        id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                </li>
                            @endforeach
                        </ul>
                        <div class="lang_form" id="default-form">
                            <div class="tps-field">
                                <div class="error-wrapper">
                                    <label class="tps-field__label" for="default_title">
                                        {{ translate('Name') }} ({{ translate('Default') }})
                                        <span class="tps-req" data-toggle="tooltip" data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}">*</span>
                                    </label>
                                    <input type="text" name="name[]" id="default_title" class="form-control"
                                        placeholder="{{ translate('messages.Ex') }}: Size"
                                        value="{{ $attribute?->getRawOriginal('name') }}" maxlength="100" required>
                                </div>
                                <small class="tps-field__hint">
                                    {{ translate('Name the choice itself, not its values — size, not small.') }}
                                </small>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                        </div>
                        @foreach ($language as $lang)
                            @php($translate = [])
                            @foreach ($attribute['translations'] as $t)
                                @if ($t->locale == $lang && $t->key == 'name')
                                    @php($translate[$lang]['name'] = $t->value)
                                @endif
                            @endforeach
                            <div class="d-none lang_form" id="{{ $lang }}-form">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="{{ $lang }}_title">
                                        {{ translate('Name') }} ({{ strtoupper($lang) }})
                                        <span class="tps-opt">{{ translate('Optional') }}</span>
                                    </label>
                                    <input type="text" name="name[]" id="{{ $lang }}_title" class="form-control"
                                        placeholder="{{ translate('messages.Attribute name') }}"
                                        value="{{ $translate[$lang]['name'] ?? '' }}" maxlength="100">
                                    <small class="tps-field__hint">
                                        {{ translate('Leave it empty to fall back to the default name.') }}
                                    </small>
                                </div>
                                <input type="hidden" name="lang[]" value="{{ $lang }}">
                            </div>
                        @endforeach
                    @else
                        <div id="default-form">
                            <div class="tps-field">
                                <div class="error-wrapper">
                                    <label class="tps-field__label" for="default_title">
                                        {{ translate('Name') }} ({{ translate('Default') }})
                                        <span class="tps-req">*</span>
                                    </label>
                                    <input type="text" name="name[]" id="default_title" class="form-control"
                                        placeholder="{{ translate('messages.Ex') }}: Size" value="{{ $attribute['name'] }}"
                                        maxlength="100" required>
                                </div>
                                <small class="tps-field__hint">
                                    {{ translate('Name the choice itself, not its values — size, not small.') }}
                                </small>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                        </div>
                    @endif
                </div>

                <div class="tps-card__foot">
                    <span class="tps-foot-note">{{ translate('An attribute name can only be used once.') }}</span>
                    <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i>
                        {{ translate('messages.Reset') }}</button>
                    <button type="submit" class="btn btn--primary"><i class="tio-save"></i>
                        {{ translate('Update') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        $(".lang_link").click(function (e) {
            e.preventDefault();
            $(".lang_link").removeClass('active');
            $(".lang_form").addClass('d-none');
            $(this).addClass('active');

            let form_id = this.id;
            let lang = form_id.substring(0, form_id.length - 5);
            $("#" + lang + "-form").removeClass('d-none');
        });
    </script>
@endpush
