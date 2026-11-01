@php
    /* Whole phrases rather than translate('messages.edit_' . $title): a key
       built at runtime is invisible to the translation tooling, and the
       concatenated forms were never in messages.php. $title is 'Store' or
       'Provider' (shared from AppServiceProvider). */
    $L = $title === 'Provider'
        ? ['edit' => translate('Edit provider'), 'edit_info' => translate('messages.edit_provider_info'),
           'name' => translate('messages.Provider name'), 'entity' => translate('messages.Provider')]
        : ['edit' => translate('Edit store'), 'edit_info' => translate('messages.Edit store information'),
           'name' => translate('Store name'), 'entity' => translate('messages.Store')];
@endphp
@extends('layouts.vendor.app')
@section('title', $L['edit'])
@push('css_or_js')

@endpush
@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/store.png') }}" alt="">
                </span>
                <span>
                    {{ $L['edit_info'] }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('The name, logo, cover and contact details customers see for your store.') }}</p>
        </div>


        <form action="{{route('vendor.shop.update')}}" method="post"
                enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                @if($language)
                            <ul class="nav nav-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active"
                                    href="#"
                                    id="default-link">{{ translate('Default') }}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link"
                                            href="#"
                                            id="{{ $lang }}-link">{{ $language_labels[$lang] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                            @endif
                            <div class="col-12">
                                    @if ($language)
                                    <div class="lang_form"
                                    id="default-form">
                                        <div class="form-group">
                                            <label class="input-label"
                                                for="default_name">{{ translate('Name') }}
                                                ({{ translate('Default') }}) <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="name[]" id="default_name"
                                                class="form-control" placeholder="{{ $L['name'] }}" value="{{$shop->getRawOriginal('name')}}"

                                                 >
                                        </div>
                                        <input type="hidden" name="lang[]" value="default">
                                        <div class="form-group mb-0">
                                            <label class="input-label"
                                                for="exampleFormControlInput1">{{ translate('messages.Address') }} ({{ translate('Default') }}) <span class="text-danger">*</span></label>
                                            <textarea type="text" name="address[]" placeholder="{{ $L['entity'] }}" class="form-control min-h-90px ckeditor">{{$shop->getRawOriginal('address')}}</textarea>
                                        </div>
                                    </div>
                                        @foreach ($language as $lang)
                                        <?php
                                            if(count($shop['translations'])){
                                                $translate = [];
                                                foreach($shop['translations'] as $t)
                                                {
                                                    if($t->locale == $lang && $t->key=="name"){
                                                        $translate[$lang]['name'] = $t->value;
                                                    }
                                                    if($t->locale == $lang && $t->key=="address"){
                                                        $translate[$lang]['address'] = $t->value;
                                                    }
                                                }
                                            }
                                        ?>
                                            <div class="d-none lang_form"
                                                id="{{ $lang }}-form">
                                                <div class="form-group">
                                                    <label class="input-label"
                                                        for="{{ $lang }}_name">{{ translate('Name') }}
                                                        ({{ strtoupper($lang) }})
                                                    </label>
                                                    <input type="text" name="name[]" id="{{ $lang }}_name"
                                                        class="form-control" value="{{ $translate[$lang]['name']??'' }}" placeholder="{{ translate('Store name') }}"
                                                         >
                                                </div>
                                                <input type="hidden" name="lang[]" value="{{ $lang }}">
                                                <div class="form-group mb-0">
                                                    <label class="input-label"
                                                        for="exampleFormControlInput1">{{ translate('messages.Address') }} ({{ strtoupper($lang) }})</label>
                                                    <textarea type="text" name="address[]" placeholder="{{translate('messages.Store')}}" class="form-control min-h-90px ckeditor">{{ $translate[$lang]['address']??'' }}</textarea>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div id="default-form">
                                            <div class="form-group">
                                                <label class="input-label"
                                                    for="exampleFormControlInput1">{{ translate('Name') }} ({{ translate('Default') }})</label>
                                                <input type="text" name="name[]" class="form-control"
                                                    placeholder="{{ translate('Store name') }}" required>
                                            </div>
                                            <input type="hidden" name="lang[]" value="default">
                                            <div class="form-group mb-0">
                                                <label class="input-label"
                                                    for="exampleFormControlInput1">{{ translate('messages.Address') }}
                                                </label>
                                                <textarea type="text" name="address[]" placeholder="{{translate('messages.Store')}}" class="form-control min-h-90px ckeditor"></textarea>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="form-group mt-2">
                                        <label for="name">{{translate('Contact number')}} <span class="text-danger">*</span></label>
                                        <input type="tel" name="contact" value="{{$shop->phone}}" class="form-control" id="name"
                                                required>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title font-regular">
                                {{translate('Upload logo')}} <span class="text-danger">*</span>
                            </h5>
                        </div>
                        <div class="card-body d-flex flex-column pt-0">


                              <div class="mx-auto text-center">
                                            @include('admin-views.partials._image-uploader', [
                                                    'id' => 'image-input',
                                                    'name' => 'image',
                                                    'ratio' => '1:1',
                                                    'isRequired' => true,
                                                    'existingImage' => $shop->logo_full_url,
                                                    'imageExtension' => IMAGE_EXTENSION,
                                                    'imageFormat' => IMAGE_FORMAT,
                                                    'maxSize' => MAX_FILE_SIZE,
                                                    ])
                                        </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title font-regular">
                                {{translate('Upload cover photo')}} <span class="text-danger">({{translate('messages.Ratio')}} 2:1)</span>
                                <span class="text-danger">*</span>
                            </h5>
                        </div>
                        <div class="card-body d-flex flex-column pt-0">

                             <div class="mx-auto text-center">
                                            @include('admin-views.partials._image-uploader', [
                                                    'id' => 'image-input',
                                                    'name' => 'photo',
                                                    'ratio' => '2:1',
                                                    'isRequired' => true,
                                                    'existingImage' => $shop->cover_photo_full_url,
                                                    'imageExtension' => IMAGE_EXTENSION,
                                                    'imageFormat' => IMAGE_FORMAT,
                                                    'maxSize' => MAX_FILE_SIZE,
                                                    ])
                                        </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-3 justify-content-end btn--container">
                <a class="btn btn--danger text-capitalize" href="{{route('vendor.shop.view')}}"><i class="tio-clear-circle-outlined"></i> {{translate('messages.Cancel')}}</a>
                <button type="submit" class="btn btn--primary text-capitalize" id="btn_update"><i class="tio-save"></i> {{translate('Update')}}</button>
            </div>
        </form>
    </div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/vendor/shop-edit.js"></script>
@endpush
