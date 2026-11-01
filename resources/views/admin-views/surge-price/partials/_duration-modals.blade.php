{{-- The weekly-days picker, the custom-schedule builder, its time-range picker and the
     conflict dialog — all ported with the Duration Setup card they belong to. --}}
<div class="modal shedule-modal fade" id="weeklySelectDays_btn" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content pb-1">
      <div class="modal-header">
        <div>
            <h3 class="title-clr mb-0">{{ translate('Select days') }}</h3>
            <p class="fz-12 m-0">{{ translate('messages.Your Surge price active date') }}</p>
        </div>
        <button type="button" class="close bg-light w-30px h-30 rounded-circle" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="modal-body-inner max-h-100vh-500px">
            <div class="resturant-type-group bg-light rounded p-3 mb-3 gap-4">
                <label class="form-check form--check mr-2 mr-md-4">
                    <input autocomplete="off" class="form-check-input rounded" type="checkbox" value="1" name="days" @checked(in_array('Saturday', $selectedWeekdays, true))>
                    <span class="form-check-label">Saturday</span>
                </label>
                <label class="form-check form--check mr-2 mr-md-4">
                    <input autocomplete="off" class="form-check-input rounded" type="checkbox" value="0" name="days" @checked(in_array('Sunday', $selectedWeekdays, true))>
                    <span class="form-check-label">Sunday</span>
                </label>
                <label class="form-check form--check mr-2 mr-md-4">
                    <input autocomplete="off" class="form-check-input rounded" type="checkbox" value="0" name="days" @checked(in_array('Monday', $selectedWeekdays, true))>
                    <span class="form-check-label">Monday</span>
                </label>
                <label class="form-check form--check mr-2 mr-md-4">
                    <input autocomplete="off" class="form-check-input rounded" type="checkbox" value="0" name="days" @checked(in_array('Tuesday', $selectedWeekdays, true))>
                    <span class="form-check-label">Tuesday</span>
                </label>
                <label class="form-check form--check mr-2 mr-md-4">
                    <input autocomplete="off" class="form-check-input rounded" type="checkbox" value="0" name="days" @checked(in_array('Wednesday', $selectedWeekdays, true))>
                    <span class="form-check-label">Wednesday</span>
                </label>
                <label class="form-check form--check mr-2 mr-md-4">
                    <input autocomplete="off" class="form-check-input rounded" type="checkbox" value="0" name="days" @checked(in_array('Thursday', $selectedWeekdays, true))>
                    <span class="form-check-label">Thursday</span>
                </label>
                <label class="form-check form--check mr-2 mr-md-4">
                    <input autocomplete="off" class="form-check-input rounded" type="checkbox" value="0" name="days" @checked(in_array('Friday', $selectedWeekdays, true))>
                    <span class="form-check-label">Friday</span>
                </label>
            </div>
            <div class="bg-light rounded p-3">
                <div class="mb-20">
                    <h5 class="title-clr mb-0">{{ translate('Date range') }}</h5>
                    <p class="fz-12">{{ translate('messages.Select the date range you want to repeat this cycle every week') }}</p>
                </div>
                <div class="mb-20">
                    <label class="form-label">{{ translate('Date range') }} <span class="text-danger">*</span></label>
                    <div class="position-relative date-range__custom">
                        <i class="tio-calendar-month icon-absolute-on-right"></i>
                        {{-- Seeded from the form field so the picker opens on the saved range instead
                             of blank — the Yes button rejects an empty range, so an unseeded modal
                             made every edit re-pick dates it already had. --}}
                        {{-- data-no-global-daterangepicker: common.js's page-wide `input[name="dates"]`
                             initializer (meant for the report-style filter in
                             admin-views/partials/_date-range.blade.php) matches this field by name
                             alone and has no scoping of its own. Left unmarked, it fired after
                             initDateRangePicker() below, immediately overwrote the field's text with
                             today's date in its own hardcoded MM/DD/YYYY format (autoUpdateInput
                             defaults true there), and initDateRangePicker() then read that text back
                             as if it were already-saved "DD MMM YYYY" state — moment's non-strict
                             parser "succeeds" on the mismatched format anyway, silently producing
                             09 Jan 2020 (the DD, "09", is all it can actually match), which is what
                             opened the calendar on January 2020 instead of today. --}}
                        <input autocomplete="off" type="text" class="form-control h-45 position-relative no-type {{ $isPermanent ? 'bg-transparent' : '' }}" id="weekly_modal_date"  name="dates" placeholder="{{ translate('Select date') }}" value="{{ $isPermanent ? '' : $ranges['weekly_date_range'] }}" {{ $isPermanent ? 'disabled' : '' }} data-no-global-daterangepicker>
                    </div>
                </div>
                <label class="form-check form--check mr-2 mr-md-4">
                    <input autocomplete="off" class="form-check-input rounded" type="checkbox" value="1" name="assign" id="weekly_is_permanent" @checked($isPermanent)>
                    <span class="form-check-label">{{ translate('messages.Assign this surge price permanently') }}</span>
                </label>
            </div>
        </div>
      </div>
      <div class="modal-footer justify-content-end border-0 pt-0 gap-2">
        <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal">{{ translate('messages.No') }}</button>
        <button type="button" class="btn min-w-120px btn--primary yes_date">{{ translate('messages.Yes') }}</button>
      </div>
    </div>
  </div>
</div>

<div class="modal shedule-modal fade" id="surgeCustom_sheduleBtn" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog modal-lg" modal-dialog-centered>
    <div class="modal-content">
      <div class="modal-header px-4 pt-4">
        <div>
            <h3 class="title-clr mb-0">{{ translate('Select date') }}</h3>
            <p class="fz-12 m-0">{{ translate('messages.Your Surge price active date') }}</p>
        </div>
        <button type="button" class="close bg-light w-30px h-30 rounded-circle" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-4">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="calendar">
                    <div class="d-flex align-items-center gap-4 justify-content-center">
                        <pre class="left"><i class="tio-chevron-left fs-24 cursor-pointer"></i></pre>
                        <div class="header-display">
                            <p class="display fs-14 font-semibold">""</p>
                        </div>
                        <pre class="right"><i class="tio-chevron-right fs-24 cursor-pointer"></i></pre>
                    </div>

                    <div class="week">
                    <div>Su</div>
                    <div>Mo</div>
                    <div>Tu</div>
                    <div>We</div>
                    <div>Th</div>
                    <div>Fr</div>
                    <div>Sa</div>
                    </div>
                    <div class="days"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="selected-listall">
                    <h5 class="mb-20">{{ translate('messages.Selected Days List') }}</h5>
                    <div class="d-flex align-items-center justify-content-md-start justify-content-between gap-1 mb-2">
                        <span class="fs-12 text-title opacity-50 text-uppercase min-w-110px">{{ translate('messages.Date') }}</span>
                        <span class="fs-12 text-title opacity-50 text-uppercase pe-30 me-3">{{ translate('messages.Time') }}</span>
                    </div>
                    <div class="selected-list-inner d-flex flex-column gap-3">

                    </div>
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer justify-content-end border-0 pt-0 pb-4 gap-2">
        <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal">{{ translate('messages.Cancel') }}</button>
        <button type="button" class="btn min-w-120px btn--primary">{{ translate('messages.Submit') }}</button>
      </div>
    </div>
  </div>
</div>

<div class="modal shedule-modal fade" id="timeRange_btn" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content pb-1">
      <div class="modal-header">
        <div>
            <h3 class="title-clr mb-0" id="edit-time-date-label">{{ translate('messages.Selected Date') }}</h3>
        </div>
        <button type="button" class="close bg-light w-30px h-30 rounded-circle" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="bg-light rounded p-3">
            <div class="time-range-wrapper">
                <label class="form-label">{{ translate('Change Time') }}</label>
                <div class="position-relative cursor-pointer">
                    <i class="tio-time icon-absolute-on-right"></i>
                    <input autocomplete="off" type="text" class="form-control h-45 position-relative bg-transparent time-range-picker no-type" id="edit-time-range-input" placeholder="{{ translate('messages.Select Time') }}">
                </div>
            </div>
        </div>
      </div>
      <div class="modal-footer justify-content-end border-0 pt-0 gap-2">
        <button type="button" class="btn min-w-120px btn--reset" data-dismiss="modal">{{ translate('messages.Cancel') }}</button>
        <button type="button" class="btn min-w-120px btn--primary" id="update-time-btn">{{ translate('Update') }}</button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="error-modal">
    <div class="modal-dialog status-warning-modal">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true" class="tio-clear"></span>
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="max-349 mx-auto mb-20">
                    <div>
                        <div class="text-center">
                            <img id="toggle-image" alt="" src="{{ asset('public/assets/admin/img/modal-error.png') }}" class="mb-20">
                            <h5 class="modal-title" id="toggle-title">{{ translate('Surge Price setup Overlap!') }}</h5>
                        </div>
                        <div class="text-center" id="toggle-message">
                            <p>{{ translate('This surge price overlaps with another. Please change the module or reschedule to fix it.') }}</p>
                        </div>
                    </div>
                    <div class="btn--container justify-content-center">
                        <button type="button" id="toggle-ok-button" class="btn btn--primary min-w-120" data-dismiss="modal" >{{translate('Okay')}}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


