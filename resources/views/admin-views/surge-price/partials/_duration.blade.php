{{-- Duration Setup — ported from the screen this replaces rather than rebuilt.
     The old form already had exactly the card the new design asks for: the three schedule-type
     radios across one row, and a two-column body with the explanation on the left and the
     pickers on the right. What it also had, and what a rewrite would have thrown away, is the
     weekly-days modal, the permanent-weekly flag and the custom-schedule table with its
     per-day time rows.

     Only the prefills are new: the old screen was create-only in this card, so every input
     starts blank there. Here they come from $ranges, $selectedType, $selectedWeekdays,
     $customDays and $customTimes so the edit form reopens what was saved.

     Its scripts are in _duration-scripts, its modals in _duration-modals. --}}
        <div class="card mb-20 shedule-checkbox_wrapper">
            <div class="card-header">
                <h4 class="mb-0">{{ translate('Duration Setup')}}</h4>
            </div>
            <div class="card-body">
                <div class="form-group w-100 mb-20">
                    <label class="input-label" for="">{{ translate('Surge Price Schedule Type') }} <span class="text-danger">*</span></label>
                    <div class="resturant-type-group shedule-checkbox-inner flex-md-nowrap border">
                        <label class="form-check w-100 form--check mr-2 mr-md-4">
                            <input autocomplete="off" class="form-check-input" type="radio" value="daily" name="duration_type" {{ $selectedType === 'daily' ? 'checked' : '' }}>
                            <span class="form-check-label">{{ translate('Daily Schedule') }}</span>
                        </label>
                        <label class="form-check w-100 form--check mr-2 mr-md-4">
                            <input autocomplete="off" class="form-check-input" type="radio" value="weekly" name="duration_type" {{ $selectedType === 'weekly' ? 'checked' : '' }}>
                            <span class="form-check-label">{{ translate('Weekly Schedule') }}</span>
                        </label>
                        <label class="form-check w-100 form--check mr-2 mr-md-4">
                            <input autocomplete="off" class="form-check-input" type="radio" value="custom" name="duration_type" {{ $selectedType === 'custom' ? 'checked' : '' }}>
                            <span class="form-check-label">{{ translate('Custom Schedule') }}</span>
                        </label>
                    </div>
                </div>
                <div class="bg-light p-20 p-mobile-0 bg-mobile-transparent">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div>
                                <h5 class="mb-1">{{ translate('Duration Setup') }}</h5>
                                <p class="fs-12 m-0">{{ translate('Select your suitable time within a time range you want add surge price') }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="change-shedule-wrapper">
                                <div class="shedule_item">
                                    <div class="bg-white p-sm-3 d-flex flex-column gap-3">
                                        <div>
                                            <label class="form-label">{{ translate('Date range') }} <span class="text-danger">*</span></label>
                                            <div class="position-relative date-range__custom">
                                                <i class="tio-calendar-month icon-absolute-on-right"></i>
                                                <input autocomplete="off" type="text" class="form-control h-45 position-relative bg-transparent no-type"  name="daily_date_range" placeholder="{{ translate('Select date') }}" value="{{ $ranges['daily_date_range'] }}">
                                            </div>
                                        </div>
                                        <div class="time-range-wrapper">
                                            <label class="form-label">{{ translate('Time Range') }} <span class="text-danger">*</span></label>
                                            <div class="position-relative cursor-pointer">
                                                <i class="tio-time icon-absolute-on-right"></i>
                                                 <input autocomplete="off" type="text" class="form-control h-45 position-relative bg-transparent time-range-picker no-type" name="daily_time_range" placeholder="{{ translate('messages.Select Time') }}" value="{{ $ranges['daily_time_range'] }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                 <div class="shedule_item">
                                     <div class="bg-white p-sm-3 d-flex flex-column gap-3">
                                         <div class="cursor-pointer" data-toggle="modal" data-target="#weeklySelectDays_btn">
                                             <label class="form-label">{{ translate('Date range') }} <span class="text-danger">*</span></label>
                                             <div class="position-relative date-range__custom">
                                                 <i class="tio-calendar-month icon-absolute-on-right"></i>
                                                 <input autocomplete="off" type="text" class="form-control h-45 position-relative bg-transparent date-range-input-demo no-type"
                                                     name="weekly_date_range" placeholder="{{ translate('Select date') }}" value="{{ $ranges['weekly_date_range'] }}">
                                             </div>
                                         </div>
                                         <p class="fs-12 m-0 weekly-summary {{ $weeklySummary === '' ? 'd-none' : '' }}">
                                            {{ translate('Every week from')}} <span class="font-semibold" id="selected-weekdays-text">{{ $weeklySummary }}</span>
                                        </p>
                                         <div class="time-range-wrapper">
                                             <label class="form-label">{{ translate('Time Range') }} <span class="text-danger">*</span></label>
                                             <div class="position-relative cursor-pointer">
                                                 <i class="tio-time icon-absolute-on-right"></i>
                                                  <input autocomplete="off" type="text" class="form-control h-45 position-relative bg-transparent time-range-picker no-type" name="weekly_time_range" placeholder="{{ translate('messages.Select Time') }}" value="{{ $ranges['weekly_time_range'] }}">
                                             </div>
                                         </div>
                                         <input autocomplete="off" type="hidden" name="weekly_days" id="weekly_days" value="{{ implode(',', $selectedWeekdays) }}">
                                         <input autocomplete="off" type="hidden" name="is_permanent" id="is_permanent" value="{{ $isPermanent ? 1 : 0 }}">
                                     </div>
                                 </div>
                                 <div class="shedule_item">
                                     <div class="bg-white p-sm-3 d-flex flex-column gap-3">
                                         <div class="cursor-pointer" data-toggle="modal" data-target="#surgeCustom_sheduleBtn">
                                             <label class="form-label">{{ translate('Date & time select') }} <span class="text-danger">*</span></label>
                                             <div class="position-relative">
                                                 <i class="tio-calendar-month icon-absolute-on-right"></i>
                                                 <input autocomplete="off" type="text" id="custom_schedule_input" class="form-control h-45 position-relative bg-transparent no-type" name="" placeholder="{{ $customSummary['placeholder'] }}">
                                             </div>
                                         </div>
                                         <input autocomplete="off" type="hidden" name="custom_days" id="custom_days" value="{{ $customDays }}">
                                         <input autocomplete="off" type="hidden" name="custom_times" id="custom_times" value="{{ $customTimes }}">
                                         <p class="fs-12 m-20 {{ $customSummary['rows'] ? '' : 'd-none' }}" id="custom-date-range-text">
                                            {{ translate('Date range') }} <span class="font-semibold" id="custom-date-min">{{ $customSummary['min'] }}</span> - <span class="font-semibold" id="custom-date-max">{{ $customSummary['max'] }}</span>
                                        </p>
                                         <div class="table-responsive p-0 date-table {{ $customSummary['rows'] ? '' : 'd-none' }}" id="custom-schedule-table">
                                             <table id="columnSearchDatatable" class="table m-0 table-borderless table-thead-bordered table-align-middle">
                                                 <thead class="thead-light border-0">
                                                     <tr>
                                                         <th class="border-0 fs-14">{{ translate('messages.SL') }}</th>
                                                         <th class="border-0 fs-14">{{ translate('messages.Title') }}</th>
                                                         <th class="border-0 fs-14 text-center">{{ translate('messages.Action') }}</th>
                                                     </tr>
                                                 </thead>
                                                 <tbody id="customScheduleTableBody">
                                                     @foreach ($customSummary['rows'] as $index => $row)
                                                         <tr data-index="{{ $index }}">
                                                             <td class="pl-4">{{ $index + 1 }}</td>
                                                             <td>
                                                                 <span class="d-block max-w-220px min-w-176px">
                                                                     <span class="d-block text-title">{{ $row['date'] }}</span>
                                                                     <span>{{ $row['time'] }}</span>
                                                                 </span>
                                                             </td>
                                                             <td class="text-center">
                                                                 <div class="btn--container justify-content-center">
                                                                     <a class="btn action-btn action-btn--edit edit-custom-schedule"
                                                                         href="javascript:" data-index="{{ $index }}"
                                                                         title="{{ translate('Edit') }}"><i class="tio-edit"></i></a>
                                                                     <a class="btn action-btn action-btn--delete remove-custom-schedule"
                                                                         href="javascript:" data-index="{{ $index }}"
                                                                         title="{{ translate('Delete') }}"><i class="tio-delete-outlined"></i></a>
                                                                 </div>
                                                             </td>
                                                         </tr>
                                                     @endforeach
                                                 </tbody>
                                             </table>
                                         </div>
                                     </div>
                                 </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
