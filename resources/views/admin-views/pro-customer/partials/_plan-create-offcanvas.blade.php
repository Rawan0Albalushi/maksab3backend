<div id="offcanvas__createplan" class="custom-offcanvas d-flex flex-column" style="--offcanvas-width: 480px">
    <div class="custom-offcanvas-header bg-light d-flex justify-content-between align-items-center flex-shrink-0">
        <div class="px-3 py-3 d-flex justify-content-between w-100">
            <h3 class="mb-0 fs-18 text-title fw-500 text-capitalize">{{ translate('Create Subscription Plan') }}</h3>
            <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0" aria-label="Close">&times;</button>
        </div>
    </div>

    <form action="{{ route('admin.pro-customer.plan.store') }}" method="post" id="pro-plan-create-form" class="d-flex flex-column flex-grow-1 overflow-hidden">
        @csrf
        <div class="custom-offcanvas-body p-20 overflow-auto flex-grow-1">

                <div class="bg-light2 p-xl-20 p-3 rounded mb-20">
                    <p class="fs-14 fw-500 text-dark mb-3">{{ translate('Subscription plan') }}</p>
                    <div class="resturant-type-group module_select-area w-100 flex-sm-nowrap flex-wrap gap-2 border bg-white">
                        <label class="form-check form--check w-100" for="plan-type-paid-create">
                            <input class="form-check-input pro-plan-type" type="radio" name="plan_type" id="plan-type-paid-create" value="paid" checked>
                            <span class="form-check-label">{{ translate('messages.paid') }}</span>
                        </label>
                        <label class="form-check form--check w-100" for="plan-type-free-create">
                            <input class="form-check-input pro-plan-type" type="radio" name="plan_type" id="plan-type-free-create" value="free_trial">
                            <span class="form-check-label">{{ translate('Free trial') }}</span>
                        </label>
                    </div>

                </div>

                <div class="bg-light2 p-xl-20 p-3 rounded mb-20">
                    @if($language ?? null)
                        <div class="js-nav-scroller hs-nav-scroller-horizontal">
                            <ul class="nav nav-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#" id="default-link-plan-create">{{ translate('Default') }}</a>
                                </li>
                                @foreach($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link" href="#" id="{{ $lang }}-link-plan-create">
                                            {{ \App\CentralLogics\Helpers::get_language_name($lang) . ' (' . strtoupper($lang) . ')' }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <input type="hidden" name="lang[]" value="default">
                    <div class="lang_form" id="default-form-plan-create">
                        <div class="form-group mb-0">
                            <label class="input-label fw-400 text-capitalize">
                                {{ translate('Plan name') }} ({{ translate('Default') }})
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="plan_name[]" maxlength="70"
                                class="form-control plan-name-default"
                                placeholder="{{ translate('messages.Ex') . ': Monthly Plan' }}">
                            <div class="d-flex justify-content-end">
                                <span class="text-body-light d-block mt-1 pro-plan-name-counter">0 / 70</span>
                            </div>
                        </div>
                    </div>

                    @if($language ?? null)
                        @foreach($language as $lang)
                            <input type="hidden" name="lang[]" value="{{ $lang }}">
                            <div class="d-none lang_form" id="{{ $lang }}-form-plan-create">
                                <div class="form-group mb-0">
                                    <label class="input-label fw-400 text-capitalize">
                                        {{ translate('Plan name') }} ({{ strtoupper($lang) }})
                                    </label>
                                    <input type="text" name="plan_name[]" maxlength="70"
                                        data-locale="{{ $lang }}"
                                        class="form-control plan-name-lang"
                                        placeholder="{{ translate('messages.Ex') . ': Monthly Plan' }}">
                                    <div class="d-flex justify-content-end">
                                        <span class="text-body-light d-block mt-1 pro-plan-name-counter">0 / 70</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="bg-light2 p-xl-20 p-3 rounded">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label class="input-label fw-400 text-capitalize">
                                    {{ translate('Plan price') }}
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="number" name="price" min="0"
                                    step="{{ \App\CentralLogics\Helpers::getDecimalPlaces() }}"
                                    class="form-control"
                                    placeholder="{{ translate('messages.Ex') . ': 9.99' }}" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group mb-0">
                                <label class="input-label fw-400 text-capitalize">
                                    {{ translate('messages.Duration days') }}
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="number" name="duration" min="1" max="3650"
                                    class="form-control"
                                    placeholder="{{ translate('messages.Ex') . ': 30' }}" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <div class="align-items-center bg-white d-flex gap-3 justify-content-center offcanvas-footer p-3 flex-shrink-0 border-top">
            <button type="button" class="btn w-100 btn--reset offcanvas-close text-capitalize"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
            <button type="submit" class="btn w-100 btn--primary text-capitalize"><i class="tio-add-circle"></i> {{ translate('Add') }}</button>
        </div>
    </form>
</div>
