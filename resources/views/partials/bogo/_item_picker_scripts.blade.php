{{-- BOGO item selection: the buy and get lists, their quantity rules and the enrolment prefill.

     The variation, add-on and pricing engine behind the config modal is shared with every other
     promotion picker and lives in partials.promotion._item_options_scripts, included below.
     This file supplies only what a BOGO offer adds on top of it: two sides with their own
     required quantities, and rows that fold together when they are the same selection.

     The including page must define these first:
       requiredQty  {buy, get}   how many units each side needs
       selected     {buy: [], get: []}   the working selection
       foodCatalog  []           filled from the store-items endpoint
       drawer       jQuery el    container select2 anchors its dropdown to
       foodsEndpoint string      url of the store-items endpoint, used to reopen an
                                 enrolled line read-only --}}
@include('partials.promotion._item_options_scripts')

<script>
    "use strict";

        $(document).on('change', '.food-picker', function () {
            const type = $(this).data('type');
            const food = foodCatalog.find(f => f.id == $(this).val());

            $(this).val('').trigger('change.select2');

            if (food) {
                openFoodConfig(type, null, food);
            }
        });

        function render(type) {
            normaliseSelection(type);

            const container = $(`.selected-items[data-type="${type}"]`);
            container.empty();

            selected[type].forEach((item, index) => {
                const variations = variationText(item);
                const addOns = addOnText(item);

                if (index > 0) {
                    container.append(`
                        <div class="d-flex align-items-center gap-2 my-2">
                            <span class="flex-grow-1 border-top"></span>
                            <span class="font-size-sm opacity-75">{{ translate('messages.And') }}</span>
                            <span class="flex-grow-1 border-top"></span>
                        </div>`);
                }

                container.append(`
                    <div class="bg-white rounded p-3">
                        <div class="d-flex align-items-center gap-3">
                            <img class="rounded onerror-image" style="width:56px;height:56px;object-fit:cover"
                                 data-onerror-image="{{asset('public/assets/admin/img/100x100/1.png')}}"
                                 src="${item.image || ''}" alt="food">
                            <div class="flex-grow-1">
                                <strong class="d-block">${item.name}
                                    ${Number(item.quantity) > 1
                                        ? `<span class="badge badge-soft-dark ml-1">x${Number(item.quantity)}</span>`
                                        : ''}
                                </strong>
                                ${variations ? `<span class="d-block font-size-sm opacity-75">${variations}</span>` : ''}
                                ${addOns ? `<span class="d-block font-size-sm opacity-75">{{ translate('Add ons') }} : ${addOns}</span>` : ''}
                                <a href="javascript:" class="font-size-sm edit-food" data-type="${type}" data-index="${index}">
                                    <i class="tio-edit"></i> {{ translate('messages.Edit') }}</a>
                            </div>
                            <button type="button" class="btn btn-sm btn--danger btn-outline-danger remove-item"
                                    data-type="${type}" data-index="${index}"><i class="tio-delete"></i></button>
                        </div>
                    </div>`);
            });

            const total = selected[type].reduce((sum, i) => sum + Number(i.quantity), 0);
            $(`.${type}-total`).text(total);
            $(`.${type}-total-wrap`).toggleClass('d-none', selected[type].length === 0)
                .css('color', total === requiredQty[type] ? '#019463' : '#dc3545');
        }

        // Two rows are the same row when they name the same food configured the same way: same
        // variation options, same add-ons in the same counts. Anything else is a different
        // thing to buy even though it is the same food, which is why a Medium and a Large of
        // one pizza stay two rows.
        function lineSignature(foodId, variations, addOnIds, addOnQtys) {
            // Both shapes, or two different non-food combinations of one item hash the same and
            // get folded into a single row: {type} carries no name and no values.
            const options = (variations || [])
                .map(v => v.type
                    ? 'type:' + v.type
                    : v.name + ':' + ((v.values || {}).label || []).slice().sort().join(','))
                .sort().join('|');

            const addons = (addOnIds || [])
                .map((id, i) => String(id) + 'x' + ((addOnQtys || [])[i] || 1))
                .sort().join(',');

            return [foodId, options, addons].join('#');
        }

        // Folds identical rows together. Used on prefill: an enrolment saved before rows were
        // merged holds the same food twice, and reopening it would show the duplicate joined
        // by AND that the picker no longer creates.
        function normaliseSelection(type) {
            const merged = [];
            const seen = {};

            selected[type].forEach(function (item) {
                const key = lineSignature(item.item_id, item.selected_variations, item.add_on_ids, item.add_on_qtys);

                if (seen[key] === undefined) {
                    seen[key] = merged.push(item) - 1;
                    return;
                }

                merged[seen[key]].quantity = Number(merged[seen[key]].quantity || 1) + Number(item.quantity || 1);
            });

            selected[type] = merged;
        }

        // Units on one side, optionally ignoring the row that is about to be replaced.
        function sideQuantity(type, ignoreIndex) {
            return selected[type].reduce(function (sum, item, index) {
                return index === ignoreIndex ? sum : sum + Number(item.quantity || 0);
            }, 0);
        }

        // The shared engine takes the row itself and an opaque tag; BOGO's tag is which side the
        // row belongs to and where in that side it sits.
        function openFoodConfig(type, index, food) {
            openItemConfig(food, index !== null ? selected[type][index] : null, {type: type, index: index});
        }

        /**
         * Reopen one enrolled line in the food config modal, read-only.
         *
         * The item is fetched rather than rebuilt from the enrolment, so the panel shows every
         * option the item carries with the agreed ones ticked - the enrolment stores only what
         * was chosen, which on its own would render a list of one.
         */
        function openEnrolledItemView(item) {
            const show = function (foods) {
                const food = (foods || []).find(f => String(f.id) === String(item.item_id));

                if (!food) {
                    toastr.warning('{{ translate('No data found') }}');
                    return;
                }

                fc = {
                    meta: null,
                    food: food,
                    variations: JSON.parse(JSON.stringify(item.selected_variations || [])),
                    addOnIds: (item.add_on_ids || []).map(String),
                    addOnQtys: {},
                };

                (item.add_on_ids || []).forEach(function (id, i) {
                    fc.addOnQtys[String(id)] = (item.add_on_qtys || [])[i] || 1;
                });

                // Puts the radios back on a non-food line being viewed; a no-op for food.
                restoreChoiceSelection();

                $('#fc_image').attr('src', food.image_full_url || '');
                $('#fc_name').text(food.name);
                $('#fc_veg_badge')
                    .text(food.veg ? '{{ translate('messages.Veg') }}' : '{{ translate('Non veg') }}')
                    .attr('class', 'badge position-absolute ' + (food.veg ? 'badge-success' : 'badge-danger'));

                // The frozen price of the line, not today's menu price: what the enrolment agreed.
                $('#fc_old_price').hide();
                $('#fc_price').text(item.price);
                $('#fc_discount').hide();

                renderDescription(food.description || '');
                renderVariations();
                renderAddons();

                $('#fc_total').text(item.price);
                setFoodConfigReadOnly(true);

                $('#foodConfigModal').modal('show');
            };

            if (enrolledFoodCache[item.store_id]) {
                show(enrolledFoodCache[item.store_id]);
                return;
            }

            $.get(foodsEndpoint, {store_id: item.store_id}, function (foods) {
                enrolledFoodCache[item.store_id] = foods;
                show(foods);
            });
        }

        // Kept per store: a drawer is reopened often and the list does not change between
        // opens. foodCatalog is not reused because the picker overwrites it per selection.
        const enrolledFoodCache = {};

        // Delegated: the rows arrive with the drawer, long after this runs.
        $(document).on('click', '.bogo-item-row', function () {
            openEnrolledItemView($(this).data('item') || {});
        });

        /**
         * Why this configured item cannot join the side it was picked for.
         *
         * Called by the shared engine after the item's own variation rules pass; returning a
         * message stops the add. Both caps count units rather than rows, because one row may
         * now hold several units of the same food.
         */
        function itemConfigBlocker(entry, meta) {
            const editing = meta.index !== null;
            // Editing carries the row's own count across; a fresh pick is always one unit.
            const addedQty = editing ? Number(selected[meta.type][meta.index].quantity || 1) : 1;

            // The offer's own quantity is the hard cap. The server already refuses a third buy
            // food on a Buy 2 offer, but only after Join, which left the vendor building a
            // selection the panel had let them build and then rejected wholesale.
            const required = Number(requiredQty[meta.type] || 0);

            if (required && sideQuantity(meta.type, meta.index) + addedQty > required) {
                return '{{ translate('messages.You can add a maximum of') }} ' + required +
                    ' {{ translate('messages.quantities for this BOGO offer') }}';
            }

            const max = entry.max;

            if (max) {
                const alreadyOn = selected[meta.type].reduce(function (sum, i, idx) {
                    return (idx !== meta.index && i.item_id === entry.item_id)
                        ? sum + Number(i.quantity || 0)
                        : sum;
                }, 0);

                if (alreadyOn + addedQty > max) {
                    return '{{ translate('messages.You can add a maximum of') }} ' + max +
                        ' {{ translate('messages.quantities for this item') }}';
                }
            }

            return null;
        }

        function onItemConfigured(entry, meta) {
            const editing = meta.index !== null;
            const addedQty = editing ? Number(selected[meta.type][meta.index].quantity || 1) : 1;

            // Adding what is already on the list is one more of it, not a second identical
            // row -- a duplicate joined by AND reads as two different items to buy when it is
            // one item twice.
            const signature = lineSignature(entry.item_id, entry.selected_variations, entry.add_on_ids, entry.add_on_qtys);
            const twin = selected[meta.type].findIndex((i, idx) =>
                idx !== meta.index &&
                lineSignature(i.item_id, i.selected_variations, i.add_on_ids, i.add_on_qtys) === signature);

            if (twin > -1) {
                selected[meta.type][twin].quantity = Number(selected[meta.type][twin].quantity || 1) + addedQty;
                // Editing a row into an existing one leaves the row it came from behind. The
                // twin was mutated by reference above, so the splice cannot disturb it.
                if (editing) selected[meta.type].splice(meta.index, 1);
            } else if (editing) {
                selected[meta.type][meta.index] = Object.assign(entry, {quantity: addedQty});
            } else {
                selected[meta.type].push(entry);
            }

            render(meta.type);
        }

        $(document).on('click', '.edit-food', function () {
            const type = $(this).data('type'), index = $(this).data('index');
            const item = selected[type][index];
            const food = foodCatalog.find(f => f.id == item.item_id);

            if (food) openFoodConfig(type, index, food);
        });

        $(document).on('click', '.remove-item', function () {
            const type = $(this).data('type');
            selected[type].splice($(this).data('index'), 1);
            render(type);
        });

        function resetSelections() {
            selected.buy = [];
            selected.get = [];
            render('buy');
            render('get');
        }

        // Fills both sides from an enrolment's frozen selection, so editing one opens on what is
        // already there instead of making the store rebuild it. Call after foodCatalog is loaded:
        // the snapshot records the choices, and everything the picker renders, caps and prices
        // with is read back off the live menu row.
        //
        // An item that has left the menu since is dropped rather than seeded blank. There is
        // nothing to configure it against, and both variation helpers start at 0 and only
        // accumulate on a match, so a row with no catalog behind it would price at zero silently.
        function seedSelections(payload) {
            selected.buy = [];
            selected.get = [];

            ['buy', 'get'].forEach(function (type) {
                (payload[type] || []).forEach(function (row) {
                    const food = foodCatalog.find(f => f.id == row.item_id);

                    if (!food) return;

                    selected[type].push({
                        item_id: food.id,
                        name: food.name,
                        image: food.image_full_url,
                        quantity: Number(row.quantity) || 1,
                        max: food.maximum_cart_quantity || 0,
                        variations: food.variations || [],
                        choice_options: food.choice_options || [],
                        variation_combinations: food.variation_combinations || [],
                        selected_variations: row.selected_variations || [],
                        add_ons: food.add_ons || [],
                        add_on_ids: (row.add_on_ids || []).map(String),
                        // Positional, matching add_on_ids - the shape resolveUnitPrice reads.
                        add_on_qtys: (row.add_on_ids || []).map((id, i) => (row.add_on_qtys || [])[i] || 1)
                    });
                });

                render(type);
            });
        }

</script>
