
<form action="{{ route('admin.business-settings.reactFaqUpdate',[$faq['id']]) }}" method="post"
    class="d-flex flex-column h-100" enctype="multipart/form-data">

    @csrf
    <div>
        <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
            <h3 class="mb-0">{{ translate('Edit FAQ') }}</h3>
            <button type="button"
                class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0"
                aria-label="Close">&times;</button>
        </div>
        <div class="custom-offcanvas-body p-20">




            <div class="bg--secondary rounded p-20 mb-20">

                @if ($language)
                    <ul class="nav nav-tabs mb-4 border-0">
                        <li class="nav-item">
                            <a class="nav-link lang_link1 active" href="#"
                                id="default-link">{{ translate('Default') }}</a>
                        </li>
                        @foreach ($language as $lang)
                            <li class="nav-item">
                                <a class="nav-link lang_link1" href="#"
                                    id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="row">
                    <div class="col-12">
                        @if ($language)
                            <div class="form-group lang_form1" id="default-form1">
                                <div class="col-md-12">
                                    <label class="input-label"
                                        for="exampleFormControlInput1">{{ translate('Question') }}
                                        ({{ translate('Default') }})

                                        <span class="form-label-secondary text-danger" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>

                                    </label>
                                    <input id="Reviewer_name" data-maxlength="150" type="text" name="question[]"
                                        class="form-control" value="{{ $faq?->getRawOriginal('question') }}"
                                        placeholder="{{ translate('Enter question') }}" required>

                                    <div class="d-flex justify-content-end">
                                        <span class="text-body-light text-counting text-right d-block mt-1">0/150</span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label class="input-label"
                                        for="exampleFormControlInput1">{{ translate('messages.Answer') }}
                                        ({{ translate('Default') }})
                                        <span class="form-label-secondary text-danger" data-toggle="tooltip"
                                            data-placement="right"
                                            data-original-title="{{ translate('messages.Required.') }}"> *
                                        </span>

                                    </label>

                                    <textarea id="Reviewer_review" data-maxlength="100"
                                          type="text"
                                        name="answer[]" class="form-control" placeholder="{{ translate('Enter answer') }}" required>{{ $faq?->getRawOriginal('answer') }}</textarea>

                                    <div class="d-flex justify-content-end">
                                        <span class="text-body-light text-counting text-right d-block mt-1">0/500</span>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="lang[]" value="default">

                            @foreach ($language as $key => $lang)
                                <?php
                                if (count($faq['translations'])) {
                                    $translate = [];
                                    foreach ($faq['translations'] as $t) {
                                        if ($t->locale == $lang && $t->key == 'question') {
                                            $translate[$lang]['question'] = $t->value;
                                        }
                                        if ($t->locale == $lang && $t->key == 'answer') {
                                            $translate[$lang]['answer'] = $t->value;
                                        }
                                    }
                                }
                                ?>

                                <div class="form-group d-none lang_form1" id="{{ $lang }}-form1">

                                    <div class="col-md-12">

                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('Question') }}
                                            ({{ strtoupper($lang) }})
                                        </label>
                                        <input type="text" name="question[]"
                                            value="{{ $translate[$lang]['question'] ?? '' }}" class="form-control"
                                            data-maxlength="150" placeholder="{{ translate('Question') }}"
                                            maxlength="191">
                                        <div class="d-flex justify-content-end">
                                            <span class="text-body-light text-counting text-right d-block mt-1">0/150</span>
                                        </div>
                                    </div>

                                    <div class="col-md-12">

                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('Answer') }}
                                            ({{ strtoupper($lang) }})
                                        </label>
                                        <textarea type="text" name="answer[]"
                                            class="form-control"
                                            data-maxlength="500" placeholder="{{ translate('messages.Answer') }}"
                                            maxlength="191">{{ $translate[$lang]['answer'] ?? '' }}</textarea>
                                        <div class="d-flex justify-content-end">
                                            <span class="text-body-light text-counting text-right d-block mt-1">0/500</span>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="lang[]" value="{{ $lang }}">
                            @endforeach

                        @endif

                    </div>

                </div>

            </div>


        </div>
    </div>
    <div
        class="align-items-center bg-white bottom-0 d-flex gap-3 justify-content-center mt-auto offcanvas-footer p-3 position-sticky">
        <button type="button"
            class="btn w-100 btn--secondary offcanvas-close h--40px"><i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}</button>
        <button type="submit" class="btn w-100 btn--primary h--40px"><i class="tio-save"></i> {{ translate('Update') }}</button>
    </div>
</form>
