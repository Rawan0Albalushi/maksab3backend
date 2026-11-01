{{--
    The promotion lists' name cell. Shared by BOGO and Happy Hour, which are the same table with
    different columns in the middle.

    The tables are table-nowrap, so without a bound one long title widens the whole table and
    pushes the action column past the right edge -- which is what put a horizontal scrollbar under
    the rows and left the action buttons off screen.

    max-width rather than width: a short name should not reserve a column's worth of space.
--}}
<style>
    /* The cap belongs on the cell, not on a div inside it: the tables are table-nowrap, so the
       cell's min-content width is the whole unbroken title and an inner max-width cannot lower
       it. Capping the td is what actually stops one long name widening the table. */
    td.promo-name-cell {
        max-width: 260px;
    }

    .promo-name {
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
    }

    /* The title takes what is left and ellipses; the full text is on its own tooltip, so
       nothing is lost to the truncation. */
    .promo-name__text {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* The warning keeps its 22px whatever the title does, so a long name cannot push it out
       of the row. */
    .promo-name .promo-name-warning {
        flex-shrink: 0;
        width: 22px;
        height: 22px;
        min-height: 22px;
        padding: 0;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .promo-name .promo-name-warning i {
        font-size: 12px;
    }
</style>
