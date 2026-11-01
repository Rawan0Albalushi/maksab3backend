@extends('layouts.admin.app')

@section('title',translate('Update business module'))

@push('css_or_js')
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/third-party-setup.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/module-setup.css')}}">
@endpush

@section('edit_module')
active
@endsection

@section('content')
@php
    /*
     * One pass over the eager-loaded translations instead of a nested loop per
     * language tab — the controller loads every locale here (the edit form needs
     * them all), so the rows are already in memory.
     */
    $translations = [];
    foreach ($module->translations as $t) {
        $translations[$t->locale][$t->key] = $t->value;
    }

    $typeMeta = config('module.module_type_meta', []);
    $typeIcon = $typeMeta[$module->module_type]['icon'] ?? 'tio-layers-outlined';
    $typeDescription = config('module.'.$module->module_type.'.description');
@endphp
<div class="content container-fluid tps mds">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/module.png')}}" alt="">
                </span>
                <span>
                    {{translate('Edit business module')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Change this module\'s name, artwork or the zones it is switched on in.') }}</p>
        </div>
        <div class="page-header-actions">
            <a href="{{route('admin.business-settings.module.index')}}" class="btn btn--reset">
                <i class="tio-arrow-backward"></i> {{translate('messages.Back')}}
            </a>
        </div>
    </div>

    <form action="{{route('admin.business-settings.module.update',[$module['id']])}}" method="post" enctype="multipart/form-data" class="row g-3">
        @method('PUT')
        @csrf

        <div class="col-xl-8">
            <div class="tps-card">
                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-file-text-outlined"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">{{translate('Module details')}}</h2>
                        <p class="tps-card__subtitle">{{translate('The name and description customers see for this module in the apps and on your landing page.')}}</p>
                    </div>
                </div>
                <div class="tps-card__body">
                    @if($language)
                        <ul class="nav nav-tabs mb-3 border-0">
                            <li class="nav-item">
                                <a class="nav-link lang_link active" href="#" id="default-link">{{translate('Default')}}</a>
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
                                <label class="tps-field__label" for="module_name_default">
                                    {{ translate('Business module name')}} ({{ translate('Default') }})
                                    <span class="tps-req">*</span>
                                </label>
                                <input type="text" name="module_name[]" id="module_name_default" class="form-control" maxlength="191"
                                       value="{{$module?->getRawOriginal('module_name')}}">
                                <small class="tps-field__hint">{{translate('Keep it short — it is the label shown in the module switcher.')}}</small>
                            </div>
                            <div class="tps-field">
                                <label class="tps-field__label" for="description">
                                    {{translate('messages.Description')}} ({{ translate('Default') }})
                                    <span class="tps-req">*</span>
                                </label>
                                <textarea data-value="{!! $module?->getRawOriginal('description') ?? '' !!}" id="description" class="ckeditor form-control" name="description[]">{!! $module?->getRawOriginal('description') ?? '' !!}</textarea>
                                <small class="tps-field__hint">{{ translate('messages.Write a short description of your new business module.') }} {{ translate('Word limit') }}: 100, {{ translate('Character limit') }}: 550</small>
                            </div>
                            <div class="tps-field">
                                <label class="tps-field__label" for="short_description_default">
                                    {{ translate('Short description') }} ({{ translate('Default') }})
                                    <span class="tps-opt">{{ translate('Optional') }}</span>
                                </label>
                                <textarea class="form-control" name="short_description[]" id="short_description_default" maxlength="100" rows="2"
                                          placeholder="{{ translate('messages.Write a short description') }}">{{ $module?->getRawOriginal('short_description') }}</textarea>
                                <small class="tps-field__hint">{{translate('One line that sits under the module name in the module list.')}} {{translate('Character limit')}}: 100</small>
                            </div>
                        </div>
                        <input type="hidden" name="lang[]" value="default">

                        @foreach($language as $lang)
                            <div class="d-none lang_form" id="{{$lang}}-form">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="module_name_{{$lang}}">
                                        {{ translate('Business module name')}} ({{strtoupper($lang)}})
                                        <span class="tps-opt">{{ translate('Optional') }}</span>
                                    </label>
                                    <input type="text" name="module_name[]" id="module_name_{{$lang}}" class="form-control" maxlength="191"
                                           value="{{$translations[$lang]['module_name'] ?? ''}}">
                                    <small class="tps-field__hint">{{translate('Leave it empty to fall back to the default name.')}}</small>
                                </div>
                                <div class="tps-field">
                                    <label class="tps-field__label" for="description{{ $lang }}">
                                        {{translate('messages.Description')}} ({{strtoupper($lang)}})
                                        <span class="tps-opt">{{ translate('Optional') }}</span>
                                    </label>
                                    <textarea data-value="{!! $translations[$lang]['description'] ?? '' !!}" id="description{{ $lang }}" class="ckeditor form-control" name="description[]">{!! $translations[$lang]['description'] ?? '' !!}</textarea>
                                    <small class="tps-field__hint">{{ translate('messages.Write a short description of your new business module.') }} {{ translate('Word limit') }}: 100, {{ translate('Character limit') }}: 550</small>
                                </div>
                                <div class="tps-field">
                                    <label class="tps-field__label" for="short_description_{{$lang}}">
                                        {{ translate('Short description') }} ({{strtoupper($lang)}})
                                        <span class="tps-opt">{{ translate('Optional') }}</span>
                                    </label>
                                    <textarea class="form-control" name="short_description[]" id="short_description_{{$lang}}" maxlength="100" rows="2"
                                              placeholder="{{ translate('messages.Write a short description') }}">{{ $translations[$lang]['short_description'] ?? '' }}</textarea>
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="{{$lang}}">
                        @endforeach
                    @else
                        <div class="tps-field">
                            <label class="tps-field__label" for="module_name_default">
                                {{ translate('Business module name')}} <span class="tps-req">*</span>
                            </label>
                            <input type="text" name="module_name" id="module_name_default" class="form-control" maxlength="191"
                                   value="{{$module?->getRawOriginal('module_name')}}">
                            <small class="tps-field__hint">{{translate('Keep it short — it is the label shown in the module switcher.')}}</small>
                        </div>
                        <div class="tps-field">
                            <label class="tps-field__label" for="description">
                                {{translate('messages.Description')}} <span class="tps-req">*</span>
                            </label>
                            <textarea data-value="{!! $module->description !!}" id="description" class="ckeditor form-control" name="description">{!! $module->description !!}</textarea>
                            <small class="tps-field__hint">{{ translate('messages.Write a short description of your new business module.') }} {{ translate('Word limit') }}: 100, {{ translate('Character limit') }}: 550</small>
                        </div>
                        <div class="tps-field">
                            <label class="tps-field__label" for="short_description_default">
                                {{ translate('Short description') }} <span class="tps-opt">{{ translate('Optional') }}</span>
                            </label>
                            <textarea class="form-control" name="short_description" id="short_description_default" maxlength="100" rows="2"
                                      placeholder="{{ translate('messages.Write a short description') }}">{{ $module?->getRawOriginal('short_description') }}</textarea>
                            <small class="tps-field__hint">{{translate('One line that sits under the module name in the module list.')}} {{translate('Character limit')}}: 100</small>
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    @endif
                </div>
            </div>

            {{-- The type is fixed at creation, so the form states the one that is
                 set instead of disabling a list of radios nobody can move. No
                 input is posted either — ModuleUpdateRequest does not accept one. --}}
            <div class="tps-card mt-3">
                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-category-outlined"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">{{translate('Business module type')}}</h2>
                        <p class="tps-card__subtitle">{{translate('The type decides how stores, items and orders behave. It cannot be changed after the module is created.')}}</p>
                    </div>
                    <div class="tps-card__aside">
                        <span class="badge badge-soft-secondary"><i class="tio-lock-outlined"></i> {{ translate('Not editable') }}</span>
                    </div>
                </div>
                <div class="tps-card__body">
                    <div class="mds-locked-type">
                        <span class="mds-type__icon"><i class="{{$typeIcon}}"></i></span>
                        <div class="mds-locked-type__body">
                            <h6 class="mds-locked-type__name">{{translate($module->module_type)}}</h6>
                            @if($typeDescription)
                                <p class="mds-locked-type__desc">{{$typeDescription}}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="tps-card">
                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-photo-gallery"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">{{translate('Choose related images')}}</h2>
                        <p class="tps-card__subtitle">{{translate('The icon shows in the module switcher, the thumbnail on your landing page.')}}</p>
                    </div>
                </div>
                <div class="tps-card__body">
                    <div class="mds-media">
                        <label class="mds-upload">
                            <span class="mds-upload__label">
                                {{translate('messages.Icon')}} <span class="mds-upload__ratio">{{translate('messages.Ratio')}} 1:1</span>
                            </span>
                            <input type="file" name="icon" id="customFileEg1" accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                            <span class="mds-upload__frame">
                                <img id="viewer" class="onerror-image" src="{{ $module['icon_full_url'] }}"
                                     data-onerror-image="{{asset('public/assets/admin/img/upload-img.png')}}"
                                     alt="{{translate('messages.Icon')}}">
                                <span class="mds-upload__action"><i class="tio-edit"></i> {{translate('messages.Change')}}</span>
                            </span>
                            <small class="mds-upload__hint">JPG, PNG, WEBP &middot; max 2 MB</small>
                        </label>

                        <label class="mds-upload">
                            <span class="mds-upload__label">
                                {{translate('Thumbnail')}} <span class="mds-upload__ratio">{{translate('messages.Ratio')}} 1:1</span>
                            </span>
                            <input type="file" name="thumbnail" id="customFileEg2" accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                            <span class="mds-upload__frame">
                                <img id="viewer2" class="onerror-image" src="{{ $module['thumbnail_full_url'] }}"
                                     data-onerror-image="{{asset('public/assets/admin/img/upload-img.png')}}"
                                     alt="{{translate('Thumbnail')}}">
                                <span class="mds-upload__action"><i class="tio-edit"></i> {{translate('messages.Change')}}</span>
                            </span>
                            <small class="mds-upload__hint">JPG, PNG, WEBP &middot; max 2 MB</small>
                        </label>
                    </div>
                </div>
            </div>

            <div class="tps-card mt-3">
                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-info-outined"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">{{translate('At a glance')}}</h2>
                    </div>
                </div>
                <div class="tps-card__body">
                    <dl class="mds-facts">
                        <dt>{{translate('messages.Module ID')}}</dt>
                        <dd>#{{$module['id']}}</dd>
                        <dt>{{translate('messages.Status')}}</dt>
                        <dd>
                            <span class="tps-pill tps-pill--{{$module->status ? 'on' : 'off'}}">
                                {{$module->status ? translate('messages.Active') : translate('messages.Inactive')}}
                            </span>
                        </dd>
                        <dt>{{translate('All zones')}}</dt>
                        <dd>{{$module->all_zone_service ? translate('messages.Yes') : translate('messages.No')}}</dd>
                        <dt>{{translate('messages.Created at')}}</dt>
                        <dd>{{$module->created_at ? \App\CentralLogics\Helpers::date_format($module->created_at) : '-'}}</dd>
                    </dl>
                    <p class="tps-card__subtitle mt-3 mb-0">
                        {{translate('Turn the module on or off from the module list.')}}
                    </p>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="tps-card mds-actions">
                <div class="tps-card__foot">
                    <span class="tps-foot-note">{{translate('Changes go live for customers as soon as you save.')}}</span>
                    <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{translate('messages.Save changes')}}</button>
                </div>
            </div>
        </div>
    </form>
</div>

@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/ckeditor/ckeditor.js')}}"></script>
    <script>
        "use strict";

        function readURL(input, id) {
            if (input.files && input.files[0]) {
                let reader = new FileReader();

                reader.onload = function (e) {
                    $('#'+id).attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function () {
            readURL(this,'viewer');
        });

        $("#customFileEg2").change(function () {
            readURL(this,'viewer2');
        });

        $(".lang_link").click(function(e) {
            e.preventDefault();
            $(".lang_link").removeClass('active');
            $(".lang_form").addClass('d-none');
            $(this).addClass('active');

            let form_id = this.id;
            let lang = form_id.substring(0, form_id.length - 5);
            $("#" + lang + "-form").removeClass('d-none');
        });

        $(document).ready(function () {
            $('.ckeditor').ckeditor();
        });

        $('#reset_btn').click(function(){
            $('.ckeditor').each(function() {
                CKEDITOR.instances[$(this).attr('id')].setData($(this).data('value'));
            });

            $('#viewer').attr('src','{{ $module['icon_full_url'] }}');
            $('#viewer2').attr('src','{{ $module['thumbnail_full_url'] }}');
        })
</script>
@endpush
