{{-- Ported with the Duration Setup card: the date and time range pickers, the weekly-days
     modal, the permanent-weekly flag and the custom-schedule table. --}}
    <script>

        {{-- The edit form prints a saved range as "31 Jul 2025 - 31 Aug 2026" (SurgePriceController::rangeValues),
             so the pickers have to read and write that same format. Left on the library's MM/DD/YYYY
             default the prefill parsed as an invalid date, which is what put "Invalid date" in the
             calendar header and a grid of NaN under it. --}}
        var SURGE_DATE_FORMAT = 'DD MMM YYYY';
        var SURGE_DATE_SEPARATOR = ' - ';

        /** The two dates a range field already holds, or null when it holds nothing readable. */
        function surgeReadDateRange($input) {
            var parts = String($input.val() || '').split(SURGE_DATE_SEPARATOR);

            if (parts.length !== 2) {
                return null;
            }

            var start = moment(parts[0].trim(), SURGE_DATE_FORMAT);
            var end = moment(parts[1].trim(), SURGE_DATE_FORMAT);

            return start.isValid() && end.isValid() ? { start: start, end: end } : null;
        }

        function initDateRangePicker(selector) {
            let $input = $(selector);

            if (!$input.length) {
                return;
            }

            let saved = surgeReadDateRange($input);
            let today = moment().startOf('day');

            $input.daterangepicker({
                // A surge that is already running started before today. Holding the floor at today
                // would drag its start forward the moment the picker opened, so an existing range
                // lowers the floor to its own start.
                minDate: saved && saved.start.isBefore(today) ? saved.start.clone() : today,
                // Always explicit: with both dates passed the library skips parsing the field itself,
                // so a value it cannot read can never reach the calendar.
                startDate: saved ? saved.start : today.clone(),
                endDate: saved ? saved.end : today.clone(),
                autoUpdateInput: false,
                locale: {
                    format: SURGE_DATE_FORMAT,
                    separator: SURGE_DATE_SEPARATOR,
                    cancelLabel: 'Clear'
                }
            });
            $input.on('apply.daterangepicker', function (ev, picker) {
                $(this).val(
                    picker.startDate.format(SURGE_DATE_FORMAT) + SURGE_DATE_SEPARATOR + picker.endDate.format(SURGE_DATE_FORMAT)
                );
            });
            $input.on('cancel.daterangepicker', function () {
                $(this).val('');
                $(this).attr('placeholder', $(this).data('placeholder'));
            });
            if (!$input.val()) {
                $input.attr('placeholder', $input.data('placeholder'));
            }
        }

        $(function () {
            initDateRangePicker('input[name="daily_date_range"]');
        });

        $(function() {
            $(document).ready(function () {
                let selectedDateRange = "";

                // Same picker, same format: the modal writes what the form field already shows, so
                // reopening it on edit does not turn a saved range into a second notation.
                initDateRangePicker('#weeklySelectDays_btn input[name="dates"]');

                $('#weeklySelectDays_btn input[name="dates"]').on('apply.daterangepicker', function () {
                    selectedDateRange = $(this).val();
                });

                $('#weeklySelectDays_btn input[name="dates"]').on('cancel.daterangepicker', function (ev, picker) {
                    $(this).val('');
                    selectedDateRange = '';
                });

                $('.yes_date').on('click', function () {
                    let selectedDateRange = $('#weeklySelectDays_btn input[name="dates"]').val();
                    let selectedDays = [];
                    $('input[name="days"]:checked').each(function () {
                        selectedDays.push($(this).siblings('.form-check-label').text().trim());
                    });

                    let isPermanent = $('input[name="assign"]').is(':checked') ? 1 : 0;

                    if (selectedDays.length === 0) {
                        toastr.error(@json(translate('messages.Please select at least one day.')));
                        return;
                    }

                    if (!selectedDateRange && !isPermanent) {
                        toastr.error(@json(translate('messages.Please select a date range.')));
                        return;
                    }



                    $('#weekly_days').val(selectedDays.join(','));
                    $('#is_permanent').val(isPermanent);
                    $('.shedule_item .date-range-input-demo').val(selectedDateRange);

                    function formatSelectedDays(days) {
                        if (days.length === 1) {
                            return days[0];
                        } else if (days.length === 2) {
                            return days[0] + ' & ' + days[1];
                        } else {
                            return days.slice(0, -1).join(', ') + ' & ' + days[days.length - 1];
                        }
                    }
                    $('#selected-weekdays-text').text(formatSelectedDays(selectedDays));

                    if (selectedDays) {
                        $('.weekly-summary').removeClass('d-none');
                    } else {
                        $('.weekly-summary').addClass('d-none');
                    }

                    $('#weeklySelectDays_btn').modal('hide');
                });

                $('#weekly_is_permanent').on('change', function () {
                    if ($(this).is(':checked')) {
                        $('#weekly_modal_date')
                            .prop('disabled', true)
                            .addClass('bg-transparent').val('');
                             $('.shedule_item .date-range-input-demo').val('');
                    } else {
                        $('#weekly_modal_date')
                            .prop('disabled', false)
                            .removeClass('bg-transparent');
                    }
                });
            });

            $(document).ready(function () {
                function updateScheduleVisibility() {
                    const radios = $('.shedule-checkbox-inner .form-check-input');
                    const items = $('.change-shedule-wrapper .shedule_item');
                    items.hide();
                    radios.each(function(index) {
                        if ($(this).is(':checked')) {
                            items.eq(index).show();
                        }
                    });
                }

                updateScheduleVisibility();

                $('.shedule-checkbox-inner .form-check-input').on('change', function () {
                    updateScheduleVisibility();
                });
            });

            $('.js-select').each(function () {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });

            $(document).ready(function() {
                $('#module_selected').select2({
                    placeholder: 'Module'
                });
            });

            {{-- closeOnSelect is false on the module picker so several modules can be chosen
                 without reopening the list -- but select2 leaves the typed term sitting in the
                 search box when the dropdown stays open, so the keyword just searched for
                 remained behind next to the new chip, reading as though it had been taken as a
                 value. Cleared on each pick, which unfilters the list for the next one too.
                 Delegated: the theme re-initialises .js-select2-custom after an ajax mount. --}}
            $(document).on('select2:select select2:unselect', '#module_ids', function () {
                $(this).siblings('.select2-container')
                    .find('.select2-search__field')
                    .val('')
                    .trigger('input');
            });

            // Matches SurgePriceController::rangeValues(), which prints a saved time in this
            // same 'g:i A' shape — without it, a fresh page load never tells the picker what
            // the field already holds and it falls back to the library's own default range
            // (12:00 AM - 11:59 PM) until the admin picks a time and applies it once.
            var SURGE_TIME_FORMAT = 'h:mm A';

            function surgeReadTimeRange($input) {
                var parts = String($input.val() || '').split(SURGE_DATE_SEPARATOR);

                if (parts.length !== 2) {
                    return null;
                }

                var start = moment(parts[0].trim(), SURGE_TIME_FORMAT);
                var end = moment(parts[1].trim(), SURGE_TIME_FORMAT);

                return start.isValid() && end.isValid() ? { start: start, end: end } : null;
            }

            $('.time-range-picker').each(function () {
                const $input = $(this);
                const saved = surgeReadTimeRange($input);

                // The constructor's own (start, end) callback only fires when the picker's
                // internal startDate/endDate actually differ from what they were when it
                // opened (bootstrap-daterangepicker's hide(): "isSame(oldStartDate) || callback(...)").
                // Clicking Apply without first touching a dropdown is exactly the case where
                // nothing "changed" — so on a fresh open the callback never runs and the field
                // stays empty. Binding 'apply.daterangepicker' instead reads the event the
                // library always fires on Apply, unconditionally — the same pattern already
                // used for the date-range picker above.
                $input.daterangepicker({
                    timePicker: true,
                    timePicker24Hour: false,
                    timePickerIncrement: 5,
                    startDate: saved ? saved.start : moment().startOf('day'),
                    endDate: saved ? saved.end : moment().endOf('day'),
                    locale: {
                        format: SURGE_TIME_FORMAT
                    },
                    singleDatePicker: false,
                    showDropdowns: false,
                    autoUpdateInput: false
                });

                $input.on('apply.daterangepicker', function (ev, picker) {
                    $(this).val(picker.startDate.format(SURGE_TIME_FORMAT) + SURGE_DATE_SEPARATOR + picker.endDate.format(SURGE_TIME_FORMAT));
                });

                $input.on('show.daterangepicker', function(ev, picker) {
                    picker.container.find('.calendar-table').hide();
                    picker.container.find('.calendar-time').css('visibility', 'visible');
                });
            });

            $(document).on('click', '.selected-list-inner .removeDay', function () {
                $(this).closest('.selected-list-item').remove();
            });

            let display = document.querySelector(".display");
            let days = document.querySelector(".days");
            let previous = document.querySelector(".left");
            let next = document.querySelector(".right");
            let selectedListInner = document.querySelector(".selected-list-inner");

            let date = new Date();
            let year = date.getFullYear();
            let month = date.getMonth();
            let selectedDates = new Set();

            function displayCalendar() {
                days.innerHTML = "";

                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const firstDayIndex = firstDay.getDay();
                const numberOfDays = lastDay.getDate();
                const minDate = new Date();
                minDate.setHours(0, 0, 0, 0);

                let formattedDate = date.toLocaleString("en-US", {
                    month: "long",
                    year: "numeric"
                });

                display.innerHTML = `${formattedDate}`;

                for (let x = 0; x < firstDayIndex; x++) {
                    const div = document.createElement("div");
                    days.appendChild(div);
                }

                for (let i = 1; i <= numberOfDays; i++) {
                    let div = document.createElement("div");
                    let currentDate = new Date(year, month, i);
                    currentDate.setHours(0, 0, 0, 0);
                    let dateStr = currentDate.toDateString();

                    div.dataset.date = dateStr;
                    div.textContent = i;

                    if (currentDate < minDate) {
                        div.classList.add("disabled");
                    } else {
                        if (currentDate.toDateString() === new Date().toDateString()) {
                            div.classList.add("active");
                        }

                        if (selectedDates.has(dateStr)) {
                            div.classList.add("active");
                        }
                    }

                    days.appendChild(div);
                }

                bindDateClickEvents();
            }
            function bindDateClickEvents() {
                const dayElements = document.querySelectorAll(".days div[data-date]");
                dayElements.forEach((day) => {
                    day.addEventListener("click", (e) => {
                        const clickedDate = e.target.dataset.date;

                        if (!selectedDates.has(clickedDate)) {
                            selectedDates.add(clickedDate);
                            e.target.classList.add("active");
                            appendSelectedItem(clickedDate);
                        }
                    });
                });
            }
            /** `timeString` is the saved "hh:mm A - hh:mm A" pair on edit, and empty on a fresh pick. */
            function appendSelectedItem(dateString, timeString) {
                const item = document.createElement("div");
                item.className = "selected-list-item d-flex flex-sm-nowrap flex-wrap align-items-center justify-content-between justify-content-md-start gap-2 bg-light rounded py-2 px-2";

                item.innerHTML = `
                    <span class="fs-12 text-title text-nowrap after-date">
                        <div class="display-selected">
                            <p class="selected m-0 p-0">${dateString}</p>
                        </div>
                    </span>
                    <input autocomplete="off" type="hidden" name="custom_dates[]" id="custom_dates" value="${dateString}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="position-relative cursor-pointer">
                            <i class="tio-time icon-absolute-on-right fs-12"></i>
                            <input autocomplete="off" type="text" class="form-control position-relative fs-10 h-32px bg-white time-range-picker" name="custom_time_range[]" placeholder="Select Time">
                        </div>
                        <button type="button" class="removeDay text-danger btn p-0"><i class="tio-clear-circle-outlined fs-20"></i></button>
                    </div>
                `;

                item.querySelector(".removeDay").addEventListener("click", () => {
                    selectedDates.delete(dateString);
                    item.remove();
                    document.querySelectorAll(`.days div[data-date="${dateString}"]`).forEach(el => el.classList.remove("active"));
                });

                selectedListInner.appendChild(item);

                // A saved row reopens on its own times; a new one on the current hour.
                let startTime = moment().startOf('hour');
                let endTime = moment().startOf('hour').add(1, 'hour');

                if (timeString && timeString.indexOf(' - ') !== -1) {
                    const bounds = timeString.split(' - ');
                    const savedStart = moment(bounds[0].trim(), 'hh:mm A');
                    const savedEnd = moment(bounds[1].trim(), 'hh:mm A');

                    if (savedStart.isValid() && savedEnd.isValid()) {
                        startTime = savedStart;
                        endTime = savedEnd;
                        $(item).find('.time-range-picker').val(timeString);
                    }
                }

                $(item).find('.time-range-picker').daterangepicker({
                    timePicker: true,
                    timePicker24Hour: false,
                    timePickerIncrement: 5,
                    locale: {
                        format: 'hh:mm A'
                    },
                    singleDatePicker: false,
                    autoApply: true,
                    startDate: startTime,
                    endDate: endTime,
                }, function(start, end, label) {
                    const formatted = start.format("hh:mm A") + ' - ' + end.format("hh:mm A");
                    $(this.element).val(formatted);
                }).on('show.daterangepicker', function(ev, picker) {
                    picker.container.addClass('calendar-table-custom');
                });
            }
            previous.addEventListener("click", () => {
                if (month === 0) {
                    month = 11;
                    year -= 1;
                } else {
                    month--;
                }
                date.setMonth(month);
                displayCalendar();
            });
            next.addEventListener("click", () => {
                if (month === 11) {
                    month = 0;
                    year += 1;
                } else {
                    month++;
                }
                date.setMonth(month);
                displayCalendar();
            });
            {{-- The dates are stored exactly as this calendar writes them — JS `toDateString()`
                 strings, comma-joined by `#custom_days` — so they are read back the same way.
                 Without this the modal opened on today no matter what was saved, and pressing
                 Submit overwrote the real schedule with that one day. --}}
            function seedSelectedDates() {
                const savedDays = String($('#custom_days').val() || '').split(',');
                const savedTimes = String($('#custom_times').val() || '').split(',');
                let seeded = 0;

                savedDays.forEach(function (raw, index) {
                    const parsed = new Date(raw.trim());

                    if (!raw.trim() || isNaN(parsed.getTime())) {
                        return;
                    }

                    // Normalised, so the key matches the `data-date` the day cells carry.
                    const dateStr = parsed.toDateString();

                    if (selectedDates.has(dateStr)) {
                        return;
                    }

                    selectedDates.add(dateStr);
                    appendSelectedItem(dateStr, (savedTimes[index] || '').trim());
                    seeded++;
                });

                if (seeded === 0) {
                    const todayStr = new Date().toDateString();
                    selectedDates.add(todayStr);
                    appendSelectedItem(todayStr, '');
                }
            }

            // Seeded before the grid is drawn so the saved days come up already marked active.
            seedSelectedDates();
            displayCalendar();


            $('#surgeCustom_sheduleBtn .btn--primary').on('click', function () {
                let customDates = [];
                let customTimes = [];

                $('.selected-list-inner .selected-list-item').each(function () {
                    const date = $(this).find('input[name="custom_dates[]"]').val();
                    const time = $(this).find('input[name="custom_time_range[]"]').val();

                    if (date && time) {
                        customDates.push(date.trim());
                        customTimes.push(time.trim());
                    }
                });

                if (customDates.length === 0 || customTimes.includes("")) {
                    toastr.error(@json(translate('messages.Please select both date and time for all custom entries.')));
                    return;
                }

                $('#custom_days').val(customDates);
                $('#custom_times').val(customTimes);

                const selectedCount = customDates.length;
                const placeholderText = selectedCount + ' day' + (selectedCount > 1 ? 's' : '') + ' selected';

                $('#custom_schedule_input').attr('placeholder', placeholderText);

                const sortedDates = customDates.map(date => new Date(date)).sort((a, b) => a - b);

                const minDate = sortedDates[0];
                const maxDate = sortedDates[sortedDates.length - 1];

                const options = { day: 'numeric', month: 'short', year: 'numeric' };

                const formattedMin = minDate.toLocaleDateString('en-US', options);
                const formattedMax = maxDate.toLocaleDateString('en-US', options);

                $('#custom-date-min').text(formattedMin);
                $('#custom-date-max').text(formattedMax);
                $('#custom-date-range-text').removeClass('d-none');
                $('#custom-schedule-table').removeClass('d-none');

                renderCustomScheduleTable(customDates, customTimes);

                $('#surgeCustom_sheduleBtn').modal('hide');
            });

            function renderCustomScheduleTable(dates, times) {
                const tbody = $('#customScheduleTableBody');
                tbody.empty();

                dates.forEach((date, index) => {
                    const time = times[index] || '';
                    const sl = index + 1;

                    const formattedDate = new Date(date).toLocaleDateString('en-US', {
                        weekday: 'short', month: 'short', day: '2-digit'
                    });

                    const row = `
                        <tr data-index="${index}">
                            <td class="pl-4">${sl}</td>
                            <td>
                                <span class="d-block max-w-220px min-w-176px">
                                    <span class="d-block text-title">${formattedDate}</span>
                                    <span>${time}</span>
                                </span>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a href="#0" class="btn action-btn action-btn--edit btn-edit-schedule" data-toggle="modal" data-target="#timeRange_btn">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn action-btn--delete btn-remove-schedule" href="#0">
                                        <i class="tio-delete-outlined"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    `;
                    tbody.append(row);
                });
            }

            $(document).on('click', '.btn-remove-schedule', function (e) {
                e.preventDefault();

                const $row = $(this).closest('tr');
                const index = $row.data('index');

                const $selectedItem = $('.selected-list-inner .selected-list-item').eq(index);
                const dateStr = $selectedItem.find('input[name="custom_dates[]"]').val();

                if (dateStr) {
                    selectedDates.delete(dateStr);
                    document.querySelectorAll(`.days div[data-date="${dateStr}"]`).forEach(el => el.classList.remove("active"));
                }

                $selectedItem.remove();

                let updatedDates = [];
                let updatedTimes = [];

                $('.selected-list-inner .selected-list-item').each(function () {
                    const date = $(this).find('input[name="custom_dates[]"]').val();
                    const time = $(this).find('input[name="custom_time_range[]"]').val();

                    if (date && time) {
                        updatedDates.push(date.trim());
                        updatedTimes.push(time.trim());
                    }
                });

                $('#custom_days').val(updatedDates);
                $('#custom_times').val(updatedTimes);

                const selectedCount = updatedDates.length;
                const placeholderText = selectedCount > 0
                    ? selectedCount + ' day' + (selectedCount > 1 ? 's' : '') + ' selected'
                    : '{!! translate('Select date & time') !!}';

                $('#custom_schedule_input').attr('placeholder', placeholderText);


                if(updatedDates.length > 0) {
                    const sortedDates = updatedDates.map(date => new Date(date)).sort((a, b) => a - b);

                    const minDate = sortedDates[0];
                    const maxDate = sortedDates[sortedDates.length - 1];

                    const options = { day: 'numeric', month: 'short', year: 'numeric' };

                    const formattedMin = minDate.toLocaleDateString('en-US', options);
                    const formattedMax = maxDate.toLocaleDateString('en-US', options);

                    $('#custom-date-min').text(formattedMin);
                    $('#custom-date-max').text(formattedMax);
                }


                renderCustomScheduleTable(updatedDates, updatedTimes);

                if (updatedDates.length === 0) {
                    $('#custom-date-range-text').addClass('d-none');
                    $('#custom-schedule-table').addClass('d-none');
                }
            });



            let currentEditIndex = null;

            $(document).on('click', '.btn-edit-schedule', function () {
                const $row = $(this).closest('tr');
                currentEditIndex = $row.data('index');

                const $targetItem = $('.selected-list-inner .selected-list-item').eq(currentEditIndex);
                const dateStr = $targetItem.find('input[name="custom_dates[]"]').val();
                const timeStr = $targetItem.find('input[name="custom_time_range[]"]').val();

                $('#edit-time-date-label').text(new Date(dateStr).toDateString());

                const $editInput = $('#edit-time-range-input');

                let startTime = moment().startOf('hour');
                let endTime = moment().startOf('hour').add(1, 'hour');

                if (timeStr && timeStr.includes(' - ')) {
                    const [start, end] = timeStr.split(' - ');
                    startTime = moment(start.trim(), 'hh:mm A');
                    endTime = moment(end.trim(), 'hh:mm A');
                }

                const formatted = startTime.format("hh:mm A") + ' - ' + endTime.format("hh:mm A");
                $editInput.val(formatted);

                $editInput.daterangepicker({
                    timePicker: true,
                    timePicker24Hour: false,
                    timePickerIncrement: 5,
                    locale: {
                        format: 'hh:mm A'
                    },
                    singleDatePicker: false,
                    autoApply: true,
                    startDate: startTime,
                    endDate: endTime
                }, function (start, end) {
                    const updated = start.format("hh:mm A") + ' - ' + end.format("hh:mm A");
                    $editInput.val(updated);
                }).on('show.daterangepicker', function (ev, picker) {
                    picker.container.addClass('calendar-table-custom');
                });

                $('#timeRange_btn').modal('show');
            });


            $('#update-time-btn').on('click', function () {
                const newTime = $('#edit-time-range-input').val();

                if (!newTime) {
                    toastr.error(@json(translate('messages.Please select a time.')));
                    return;
                }

                if (currentEditIndex !== null) {
                    const $item = $('.selected-list-inner .selected-list-item').eq(currentEditIndex);

                    $item.find('input[name="custom_time_range[]"]').val(newTime);

                    const $timeInput = $item.find('.time-range-picker');
                    $timeInput.val(newTime);

                    const pickerInstance = $timeInput.data('daterangepicker');
                    if (pickerInstance && newTime.includes(' - ')) {
                        const [start, end] = newTime.split(' - ');
                        const startTime = moment(start.trim(), 'hh:mm A');
                        const endTime = moment(end.trim(), 'hh:mm A');
                        pickerInstance.setStartDate(startTime);
                        pickerInstance.setEndDate(endTime);
                    }

                    let updatedDates = [];
                    let updatedTimes = [];

                    $('.selected-list-inner .selected-list-item').each(function () {
                        const date = $(this).find('input[name="custom_dates[]"]').val();
                        const time = $(this).find('input[name="custom_time_range[]"]').val();
                        if (date && time) {
                            updatedDates.push(date.trim());
                            updatedTimes.push(time.trim());
                        }
                    });

                    $('#custom_days').val(updatedDates);
                    $('#custom_times').val(updatedTimes);

                    renderCustomScheduleTable(updatedDates, updatedTimes);

                    const selectedCount = updatedDates.length;
                    const placeholderText = selectedCount > 0
                        ? selectedCount + ' day' + (selectedCount > 1 ? 's' : '') + ' selected'
                        : '{{ translate('Select date & time') }}';
                    $('#custom_schedule_input').attr('placeholder', placeholderText);

                    if (updatedDates.length > 0) {
                        const sorted = updatedDates.map(d => new Date(d)).sort((a, b) => a - b);
                        const options = { day: 'numeric', month: 'short', year: 'numeric' };
                        $('#custom-date-min').text(sorted[0].toLocaleDateString('en-US', options));
                        $('#custom-date-max').text(sorted[sorted.length - 1].toLocaleDateString('en-US', options));
                        $('#custom-date-range-text').removeClass('d-none');
                        $('#custom-schedule-table').removeClass('d-none');
                    }

                    $('#timeRange_btn').modal('hide');
                }
            });


            // The old screen posted this form by AJAX and read a JSON envelope back, because its
            // controller answered `{status, message}` and a bare 409 for a schedule conflict. The
            // form submits normally now: the conflict is DESIGN RULE G1 and arrives as a field
            // error on `duration_type`, which only a normal redirect-back can show in place.
        });

    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const maxLength = 50;

        function updateCharCount(input) {
            const countSpan = input.closest('.form-group')?.querySelector('.surge-char-count');

            if (!countSpan) {
                return;
            }

            const length = input.value.length;

            if (length > maxLength) {
                input.value = input.value.substring(0, maxLength);
            }

            countSpan.textContent = `${input.value.length}/${maxLength}`;
        }

        // A textarea in the new design, an input in the old one — both are matched.
        const customerNoteInputs = document.querySelectorAll('[name="customer_note[]"]');

        customerNoteInputs.forEach(input => {
            input.addEventListener('input', function () {
                updateCharCount(input);
            });

            updateCharCount(input);
        });

        // The note toggle only lives on the default tab, and only that tab's textarea is
        // validated (customer_note.0) — mirror the server-side requiredIf rule here.
        const noteToggle = document.getElementById('customer_note_status');
        const defaultNoteInput = document.getElementById('customer_note_default');

        if (noteToggle && defaultNoteInput) {
            noteToggle.addEventListener('change', function () {
                defaultNoteInput.required = noteToggle.checked;
            });
        }
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('input.no-type');

    inputs.forEach(input => {
        input.addEventListener('keydown', function (e) {
            e.preventDefault();
        });
        input.style.cursor = 'pointer';
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const priceInput = document.getElementById('price');
    const priceTypeSelect = document.getElementById('price_type');

    function updateMaxLimit() {
        if (priceTypeSelect.value === 'percent') {
            priceInput.max = '100';

            if (parseFloat(priceInput.value) > 100) {
                priceInput.value = '100';
            }

            priceInput.addEventListener('input', percentLimiter);
        } else {
            priceInput.removeAttribute('max');
            priceInput.removeEventListener('input', percentLimiter);
        }
    }

    function percentLimiter(e) {
        if (parseFloat(priceInput.value) > 100) {
            priceInput.value = '100';
        }
    }

    priceTypeSelect.addEventListener('change', updateMaxLimit);
    updateMaxLimit();
});
</script>
