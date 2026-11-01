@include('partials.promotion._item_options_scripts')

<script>
    "use strict";

    const bundleItems = [];

    const bundleIsService = @json($isServiceModule ?? false);

    $(document).on('change', '.bundle-item-picker', function () {
        const food = foodCatalog.find(f => f.id == $(this).val());

        $(this).val('').trigger('change.select2');

        if (!food) {
            return;
        }

        if (bundleIsService) {
            openServiceConfig(food, null, {index: null});
            return;
        }

        openItemConfig(food, null, {index: null});
    });

    let sc = {meta: null, service: null, variantKey: null};

    function openServiceConfig(service, existing, meta) {
        const variants = service.variants || [];

        sc = {
            meta: meta || null,
            service: service,
            variantKey: existing
                ? (((existing.selected_variations || [])[0] || {}).variant_key || null)
                : (variants.length ? variants[0].variant_key : null)
        };

        $('#sc_image').attr('src', service.image_full_url || '');
        $('#sc_name').text(service.name);
        $('#sc_price').text(currency(service.price));
        $('#sc_category').text(service.category_name || '');

        const description = service.description || '';
        $('#sc_description').text(description);
        $('#sc_description_wrap').toggle(description !== '');

        renderServiceVariants();

        $('#serviceConfigModal').modal('show');
    }

    function renderServiceVariants() {
        const variants = sc.service.variants || [];
        const wrap = $('#sc_variants').empty();

        $('#sc_variant_wrap').toggle(variants.length > 0);

        variants.forEach(function (variant) {
            const checked = variant.variant_key === sc.variantKey ? 'checked' : '';

            wrap.append(`
                <label class="d-flex align-items-center justify-content-between bg-global-gray rounded-8 p-3 mb-0" style="cursor:pointer">
                    <span class="d-flex align-items-center gap-2">
                        <input type="radio" name="sc_variant" value="${escapeHtml(variant.variant_key)}" ${checked}>
                        <span>${escapeHtml(variant.name)}</span>
                    </span>
                    <strong>${currency(variant.price)}</strong>
                </label>`);
        });

        $('#sc_total').text(currency(serviceConfigPrice()));
    }

    function serviceConfigPrice() {
        return servicePrice(sc.service, sc.variantKey ? [{variant_key: sc.variantKey}] : []);
    }

    $(document).on('change', 'input[name="sc_variant"]', function () {
        sc.variantKey = $(this).val();
        $('#sc_total').text(currency(serviceConfigPrice()));
    });

    $('#sc_submit').on('click', function () {
        const variant = (sc.service.variants || []).find(v => v.variant_key === sc.variantKey);
        const selected = variant ? [variant] : [];

        if (sc.meta && sc.meta.index !== null) {
            const row = bundleItems[sc.meta.index];

            row.selected_variations = selected;
            row.unit_price = servicePrice(sc.service, selected);

            renderBundleItems();
        } else {
            addServiceToBundle(sc.service, selected);
        }

        $('#serviceConfigModal').modal('hide');
    });

    function addServiceToBundle(service, selected) {
        bundleItems.push({
            item_id: service.id,
            service_id: service.id,
            is_service: true,
            name: service.name,
            image: service.image_full_url,
            category_name: service.category_name,
            quantity: 1,
            max: 0,
            variants: service.variants || [],
            selected_variations: selected,
            unit_price: servicePrice(service, selected),
            variations: [],
            choice_options: [],
            variation_combinations: [],
            add_ons: [],
            add_on_ids: [],
            add_on_qtys: []
        });

        renderBundleItems();
    }

    function servicePrice(service, selected) {
        const key = (selected && selected[0] && selected[0].variant_key) || null;

        if (!key) {
            return parseFloat(service.price) || 0;
        }

        const variant = (service.variants || []).find(v => v.variant_key === key);

        return variant ? (parseFloat(variant.price) || 0) : (parseFloat(service.price) || 0);
    }

    $(document).on('click', '.bundle-service-edit', function () {
        const index = parseInt($(this).data('index'), 10);
        const row = bundleItems[index];

        if (!row) {
            return;
        }

        openServiceConfig(foodCatalog.find(f => f.id == row.service_id) || row, row, {index: index});
    });

    function itemConfigBlocker(entry, meta) {
        const max = entry.max;

        if (!max) {
            return null;
        }

        const alreadyOn = bundleItems.reduce(function (count, row, index) {
            return (index !== meta.index && row.item_id === entry.item_id) ? count + 1 : count;
        }, 0);

        if (alreadyOn + 1 > max) {
            return '{{ translate('messages.You can add a maximum of') }} ' + max +
                ' {{ translate('messages.quantities for this item') }}';
        }

        return null;
    }

    function onItemConfigured(entry, meta) {
        entry.unit_price = unitPrice();

        if (meta.index === null) {
            bundleItems.push(entry);
        } else {
            bundleItems[meta.index] = entry;
        }

        renderBundleItems();
    }

    function renderBundleItems() {
        const container = $('#bundle-selected-items').empty();

        bundleItems.forEach(function (item, index) {
            const variations = item.is_service
                ? ((item.selected_variations || [])[0] || {}).name || ''
                : variationText(item);
            const addOns = item.is_service ? '' : addOnText(item);

            const meta = [item.category_name, variations].filter(Boolean).join('  |  ');

            container.append(`
                <div class="bundle-item-row d-flex align-items-center gap-3">
                    <img class="bundle-item-row__thumb onerror-image"
                         data-onerror-image="{{ asset('public/assets/admin/img/100x100/1.png') }}"
                         src="${item.image || ''}" alt="">
                    <div class="flex-grow-1" style="min-width:0">
                        <strong class="d-block bundle-item-row__name">${escapeHtml(item.name)}</strong>
                        ${meta ? `<span class="d-block bundle-item-row__meta">${escapeHtml(meta)}</span>` : ''}
                        ${addOns ? `<span class="d-block bundle-item-row__meta">{{ translate('messages.Add-on') }} : ${escapeHtml(addOns)}</span>` : ''}
                        <span class="d-block bundle-item-row__price">${currency(item.unit_price)}</span>
                    </div>
                    <div class="btn--container justify-content-center flex-shrink-0">
                        ${(item.is_service && (item.variants || []).length) ? `
                            <a class="btn btn-sm btn--primary btn-outline-primary action-btn bundle-service-edit"
                               href="javascript:" data-index="${index}" title="{{ translate('Edit') }}">
                                <i class="tio-edit"></i>
                            </a>` : ''}
                        <a class="btn btn-sm btn--danger btn-outline-danger action-btn bundle-item-remove"
                           href="javascript:" data-index="${index}" title="{{ translate('Delete') }}">
                            <i class="tio-delete-outlined"></i>
                        </a>
                    </div>
                </div>`);
        });

        recalcBundlePrices();
    }

    function recalcBundlePrices() {
        const base = bundleItems.reduce((sum, item) => sum + Number(item.unit_price || 0), 0);

        const $discount = $('#bundle_discount');
        const raw = $discount.val();

        let percentage = Number(raw || 0);

        if (percentage < 0) percentage = 0;
        if (percentage > maxDiscount) percentage = maxDiscount;

        // A discount typed outside [0, maxDiscount] (e.g. 100, when the server caps at 99 so a
        // bundle can never be free) was previously clamped for the price math below but left the
        // field itself showing the out-of-range number -- so the field read "100" while the price
        // preview already reflected 99%, which looked like the wrong bundle price for what was on
        // screen. Snap the field to the value that will actually be saved, so what's displayed
        // and what's typed always agree.
        if (raw !== '' && Number(raw) !== percentage) {
            $discount.val(percentage);
        }

        $('#bundle_base_price').val(base.toFixed(2));
        $('#bundle_final_price').val(Math.max(0, base - (base * percentage / 100)).toFixed(2));
    }

    $(document).on('input', '#bundle_discount', recalcBundlePrices);

    $(document).on('click', '.bundle-item-remove', function () {
        bundleItems.splice($(this).data('index'), 1);
        renderBundleItems();
    });

    function resetBundleItems() {
        bundleItems.length = 0;
        renderBundleItems();
    }

    function loadBundleCatalog(storeId, onReady) {
        const $picker = $('.bundle-item-picker');

        if (!storeId) {
            foodCatalog = [];
            if ($picker.hasClass('select2-hidden-accessible')) $picker.select2('destroy');
            $picker.prop('disabled', true).html('<option value="">{{ translate('messages.Select Item') }}</option>');
            return;
        }

        $.get(itemsEndpoint, {store_id: storeId}, function (foods) {
            foodCatalog = foods || [];

            if ($picker.hasClass('select2-hidden-accessible')) $picker.select2('destroy');

            $picker.prop('disabled', false).html(
                '<option value="">{{ translate('messages.Select Item') }}</option>' +
                foodCatalog.map(f => '<option value="' + f.id + '">' + escapeHtml(f.name) + unsellableNote(f) + '</option>').join('')
            );

            initFoodSelect2($picker, '{{ translate('messages.Select Item') }}');

            if (typeof onReady === 'function') onReady();
        });
    }

    // A product that is switched off, or out of stock where the module counts stock, can be put in
    // a bundle -- an admin may be building around a delivery that lands tomorrow -- but the bundle
    // will not serve while it stays that way. Say so at the point of choosing rather than leaving
    // the admin to find out from the customer-side availability check.
    function unsellableNote(food) {
        if (food.is_active === false) {
            return ' — {{ translate('Currently unavailable') }}';
        }

        if (food.out_of_stock) {
            return ' — {{ translate('Out of stock') }}';
        }

        return '';
    }

    function addOnQtyMap(row) {
        const map = {};

        (row.add_on_ids || []).forEach(function (id, i) {
            map[String(id)] = (row.add_on_qtys || [])[i] || 1;
        });

        return map;
    }

    function seedBundleItems(rows) {
        bundleItems.length = 0;

        (rows || []).forEach(function (row) {
            const food = foodCatalog.find(f => f.id == (bundleIsService ? row.service_id : row.item_id));

            if (!food) return;

            if (bundleIsService) {
                addServiceToBundle(food, row.selected_variations || []);

                return;
            }

            bundleItems.push({
                item_id: food.id,
                name: food.name,
                image: food.image_full_url,
                quantity: 1,
                max: food.maximum_cart_quantity || 0,
                unit_price: resolveItemPrice(food, row.selected_variations || [], row.add_on_ids || [], addOnQtyMap(row)),
                variations: food.variations || [],
                choice_options: food.choice_options || [],
                variation_combinations: food.variation_combinations || [],
                selected_variations: row.selected_variations || [],
                add_ons: food.add_ons || [],
                add_on_ids: (row.add_on_ids || []).map(String),
                add_on_qtys: (row.add_on_ids || []).map((id, i) => (row.add_on_qtys || [])[i] || 1)
            });
        });

        renderBundleItems();
    }

    function bundleItemFields() {
        const fields = [];

        bundleItems.forEach(function (item, index) {
            const prefix = 'items[' + index + ']';

            if (item.is_service) {
                fields.push([prefix + '[service_id]', item.service_id]);
                fields.push([prefix + '[variations]', JSON.stringify(item.selected_variations || [])]);

                return;
            }

            fields.push([prefix + '[item_id]', item.item_id]);
            fields.push([prefix + '[variations]', JSON.stringify(item.selected_variations || [])]);
            fields.push([prefix + '[add_on_ids]', JSON.stringify(item.add_on_ids || [])]);
            fields.push([prefix + '[add_on_qtys]', JSON.stringify(item.add_on_qtys || [])]);
        });

        return fields;
    }
</script>
