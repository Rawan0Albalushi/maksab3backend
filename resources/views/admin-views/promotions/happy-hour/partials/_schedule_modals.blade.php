{{-- Weekly: "Select Days" - weekday checkboxes, date range and the permanent flag. --}}
<div class="modal fade" id="selectDaysModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:620px">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 align-items-start">
                <div>
                    <h4 class="modal-title mb-1">{{translate('Select days')}}</h4>
                    <p class="opacity-75 mb-0">{{translate('Your happy hour active days')}}</p>
                </div>
                <button type="button" class="btn-close w-30px h-30px border rounded-circle d-center bg--secondary p-0"
                        data-dismiss="modal" aria-label="Close">&times;</button>
            </div>

            <div class="modal-body">
                <div class="hh-panel mb-3">
                    <div class="row">
                        @foreach(['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'] as $day)
                            <div class="col-6 col-md-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input weekday-check" type="checkbox" value="{{$day}}" id="modal_day_{{$day}}">
                                    <label class="form-check-label" for="modal_day_{{$day}}">{{translate('messages.'.$day)}}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="hh-panel">
                    <h3 class="hh-panel__title">{{translate('Date range')}}</h3>
                    <p class="hh-panel__desc mb-3">{{translate('messages.Select the date range you want to repeat this cycle every week')}}</p>

                    <div class="form-group">
                        <label class="input-label">
                            {{translate('Date range')}} <span class="text-danger">*</span>
                            <span class="input-label-secondary text--title ml-0 mr-1" data-toggle="tooltip" data-placement="top"
                                  data-original-title="{{translate('messages.The window the weekly cycle repeats inside')}}">
                                <i class="tio-info text-gray1 fs-16"></i>
                            </span>
                        </label>
                        <div class="hh-field hh-field--range cursor-pointer" id="modal_range_field">
                            <input type="text" id="modal_range_display" class="cursor-pointer"
                                   placeholder="{{translate('Select date range')}}" readonly>
                            <i class="tio-calendar"></i>
                        </div>
                    </div>

                    {{-- Permanent drops the range entirely: the rule then matches on weekday alone. --}}
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="modal_is_permanent">
                        <label class="form-check-label" for="modal_is_permanent">{{translate('messages.Assign this happy hour permanently')}}</label>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn min-w-120 h--45px btn--reset" data-dismiss="modal">
                    <i class="tio-clear-circle-outlined"></i> {{translate('messages.No')}}
                </button>
                <button type="button" class="btn min-w-120 h--45px btn--primary" id="save_weekly_days">
                    <i class="tio-save"></i> {{translate('messages.Save')}}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Custom: "Select Days & Time" - month calendar on the left, per-date time rows on the right. --}}
<div class="modal fade" id="selectDaysTimeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:1000px">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0 align-items-start">
                <div>
                    <h4 class="modal-title mb-1">{{translate('Select days & time')}}</h4>
                    <p class="opacity-75 mb-0">{{translate('Your happy hour active date')}}</p>
                </div>
                <button type="button" class="btn-close w-30px h-30px border rounded-circle d-center bg--secondary p-0"
                        data-dismiss="modal" aria-label="Close">&times;</button>
            </div>

            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="hh-panel hh-panel--plain h-100">
                            <div class="d-flex align-items-center justify-content-center gap-4 mb-3">
                                <button type="button" class="hh-cal-nav" id="cal_prev"><i class="tio-chevron-left"></i></button>
                                <strong id="cal_label"></strong>
                                <button type="button" class="hh-cal-nav" id="cal_next"><i class="tio-chevron-right"></i></button>
                            </div>
                            <table class="table table-borderless text-center mb-0 hh-calendar">
                                <thead>
                                <tr class="opacity-75 fs-13">
                                    @php($weekStart = \Carbon\Carbon::now()->startOfWeek(\Carbon\CarbonInterface::MONDAY))
                                    @for ($day = 0; $day < 7; $day++)
                                        <th>{{ $weekStart->copy()->addDays($day)->translatedFormat('D') }}</th>
                                    @endfor
                                </tr>
                                </thead>
                                <tbody id="calendar_body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="hh-panel hh-panel--plain h-100 d-flex flex-column">
                            <h6 class="mb-3">{{translate('messages.Selected Days List')}}</h6>
                            {{-- Column headings are hidden while the list is empty: they label
                                 rows that are not there yet. --}}
                            <div id="selected_days_head" class="d-flex align-items-center gap-2 opacity-75 fs-13 mb-2 px-2 d-none">
                                <span class="flex-grow-1">{{translate('messages.Date')}}</span>
                                <span style="width:200px">{{translate('messages.Time')}}</span>
                                <span style="width:24px"></span>
                            </div>
                            <div id="selected_days_list" class="flex-grow-1" style="max-height:330px; overflow-y:auto"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn min-w-120 h--45px btn--reset" data-dismiss="modal">
                    <i class="tio-clear-circle-outlined"></i> {{translate('messages.No')}}
                </button>
                <button type="button" class="btn min-w-120 h--45px btn--primary" id="save_custom_days">
                    <i class="tio-save"></i> {{translate('messages.Save')}}
                </button>
            </div>
        </div>
    </div>
</div>
