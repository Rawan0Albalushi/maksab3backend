<div class="modal fade p-0" id="aiAssistantModal" tabindex="-1" aria-labelledby="aiAssistantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-slideInRight modal-dialog-scrollable modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2 aiAssistantModalLabel" id="aiAssistantModalLabel">
                    <span class="square-div">
                        <span class="ai-btn-animation">
                            <span class="gradientCirc"></span>
                        </span>
                        <img class="position-relative z-1" width="15" height="12" src="{{ asset('public/assets/admin/img/svg/blink-right.svg') }}" alt="">
                    </span>
                    <span id="modalTitle">{{ translate('AI assistant') }}</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" ria-label="{{ translate('Close') }}">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body">
                <div id="mainAiContent" class="ai-modal-content" style="display: none;">
                    <div class="text-center mb-4">
                        <div class="ai-avatar mb-3">
                            <div class="avatar-circle mx-auto">
                                <span class="ai-btn-animation">
                                    <span class="gradientCirc"></span>
                                </span>
                                <img class="position-relative z-1" width="40" height="34" src="{{ asset('public/assets/admin/img/svg/blink-right.svg') }}" alt="">
                            </div>
                        </div>

                        <div class="ai-greeting mb-5">
                            <h4 class="text-title">{{ translate('Hi there') }},</h4>
                            <h2 class="mb-2">{{ translate('I am here to help you') }}</h2>
                            <p class="text-muted">
                                {{ translate('Choose how you want to give instructions for generating item data.') }}
                            </p>
                        </div>

                        <div class="ai-actions d-grid gap-3">
                            <button type="button" class="btn btn-outline-secondary bg-transparent btn-block d-flex gap-2 mb-3 ai-action-btn"
                                data-action="upload">
                                <img width="18" height="18" src="{{ asset('public/assets/admin/img/svg/picture.svg') }}" alt="">
                                <span class="text-title">{{ translate('Upload image') }}</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary bg-transparent btn-block d-flex gap-2 ai-action-btn"
                                data-action="title">
                                <img width="18" height="18" src="{{ asset('public/assets/admin/img/svg/text-generate.svg') }}" alt="">
                                <span class="text-title">{{ translate('Generate item name') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="uploadImageContent" class="ai-modal-content" style="display: none;">
                    <div class="mt-10">
                        <div class="mb-4">
                            <h5 class="mb-3 fs-16 font-bold">
                                {{ translate('Give the product name or upload image') }}
                            </h5>
                            <p class="mb-3">{{ translate('Please give proper product name or image to generate full data for your product') }}</p>
                            <ul class="mb-5 pl-4">
                                <li>{{ translate('Try to use a clean & avoid blur image') }}</li>
                                <li>{{ translate('Use as close as your product image') }}</li>
                            </ul>
                        </div>
                        <div class="text-center mb-4">
                            <label class="upload-zone w-100 mx-auto" id="chooseImageBtn">
                                <input type="file" id="aiImageUpload" class="image-compressor"  hidden class="d-none" accept="image/*">
                                <input type="file" id="aiImageUploadOriginal" hidden accept="image/*">
                                <div class="text-box mx-auto">
                                    <div class="w-100 d-flex flex-column gap-2 justify-content-center align-items-center py-4">
                                        <img width="40" height="40" src="{{ asset('public/assets/admin/img/svg/image-upload.svg') }}" alt="">
                                        <div class="d-flex gap-2 align-items-center justify-content-center fs-14">
                                            <span class="text-dark">{{ translate('Drag & drop your image') }}</span>
                                            <span class="text-lowercase">{{ translate('Or') }}</span>
                                            <span type="button" class="text-primary font-semibold fs-12 text-underline">
                                                <i class="fi fi-rr-cloud-upload-alt"></i>
                                                {{ translate('Browse image') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                 <div id="imagePreview" class="mx-auto position-relative" style="display: none;">
                                     <img id="previewImg" src="" alt="{{ translate('Preview') }}"
                                         class="upload-zone_img" style="max-height: 200px;">
                                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                                            <button type="button" class="btn btn-danger p-0 square-div z-2 remove_image_btn" id="removeImageBtn" data-toggle="tooltip" title="{{ translate('Remove image') }}">
                                                <i class="tio-clear"></i>
                                            </button>
                                        </div>
                                    </div>
                                </label>
                                <div class="mt-4 text-center analyzeImageBtn_wrapper">
                                    <button type="button" class="btn btn-primary mb-3 d-flex align-items-center gap-2 opacity-1 border-0 mx-auto"
                                        id="analyzeImageBtn" data-url="{{ route('admin.product.analyze-image-auto-fill') }}"
                                        data-lang="{{ \App\CentralLogics\Helpers::system_default_language() }}">
                                        <span class="ai-btn-animation d-none">
                                            <span class="gradientRect"></span>
                                        </span>
                                        <span class="position-relative z-1 d-flex gap-2 align-items-center">
                                            <span
                                                class="d-flex align-items-center btn-text">{{ translate('Generate item description') }}</span>
                                                <img width="17" height="15" src="{{ asset('public/assets/admin/img/svg/blink-left.svg') }}" alt="">
                                        </span>
                                    </button>
                                </div>
                        </div>

                    </div>
                </div>

                <div id="giveTitleContent" class="ai-modal-content" style="display: none;">
                    <div class="mb-4">
                        <div class="giveTitleContent_text">
                            <h5 class="mb-3 fs-16 font-bold">
                                {{ translate('great!') }}
                                <br>
                                {{ translate('Now, tell me which product you want to create. Just type it simply, like') }}:
                            </h5>
                            <ul class="mb-3 pl-4">
                                <li>{{ translate('I need product details for men\'s converse shoes') }}</li>
                                <li>{{ translate('I want to add a men\'s t-shirt') }}</li>
                                <li>{{ translate('I want to create a product for women\'s jeans') }}</li>
                            </ul>
                            <p class="mb-4">{{ translate('Feel free to describe it your own way!') }}</p>
                        </div>
                        <div class="generate-text-input-group">
                            <input type="text" class="form-control" id="productKeywords"
                                placeholder="{{ translate('Tell me about your item') }}" data-role="tagsinput">
                                <button type="button" class="btn btn-primary border-0"
                                    id="generateTitleBtn" data-route="{{ route('admin.product.generate-title-suggestions') }}"
                                    data-lang="en">
                                    <span class="ai-loader-animation z-2 d-none">
                                        <span class="loader-circle"></span>
                                        <img width="15" height="15" class="position-relative h-100" src="{{ asset('public/assets/admin/img/svg/blink-left.svg') }}" alt="">
                                    </span>
                                    <span class="position-rtelative z-1"><i class="tio-arrow-forward"></i></span>
                                </button>
                        </div>


                    </div>

                    <div id="generatedTitles" style="display: none;">
                        <div class="text-primary generate_btn_wrapper show_generating_text d-none mb-3">
                            <div class="btn-svg-wrapper">
                                <img width="18" height="18" class="" src="{{ asset('public/assets/admin/img/svg/blink-right-small.svg') }}"
                                alt="">
                            </div>
                            <span class="ai-text-animation ai-text-animation-visible">
                                {{ translate('Just a second') }}
                            </span>
                        </div>
                        <h4 class="mb-2 titlesList_title d-none">{{ translate('Suggest item name') }}</h4>
                        <div id="titlesList" class="list-group">
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@if (isset($openai_config) && data_get($openai_config, 'status') == 1)
    <div class="floating-ai-button">
        <button type="button" class="btn btn-lg rounded-circle shadow-lg" data-toggle="modal"
        data-target="#aiAssistantModal" data-action="main" title="AI Assistant">
            <span class="ai-btn-animation">
                <span class="gradientCirc"></span>
            </span>
            <span class="position-relative z-1 text-white d-flex flex-column gap-1 align-items-center">
                <img width="16" height="17" src="{{ asset('public/assets/admin/img/svg/hexa-ai.svg') }}" alt="">
                <span class="fs-12 font-semibold">{{ translate('Use AI') }}</span>
            </span>
        </button>
        <div class="ai-tooltip">
            <span>{{ translate('AI assistant') }}</span>
        </div>
    </div>
@endif
