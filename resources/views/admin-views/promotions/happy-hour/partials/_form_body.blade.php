@php($hh = $happyHour ?? null)
{{-- getWebConfig rather than a direct BusinessSetting query: settings are cached and
     querying the table directly is banned in new code. Already an array, so no json_decode. --}}
@php($language = $language ?? getWebConfig('language'))

<div class="card">
    <div class="card-body">
        <div class="hh-section">
            <span class="hh-section__step">1</span>
            <div>
                <h2 class="hh-section__title">{{translate('General information')}}</h2>
                <p class="hh-section__desc">{{translate('Enter the happy hour name add a short description and upload the cover image and icon to help customers recognize the offer')}}</p>
            </div>
        </div>

        <div class="row">
            {{-- xl, not lg: the image column needs ~370px for a 3:1 cover beside a 1:1 icon, and
                 5/12 of the content area only clears that past ~1200px. Between 992 and 1200 the
                 lg split still sat them side by side in a column too narrow to hold them, so the
                 cover overflowed its card. Below xl the whole image block drops under the title
                 and description instead, at full width, where the two boxes fit side by side. --}}
            <div class="col-xl-7 mb-3 mb-xl-0">
                <div class="hh-panel h-100">
                    @if($language)
                        <div class="js-nav-scroller hs-nav-scroller-horizontal">
                            <ul class="nav nav-tabs mb-3">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                                </li>
                                @foreach($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link" href="#" id="{{$lang}}-link">{{\App\CentralLogics\Helpers::get_language_name($lang).'('.strtoupper($lang).')'}}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="lang_form" id="default-form">
                            <div class="form-group">
                                <label class="input-label">
                                    {{translate('Happy hour title')}} ({{ translate('Default') }}) <span class="text-danger">*</span>
                                    <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                          data-original-title="{{translate('The name customers will see for this happy hour')}}">
                                        <i class="tio-info text-gray1 fs-16"></i>
                                    </span>
                                </label>
                                <input type="text" name="title[]" class="form-control h--45px" maxlength="100"
                                       data-counter="count_title_default" placeholder="{{ translate('Type title') }}"
                                       value="{{ $hh?->getRawOriginal('title') }}">
                                <small class="d-block text-right opacity-75">
                                    <span id="count_title_default">{{ strlen($hh?->getRawOriginal('title') ?? '') }}</span>/100
                                </small>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                            <div class="form-group mb-0">
                                <label class="input-label">
                                    {{translate('Short description')}} ({{ translate('Default') }})
                                    <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                          data-original-title="{{translate('A short line explaining what this happy hour offers')}}">
                                        <i class="tio-info text-gray1 fs-16"></i>
                                    </span>
                                </label>
                                <textarea name="short_description[]" class="form-control" maxlength="150"
                                          data-counter="count_desc_default"
                                          placeholder="{{ translate('messages.Type about the description') }}">{{ $hh?->getRawOriginal('short_description') }}</textarea>
                                <small class="d-block text-right opacity-75">
                                    <span id="count_desc_default">{{ strlen($hh?->getRawOriginal('short_description') ?? '') }}</span>/150
                                </small>
                            </div>
                        </div>

                        @foreach($language as $lang)
                            <?php
                                $translate = [];
                                if ($hh && count($hh['translations'])) {
                                    foreach ($hh['translations'] as $t) {
                                        if ($t->locale == $lang && $t->key == 'title') {
                                            $translate[$lang]['title'] = $t->value;
                                        }
                                        if ($t->locale == $lang && $t->key == 'short_description') {
                                            $translate[$lang]['short_description'] = $t->value;
                                        }
                                    }
                                }
                            ?>
                            <div class="d-none lang_form" id="{{$lang}}-form">
                                <div class="form-group">
                                    <label class="input-label">
                                        {{translate('Happy hour title')}} ({{strtoupper($lang)}})
                                        <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                              data-original-title="{{translate('The name customers will see for this happy hour')}}">
                                            <i class="tio-info text-gray1 fs-16"></i>
                                        </span>
                                    </label>
                                    <input type="text" name="title[]" class="form-control h--45px" maxlength="100"
                                           data-counter="count_title_{{$lang}}" placeholder="{{ translate('Type title') }}"
                                           value="{{ $translate[$lang]['title'] ?? '' }}">
                                    <small class="d-block text-right opacity-75">
                                        <span id="count_title_{{$lang}}">{{ strlen($translate[$lang]['title'] ?? '') }}</span>/100
                                    </small>
                                </div>
                                <input type="hidden" name="lang[]" value="{{$lang}}">
                                <div class="form-group mb-0">
                                    <label class="input-label">
                                        {{translate('Short description')}} ({{strtoupper($lang)}})
                                        <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                              data-original-title="{{translate('A short line explaining what this happy hour offers')}}">
                                            <i class="tio-info text-gray1 fs-16"></i>
                                        </span>
                                    </label>
                                    <textarea name="short_description[]" class="form-control" maxlength="150"
                                              data-counter="count_desc_{{$lang}}"
                                              placeholder="{{ translate('messages.Type about the description') }}">{{ $translate[$lang]['short_description'] ?? '' }}</textarea>
                                    <small class="d-block text-right opacity-75">
                                        <span id="count_desc_{{$lang}}">{{ strlen($translate[$lang]['short_description'] ?? '') }}</span>/150
                                    </small>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div id="default-form">
                            <div class="form-group">
                                <label class="input-label">{{translate('Happy hour title')}} <span class="text-danger">*</span></label>
                                <input type="text" name="title[]" class="form-control h--45px" maxlength="100"
                                       data-counter="count_title_default" placeholder="{{ translate('Type title') }}"
                                       value="{{ $hh?->getRawOriginal('title') }}">
                                <small class="d-block text-right opacity-75">
                                    <span id="count_title_default">{{ strlen($hh?->getRawOriginal('title') ?? '') }}</span>/100
                                </small>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                            <div class="form-group mb-0">
                                <label class="input-label">{{translate('Short description')}}</label>
                                <textarea name="short_description[]" class="form-control" maxlength="150"
                                          data-counter="count_desc_default"
                                          placeholder="{{ translate('messages.Type about the description') }}">{{ $hh?->getRawOriginal('short_description') }}</textarea>
                                <small class="d-block text-right opacity-75">
                                    <span id="count_desc_default">{{ strlen($hh?->getRawOriginal('short_description') ?? '') }}</span>/150
                                </small>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- The shared uploader the food form uses, so both screens drag, preview,
                 validate size and reset the same way. isRequired is true on both create
                 and edit: the partial then asks for a file only when there is no stored
                 one (create), and hides the remove button, which is right here because
                 the controller only ever replaces an image - it has no path to clear one. --}}
            {{-- The box is sized from its ratio against the partial's fixed 100px height, so a
                 3:1 cover is 300px wide and a 1:1 icon 100px. A fixed col-7/col-5 split (a
                 percentage of whatever THIS column happens to get from col-xl-5 above) had no
                 relation to that 300px+100px need -- it fit only when col-xl-5 itself landed on a
                 wide enough viewport, and squeezed both boxes out of their own ratio everywhere
                 else. The pair now sizes and grows from its own content need instead, so it holds
                 up whether col-xl-5 is narrow or has already stacked full-width below it. --}}
            <div class="col-xl-5">
                <div class="row h-100 hh-upload-row">
                    @foreach([
                        ['key' => 'cover_image', 'input' => 'cover-input', 'title' => 'Cover_image', 'sub' => 'Upload_Happy_Hour_Cover_image', 'ratio' => '3:1', 'col' => 'hh-upload-col--cover'],
                        ['key' => 'icon', 'input' => 'icon-input', 'title' => 'Icon', 'sub' => 'Upload_Happy_Hour_Icon', 'ratio' => '1:1', 'col' => 'hh-upload-col--icon'],
                    ] as $img)
                        <div class="hh-upload-col {{ $img['col'] }}">
                            <div class="hh-upload">
                                <h3 class="hh-upload__title">{{translate('messages.'.$img['title'])}} <span class="text-danger">*</span></h3>
                                <p class="hh-upload__desc">{{translate('messages.'.$img['sub'])}}</p>

                                {{-- Guarded on the column, not on the accessor: *_full_url falls back
                                     to a placeholder, which the uploader would then show as though a
                                     real image had been saved. --}}
                                @php($existing = $hh && $hh->{$img['key']}
                                    ? ($img['key'] === 'icon' ? $hh->icon_full_url : $hh->cover_image_full_url)
                                    : null)

                                <div class="hh-upload__box">
                                    @include('admin-views.partials._image-uploader', [
                                        'id' => $img['input'],
                                        'name' => $img['key'],
                                        'isRequired' => true,
                                        'existingImage' => $existing,
                                        'defaultImage' => $existing,
                                        'ratio' => $img['ratio'],
                                        'textPosition' => 'bottom',
                                        'maxSize' => MAX_FILE_SIZE,
                                        'imageExtension' => IMAGE_EXTENSION,
                                        'imageFormat' => IMAGE_FORMAT,
                                    ])
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="hh-section">
            <span class="hh-section__step">2</span>
            <div>
                <h2 class="hh-section__title">{{translate('messages.Discount Setup')}}</h2>
                <p class="hh-section__desc">{{translate('messages.Set the discount percentage and if needed define a minimum order amount required to apply the offer')}}</p>
            </div>
        </div>

        <div class="hh-panel">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-md-0">
                        <label class="input-label" for="discount">
                            {{translate('messages.Discount')}} <span class="text-danger">*</span>
                            <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                  data-original-title="{{translate('Percentage taken off the order while the happy hour runs')}}">
                                <i class="tio-info text-gray1 fs-16"></i>
                            </span>
                        </label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="discount" id="discount" class="form-control h--45px"
                                   placeholder="{{ translate('Ex') }}: 5" value="{{ $hh?->discount }}" required>
                            <div class="input-group-append"><span class="input-group-text">%</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <div class="hh-label-row">
                            <label class="input-label" for="min_order_amount">
                                {{translate('Min order amount')}}({{\App\CentralLogics\Helpers::currency_symbol()}})
                                <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                      data-original-title="{{translate('messages.Orders below this amount will not get the discount')}}">
                                    <i class="tio-info text-gray1 fs-16"></i>
                                </span>
                            </label>
                            <label class="toggle-switch toggle-switch-sm mb-0" for="min_order_toggle">
                                <input type="checkbox" class="toggle-switch-input" id="min_order_toggle" {{ $hh && $hh->min_order_amount ? 'checked' : '' }}>
                                <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                            </label>
                        </div>
                        {{-- The toggle itself is not posted, so its state travels here and the server can
                             require an amount when it is on rather than saving a minimum of nothing. --}}
                        <input type="hidden" name="min_order_enabled" id="min_order_enabled"
                               value="{{ $hh && $hh->min_order_amount ? 1 : 0 }}">
                        <input type="number" step="0.01" min="0" name="min_order_amount" id="min_order_amount"
                               class="form-control h--45px" placeholder="{{ translate('Ex') }}: 50" value="{{ $hh?->min_order_amount }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="hh-section">
            <span class="hh-section__step">3</span>
            <div>
                <h2 class="hh-section__title">{{translate('Schedule & duration setup')}}</h2>
                <p class="hh-section__desc">{{translate('Choose when the happy hour offer will run by selecting a schedule type date range and time range')}}</p>
            </div>
        </div>

        <div class="form-group">
            <label class="input-label">
                {{translate('Offer schedule type')}} <span class="text-danger">*</span>
                <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                      data-original-title="{{translate('Daily repeats every day weekly repeats on chosen weekdays custom picks individual dates')}}">
                    <i class="tio-info text-gray1 fs-16"></i>
                </span>
            </label>
            <div class="row g-3">
                @foreach([
                    'daily' => ['label' => 'Daily_Schedule', 'icon' => 'tio-calendar', 'hint' => 'Every day inside a date range'],
                    'weekly' => ['label' => 'Weekly_Schedule', 'icon' => 'tio-repeat', 'hint' => 'The same weekdays, week after week'],
                    'custom' => ['label' => 'Custom_Schedule', 'icon' => 'tio-date-range', 'hint' => 'Hand-picked dates, each with its own time'],
                ] as $value => $type)
                    <div class="col-md-4 mb-3 mb-md-0">
                        <label class="hh-choice" for="duration_{{$value}}">
                            <input class="hh-choice__input duration-type" type="radio" name="duration_type" value="{{$value}}"
                                   id="duration_{{$value}}" {{ ($hh?->duration_type ?? 'daily') === $value ? 'checked' : '' }}>
                            <span class="hh-choice__icon"><i class="{{ $type['icon'] }}"></i></span>
                            <span>
                                <span class="hh-choice__name">{{translate('messages.'.$type['label'])}}</span>
                                <span class="hh-choice__hint">{{translate($type['hint'])}}</span>
                            </span>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="alert alert-soft-warning alert--note d-flex gap-2">
            <i class="tio-error"></i>
            <span>{{translate('When creating a happy hour the selected time must not overlap with an existing happy hour in the same module')}}</span>
        </div>

        {{-- Daily: pick a range and a start time; every date in the range gets that window. --}}
        <div class="hh-panel daily-block d-none">
            <div class="mb-3">
                <h3 class="hh-panel__title">{{translate('Select date & time')}}</h3>
                <p class="hh-panel__desc">{{translate('messages.Select your suitable time within a time range')}}</p>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3 mb-md-0">
                    <label class="input-label" for="daily_range_display">
                        {{translate('Date range')}} <span class="text-danger">*</span>
                        <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                              data-original-title="{{translate('The first and last day this happy hour runs')}}">
                            <i class="tio-info text-gray1 fs-16"></i>
                        </span>
                    </label>
                    <div class="hh-field hh-field--range cursor-pointer">
                        <input type="text" id="daily_range_display" class="cursor-pointer"
                               placeholder="{{translate('Select date range')}}" readonly>
                        <i class="tio-calendar"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="input-label">
                        {{translate('messages.Time Range')}} <span class="text-danger">*</span>
                        <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                              data-original-title="{{translate('messages.Pick the start time the window always ends one hour later')}}">
                            <i class="tio-info text-gray1 fs-16"></i>
                        </span>
                    </label>
                    {{-- Only the start is editable: the end is always start + 1h. --}}
                    <div class="hh-field hh-time cursor-pointer">
                        <span class="hh-time__placeholder">{{translate('messages.Select Time Range')}}</span>
                        <span class="hh-time__value d-none">
                            <input type="time" max="22:59" id="daily_start_time" class="start-time">
                            <span>-</span>
                            <span class="hh-time__end derived-end"></span>
                        </span>
                        <i class="tio-time"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- Weekly: the weekday set, range and permanent flag all live in the Select Days modal. --}}
        <div class="hh-panel weekly-block d-none">
            <div class="mb-3">
                <h3 class="hh-panel__title">{{translate('Select date & time')}}</h3>
                <p class="hh-panel__desc">{{translate('messages.Select your suitable time within a time range')}}</p>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3 mb-md-0">
                    <label class="input-label" for="weekly_range_display">
                        {{translate('Date range')}} <span class="text-danger">*</span>
                        <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                              data-original-title="{{translate('messages.Opens the day picker where the weekdays and range are chosen')}}">
                            <i class="tio-info text-gray1 fs-16"></i>
                        </span>
                    </label>
                    <div class="hh-field hh-field--range cursor-pointer" id="open_weekly_modal">
                        <input type="text" id="weekly_range_display" class="cursor-pointer"
                               placeholder="{{translate('Select date range')}}" readonly>
                        <i class="tio-calendar"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="input-label">
                        {{translate('messages.Time Range')}} <span class="text-danger">*</span>
                        <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                              data-original-title="{{translate('messages.Pick the start time the window always ends one hour later')}}">
                            <i class="tio-info text-gray1 fs-16"></i>
                        </span>
                    </label>
                    <div class="hh-field hh-time cursor-pointer">
                        <span class="hh-time__placeholder">{{translate('messages.Select Time Range')}}</span>
                        <span class="hh-time__value d-none">
                            <input type="time" max="22:59" id="weekly_start_time" class="start-time"
                                   value="{{ $hh && $hh->start_time ? substr($hh->start_time, 0, 5) : '' }}">
                            <span>-</span>
                            <span class="hh-time__end derived-end"></span>
                        </span>
                        <i class="tio-time"></i>
                    </div>
                </div>
            </div>

            <p class="mb-0 mt-3 fs-13" id="weekly_summary"></p>
        </div>

        {{-- Custom: individual dates, each with its own start time.

             Split left/right rather than stacked like the daily and weekly panels above: this one
             carries a list that grows with every date picked, and run full width the rows stretched
             their three columns metres apart. The heading and its description hold the left, the
             picker and the list share the right, so the rows stay legible however many there are. --}}
        <div class="hh-panel custom-block d-none">
            <div class="row">
                <div class="col-lg-5 mb-3 mb-lg-0">
                    <h3 class="hh-panel__title">{{translate('Select date & time')}}</h3>
                    <p class="hh-panel__desc">{{translate('Select the time range during which the happy hour offer will be available')}}</p>
                </div>

                <div class="col-lg-7">
                    <label class="input-label" for="custom_range_display">
                        {{translate('Select date & time')}} <span class="text-danger">*</span>
                        <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                              data-original-title="{{translate('messages.Opens the calendar where each date gets its own start time')}}">
                            <i class="tio-info text-gray1 fs-16"></i>
                        </span>
                    </label>
                    <div class="hh-field hh-field--range cursor-pointer" id="open_custom_modal">
                        <input type="text" id="custom_range_display" class="cursor-pointer"
                               placeholder="{{translate('Select date & time range')}}" readonly>
                        <i class="tio-calendar"></i>
                    </div>

                    {{-- Whole block stays out of the way until dates exist, otherwise the
                         white panel shows as an empty strip under a "0 days selected". --}}
                    <div id="custom_summary_wrap" class="d-none mt-2">
                        {{-- Count is dark, the label beside it is muted, per design. --}}
                        <p class="mb-2 fs-13">
                            <strong id="custom_count">0</strong>
                            <span class="opacity-75">{{translate('messages.days_selected')}}</span>
                        </p>
                        <p class="hh-summary__title">
                            {{translate('Selected days & time')}} (<span id="custom_count_title">0</span>)
                        </p>
                        <div class="hh-panel hh-panel--plain p-0 py-2" id="custom_summary_list"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Populated by the schedule scripts; these are what the controller reads. --}}
        <input type="hidden" name="date_range" id="date_range_input">
        <input type="hidden" name="start_time" id="start_time_input">
        <input type="hidden" name="weekly_days" id="weekly_days_input">
        <input type="hidden" name="is_permanent" id="is_permanent_input" value="0">
        <input type="hidden" name="custom_days" id="custom_days_input">
        <input type="hidden" name="custom_times" id="custom_times_input">

        <div class="alert alert-soft-info alert--note d-flex gap-2 mt-3 mb-0">
            <img src="{{ asset('public/assets/admin/img/svg/bulb.svg') }}" class="svg flex-shrink-0" alt="">
            <span>{{translate('The happy hour offer will only be available during the selected schedule')}}</span>
        </div>
    </div>
</div>
