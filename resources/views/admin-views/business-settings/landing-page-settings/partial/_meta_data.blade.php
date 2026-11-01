@php
    $data = [];
    $image_name = 'meta_image';
    if (isset($pageMetaData)) {
        $data['title'] = $pageMetaData->title;
        $data['description'] = $pageMetaData->description;
        $data['image'] = $pageMetaData?->image ? $pageMetaData->imageFullUrl:'';
        $data['meta_data'] = $pageMetaData->meta_data ?? [];
    } elseif (isset($landingData)) {
        $data['title'] = isset($landingData['meta_title']) ? $landingData['meta_title']->getRawOriginal('value') : '';
        $data['description'] = isset($landingData['meta_description']) ? $landingData['meta_description']->getRawOriginal('value') : '';
        $data['image'] = isset($landingData['meta_image']) ? \App\CentralLogics\Helpers::get_full_url('landing/meta_image', $landingData['meta_image']->getRawOriginal('value'), $landingData['meta_image']->storage[0]->value ?? 'public', 'aspect_1') : '';
        $data['meta_data'] = isset($landingData['meta_data']) ? json_decode($landingData['meta_data']->getRawOriginal('value'), true) : [];
    } elseif (isset($store)) {
        $data['title'] = $store->meta_title;
        $data['description'] = $store->meta_description;
        $data['image'] = $store->meta_image ? $store->meta_image_full_url : '';
        $data['meta_data'] = isset($store->meta_data) ? (is_string($store->meta_data) ? json_decode($store->meta_data, true) : $store->meta_data) : [];
        $image_name = 'meta_image';
    } elseif (isset($item)) {
        $data['title'] = $item->seoData?->title;
        $data['description'] = $item->seoData?->description;
        $data['image'] = isset($item->seoData->image) ? $item->seoData->image_full_url : '';
        $data['meta_data'] = isset($item->seoData->meta_data) ? (is_string($item->seoData->meta_data) ? json_decode($item->seoData->meta_data, true) : $item->seoData->meta_data) : [];
        $image_name = 'meta_image';
    } else {
        $data['title'] = '';
        $data['description'] = '';
        $data['image'] = '';
        $data['meta_data'] = [];
        $image_name = 'meta_image';
    }
    $metaData = $data['meta_data'] ?? [];
@endphp

<div class="card">
    <div class="card-header flex-sm-nowrap flex-wrap pt-3 pb-3 gap-2">
        <div class="">
            <h4 class="fs-16 text-dark">{{ translate('Meta data setup')}}</h4>
            <p class="fs-12 mb-0">{{ translate('Include meta information to improve search engine visibility and social media sharing')}}</p>
        </div>
        @if(Request::is('admin/business-settings/seo-settings*'))
        <a href="{{ route('admin.business-settings.seo-settings.pageMetaData') }}" class="theme-clr text-nowrap text-underline fs-14 font-weight-medium">
            {{ translate('Back to list') }}
        </a>
        @endif
    </div>
    <div class="card-body">

        <div class="row g-4">
            <div class="col-xxl-8 col-lg-7">
                <div class="bg-light2 rounded p-sm-4 p-3 h-100">
                    <div class="d-flex flex-column gap-2">
                        <div class="form-group m-0">
                            <label for="" class>
                                {{ translate('Meta title') }}
                                <span data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('Shown in browser tabs, search results and link previews. Keep it short and keyword-focused.') }}"><i class="tio-info text-muted fs-14"></i></span>
                            </label>
                            <textarea name="meta_title" type="text" rows="1" maxlength="100" placeholder="{{ translate('Ex') . ':' . translate('Type meta title') }}" class="form-control">{{ $data['title'] }}</textarea>
                            <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
                        </div>
                        <div class="form-group m-0">
                            <label for="" class>
                                {{ translate('Meta description') }}
                                <span data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('A brief summary that appears under your page title in search results. Keep it compelling and relevant.') }} {{ translate('Recommended character range') }}: 120-160"><i class="tio-info text-muted fs-14"></i></span>
                            </label>
                            <textarea name="meta_description" type="text" rows="4" maxlength="200" placeholder="{{ translate('Type a short meta description') }}" class="form-control">{{ $data['description']}}</textarea>
                            <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/200</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-4 col-lg-5">
                <div class="bg-light2 d-center rounded p-4 h-100">
                    <div class="">
                        <div class="mb-30 text-center">
                            <h4 class="mb-1">{{ translate('Meta image') }} </h4>
                            <p class="mb-0 fs-12 gray-dark">
                                {{translate('Upload a rectangular image')}} 
                            </p>
                        </div>
                        @include('admin-views.partials._image-uploader', [
                            'name' => $image_name,
                            'id' => 'meta_data_image',
                            'existingImage' => $data['image'] ?? '',
                            'ratio' => '2:1',
                            'textPosition' => 'bottom',
                        ])
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="bg-light2 rounded p-sm-4 p-3 h-100">
                    <div class="resturant-type-group gap-2 py-3 px-3 bg-white rounded mb-20">
                        <label class="form-check flex-grow-1 form--check">
                            <input class="form-check-input" type="radio" value="1" name="meta_index" {{ ($metaData['meta_index'] ?? '') != 0 ? 'checked' : '' }}>
                            <span class="form-check-label">{{ translate('Index') }}</span>
                            <span class="ms-4px" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Allow search engines to put this web page on their list or index & show it on search results') }}">
                                <i class="tio-info text-muted fs-14"></i>
                            </span>
                        </label>
                        <label class="form-check flex-grow-1 form--check">
                            <input class="form-check-input" type="radio" value="0" name="meta_index"{{ ($metaData['meta_index'] ?? '') == 0 ? 'checked' : '' }}>
                            <span class="form-check-label">{{ translate('No index') }}</span>
                            <span class="ms-4px" data-toggle="tooltip" data-placement="right" data-original-title="{{ translate('Keeps this page out of search engine indexes and results.') }}">
                                <i class="tio-info text-muted fs-14"></i>
                            </span>
                        </label>
                    </div>
                    <div class="bg-white rounded follow-type-group py-3 px-3">
                        <label class="custom_checkbox d-flex align-items-center gap-1 flex-grow-1 m-0">
                            <input type="checkbox" value="nofollow" name="meta_no_follow" {{ ($metaData['meta_no_follow'] ?? '') == 'nofollow' ? 'checked' : '' }}>
                            <span class="text-nowrap label-text">{{ translate('No follow') }}</span>
                            <span class="ms-4px" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('Instruct search engines not to follow links from this webpage.') }}">
                                <i class="tio-info text-muted fs-14"></i>
                            </span>
                        </label>
                        <label class="custom_checkbox d-flex align-items-center gap-1 flex-grow-1 m-0">
                            <input type="checkbox" value="noimageindex" name="meta_no_image_index" {{ ($metaData['meta_no_image_index'] ?? '') == 'noimageindex' ? 'checked' : '' }}>
                            <span class="text-nowrap label-text">{{ translate('No image index') }}</span>
                            <span class="ms-4px" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('Prevent images from being listed or indexed by search engines') }}">
                                <i class="tio-info text-muted fs-14"></i>
                            </span>
                        </label>
                        <label class="custom_checkbox d-flex align-items-center gap-1 flex-grow-1 m-0">
                            <input type="checkbox" value="noarchive" name="meta_no_archive" {{ ($metaData['meta_no_archive'] ?? '') == 'noarchive' ? 'checked' : '' }}>
                            <span class="text-nowrap label-text">{{ translate('No archive') }}</span>
                            <span class="ms-4px" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('Instruct search engines not to display this webpage\'s cached or saved version') }}">
                                <i class="tio-info text-muted fs-14"></i>
                            </span>
                        </label>
                        <label class="custom_checkbox d-flex align-items-center gap-1 flex-grow-1 m-0">
                            <input type="checkbox" value="nosnippet" name="meta_no_snippet" {{ ($metaData['meta_no_snippet'] ?? '') == 'nosnippet' ? 'checked' : '' }}>
                            <span class="text-nowrap label-text">{{ translate('No snippet') }}</span>
                            <span class="ms-4px" data-toggle="tooltip" data-placement="right"
                                data-original-title="{{ translate('Instruct search engines not to show a summary or snippet of this webpage\'s content in search results.') }}">
                                <i class="tio-info text-muted fs-14"></i>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="bg-light2 rounded p-sm-4 p-3 h-100">
                    <div class="bg-white rounded py-3 px-3">
                        <div class="row g-1 align-items-center mb-3">
                            <div class="col-sm-6">
                                <label class="custom_checkbox d-flex align-items-center gap-1 flex-grow-1 m-0">
                                    <input type="checkbox" value="1" name="meta_max_snippet" {{ ($metaData['meta_max_snippet'] ?? '') == 1 ? 'checked' : '' }}>
                                    <span class="label-text">{{ translate('Max snippet') }}</span>
                                    <span class="ms-4px" data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('Determine the maximum length of a snippet or preview text of the webpage.') }}">
                                        <i class="tio-info text-muted fs-14"></i>
                                    </span>
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group m-0">
                                    <input type="text" placeholder="0" class="form-control min-h-35px h--35px"
                                    name="meta_max_snippet_value"
                                    value="{{ $metaData['meta_max_snippet_value'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row g-1 align-items-center mb-3">
                            <div class="col-sm-6">
                                <label class="custom_checkbox d-flex align-items-center gap-1 flex-grow-1 m-0">
                                    <input type="checkbox" value="1" name="meta_max_video_preview" {{ ($metaData['meta_max_video_preview'] ?? '') == 1 ? 'checked' : '' }}>
                                    <span class="label-text">{{ translate('Max video preview') }}</span>
                                    <span class="ms-4px" data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('Determine the maximum duration of a video preview that search engines will display') }}">
                                        <i class="tio-info text-muted fs-14"></i>
                                    </span>
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group m-0">
                                    <input type="text" placeholder="10" class="form-control min-h-35px h--35px"
                                    name="meta_max_video_preview_value"
                                    value="{{ $metaData['meta_max_video_preview_value'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row g-1 align-items-center">
                            <div class="col-sm-6">
                                <label class="custom_checkbox d-flex align-items-center gap-1 flex-grow-1 m-0">
                                    <input type="checkbox" value="1" name="meta_max_image_preview" {{ ($metaData['meta_max_image_preview'] ?? '') == 1 ? 'checked' : '' }}>
                                    <span class="label-text">{{ translate('Max image preview') }}</span>
                                    <span class="ms-4px" data-toggle="tooltip" data-placement="right"
                                        data-original-title="{{ translate('Determine the maximum size or dimensions of an image preview that search engines will display') }}">
                                        <i class="tio-info text-muted fs-14"></i>
                                    </span>
                                </label>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group m-0">
                                    <select name="meta_max_image_preview_value" class="min-h-35px h--35px custom-select py-1" id="">
                                        <option value="large" {{ ($metaData['meta_max_image_preview_value'] ?? '') == 'large' ? 'selected' : '' }}>{{ translate('large') }}</option>
                                        <option value="medium" {{ ($metaData['meta_max_image_preview_value'] ?? '') == 'medium' ? 'selected' : '' }}>{{ translate('medium') }}</option>
                                        <option value="small" {{ ($metaData['meta_max_image_preview_value'] ?? '') == 'small' ? 'selected' : '' }}>{{ translate('small') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
       @if(isset($submit) && $submit)
       <div class="btn--container justify-content-end mt-4">
            <button type="reset" id="reset_btn" class="btn btn--reset min-w-120px"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
            <button type="submit" class="btn btn--primary min-w-120px"><i class="tio-save"></i> {{ translate('Save') }}</button>
        </div>
        @endif
    </div>
</div>

<div id="global_guideline_offcanvas" class="custom-offcanvas d-flex flex-column justify-content-between">
    <form action="{{ route('taxvat.store') }}" method="post">
        <div>
            <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                <div class="py-1">
                    <h3 class="mb-0 line--limit-1">{{ translate('Meta data setup') }}</h3>
                </div>
                <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="custom-offcanvas-body custom-offcanvas-body-100  p-20">
                <div class="">
                    <div class="py-3 px-3 bg-light rounded mb-3">
                        <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                            <button class="btn-collapse line--limit-1 d-flex gap-3 align-items-center bg-transparent border-0 p-0 collapse show" type="button"
                                    data-toggle="collapse" data-target="#collapseGeneralSetup_01" aria-expanded="true">
                                <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                    <i class="tio-down-ui top-01 color-656566"></i>
                                </div>
                                <span class="font-semibold text-left fs-14 text-title line--limit-1">{{ translate('What is metadata setup for pages?') }}</span>
                            </button>
                        </div>
                        <div class="collapse mt-3 show" id="collapseGeneralSetup_01">
                            <div class="card rounded border p-3 card-body">
                                <div class="mb-3">
                                    <p class="m-0 fs-12 color-656566">
                                        <strong>{{ translate('Meta data setup') }}</strong> {{ translate('Allows you to define how each page of your e-commerce site appears in') }}:
                                    </p>
                                </div>
                                <div class="mb-3">
                                    <ul class="mb-0 list-group pl-3 d-flex flex-column gap-1px">
                                        <li class="fs-12 color-656566"><strong>{{translate('Search engines')}}</strong>  (Google, Bing, etc.)</li>
                                        <li class="fs-12 color-656566"><strong>{{translate('Social media shares')}}</strong>  (Facebook, WhatsApp, Twitter, LinkedIn)</li>
                                    </ul>
                                </div>
                                <p class="m-0 fs-12 color-656566">
                                    <strong>{{ translate('Important note') }}:</strong> {{ translate('Metadata does not change page content, but it strongly affects visibility, traffic, and click-through rate.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="p-12 p-sm-20 bg-light rounded mb-3">
                        <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                            <button class="btn-collapse line--limit-1 d-flex gap-3 align-items-center bg-transparent border-0 p-0 collapsed" type="button"
                                    data-toggle="collapse" data-target="#collapseGeneralSetup_032" aria-expanded="true">
                                <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1 collapsed">
                                    <i class="tio-down-ui top-01 color-656566"></i>
                                </div>
                                <span class="font-semibold text-left fs-14 text-title line--limit-1">{{ translate('Why set up metadata for pages?') }}</span>
                            </button>
                        </div>
                        <div class="collapse mt-3" id="collapseGeneralSetup_032">
                            <div class="card rounded border p-3 card-body"> 
                                <div class="mb-3">
                                    <p class="m-0 font-weight-medium color-656566 fs-12">{{translate('Different e-commerce pages serve different purposes, so they need different SEO behaviour. This setup matters for these reasons.')}}</p>
                                </div>                               
                                <div class="mb-3">
                                    <h6 class="mb-2 fs-12 color-656566">{{translate('Calculate tax included in product price')}}</h6>
                                    <ul class="mb-0 list-group pl-3 d-flex flex-column gap-1px">
                                        <li class="fs-12 color-656566">{{translate('Improves Google ranking')}}</li>
                                        <li class="fs-12 color-656566">{{translate('Increases organic traffic')}}</li>
                                        <li class="fs-12 color-656566">{{translate('Controls which pages appear in search')}}</li>
                                        <li class="fs-12 color-656566">{{translate('Improves social media sharing previews')}}</li>
                                        <li class="fs-12 color-656566">{{translate('Prevents private pages from being indexed')}}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-12 p-sm-20 bg-light rounded mb-3">
                        <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                            <button class="btn-collapse line--limit-1 d-flex gap-3 align-items-center bg-transparent border-0 p-0 collapsed" type="button"
                                    data-toggle="collapse" data-target="#collapseGeneralSetup_033" aria-expanded="true">
                                <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1 collapsed">
                                    <i class="tio-down-ui top-01 color-656566"></i>
                                </div>
                                <span class="font-semibold text-left fs-14 text-title line--limit-1">{{ translate('How to set up metadata for pages?') }}</span>
                            </button>
                        </div>
                        <div class="collapse mt-3" id="collapseGeneralSetup_033">
                            <div class="card rounded border p-3 card-body"> 
                                <div class="mb-3">
                                    <h6 class="mb-2 fs-12 color-656566">{{translate('Before activation')}}</h6>
                                    <ul class="mb-0 list-group pl-3 d-flex flex-column gap-1px">
                                        <li class="fs-12 color-656566">{{translate('Give the page specific, meaningful text')}}</li>                                            
                                        <li class="fs-12 color-656566">{{translate('Avoid copying the same text across pages, and use keywords naturally.')}}</li>                                            
                                    </ul>
                                </div>
                                <div class="mb-3">
                                    <h6 class="mb-2 fs-12 color-656566">{{translate('Upload meta image')}}</h6>
                                    <ul class="mb-0 list-group pl-3 d-flex flex-column gap-1px">
                                        <li class="fs-12 color-656566">{{translate('Used for social sharing previews')}}</li>                                            
                                        <li class="fs-12 color-656566">{{translate('Maintain the recommended ratio & size')}}</li>                                            
                                    </ul>
                                </div>
                                <div class="mb-3">
                                    <h6 class="mb-2 fs-12 color-656566">{{translate('Select the necessary options as per instructions')}}</h6>
                                    <ul class="mb-0 list-group pl-3 d-flex flex-column gap-1px">
                                        <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('Index') }}:</strong> {{translate('Allow search engines to show this page')}}</li>                                            
                                        <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('No index') }}:</strong> {{translate('Hide page from search results')}}</li>                                            
                                        <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('No follow') }}:</strong> {{translate('Prevents search engines from following links on this page')}}</li>                                            
                                        <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('No image index') }}:</strong> {{translate('Prevents images from appearing in Google image search. Use for private/system pages.')}}</li>                                            
                                        <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('Max snippet') }}:</strong> {{translate('Controls text shown in Google results')}}</li>                                            
                                        <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('Max video preview') }}:</strong> {{translate('Video preview length')}}</li>                                            
                                        <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('Max image preview') }}:</strong> {{translate('Small / large')}}</li>                                            
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>

@push('script_2')
    <script>
        "use strict";
        document.addEventListener('DOMContentLoaded', function () {
            var fileInput = document.getElementById('meta_data_image');
            var wrapper = fileInput ? fileInput.closest('.upload-file_custom') : null;
            var removeBtn = wrapper ? wrapper.querySelector('.remove_btn') : null;
            var removeFlag = wrapper ? wrapper.querySelector('.image-delete-flag') : null;
            var previewImg = wrapper ? wrapper.querySelector('.upload-file-img') : null;
            var uploadText = wrapper ? wrapper.querySelector('.upload-file-textbox') : null;
            var form = fileInput ? fileInput.closest('form') : null;

            if (removeBtn && removeFlag && previewImg && fileInput) {
                removeBtn.addEventListener('click', function () {
                    removeFlag.value = '1';
                    fileInput.value = '';

                    previewImg.style.display = 'none';
                    previewImg.removeAttribute('src');

                    if (uploadText) uploadText.style.display = 'block';
                });
            }

            if (form && removeFlag) {
                form.addEventListener('reset', function () {
                    removeFlag.value = '0';
                    if (previewImg && previewImg.dataset.defaultSrc) {
                        previewImg.src = previewImg.dataset.defaultSrc;
                        previewImg.style.display = 'block';
                    } else if (previewImg) {
                    }
                    if (uploadText && previewImg && previewImg.style.display !== 'none') {
                        uploadText.style.display = 'none';
                    }
                });
            }

            if (fileInput && removeFlag) {
                fileInput.addEventListener('change', function () {
                    removeFlag.value = '0';
                    if (this.files && this.files[0]) {
                        var reader = new FileReader();
                        reader.onload = function (e) {
                            if (previewImg) {
                                previewImg.src = e.target.result;
                                previewImg.style.display = 'block';
                            }
                            if (uploadText) uploadText.style.display = 'none';
                        }
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
        });

        $(document).ready(function () {
            $('input[name="meta_index"][value="0"]').on('change', function () {
                if ($(this).is(':checked')) {
                    $('input[name="meta_no_follow"]').prop('checked', true);
                    $('input[name="meta_no_image_index"]').prop('checked', true);
                    $('input[name="meta_no_archive"]').prop('checked', true);
                    $('input[name="meta_no_snippet"]').prop('checked', true);
                }
            });

            $('input[name="meta_index"][value="1"]').on('change', function () {
                if ($(this).is(':checked')) {
                    $('input[name="meta_no_follow"]').prop('checked', false);
                    $('input[name="meta_no_image_index"]').prop('checked', false);
                    $('input[name="meta_no_archive"]').prop('checked', false);
                    $('input[name="meta_no_snippet"]').prop('checked', false);
                }
            });

            function toggleInput(checkboxSelector, inputSelector) {
                const checkbox = $(checkboxSelector);
                const input = $(inputSelector);

                function update() {
                    if (checkbox.is(':checked')) {
                        input.prop('disabled', false);
                    } else {
                        input.prop('disabled', true);
                    }
                }

                checkbox.on('change', update);
                update();
            }

            toggleInput('input[name="meta_max_snippet"]', 'input[name="meta_max_snippet_value"]');
            toggleInput('input[name="meta_max_video_preview"]', 'input[name="meta_max_video_preview_value"]');
            toggleInput('input[name="meta_max_image_preview"]', 'select[name="sizeSelect"]');
        });
    </script>
@endpush
