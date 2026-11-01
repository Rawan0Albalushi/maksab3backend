<script>
    "use strict";

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : value).html();
        }

        // Food rows carry a thumbnail, name and category, so select2's default text-only
        // rendering is replaced. Data comes from foodCatalog rather than the option markup
        // to keep the <option> list plain.
        function initFoodSelect2($el, placeholder) {
            $el.select2({
                width: '100%',
                placeholder: placeholder,
                dropdownParent: drawer,
                dropdownCssClass: 'food-select2',
                language: {
                    noResults: function () {
                        return typeof itemPickerNoResultsText !== 'undefined'
                            ? itemPickerNoResultsText
                            : '{{ translate('No data found') }}';
                    },
                },
                templateResult: function (state) {
                    if (!state.id) return state.text;

                    const food = foodCatalog.find(f => String(f.id) === String(state.id));
                    if (!food) return state.text;

                    return $(
                        '<div class="food-option' + (food.is_available ? '' : ' food-option--off') + '">' +
                            '<div class="food-option__thumb">' +
                                '<img src="' + escapeHtml(food.image_full_url || '') + '" alt="">' +
                                (food.is_available ? '' :
                                    '<span class="food-option__flag">{{ translate('messages.Unavailable') }}</span>') +
                            '</div>' +
                            '<div style="min-width:0">' +
                                '<span class="d-block food-option__name">' + escapeHtml(food.name) + '</span>' +
                                (food.category_name
                                    ? '<span class="d-block food-option__meta">' + escapeHtml(food.category_name) + '</span>'
                                    : '') +
                                (itemPickerShowsPrice()
                                    ? '<span class="d-block food-option__price">' + currency(food.price) + '</span>'
                                    : '') +
                            '</div>' +
                        '</div>'
                    );
                },
            });

            // select2 rebuilds the dropdown on every open, so the icon is added each time.
            // It doubles as the clear control: once something has been typed the magnifier
            // turns into an X, because a search box that only ever shows a magnifier gives the
            // user nothing to click to get the whole menu back.
            $el.on('select2:open', function () {
                const $search = $('.food-select2 .select2-search--dropdown');

                if ($search.length && !$search.find('.food-search-icon').length) {
                    $search.append('<span class="food-search-icon"><i class="tio-search"></i></span>');
                }

                syncFoodSearchIcon($search.find('.select2-search__field'));
            });
        }



        // Magnifier while the field is empty, X once it is not - and the X has to be clickable,
        // so pointer-events are turned back on only in that state.
        function itemPickerShowsPrice() {
            return typeof itemPickerWithPrice !== 'undefined' && itemPickerWithPrice;
        }

        function syncFoodSearchIcon($field) {
            const $icon = $('.food-select2 .food-search-icon');
            if (!$icon.length) return;

            const filled = String($field.val() || '').length > 0;

            $icon.toggleClass('is-clearable', filled)
                 .find('i').attr('class', filled ? 'tio-clear' : 'tio-search');
        }

        $(document).on('input', '.food-select2 .select2-search__field', function () {
            syncFoodSearchIcon($(this));
        });

        // Clearing has to go through the field's own input event: that is what select2 listens
        // to, so anything else empties the box and leaves the filtered list behind it.
        $(document).on('click', '.food-select2 .food-search-icon.is-clearable', function () {
            const $field = $('.food-select2 .select2-search--dropdown .select2-search__field');

            // select2 listens on 'keyup input', so both are raised rather than betting on which.
            $field.val('').trigger('input').trigger('keyup').focus();
            syncFoodSearchIcon($field);
        });

        function variationText(item) {
            return (item.selected_variations || [])
                // A non-food selection is the combination key itself, which is already the
                // readable "uki-jbiub" the POS shows; a food one names its group.
                .map(v => v.type ? String(v.type).split('-').join(' / ') : `${v.name} : ${v.values.label.join(', ')}`)
                .join('  |  ');
        }

        function addOnText(item) {
            return (item.add_on_ids || [])
                .map(id => (item.add_ons.find(a => a.id == id) || {}).name)
                .filter(Boolean)
                .join(', ');
        }

        let fc = null;

        function openItemConfig(food, existing, meta) {
            fc = {
                meta: meta || null,
                food: food,
                variations: existing ? JSON.parse(JSON.stringify(existing.selected_variations)) : [],
                addOnIds: existing ? existing.add_on_ids.map(String) : [],
                addOnQtys: {},
            };

            if (existing) {
                existing.add_on_ids.forEach(function (id, i) {
                    fc.addOnQtys[String(id)] = (existing.add_on_qtys || [])[i] || 1;
                });
            }

            restoreChoiceSelection();

            $('#fc_image').attr('src', food.image_full_url || '');
            $('#fc_name').text(food.name);
            $('#fc_veg_badge')
                .text(food.veg ? '{{ translate('messages.Veg') }}' : '{{ translate('Non veg') }}')
                .attr('class', 'badge position-absolute ' + (food.veg ? 'badge-success' : 'badge-danger'));

            $('#fc_old_price').hide();
            $('#fc_price').text(currency(food.price));
            $('#fc_discount').hide();

            renderDescription(food.description || '');
            setFoodConfigReadOnly(false);
            renderVariations();
            renderAddons();
            recalcTotal();

            $('#foodConfigModal').modal('show');
        }

        function setFoodConfigReadOnly(on) {
            const modal = $('#foodConfigModal');

            modal.toggleClass('fc-readonly', !!on);
            modal.find('#fc_submit').toggle(!on);
            modal.find('.variation-option').prop('disabled', !!on);
            modal.find('.addon-step').toggle(!on);
            modal.find('.addon-card').css('pointer-events', on ? 'none' : '');
        }

        function currency(value) {
            return '{{ \App\CentralLogics\Helpers::currency_symbol() }}' + Number(value || 0).toFixed(2);
        }

        function renderDescription(text) {
            $('#fc_description_wrap').toggle(!!text);
            if (!text) return;

            if (text.length > 150) {
                $('#fc_description_short').text(text.slice(0, 150) + '...');
                $('#fc_description_full').text(text).addClass('d-none');
                $('#fc_description_toggle').removeClass('d-none').text('{{ translate('See more') }}');
            } else {
                $('#fc_description_short').text(text);
                $('#fc_description_full').text('').addClass('d-none');
                $('#fc_description_toggle').addClass('d-none');
            }
        }

        $('#fc_description_toggle').on('click', function () {
            $('#fc_description_short, #fc_description_full').toggleClass('d-none');
            $(this).text($(this).text() === '{{ translate('See more') }}'
                ? '{{ translate('See less') }}'
                : '{{ translate('See more') }}');
        });

        function selectedLabels(groupName) {
            const group = fc.variations.find(v => v.name === groupName);
            return group ? group.values.label : [];
        }

        /* ---------------- the two variation shapes ----------------

           6amMart items carry variations in one of two formats and the picker has to render both.

             food      food_variations : named groups of labelled options, each option's
                       optionPrice ADDING to the base price. Several options per group are
                       possible, governed by the group's own min/max.

             non-food  choice_options  : groups of plain option strings. Exactly one option per
                       group, and the chosen combination joins with "-" into a row of the
                       `variations` column whose price REPLACES the base and which carries its
                       own stock.

           They are told apart by which array the item actually has. fc.variations holds the
           selection in whichever shape the server stores for that item -- {name, values:{label}}
           for food, {type, price, stock} for non-food -- because that is what the frozen
           enrolment line, Helpers::variation_price() and the cart all read. */
        function isChoiceMode() {
            // The server's answer, not a guess from which array is populated: items carry BOTH,
            // and guessing put the picker on the choice branch for food items that the order
            // would have priced additively off food_variations -- which fataled the save on the
            // shape mismatch and, where it did not, replaced the base price instead of adding to it.
            return fc.food.uses_food_variations === false
                && (fc.food.choice_options || []).length > 0;
        }

        /** The option currently picked in a choice group, or '' . */
        function chosenOption(groupName) {
            return (fc.choices || {})[groupName] || '';
        }

        /** The "-" joined combination key, in choice_options order. Empty until every group is set. */
        function combinationType() {
            const parts = (fc.food.choice_options || []).map(g => chosenOption(g.name));

            return parts.every(Boolean) ? parts.join('-') : '';
        }

        /** The priced row this combination resolves to, or null while the choice is incomplete. */
        function matchedCombination() {
            const type = combinationType();

            return type
                ? (fc.food.variation_combinations || []).find(v => v.type === type) || null
                : null;
        }

        /**
         * Keep fc.variations in step with the choice groups.
         *
         * Stored as a one-element list because that is exactly what Helpers::variation_price()
         * reads -- it takes [0]['type'] -- and what PlaceNewOrderTrait hands it from the cart.
         */
        function syncChoiceSelection() {
            const match = matchedCombination();

            fc.variations = match ? [match] : [];
        }

        /**
         * The reverse, for reopening a line that was already saved.
         *
         * The stored selection is the combination key alone, so which option belongs to which
         * group is recovered by splitting it in choice_options order -- the same order it was
         * joined in. Without this an edit reopened with every radio blank and, on save, wrote the
         * base price over whatever the line had actually been agreed at.
         */
        function restoreChoiceSelection() {
            fc.choices = {};

            const groups = fc.food.choice_options || [];
            const stored = (fc.variations || []).find(v => v.type);

            if (!groups.length || !stored) {
                return;
            }

            const parts = String(stored.type).split('-');

            groups.forEach((group, i) => {
                if (parts[i] !== undefined) {
                    fc.choices[group.name] = parts[i];
                }
            });
        }

        function renderChoiceOptions(wrap, readOnly) {
            (fc.food.choice_options || []).forEach((group, gi) => {
                const picked = chosenOption(group.name);

                wrap.append(`
                    <div class="bg-light rounded p-3 mb-3">
                        <span class="fs-18 font-bold">${escapeHtml(group.title || group.name)}</span>
                        <span class="fs-16 opacity-75"> ({{ translate('messages.Required.') }})</span>
                        <div class="fs-14 opacity-75 mb-3">{{ translate('messages.Select one option') }}</div>
                        ${(group.options || []).map(option => `
                            <div class="d-flex align-items-center justify-content-between py-2">
                                <div class="form-check mb-0">
                                    <input class="form-check-input choice-option" type="radio"
                                           name="fc_choice_${gi}" id="fc_choice_${gi}_${String(option).replace(/\W/g, '')}"
                                           data-group="${escapeHtml(group.name)}" value="${escapeHtml(option)}"
                                           ${picked === option ? 'checked' : ''}>
                                    <label class="form-check-label" for="fc_choice_${gi}_${String(option).replace(/\W/g, '')}">${escapeHtml(option)}</label>
                                </div>
                            </div>`).join('')}
                    </div>`);
            });

            // The combination decides the price, so it is stated once under the groups rather
            // than per option -- there is no per-option price to show in this shape.
            const match = matchedCombination();

            if (match) {
                wrap.append(`
                    <div class="alert alert-soft-info alert--note d-flex align-items-center justify-content-between py-2">
                        <span>${escapeHtml(combinationType())}</span>
                        <span class="font-bold">${currency(match.price)}</span>
                    </div>`);
            } else if (combinationType()) {
                // Every group answered and still no row: the combination was removed from the
                // item after this selection was made, or was never priced.
                wrap.append(`
                    <div class="alert alert-soft-danger alert--note py-2">
                        {{ translate('messages.This combination is not available on this item') }}
                    </div>`);
            }

            if (readOnly) {
                wrap.find('.choice-option').prop('disabled', true);
            }
        }

        $(document).on('change', '.choice-option', function () {
            fc.choices = fc.choices || {};
            fc.choices[$(this).data('group')] = $(this).val();

            syncChoiceSelection();
            renderVariations();
            recalcTotal();
        });

        function renderVariations() {
            const wrap = $('#fc_variations').empty();
            // Re-rendered on every change, so the inert state has to be reapplied afterwards.
            const readOnly = $('#foodConfigModal').hasClass('fc-readonly');

            if (isChoiceMode()) {
                renderChoiceOptions(wrap, readOnly);
                return;
            }

            (fc.food.variations || []).forEach((group, gi) => {
                const required = group.required === 'on' || group.required === true;
                const min = parseInt(group.min || 0, 10);
                const max = parseInt(group.max || 0, 10);
                const single = group.type === 'single';
                const picked = selectedLabels(group.name);

                const rule = single
                    ? '{{ translate('messages.Select one option') }}'
                    : '{{ translate('messages.You need to select minimum') }} ' + min +
                      ' {{ translate('messages.To maximum') }} ' + max + ' {{ translate('messages.Options') }}';

                wrap.append(`
                    <div class="bg-light rounded p-3 mb-3">
                        <span class="fs-18 font-bold">${group.name}</span>
                        <span class="fs-16 opacity-75">${required ? ' ({{ translate('messages.Required.') }})' : ''}</span>
                        <div class="fs-14 opacity-75 mb-3">${rule}</div>
                        ${(group.values || []).map(v => `
                            <div class="d-flex align-items-center justify-content-between py-2">
                                <div class="form-check mb-0">
                                    <input class="form-check-input variation-option" type="${single ? 'radio' : 'checkbox'}"
                                           name="fc_group_${gi}" id="fc_${gi}_${v.label.replace(/\W/g, '')}"
                                           data-group="${gi}" value="${v.label}" ${picked.includes(v.label) ? 'checked' : ''}>
                                    <label class="form-check-label" for="fc_${gi}_${v.label.replace(/\W/g, '')}">${v.label}</label>
                                </div>
                                <span class="${picked.includes(v.label) ? 'font-bold text-dark' : 'opacity-75'}">${currency(v.optionPrice)}</span>
                            </div>`).join('')}
                    </div>`);
            });

            if (readOnly) {
                wrap.find('.variation-option').prop('disabled', true);
            }
        }

        $(document).on('change', '.variation-option', function () {
            const gi = $(this).data('group');
            const group = fc.food.variations[gi];
            const single = group.type === 'single';
            const max = parseInt(group.max || 0, 10);
            const label = $(this).val();

            let picked = selectedLabels(group.name).slice();

            if (single) {
                picked = [label];
            } else if ($(this).is(':checked')) {
                if (max && picked.length >= max) {
                    $(this).prop('checked', false);
                    toastr.warning('{{ translate('messages.You can select maximum') }} ' + max + ' {{ translate('messages.Options') }}');
                    return;
                }
                picked.push(label);
            } else {
                picked = picked.filter(l => l !== label);
            }

            fc.variations = fc.variations.filter(v => v.name !== group.name);
            if (picked.length) {
                fc.variations.push({name: group.name, values: {label: picked}});
            }

            renderVariations();
            recalcTotal();
        });

        function addOnQty(id) {
            return fc.addOnQtys[String(id)] || 1;
        }

        function renderAddons() {
            const addons = fc.food.add_ons || [];
            const readOnly = $('#foodConfigModal').hasClass('fc-readonly');
            // Read-only shows only what was agreed; the rest of the menu's add-ons are noise on
            // a record of one line.
            const visible = readOnly ? addons.filter(a => fc.addOnIds.includes(String(a.id))) : addons;
            $('#fc_addon_wrap').toggle(visible.length > 0);

            $('#fc_addons').html(visible.map(function (a) {
                const on = fc.addOnIds.includes(String(a.id));

                return '<div class="addon-card' + (on ? ' is-selected' : '') + '" data-id="' + a.id + '">' +
                    '<span class="d-block addon-card__name">' + escapeHtml(a.name) + '</span>' +
                    '<span class="d-block addon-card__price">' + currency(a.price) + '</span>' +
                    (on
                        ? '<div class="addon-card__qty">' +
                              (readOnly ? '' : '<button type="button" class="addon-step" data-step="-1">&minus;</button>') +
                              '<span class="addon-card__count">' + addOnQty(a.id) + '</span>' +
                              (readOnly ? '' : '<button type="button" class="addon-step" data-step="1">+</button>') +
                          '</div>'
                        : '') +
                    '</div>';
            }).join(''));
        }

        // The stepper lives inside the card, so its clicks must not toggle the card off.
        $(document).on('click', '.addon-step', function (e) {
            e.stopPropagation();

            const id = String($(this).closest('.addon-card').data('id'));
            const next = addOnQty(id) + parseInt($(this).data('step'), 10);

            if (next < 1) return;

            fc.addOnQtys[id] = next;
            renderAddons();
            recalcTotal();
        });

        $(document).on('click', '.addon-card', function (e) {
            if ($(e.target).closest('.addon-step').length) return;

            const id = String($(this).data('id'));

            if (fc.addOnIds.includes(id)) {
                fc.addOnIds = fc.addOnIds.filter(a => a !== id);
                delete fc.addOnQtys[id];
            } else {
                fc.addOnIds = fc.addOnIds.concat(id);
                fc.addOnQtys[id] = 1;
            }

            renderAddons();
            recalcTotal();
        });

        // Mirrors resolveUnitPrice() server side, including the part that is easy to get wrong:
        // a food option's optionPrice ADDS to the base, while a non-food combination's price
        // REPLACES it -- which is what PlaceNewOrderTrait does at every one of its pricing sites.
        // BOGO suppresses the item's own discount, so discounted_price is display only.
        function unitPrice() {
            return resolveItemPrice(fc.food, fc.variations, fc.addOnIds, fc.addOnQtys);
        }

        /**
         * The same figure for any item and selection, not just the one open in the modal, so a
         * saved row can be repriced against the live menu without reopening it.
         *
         * `addOnQtys` is keyed by add-on id here, as fc holds it; a saved row's positional list
         * is converted by the caller.
         */
        function resolveItemPrice(food, variations, addOnIds, addOnQtys) {
            const ids = (addOnIds || []).map(String);
            const qtys = addOnQtys || {};
            let price = Number(food.price || 0);

            const combination = (variations || []).find(v => v.type);

            if (food.uses_food_variations === false && (food.choice_options || []).length > 0) {
                const match = combination
                    ? (food.variation_combinations || []).find(v => v.type === combination.type)
                    : null;

                if (match) {
                    price = Number(match.price || 0);
                }
            } else {
                (food.variations || []).forEach(group => {
                    const chosen = (variations || []).find(v => v.name === group.name);
                    const picked = chosen ? chosen.values.label : [];

                    (group.values || []).forEach(v => {
                        if (picked.includes(v.label)) price += Number(v.optionPrice || 0);
                    });
                });
            }

            (food.add_ons || []).forEach(a => {
                if (ids.includes(String(a.id))) {
                    price += Number(a.price || 0) * (qtys[String(a.id)] || 1);
                }
            });

            return price;
        }

        function recalcTotal() {
            $('#fc_total').text(currency(unitPrice()));
        }

        function validateItemConfig() {
            if (isChoiceMode()) {
                for (const group of fc.food.choice_options) {
                    if (!chosenOption(group.name)) {
                        toastr.error((group.title || group.name) + ' {{ translate('messages.Is required') }}');
                        return false;
                    }
                }

                if (!matchedCombination()) {
                    toastr.error('{{ translate('messages.This combination is not available on this item') }}');
                    return false;
                }
            }

            for (const group of (isChoiceMode() ? [] : (fc.food.variations || []))) {
                const required = group.required === 'on' || group.required === true;
                const min = parseInt(group.min || 0, 10);
                const picked = selectedLabels(group.name);

                if (required && picked.length === 0) {
                    toastr.error(group.name + ' {{ translate('messages.Is required') }}');
                    return false;
                }
                if (min && picked.length && picked.length < min) {
                    toastr.error('{{ translate('messages.You need to select minimum') }} ' + min +
                        ' {{ translate('messages.Options') }} - ' + group.name);
                    return false;
                }
            }

            return true;
        }

        function buildItemConfigEntry() {
            return {
                item_id: fc.food.id,
                name: fc.food.name,
                category_name: fc.food.category_name,
                image: fc.food.image_full_url,
                quantity: 1,
                max: fc.food.maximum_cart_quantity || 0,
                variations: fc.food.variations || [],
                choice_options: fc.food.choice_options || [],
                variation_combinations: fc.food.variation_combinations || [],
                selected_variations: fc.variations,
                add_ons: fc.food.add_ons || [],
                add_on_ids: fc.addOnIds,
                add_on_qtys: fc.addOnIds.map(id => addOnQty(id))
            };
        }

        $('#fc_submit').on('click', function () {
            // A read-only open carries no tag, and there is nothing to add from one. The button
            // is hidden in that state; this is here so hiding it is not the only thing stopping it.
            if (!fc || !fc.meta) {
                return;
            }

            if (!validateItemConfig()) {
                return;
            }

            const entry = buildItemConfigEntry();

            const blocked = typeof itemConfigBlocker === 'function'
                ? itemConfigBlocker(entry, fc.meta)
                : null;

            if (blocked) {
                toastr.warning(blocked);
                return;
            }

            onItemConfigured(entry, fc.meta);

            $('#foodConfigModal').modal('hide');
        });
</script>
