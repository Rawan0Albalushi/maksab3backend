<?php

namespace Modules\AI\app\Services\Products\Resource;

use App\CentralLogics\Helpers;
use App\Models\Brand;
use App\Models\AddOn;
use App\Models\Allergy;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\CommonCondition;
use App\Models\GenericName;
use App\Models\Nutrition;
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class ProductResource
{
    private $productType = ["veg", "nonveg"];
    protected Category $category;
    protected Nutrition $nutrition;
    protected Allergy $allergy;
    protected AddOn $addon;
    protected Unit $unit;
    protected Attribute $attribute;
    protected GenericName $genericName;
    protected CommonCondition $commonCondition;
    protected Brand $brand;
    public function __construct()
    {
        $this->category = new Category();
        $this->nutrition = new Nutrition();
        $this->allergy = new Allergy();
        $this->addon = new AddOn();
        $this->unit = new Unit();
        $this->attribute = new Attribute();
        $this->genericName = new GenericName();
        $this->commonCondition = new CommonCondition();
        $this->brand = new Brand();

    }
    public function productGeneralSetupData($storeId, $moduleType = null, mixed $moduleId = null): array
    {
        $data = [
            'categories'      => $this->getCategoryEntitiyData(0, $moduleId),
            'sub_categories'  => $this->getCategoryEntitiyData(1, $moduleId),
            'rawSubCategories' => $this->getSubCategoryEntitiyData($moduleId),
            'product_types'   => $this->productType,
            'units'           => $this->getUnitData(),
        ];

        if (in_array($moduleType, ['food', 'grocery'])) {
            $data = array_merge($data, [
                'addon'     => $this->getAddonEntitiyData($storeId),
                'nutrition' => $this->getNuttitionEntitiyData(),
                'allergy'   => $this->getAllergyEntitiyData(),
            ]);
        } elseif ($moduleType === 'pharmacy') {
            $data = array_merge($data, [
                'generic_names'     => $this->getGenericName(),
                'common_conditions' => $this->getCommonConditionData(),
            ]);
        } elseif ($moduleType === 'shop' || $moduleType === 'ecommerce') {
            $data = array_merge($data, [
                'brands' => $this->getBrandData(),
            ]);
        }
        return $data;
    }
    public function getVariationData(): array
    {
        $data = [
            'attributes' => $this->attribute
                ->get(['id', 'name'])
                ->mapWithKeys(fn($item) => [strtolower($item->name) => $item->id])
                ->toArray()
        ];
        return $data;
    }
    private function getCategoryEntitiyData($position = 0, mixed $moduleId = null)
    {
        $moduleId ??= Auth::guard('admin')->check()
            ? Config::get('module.current_module_id') ?? null
            : Helpers::get_store_data()?->module_id ?? null;
        return $this->nameToIdMap(
            $this->category
                ->where(['position' => $position, 'status' => 1])
                ->when($moduleId, function ($query) use ($moduleId) {
                    return $query->where('module_id', $moduleId);
                }),
            'name'
        );
    }
    private function getSubCategoryEntitiyData(mixed $moduleId = null)
    {
        $moduleId ??= Auth::guard('admin')->check()
            ? Config::get('module.current_module_id') ?? null
            : Helpers::get_store_data()->module_id ?? null;
        return $this->category
            ->where(['position' => 1, 'status' => 1])
             ->when($moduleId, function ($query) use ($moduleId) {
                return $query->where('module_id', $moduleId);
            })
            ->select(['id', 'name', 'parent_id'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'parent_id' => $item->parent_id,
                ];
            })
            ->toArray();
    }
    private function getAddonEntitiyData($storeId)
    {
        return $storeId ? $this->nameToIdMap($this->addon->where(['store_id' => $storeId, 'status' => 1]), 'name') : [];
    }
    private function getNuttitionEntitiyData()
    {
        return $this->nameToIdMap($this->nutrition, 'nutrition');
    }
    private function getAllergyEntitiyData()
    {
        return $this->nameToIdMap($this->allergy, 'allergy');
    }
    private function getGenericName()
    {
        return $this->nameToIdMap($this->genericName, 'generic_name');
    }
    private function getBrandData()
    {
        return $this->nameToIdMap($this->brand->where('status', 1), 'name');
    }
    private function getCommonConditionData()
    {
        return $this->nameToIdMap($this->commonCondition->where('status', 1), 'name');
    }
    private function getUnitData()
    {
        return $this->nameToIdMap($this->unit, 'unit');
    }
    private function nameToIdMap(mixed $query, string $labelColumn): array
    {
        return $query
            ->get(['id', $labelColumn])
            ->mapWithKeys(fn($item) => [strtolower($item->{$labelColumn}) => $item->id])
            ->toArray();
    }
}
