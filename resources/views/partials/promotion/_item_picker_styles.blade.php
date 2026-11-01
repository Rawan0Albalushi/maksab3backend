{{-- Item picker chrome, shared by every promotion panel that picks items: the select2 result
     rows and the add-on cards have no house equivalent, so they are defined once here
     rather than duplicated per panel. --}}
    <style>
        /* Food picker: select2 renders plain text rows by default, the design wants a
           card per item with thumbnail, name and category. */
        .food-select2 .select2-results__option {
            padding: 0 0 10px;
            background: transparent !important;
        }
        .food-select2 .select2-results__options {
            padding: 4px;
            max-height: 320px;
        }
        .food-select2 .food-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border: 1px solid var(--border-clr);
            border-radius: 8px;
            background: #fff;
        }
        .food-select2 .select2-results__option--highlighted .food-option {
            border-color: var(--primary-clr);
        }
        .food-select2 .food-option__thumb {
            position: relative;
            width: 56px;
            height: 56px;
            flex-shrink: 0;
        }
        .food-select2 .food-option__thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        /* Band across the thumbnail, matching how unavailable foods read in the design. */
        .food-select2 .food-option__flag {
            position: absolute;
            inset-inline: 0;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, .55);
            color: #fff;
            font-size: 9px;
            line-height: 1.6;
            text-align: center;
        }
        .food-select2 .food-option__name {
            font-size: 15px;
            color: var(--title-clr);
        }
        .food-select2 .food-option__meta {
            font-size: 13px;
            opacity: .65;
        }
        .food-select2 .food-option__price {
            font-size: 13px;
            font-weight: 600;
            color: var(--title-clr);
            margin-top: 2px;
        }

        /* select2's empty-state message inherits the row reset above, which strips its padding
           and leaves it flush against the edge. */
        .food-select2 .select2-results__message {
            padding: 18px 12px;
            text-align: center;
            font-size: 13px;
            color: var(--title-clr);
            opacity: .65;
        }

        .food-select2 .food-option--off .food-option__name,
        .food-select2 .food-option--off .food-option__meta {
            opacity: .45;
        }
        .food-select2 .food-option--off .food-option__thumb img {
            filter: grayscale(1);
        }

        .food-select2 .select2-search--dropdown {
            padding: 12px 12px 4px;
            position: relative;
        }
        .food-select2 .select2-search--dropdown .select2-search__field {
            height: 45px;
            padding: 0 46px 0 14px;
            border: 1px solid var(--border-clr) !important;
            border-radius: 8px;
            outline: none;
        }
        /* Magnifier while the box is empty; the clear control once it is not. Inert in the
           first state so a click lands on the field behind it, live in the second. */
        .food-select2 .food-search-icon {
            position: absolute;
            top: 12px;
            inset-inline-end: 12px;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-start-end-radius: 8px;
            border-end-end-radius: 8px;
            background: var(--section-bg2);
            color: var(--primary-clr);
            pointer-events: none;
        }
        .food-select2 .food-search-icon.is-clearable {
            pointer-events: auto;
            cursor: pointer;
        }

        /* The drawer body is a flex item, so its min-height defaults to its content height -
           and min-height beats the max-height it already carries. Left at auto the body simply
           grew past the viewport and overflow-y never engaged, so a selection long enough to
           reach the footer could not be scrolled to at all. */
        .custom-offcanvas .custom-offcanvas-body.flex-grow-1 {
            min-height: 0;
        }

        /* Addon cards: unselected is a bordered white tile, selected fills with the theme
           colour and reveals its own quantity stepper. */
        .addon-card {
            width: 108px;
            flex-shrink: 0;
            border: 1px solid var(--border-clr);
            border-radius: 8px;
            background: #fff;
            padding: 10px;
            cursor: pointer;
            text-align: start;
        }
        .addon-card.is-selected {
            background: var(--primary-clr);
            border-color: var(--primary-clr);
            color: #fff;
        }
        .addon-card__name {
            font-size: 13px;
            line-height: 1.3;
            min-height: 34px;
        }
        .addon-card__price {
            font-size: 14px;
            font-weight: 600;
        }
        .addon-card__qty {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 4px;
            margin-top: 8px;
            background: #fff;
            border-radius: 4px;
            padding: 2px 4px;
            color: var(--title-clr);
        }
        .addon-card__qty button {
            border: 0;
            background: transparent;
            line-height: 1;
            padding: 0 4px;
            color: inherit;
        }
    </style>
