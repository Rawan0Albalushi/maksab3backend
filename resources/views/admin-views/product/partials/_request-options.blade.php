@php
    $helpers = \App\CentralLogics\Helpers::class;
@endphp

@include('admin-views.product.partials._detail-options', [
    'food_variations' => $module_type === 'food' ? $helpers::decodeJsonToArray($product->food_variations) : [],
    'variations' => $module_type === 'food' ? [] : $helpers::decodeJsonToArray($product->variations),
    'addons' => $has_addon ? $helpers::addons_by_ids($helpers::decodeJsonToArray($product->add_ons)) : collect(),
    'chip_groups' => [
        [
            'label' => translate('messages.Tags'),
            'values' => $helpers::tags_by_ids($helpers::decodeJsonToArray($product->tag_ids))->pluck('tag'),
        ],
        [
            'label' => translate('messages.Nutrition'),
            'values' => $has_nutrition
                ? $helpers::cached_list(\App\Models\Nutrition::class)->whereIn('id', $helpers::decodeJsonToArray($product->nutrition_ids))->pluck('nutrition')
                : collect(),
        ],
        [
            'label' => translate('messages.Allergy'),
            'values' => $has_allergy
                ? $helpers::cached_list(\App\Models\Allergy::class)->whereIn('id', $helpers::decodeJsonToArray($product->allergy_ids))->pluck('allergy')
                : collect(),
        ],
        [
            'label' => translate('Generic name'),
            'values' => $has_generic
                ? $helpers::cached_list(\App\Models\GenericName::class)->whereIn('id', $helpers::decodeJsonToArray($product->generic_ids))->pluck('generic_name')
                : collect(),
        ],
    ],
])
