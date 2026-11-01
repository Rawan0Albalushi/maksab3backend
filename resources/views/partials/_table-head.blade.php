{{-- Shared list-table card header: title, record count, one-line subtitle.

     @include('partials._table-head', [
         'title'    => translate('messages.Unit list'),
         'subtitle' => translate('messages.Measurement units vendors choose from when adding an item.'),
         'count'    => $units->total(),
         'count_id' => 'itemCount',
     ])

     `count` and `count_id` are optional, but pass `count` explicitly (null when
     you do not want a badge) — `@include` merges the parent's variables, so an
     unrelated `$count` in the including view would otherwise leak in and render
     a stray badge. `count_id` keeps the id the page's JS already updates.

     SUBTITLE-ONLY MODE — omit `title` (and `count`) when the page's own
     `<h1 class="page-header-title">` already names this table. The page heading
     is the one that wins: it is the landmark, it carries the section icon and
     it matches the sidebar, so repeating it inside the card 60px lower is pure
     noise. The card then carries only the line that says something new — what
     the table actually contains — and the count badge lives on the `<h1>`.

     Pages whose `<h1>` labels a *form* card above the list ("Add new unit")
     are NOT that case: there the card title names a different card, so it stays.

     The wrapper div matters: this block sits as a direct child of
     `.search--button-wrapper`, which is a flex row, and `.card-title` is itself
     `display: flex` — without it the subtitle lands beside the title instead of
     under it. Styles live in `admin-tables.css` (section 7). --}}

@php
    $title    = $title ?? null;
    $count    = $count ?? null;
    $count_id = $count_id ?? null;
    $subtitle = $subtitle ?? null;
@endphp

<div class="table-head{{ $title ? '' : ' table-head--sub-only' }}">
    @if($title)
        <h5 class="card-title">
            {{ $title }}
            @if(!is_null($count))
                <span class="badge badge-soft-dark ml-2" @if($count_id) id="{{ $count_id }}" @endif>{{ $count }}</span>
            @endif
        </h5>
    @endif
    @if($subtitle)
        <span class="table-head__sub">{{ $subtitle }}</span>
    @endif
</div>
