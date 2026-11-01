@extends('layouts.admin.app')

@section('title',translate('Business modules'))

@push('css_or_js')
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/third-party-setup.css')}}">
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/module-setup.css')}}">
@endpush

@section('content')
@php
    /*
     * The types an admin may create by hand. rental / ride-share / service are
     * provisioned by their add-on, and ModuleAddRequest rejects them outright
     * (`not_in:rental,ride-share,service`) — so they are not offered here.
     *
     * The one-liners are literal translate() keys rather than the paragraph in
     * config/module.php: that copy is English-only and too long for a card.
     * They summarise the flags the type actually switches on.
     */
    $typeMeta = config('module.module_type_meta', []);
    $typeSummaries = [
        'grocery'   => translate('Stock is tracked per item, and delivery slots start a set time after the order.'),
        'food'      => translate('Add-ons and per-item availability windows. No stock tracking.'),
        'pharmacy'  => translate('Customers can attach a prescription at checkout. Stock is tracked per item.'),
        'ecommerce' => translate('Stores stay open around the clock. Stock is tracked per item.'),
        'parcel'    => translate('Point to point delivery. No catalogue, no stock, no store hours.'),
    ];
    $creatableTypes = array_values(array_filter(config('module.module_type'), fn ($key) => !in_array($key, ['rental', 'ride-share', 'service'])));
@endphp
<div class="content container-fluid tps mds">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('/public/assets/admin/img/module.png')}}" alt="">
                </span>
                <span>
                    {{translate('Add new business module')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('A module is one line of business, such as food or parcel, with its own stores and rules.') }}</p>
        </div>
        <div class="page-header-actions">
            <a href="{{route('admin.business-settings.module.index')}}" class="btn btn--reset">
                <i class="tio-arrow-backward"></i> {{translate('messages.Back')}}
            </a>
        </div>
    </div>

    <div class="alert alert-soft-primary alert-dismissible fade show d-flex" role="alert">
        <div>
            <img src="{{asset('/public/assets/admin/img/icons/intel.png')}}" width="22" alt="">
        </div>
        <div class="w-0 flex-grow-1 pl-3">
            <strong>{{ translate('Attention!') }}</strong> {{ translate('Don\'t forget to click the \'add module\' button below to save the new business module') }}
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="{{translate('messages.Close')}}">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <form action="{{route('admin.business-settings.module.store')}}" method="post" enctype="multipart/form-data" class="row g-3">
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
                                    {{translate('Business module name')}} ({{ translate('Default') }})
                                    <span class="tps-req">*</span>
                                </label>
                                <input type="text" name="module_name[]" id="module_name_default" class="form-control" maxlength="191"
                                       value="{{old('module_name.0')}}"
                                       placeholder="{{ translate('messages.Ex') . ': ' . 'Grocery, eCommerce, Pharmacy, etc.' }}">
                                <small class="tps-field__hint">{{translate('Keep it short — it is the label shown in the module switcher.')}}</small>
                            </div>
                            <div class="tps-field">
                                <label class="tps-field__label" for="description">
                                    {{ translate('Business module description')}} ({{ translate('Default') }})
                                    <span class="tps-req">*</span>
                                </label>
                                <textarea id="description" class="ckeditor form-control" name="description[]">{!! old('description.0') !!}</textarea>
                                <small class="tps-field__hint">{{ translate('messages.Write a short description of your new business module.') }} {{ translate('Word limit') }}: 100, {{ translate('Character limit') }}: 550</small>
                            </div>
                            <div class="tps-field">
                                <label class="tps-field__label" for="short_description_default">
                                    {{ translate('Short description') }} ({{ translate('Default') }})
                                    <span class="tps-opt">{{ translate('Optional') }}</span>
                                </label>
                                <textarea class="form-control" name="short_description[]" id="short_description_default" maxlength="100" rows="2"
                                          placeholder="{{ translate('messages.Write a short description') }}">{{old('short_description.0')}}</textarea>
                                <small class="tps-field__hint">{{translate('One line that sits under the module name in the module list.')}} {{translate('Character limit')}}: 100</small>
                            </div>
                        </div>
                        <input type="hidden" name="lang[]" value="default">

                        @foreach($language as $lang)
                            @php($langIndex = $loop->iteration)
                            <div class="d-none lang_form" id="{{$lang}}-form">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="module_name_{{$lang}}">
                                        {{translate('Business module name')}} ({{strtoupper($lang)}})
                                        <span class="tps-opt">{{ translate('Optional') }}</span>
                                    </label>
                                    <input type="text" name="module_name[]" id="module_name_{{$lang}}" class="form-control" maxlength="191"
                                           value="{{old('module_name.'.$langIndex)}}"
                                           placeholder="{{ translate('messages.Ex') . ': ' . 'Grocery, eCommerce, Pharmacy, etc.' }}">
                                    <small class="tps-field__hint">{{translate('Leave it empty to fall back to the default name.')}}</small>
                                </div>
                                <div class="tps-field">
                                    <label class="tps-field__label" for="description{{ $lang }}">
                                        {{ translate('Business module description')}} ({{strtoupper($lang)}})
                                        <span class="tps-opt">{{ translate('Optional') }}</span>
                                    </label>
                                    <textarea id="description{{ $lang }}" class="ckeditor form-control" name="description[]">{!! old('description.'.$langIndex) !!}</textarea>
                                    <small class="tps-field__hint">{{ translate('messages.Write a short description of your new business module.') }} {{ translate('Word limit') }}: 100, {{ translate('Character limit') }}: 550</small>
                                </div>
                                <div class="tps-field">
                                    <label class="tps-field__label" for="short_description_{{$lang}}">
                                        {{ translate('Short description') }} ({{strtoupper($lang)}})
                                        <span class="tps-opt">{{ translate('Optional') }}</span>
                                    </label>
                                    <textarea class="form-control" name="short_description[]" id="short_description_{{$lang}}" maxlength="100" rows="2"
                                              placeholder="{{ translate('messages.Write a short description') }}">{{old('short_description.'.$langIndex)}}</textarea>
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="{{$lang}}">
                        @endforeach
                    @else
                        <div class="tps-field">
                            <label class="tps-field__label" for="module_name_default">
                                {{translate('Business module name')}} <span class="tps-req">*</span>
                            </label>
                            <input type="text" name="module_name" id="module_name_default" class="form-control" value="{{old('module_name')}}" maxlength="191"
                                   placeholder="{{ translate('messages.Ex') }}: Business Module Name">
                            <small class="tps-field__hint">{{translate('Keep it short — it is the label shown in the module switcher.')}}</small>
                        </div>
                        <div class="tps-field">
                            <label class="tps-field__label" for="description">
                                {{ translate('Business module description')}} <span class="tps-req">*</span>
                            </label>
                            <textarea id="description" class="ckeditor form-control" name="description">{!! old('description') !!}</textarea>
                            <small class="tps-field__hint">{{ translate('messages.Write a short description of your new business module.') }} {{ translate('Word limit') }}: 100, {{ translate('Character limit') }}: 550</small>
                        </div>
                        <div class="tps-field">
                            <label class="tps-field__label" for="short_description_default">
                                {{ translate('Short description') }} <span class="tps-opt">{{ translate('Optional') }}</span>
                            </label>
                            <textarea class="form-control" name="short_description" id="short_description_default" maxlength="100" rows="2"
                                      placeholder="{{ translate('messages.Write a short description') }}">{{old('short_description')}}</textarea>
                            <small class="tps-field__hint">{{translate('One line that sits under the module name in the module list.')}} {{translate('Character limit')}}: 100</small>
                        </div>
                        <input type="hidden" name="lang[]" value="default">
                    @endif
                </div>
            </div>

            <div class="tps-card mt-3">
                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-category-outlined"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">
                            {{translate('Select business module type')}} <span class="tps-req">*</span>
                        </h2>
                        <p class="tps-card__subtitle">{{translate('The type decides how stores, items and orders behave. It cannot be changed after the module is created.')}}</p>
                    </div>
                </div>
                <div class="tps-card__body">
                    <div class="mds-types">
                        @foreach ($creatableTypes as $key)
                            <label class="tps-choice">
                                <input type="radio" name="module_type" value="{{$key}}" {{old('module_type') === $key ? 'checked' : ''}}>
                                <span class="tps-choice__box">
                                    <span class="mds-type__icon"><i class="{{$typeMeta[$key]['icon'] ?? 'tio-layers-outlined'}}"></i></span>
                                    <span>
                                        <span class="tps-choice__title text-capitalize">{{translate($key)}}</span>
                                        @if(isset($typeSummaries[$key]))
                                            <span class="tps-choice__desc">{{$typeSummaries[$key]}}</span>
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
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
                                <img id="viewer" src="{{asset('public/assets/admin/img/upload-img.png')}}" alt="{{translate('messages.Icon')}}">
                                <span class="mds-upload__action"><i class="tio-upload"></i> {{translate('messages.Upload')}}</span>
                            </span>
                            <small class="mds-upload__hint">JPG, PNG, WEBP &middot; max 2 MB</small>
                        </label>

                        <label class="mds-upload">
                            <span class="mds-upload__label">
                                {{translate('Thumbnail')}} <span class="mds-upload__ratio">{{translate('messages.Ratio')}} 1:1</span>
                            </span>
                            <input type="file" name="thumbnail" id="customFileEg2" accept=".webp, .jpg, .png, .jpeg, .gif, .bmp, .tif, .tiff|image/*">
                            <span class="mds-upload__frame">
                                <img id="viewer2" src="{{asset('public/assets/admin/img/upload-img.png')}}" alt="{{translate('Thumbnail')}}">
                                <span class="mds-upload__action"><i class="tio-upload"></i> {{translate('messages.Upload')}}</span>
                            </span>
                            <small class="mds-upload__hint">JPG, PNG, WEBP &middot; max 2 MB</small>
                        </label>
                    </div>
                </div>
            </div>

            <div class="tps-note tps-note--info mt-3">
                <i class="tio-lightbulb"></i>
                <div>
                    <p>{{translate('A new module is created switched off. Add it to a zone under zone setup, then turn it on from the module list when you are ready for stores to use it.')}}</p>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="tps-card mds-actions">
                <div class="tps-card__foot">
                    <span class="tps-foot-note">{{translate('The module type cannot be changed later.')}}</span>
                    <button type="reset" id="reset_btn" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    <button type="submit" class="btn btn--primary"><i class="tio-add-circle"></i> {{translate('Add module')}}</button>
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

        const moduleUploadPlaceholder = '{{asset('public/assets/admin/img/upload-img.png')}}';

        function readURL(input, id) {
            if (input.files && input.files[0]) {
                let reader = new FileReader();

                reader.onload = function(e) {
                    $('#' + id).attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function() {
            readURL(this, 'viewer');
        });

        $("#customFileEg2").change(function() {
            readURL(this, 'viewer2');
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
                CKEDITOR.instances[$(this).attr('id')].setData('');
            });
            $('#viewer, #viewer2').attr('src', moduleUploadPlaceholder);
        })
</script>
@endpush
