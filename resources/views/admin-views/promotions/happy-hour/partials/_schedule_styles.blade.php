<style>
    .hh-form .card {
        border: 1px solid var(--bs-border-color);
        box-shadow: var(--bs-box-shadow-sm);
    }
    .hh-form .card + .card {
        margin-block-start: 1rem;
    }
    .hh-form .card-body {
        padding: 24px;
    }

    .hh-section {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding-block-end: 18px;
        margin-block-end: 20px;
        border-block-end: 1px solid var(--bs-border-color);
    }
    .hh-section__step {
        flex: 0 0 auto;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(var(--primary-clr-rgb), .1);
        color: var(--primary-clr);
        font-size: 13px;
        font-weight: 600;
    }
    .hh-section__title {
        margin: 0 0 2px;
        font-size: 15px;
        font-weight: 600;
        color: var(--title-clr);
    }
    .hh-section__desc {
        margin: 0;
        font-size: 13px;
        line-height: 1.5;
        color: var(--bs-body-color);
    }

    .hh-panel {
        padding: 20px;
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        background: var(--section-bg);
    }
    .hh-panel--plain {
        background: var(--bs-white);
    }
    .hh-panel__title {
        margin: 0 0 2px;
        font-size: 13px;
        font-weight: 600;
        color: var(--title-clr);
    }
    .hh-panel__desc {
        margin: 0;
        font-size: 12px;
        line-height: 1.5;
        color: var(--bs-body-color);
    }

    .hh-field {
        position: relative;
        display: flex;
        align-items: center;
        gap: 8px;
        height: 45px;
        padding: 0 12px;
        background: var(--bs-white);
        border: 1px solid var(--bs-border-color);
        border-radius: var(--field-radius-sm);
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .hh-field:hover {
        border-color: rgba(var(--primary-clr-rgb), .45);
    }
    .hh-field:focus-within,
    .hh-field.is-open {
        border-color: var(--primary-clr);
        box-shadow: 0 0 0 3px rgba(var(--primary-clr-rgb), .12);
    }
    .hh-field.is-filled {
        border-color: rgba(var(--primary-clr-rgb), .35);
    }
    .hh-field--range {
        padding: 0;
    }
    {{-- .hh-field.hh-field--range, not .hh-field--range: ties .hh-field > input's specificity
         (line ~118 below, which resets padding to 0) so this always wins regardless of source
         order, instead of the two silently fighting to a draw the input's placeholder had no
         left inset and no room reserved for the calendar icon on the right. --}}
    .hh-field.hh-field--range > input {
        align-self: stretch;
        width: 100%;
        padding-inline: 12px 38px;
    }
    .hh-field--range > i {
        position: absolute;
        inset-inline-end: 13px;
        inset-block-start: 50%;
        transform: translateY(-50%);
    }
    .hh-field--sm {
        height: 38px;
        width: 200px;
        padding: 0 10px;
        gap: 6px;
        font-size: 13px;
        flex: 0 0 auto;
        white-space: nowrap;
    }
    .hh-field > input {
        flex: 1 1 auto;
        min-width: 0;
        padding: 0;
        border: 0;
        outline: none;
        background: transparent;
        color: var(--title-clr);
        font: inherit;
        font-weight: 500;
    }
    .hh-field > input::placeholder {
        color: var(--bs-body-color);
        font-weight: 400;
        opacity: .8;
    }
    .hh-field > i {
        flex: 0 0 auto;
        color: var(--primary-clr);
        opacity: .8;
        pointer-events: none;
    }
    .hh-field.is-disabled {
        background: var(--section-bg);
        opacity: .65;
        pointer-events: none;
    }

    /* Matches .hh-field--range's icon: fixed inset rather than a flex child, so the calendar
       and clock icons sit at the same offset from the field's edge regardless of how wide the
       placeholder text or the native time input happens to render. */
    .hh-time {
        padding: 0 38px 0 12px;
    }
    .hh-time > i {
        position: absolute;
        inset-inline-end: 13px;
        inset-block-start: 50%;
        transform: translateY(-50%);
    }

    .hh-time__value {
        display: flex;
        align-items: center;
        gap: 6px;
        flex: 1 1 auto;
        min-width: 0;
    }
    .hh-time__value input[type="time"] {
        flex: 0 0 auto;
        width: auto;
        padding: 0;
        border: 0;
        outline: none;
        background: transparent;
        color: var(--title-clr);
        font: inherit;
        font-weight: 500;
    }
    .hh-time__value input[type="time"]::-webkit-calendar-picker-indicator {
        display: none;
    }
    .hh-time__end {
        white-space: nowrap;
        color: var(--title-clr);
        opacity: .75;
    }
    .hh-time__placeholder {
        flex: 1 1 auto;
        color: var(--bs-body-color);
        opacity: .8;
    }

    .hh-choice {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        height: 100%;
        padding: 14px 16px;
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        background: var(--bs-white);
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
    }
    .hh-choice:hover {
        border-color: rgba(var(--primary-clr-rgb), .45);
    }
    .hh-choice__input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .hh-choice__icon {
        flex: 0 0 auto;
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: var(--section-bg);
        color: var(--bs-body-color);
        font-size: 18px;
        transition: background-color .15s ease, color .15s ease;
    }
    .hh-choice__name {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: var(--title-clr);
        line-height: 1.3;
    }
    .hh-choice__hint {
        display: block;
        margin-block-start: 2px;
        font-size: 12px;
        line-height: 1.4;
        color: var(--bs-body-color);
    }
    .hh-choice__input:checked ~ .hh-choice__icon {
        background: var(--primary-clr);
        color: var(--bs-white);
    }
    .hh-choice:has(.hh-choice__input:checked) {
        border-color: var(--primary-clr);
        background: rgba(var(--primary-clr-rgb), .04);
        box-shadow: 0 0 0 1px var(--primary-clr) inset;
    }
    .hh-choice__input:focus-visible ~ .hh-choice__icon {
        outline: 2px solid var(--primary-clr);
        outline-offset: 2px;
    }

    .hh-label-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-block-end: .5rem;
    }
    .hh-label-row .input-label {
        margin: 0;
    }

    {{-- Sized from what the boxes actually need rather than a fixed 7/5 column split, so the pair
         stays side by side and in proportion at whatever width the parent column resolves to --
         full width once it has dropped below the title/description under xl, or 5/12 above it. --}}
    .hh-upload-row {
        {{-- .row's own negative gutter margins assume .col-* children supplying matching
             padding to offset them. hh-upload-col supplies none (spacing is via gap instead),
             so left unset here they'd bleed the boxes past the column's edges. --}}
        margin: 0;
        gap: 12px;
    }
    .hh-upload-col {
        {{-- min-width:0 lets a column shrink below the intrinsic width of the 3:1 upload widget
             inside it -- the default `auto` floor is what let the cover spill out of its card. --}}
        min-width: 0;
        max-width: 100%;
        padding: 0;
    }
    {{-- Grows 3:1 against the icon, matching the two ratios, so the extra width a full-width row
         hands back lands mostly on the cover instead of being split evenly. --}}
    .hh-upload-col--cover {
        flex: 3 1 200px;
    }
    .hh-upload-col--icon {
        flex: 1 1 120px;
    }
    {{-- The widget sets block-size:100px with an aspect-ratio, so its inline size resolves to
         300px for a 3:1 cover regardless of the room available. Capped to the column here so a
         narrow column shrinks the box rather than overflowing the card. --}}
    .hh-upload__box {
        width: 100%;
    }
    .hh-upload__box .upload-file_custom,
    .hh-upload__box .upload-file__wrapper {
        max-inline-size: 100%;
    }

    .hh-upload {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        height: 100%;
        padding: 16px 12px;
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        background: var(--bs-white);
        text-align: center;
    }
    .hh-upload__title {
        margin: 0;
        font-size: 13px;
        font-weight: 600;
        color: var(--title-clr);
    }
    .hh-upload__desc {
        margin: 0 0 4px;
        font-size: 12px;
        line-height: 1.4;
        color: var(--bs-body-color);
    }
    .hh-upload .upload-file_custom {
        max-width: 100%;
    }
    .hh-upload p {
        margin-block: 8px 0;
        font-size: 11px;
        line-height: 1.4;
    }

    .hh-calendar th {
        font-weight: 500;
        font-size: 12px;
        color: var(--bs-body-color);
        padding: 4px;
    }
    .hh-calendar td {
        padding: 3px;
    }
    .hh-calendar .cal-day {
        width: 36px;
        height: 36px;
        padding: 0;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--title-clr);
        font-size: 13px;
        transition: background-color .12s ease, color .12s ease;
    }
    .hh-calendar .cal-day:hover {
        background: rgba(var(--primary-clr-rgb), .1);
    }
    .hh-calendar .cal-day.is-picked {
        background: var(--primary-clr);
        color: var(--bs-white);
        font-weight: 600;
    }
    .hh-calendar .cal-day.is-muted {
        opacity: .35;
    }
    .hh-calendar .cal-day.is-past {
        opacity: .3;
        cursor: not-allowed;
        text-decoration: line-through;
    }
    .hh-calendar .cal-day.is-past:hover {
        background: transparent;
    }

    .hh-cal-nav {
        width: 30px;
        height: 30px;
        border: 1px solid var(--bs-border-color);
        border-radius: 8px;
        background: var(--bs-white);
        color: var(--title-clr);
        line-height: 1;
        padding: 0;
    }
    .hh-cal-nav:hover {
        border-color: var(--primary-clr);
        color: var(--primary-clr);
    }

    {{-- Heads the list, so the count reads against the rows it belongs to rather than against
         the picker above it. --}}
    .hh-summary__title {
        margin: 0 0 6px;
        font-size: 13px;
        font-weight: 600;
        color: var(--title-clr);
    }

    .hh-day-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
    }
    .hh-day-row + .hh-day-row {
        border-block-start: 1px solid var(--bs-border-color);
    }
    .hh-day-row__date {
        width: 42%;
        flex-shrink: 0;
        color: var(--title-clr);
    }
    .hh-day-row__time {
        flex-grow: 1;
        color: var(--bs-body-color);
    }
    .hh-day-row__btn {
        width: 38px;
        height: 38px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border-radius: 8px;
    }

    .hh-remove {
        width: 24px;
        height: 24px;
        flex-shrink: 0;
        padding: 0;
        line-height: 1;
        border: 1px solid var(--danger-clr);
        border-radius: 50%;
        background: transparent;
        color: var(--danger-clr);
    }
    .hh-remove:hover {
        background: var(--danger-clr);
        color: var(--bs-white);
    }

    .daterangepicker {
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        box-shadow: var(--bs-box-shadow);
        font-family: inherit;
    }
    {{-- Restated explicitly against both end classes rather than the bare .active vendor.min.css
         gives it, so the fill does not depend on theme.minc619.css (loaded earlier, and not
         under this page's control) still carrying its own .active.start-date/.active.end-date
         pair — both ends of the range fill and read white here regardless of what the other two
         stylesheets do. :not(.off) matters: when start === end (today, before any pick), that
         single day is both start-date and end-date, and it also renders a second time as a
         greyed leading cell in the next month's grid — without the exclusion this rule beat
         theme.minc619.css's own .off dimming there (equal specificity, this block loads later)
         and lit up that muted duplicate solid teal, which is what made a fresh, untouched picker
         look like two dates were already selected across both calendars. --}}
    .daterangepicker td.active:not(.off),
    .daterangepicker td.active:not(.off):hover,
    .daterangepicker td.active.start-date:not(.off),
    .daterangepicker td.active.end-date:not(.off),
    .daterangepicker td.active.start-date:not(.off):hover,
    .daterangepicker td.active.end-date:not(.off):hover {
        background-color: var(--primary-clr);
        color: #fff;
    }
    {{-- Solid, not a tint: theme.minc619.css already ships a solid in-range fill, but this rule
         has equal selector specificity and loads after it, so whatever colour is set here is what
         actually wins -- a faint alpha here was silently downgrading an already-solid band to a
         barely-visible one. Matching the endpoints' own solid colour makes the whole range read as
         one continuous filled bar rather than two filled dots with a washed-out gap between them. --}}
    .daterangepicker td.in-range {
        background-color: var(--primary-clr);
        color: #fff;
    }
    .daterangepicker .drp-buttons .btn {
        border-radius: 8px;
        font-weight: 500;
    }
    .daterangepicker .applyBtn {
        background-color: var(--primary-clr);
        border-color: var(--primary-clr);
    }

    @media (max-width: 767.98px) {
        .hh-form .card-body {
            padding: 18px;
        }
        .hh-field--sm {
            width: 100%;
        }
        .hh-day-row {
            flex-wrap: wrap;
            gap: 10px;
        }
        .hh-day-row__date {
            width: auto;
        }
    }
</style>
