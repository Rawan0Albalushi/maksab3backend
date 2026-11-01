<style>
        .bundle-item-row {
            padding: 12px 0;
            border-block-start: 1px solid var(--border-clr);
        }
        .bundle-item-row:first-child {
            border-block-start: 0;
        }
        .bundle-item-row__thumb {
            width: 56px;
            height: 56px;
            flex-shrink: 0;
            object-fit: cover;
            border-radius: 8px;
        }
        .bundle-item-row__name {
            font-size: 14px;
            color: var(--title-clr);
            line-height: 1.4;
        }
        .bundle-item-row__meta {
            font-size: 12px;
            opacity: .65;
            line-height: 1.5;
        }
        .bundle-item-row__price {
            font-size: 14px;
            font-weight: 600;
            color: var(--title-clr);
            margin-block-start: 2px;
        }

        .bundle-item-row__qty {
            flex-shrink: 0;
            align-self: flex-start;
            font-size: 12px;
            color: var(--title-clr);
            background-color: var(--bs-body-bg);
            border-radius: 6px;
            padding: 4px 10px;
            white-space: nowrap;
        }

        .bundle-detail__head {
            background-color: var(--bs-body-bg);
        }
        .bundle-detail__card {
            border: 1px solid var(--border-clr);
            border-radius: 8px;
            background-color: var(--bs-body-bg);
            overflow: hidden;
            margin-block-end: 20px;
        }
        .bundle-detail__summary {
            padding: 14px;
        }
        .bundle-detail__thumb {
            width: 100px;
            height: 100px;
            flex-shrink: 0;
            align-self: center;
            object-fit: cover;
            border-radius: 8px;
        }
        .bundle-detail__title {
            font-size: 17px;
            font-weight: 700;
            color: var(--title-clr);
            margin-block-end: 8px;
        }
        .bundle-detail__row {
            font-size: 12px;
            line-height: 1.7;
        }
        .bundle-detail__label {
            min-width: 84px;
            opacity: .65;
        }
        .bundle-detail__colon {
            margin-inline-end: 6px;
            opacity: .65;
        }
        .bundle-detail__value {
            color: var(--title-clr);
        }
        .bundle-detail__status {
            border-block-start: 1px solid var(--border-clr);
            padding: 14px;
        }
        .bundle-detail__heading {
            font-size: 15px;
            font-weight: 700;
            color: var(--title-clr);
            margin-block-end: 10px;
        }
        .bundle-detail__items .bundle-item-row__thumb {
            width: 40px;
            height: 40px;
        }
        .bundle-detail__items .bundle-item-row {
            padding: 10px 0;
        }

        .bundle-popover__head {
            font-size: 12px;
            font-weight: 600;
            color: var(--title-clr);
            opacity: .65;
            padding: 8px 0 4px;
        }

        .bundle-popover {
            min-width: 240px;
            max-height: 260px;
            overflow-y: auto;
        }
        .popover.bundle-items-popover-body {
            max-width: 320px;
            background-color: #fff;
            border: 1px solid var(--border-clr);
            border-radius: 8px;
            box-shadow: 0 10px 40px 10px rgba(140, 152, 164, .175);
        }
        .popover.bundle-items-popover-body .popover-body {
            padding: 4px 12px;
            background-color: #fff;
            color: var(--title-clr);
            border-radius: 8px;
        }
        .popover.bundle-items-popover-body .arrow::after {
            border-inline-end-color: #fff;
            border-inline-start-color: #fff;
            border-top-color: #fff;
            border-bottom-color: #fff;
        }
    </style>
