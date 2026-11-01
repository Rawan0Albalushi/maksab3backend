{{--
    The main category ids a sub category row can point at. "Invalid parent
    category in file" is one of the five errors that reject a whole upload, so
    the ids that satisfy it belong on the page.
--}}
@if (count($parents ?? []))
    <div class="row g-3 mt-0">
        <div class="col-lg-8">
            <div class="tps-card">
                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-hashtag"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">{{ translate('Parent ids you can use') }}</h2>
                        <p class="tps-card__subtitle">{{ translate('Main categories in this module, for the ParentId column.') }}</p>
                    </div>
                </div>

                <div class="tps-card__body">
                    <div class="btk-filter">
                        <input type="text" class="form-control" id="btk-parent-filter" autocomplete="off"
                               placeholder="{{ translate('Search by name or ID') }}">
                    </div>

                    <ul class="btk-parents" id="btk-parents">
                        @foreach ($parents as $parent)
                            <li class="btk-parent" data-search="{{ strtolower($parent->name) }} {{ $parent->id }}">
                                <span class="btk-parent__id">{{ $parent->id }}</span>
                                <span class="btk-parent__name">{{ $parent->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="btk-parents-empty" id="btk-parents-empty">{{ translate('No match.') }}</div>
                </div>
            </div>
        </div>
    </div>

    @push('script_2')
        <script>
            "use strict";

            $('#btk-parent-filter').on('input', function () {
                const term = $(this).val().trim().toLowerCase();
                let shown = 0;

                $('#btk-parents .btk-parent').each(function () {
                    const match = !term || $(this).data('search').toString().indexOf(term) !== -1;
                    $(this).toggle(match);
                    shown += match ? 1 : 0;
                });

                $('#btk-parents').toggle(shown > 0);
                $('#btk-parents-empty').toggle(shown === 0);
            });
        </script>
    @endpush
@endif
