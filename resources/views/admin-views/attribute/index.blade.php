@extends('layouts.admin.app')

@section('title',translate('messages.attributes'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/attribute.png') }}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('messages.Add new attribute') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Choices such as size or colour that a store attaches to an item when adding it.') }}</p>
        </div>

        <div class="row g-3">
            <div class="col-12 tps">
                <div class="tps-card">
                    <form action="{{ route('admin.attribute.store') }}" method="post" class="custom-validation"
                        data-ajax-form
                        data-ajax-refresh="[data-ajax-region]"
                        data-ajax-reset>
                        @csrf

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
                                            <input type="text" name="name[]" id="default_title"
                                                class="form-control attribute-input"
                                                placeholder="{{ translate('messages.Ex') }}: Size" maxlength="100" required>
                                        </div>
                                        <small class="tps-field__hint">
                                            {{ translate('Name the choice itself, not its values — size, not small.') }}
                                        </small>
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                                @foreach ($language as $lang)
                                    <div class="d-none lang_form" id="{{ $lang }}-form">
                                        <div class="tps-field">
                                            <label class="tps-field__label" for="{{ $lang }}_title">
                                                {{ translate('Name') }} ({{ strtoupper($lang) }})
                                                <span class="tps-opt">{{ translate('Optional') }}</span>
                                            </label>
                                            <input type="text" name="name[]" id="{{ $lang }}_title"
                                                class="form-control attribute-input"
                                                placeholder="{{ translate('messages.Attribute name') }}" maxlength="100">
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
                                            <input type="text" name="name[]" id="default_title"
                                                class="form-control attribute-input"
                                                placeholder="{{ translate('messages.Ex') }}: Size" maxlength="100" required>
                                        </div>
                                        <small class="tps-field__hint">
                                            {{ translate('Name the choice itself, not its values — size, not small.') }}
                                        </small>
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                            @endif

                            <div class="d-flex flex-wrap align-items-center mt-3">
                                <small class="tps-field__hint mr-2 mt-0">{{ translate('Examples') }}:</small>
                                @foreach (['Size', 'Color', 'Weight', 'Capacity', 'Material', 'Flavour'] as $example)
                                    <button type="button"
                                        class="badge badge-soft-primary border-0 attribute-example mr-1 mb-1">{{ $example }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="tps-card__foot">
                            <span class="tps-foot-note">{{ translate('An attribute name can only be used once.') }}</span>
                            <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i>
                                {{ translate('messages.Reset') }}</button>
                            <button type="submit" class="btn btn--primary"><i class="tio-add-circle"></i>
                                {{ translate('Add') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-12">
                <div id="attribute-list-wrapper" data-ajax-region
                    data-ajax-url="{{ url()->full() }}"
                    data-ajax-links=".page-link, .list-reset-search"
                    data-ajax-forms=".search-form">
                    @include('admin-views.attribute.partials._list', [
                        'attributes' => $attributes,
                        'usageStats' => $usageStats,
                        'translatedLocales' => $translatedLocales,
                    ])
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        "use strict";

        function initAttributeTable($root) {
            let $table = ($root ? $($root) : $(document)).find('#columnSearchDatatable');

            if ($table.length && $.HSCore && $.HSCore.components && $.HSCore.components.HSDatatables) {
                $.HSCore.components.HSDatatables.init($table);
            }
        }

        $(document).on('ready', function () {
            initAttributeTable(document);
        });

        if (window.AppAjax) {
            window.AppAjax.onMount(initAttributeTable);
        }

        $(".lang_link").click(function (e) {
            e.preventDefault();
            $(".lang_link").removeClass('active');
            $(".lang_form").addClass('d-none');
            $(this).addClass('active');

            let form_id = this.id;
            let lang = form_id.substring(0, form_id.length - 5);
            $("#" + lang + "-form").removeClass('d-none');
        });

        $(document).on('click', '.attribute-example', function () {
            let $input = $('.lang_form:not(.d-none) .attribute-input').first();

            if (!$input.length) {
                $input = $('.attribute-input').first();
            }

            $input.val($(this).text().trim()).trigger('input').trigger('focus');
        });
    </script>
@endpush
