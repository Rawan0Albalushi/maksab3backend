<?php

namespace App\Http\Controllers\Admin;

use App\Services\Promotion\BundleService;
use App\Rules\ImageFile;
use App\Rules\VideoFile;
use App\Traits\Report\ExportRowFormatTrait;
use App\CentralLogics\Helpers;
use App\Exports\ItemListExport;
use App\Exports\ItemReviewExport;
use App\Exports\StoreItemExport;
use App\Http\Controllers\Controller;
use App\Models\Allergy;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CommonCondition;
use App\Models\EcommerceItemDetails;
use App\Models\GenericName;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\ItemSeoData;
use App\Models\Nutrition;
use App\Models\PharmacyItemDetails;
use App\Models\Review;
use App\Models\Store;
use App\Models\Tag;
use App\Models\TempProduct;
use App\Models\Translation;
use App\Models\Zone;
use App\Observers\ItemObserver;
use App\Scopes\StoreScope;
use App\Services\Item\ItemService;
use App\Services\Item\ReviewService;
use App\Services\Item\TempProductService;
use App\Traits\Report\ExportRowStreamTrait;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use Illuminate\Support\Facades\Log;

class ItemController extends Controller
{
    use ExportRowFormatTrait;

    use ExportRowStreamTrait;

    public function index(Request $request)
    {
        $categories = Category::where(['position' => 0])->get();

        $taxData = Helpers::getTaxSystemType();
        $productWiseTax = $taxData['productWiseTax'];
        $taxVats = $taxData['taxVats'];

        return view('admin-views.product.index', compact('categories', 'productWiseTax', 'taxVats'));
    }

    public function store(Request $request)
    {
        $minimumPrice = Helpers::getDecimalPlaces();

        $validator = Validator::make($request->all(), array_merge([
            'name.0' => 'required',
            'name.*' => 'max:191',
            'category_id' => 'required',
            'image' => ImageFile::rules(Rule::requiredIf(function () use ($request) {
                return Config::get('module.current_module_type') != 'food' && $request?->product_gellary == null;
            })),
            'price' => 'required|numeric|between:' . $minimumPrice . ',999999999999.999',
            'discount' => 'nullable|numeric|min:0',
            'store_id' => 'required',
            'description.*' => 'max:1000',
            'name.0' => 'required',
            'description.0' => 'required',
        ], $this->productVideoValidationRules()), [
            'description.*.max' => translate('messages.Description is too long.') . ' ' . translate('messages.Character limit') . ': 1000',
            'name.0.required' => translate('messages.Item name required'),
            'category_id.required' => translate('messages.Category required'),
            'image.required' => translate('messages.Thumbnail image is required'),
            'name.0.required' => translate('Default name is required'),
            'description.0.required' => translate('Default description is required'),
        ]);

        if(!isset($request['discount']) || $request['discount'] == null){
            $request['discount'] = 0;
        }

        if ($request['discount_type'] == 'percent') {
            $dis = ($request['price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($dis > 0 && $request['price'] <= $dis) {
            $validator->getMessageBag()->add('unit_price', translate('Discount must be less than the unit price'));
        }

        if (($dis > 0 && $request['price'] <= $dis )|| $validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $images = [];

        $gallerySourceItem = null;

        if ($request->item_id && $request?->product_gellary == 1) {
            $item_data = Item::withoutGlobalScope(StoreScope::class)->findOrfail($request->item_id);
            $gallerySourceItem = $item_data;
            if (! $request->has('image')) {

                $oldDisk = 'public';
                if ($item_data->storage && count($item_data->storage) > 0) {
                    foreach ($item_data->storage as $value) {
                        if ($value['key'] == 'image') {
                            $oldDisk = $value['value'];
                        }
                    }
                }
                $oldPath = "product/{$item_data->image}";
                $newFileNamethumb = Carbon::now()->toDateString().'-'.uniqid().'.png';
                $newPath = "product/{$newFileNamethumb}";
                $dir = 'product/';
                $newDisk = Helpers::getDisk();

                try {
                    if ($newDisk == 's3' && $item_data->image) {
                        Storage::disk($newDisk)->put($newPath, Storage::disk($oldDisk)->get($oldPath));
                    } else {
                        if (Storage::disk($oldDisk)->exists($oldPath)) {
                            if (! Storage::disk($newDisk)->exists($dir)) {
                                Storage::disk($newDisk)->makeDirectory($dir);
                            }
                            $fileContents = Storage::disk($oldDisk)->get($oldPath);
                            Storage::disk($newDisk)->put($newPath, $fileContents);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('admin.item_controller.store_failed', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile().':'.$e->getLine(),
                    ]);
                }
            }
            foreach ($item_data->images as $key => $value) {
                if (! in_array(is_array($value) ? $value['img'] : $value, explode(',', $request->removedImageKeys))) {
                    $value = is_array($value) ? $value : ['img' => $value, 'storage' => 'public'];
                    $oldDisk = $value['storage'];
                    $oldPath = "product/{$value['img']}";
                    $newFileName = Carbon::now()->toDateString().'-'.uniqid().'.png';
                    $newPath = "product/{$newFileName}";
                    $dir = 'product/';
                    $newDisk = Helpers::getDisk();
                    try {
                        if ($newDisk == 's3') {
                            Storage::disk($newDisk)->put($newPath, Storage::disk($oldDisk)->get($oldPath));
                        } else {
                            if (Storage::disk($oldDisk)->exists($oldPath)) {
                                if (! Storage::disk($newDisk)->exists($dir)) {
                                    Storage::disk($newDisk)->makeDirectory($dir);
                                }
                                $fileContents = Storage::disk($oldDisk)->get($oldPath);
                                Storage::disk($newDisk)->put($newPath, $fileContents);
                            }
                        }

                    } catch (\Exception $e) {
                        Log::warning('admin.item_controller.store_failed', [
                            'error' => $e->getMessage(),
                            'file' => $e->getFile().':'.$e->getLine(),
                        ]);
                    }
                    $images[] = ['img' => $newFileName, 'storage' => Helpers::getDisk()];
                }
            }
        }

        $tag_ids = [];
        if ($request->tags != null) {
            $tags = explode(',', $request->tags);
        }
        if (isset($tags)) {
            foreach ($tags as $key => $value) {
                $tag = Tag::firstOrNew(
                    ['tag' => $value]
                );
                $tag->save();
                array_push($tag_ids, $tag->id);
            }
        }

        $nutrition_ids = [];
        if ($request->nutritions != null) {
            $nutritions = $request->nutritions;
        }
        if (isset($nutritions)) {
            foreach ($nutritions as $key => $value) {
                $nutrition = Nutrition::firstOrNew(
                    ['nutrition' => $value]
                );
                $nutrition->save();
                array_push($nutrition_ids, $nutrition->id);
            }
        }
        $generic_ids = [];
        if ($request->generic_name != null) {
            $generic_name = GenericName::firstOrNew(
                ['generic_name' => $request->generic_name]
            );
            $generic_name->save();
            array_push($generic_ids, $generic_name->id);
        }

        $allergy_ids = [];
        if ($request->allergies != null) {
            $allergies = $request->allergies;
        }
        if (isset($allergies)) {
            foreach ($allergies as $key => $value) {
                $allergy = Allergy::firstOrNew(
                    ['allergy' => $value]
                );
                $allergy->save();
                array_push($allergy_ids, $allergy->id);
            }
        }

        $item = new Item;
        $item->name = $request->name[array_search('default', $request->lang)];

        $category = [];
        if ($request->category_id != null) {
            array_push($category, [
                'id' => $request->category_id,
                'position' => 1,
            ]);
        }
        if ($request->sub_category_id != null) {
            array_push($category, [
                'id' => $request->sub_category_id,
                'position' => 2,
            ]);
        }
        if ($request->sub_sub_category_id != null) {
            array_push($category, [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ]);
        }
        $item->category_ids = json_encode($category);
        $item->category_id = $request->sub_category_id ? $request->sub_category_id : $request->category_id;
        $item->store_category_id = $request->filled('store_category_id') ? (int) $request->store_category_id : null;
        $item->description = $request->description[array_search('default', $request->lang)];

        $choice_options = [];
        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_'.$no;
                if ($request[$str][0] == null) {
                    $validator->getMessageBag()->add('name', translate('messages.Attribute choice option value can not be null'));

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $temp['name'] = 'choice_'.$no;
                $temp['title'] = $request->choice[$key];
                $temp['options'] = explode(',', implode('|', preg_replace('/\s+/', ' ', $request[$str])));
                array_push($choice_options, $temp);
            }
        }
        $item->choice_options = json_encode($choice_options);
        $variations = [];
        $options = [];
        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_'.$no;
                $my_str = implode('|', $request[$name]);
                array_push($options, explode(',', $my_str));
            }
        }
        $combinations = Helpers::combinations($options);
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $temp) {
                    if ($k > 0) {
                        $str .= '-'.str_replace(' ', '', $temp);
                    } else {
                        $str .= str_replace(' ', '', $temp);
                    }
                }
                $temp = [];
                $temp['type'] = $str;
                $temp['price'] = abs($request['price_'.str_replace('.', '_', $str)]);

                if ($request->discount_type == 'amount' && $temp['price'] < $request->discount) {
                    $validator->getMessageBag()->add('unit_price', translate('Variation price must be greater than discount amount'));

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }

                $temp['stock'] = abs($request['stock_'.str_replace('.', '_', $str)]);
                array_push($variations, $temp);
            }
        }

        if (! empty($request->file('item_images'))) {
            foreach ($request->item_images as $img) {
                $image_name = Helpers::upload('product/', 'png', $img);
                $images[] = ['img' => $image_name, 'storage' => Helpers::getDisk()];
            }
        }
        $food_variations = [];
        if (isset($request->options)) {
            foreach (array_values($request->options) as $key => $option) {

                $temp_variation['name'] = $option['name'];
                $temp_variation['type'] = $option['type'];
                $temp_variation['min'] = $option['min'] ?? 0;
                $temp_variation['max'] = $option['max'] ?? 0;
                $temp_variation['required'] = $option['required'] ?? 'off';
                if ($option['min'] > 0 && $option['min'] > $option['max']) {
                    $validator->getMessageBag()->add('name', translate('messages.Minimum value can not be greater then maximum value'));

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                if (! isset($option['values'])) {
                    $validator->getMessageBag()->add('name', translate('messages.Please add options for').$option['name']);

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                if ($option['max'] > count($option['values'])) {
                    $validator->getMessageBag()->add('name', translate('messages.Please add more options or change the max value for').$option['name']);

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $temp_value = [];

                foreach (array_values($option['values']) as $value) {
                    if (isset($value['label'])) {
                        $temp_option['label'] = $value['label'];
                    }
                    $temp_option['optionPrice'] = $value['optionPrice'];
                    array_push($temp_value, $temp_option);
                }
                $temp_variation['values'] = $temp_value;
                array_push($food_variations, $temp_variation);
            }
        }

        $item->food_variations = json_encode($food_variations);
        $item->variations = json_encode($variations);
        $item->price = $request->price;
        $item->image = $request->has('image') ? Helpers::upload('product/', 'png', $request->file('image')) : $newFileNamethumb ?? null;
        $videoData = $this->resolveCreateVideoData($request, $gallerySourceItem);
        $item->video = $videoData['video'];
        $item->video_link = $videoData['video_link'];
        $item->available_time_starts = $request->available_time_starts ?? '00:00:00';
        $item->available_time_ends = $request->available_time_ends ?? '23:59:59';
        $item->discount = $request->discount_type == 'amount' ? $request->discount : $request->discount;
        $item->discount_type = $request->discount_type;
        $item->unit_id = $request->unit;
        $item->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
        $item->add_ons = $request->has('addon_ids') ? json_encode($request->addon_ids) : json_encode([]);
        $item->store_id = $request->store_id;
        $item->maximum_cart_quantity = $request->maximum_cart_quantity;
        $item->veg = $request->veg ?? 0;
        $item->module_id = Config::get('module.current_module_id');
        $module_type = Config::get('module.current_module_type');
        if ($module_type == 'grocery') {
            $item->organic = $request->organic ?? 0;
        }
        $item->stock = $request->current_stock ?? 0;
        $item->images = $images;
        $item->is_halal = $request->is_halal ?? 0;
        $item->save();
        $item->tags()->sync($tag_ids);
        $item->nutritions()->sync($nutrition_ids);
        $item->allergies()->sync($allergy_ids);
        if ($module_type == 'pharmacy') {
            $item_details = new PharmacyItemDetails;
            $item_details->item_id = $item->id;
            $item_details->common_condition_id = $request->condition_id;
            $item_details->is_basic = $request->basic ?? 0;
            $item_details->is_prescription_required = $request->is_prescription_required ?? 0;
            $item_details->unit_value = $request->unit_value;
            $item_details->manufacturer = $request->manufacturer;
            $item_details->save();
            $item->generic()->sync($generic_ids);
        }
        if (in_array($module_type, ['ecommerce', 'grocery'])) {
            $item_details = new EcommerceItemDetails;
            $item_details->item_id = $item->id;
            $item_details->brand_id = $request->brand_id;
            $item_details->save();
        }

        if (addon_published_status('TaxModule')) {
            $SystemTaxVat = \Modules\TaxModule\Entities\SystemTaxSetup::where('is_active', 1)->where('is_default', 1)->first();
            if ($SystemTaxVat?->tax_type == 'product_wise') {
                foreach ($request['tax_ids'] ?? [] as $tax_id) {
                    \Modules\TaxModule\Entities\Taxable::create(
                        [
                            'taxable_type' => Item::class,
                            'taxable_id' => $item->id,
                            'system_tax_setup_id' => $SystemTaxVat->id,
                            'tax_id' => $tax_id,
                        ],
                    );
                }
            }
        }

        Helpers::add_or_update_translations(request: $request, key_data: 'name', name_field: 'name', model_name: 'Item', data_id: $item->id, data_value: $item->name);
        Helpers::add_or_update_translations(request: $request, key_data: 'description', name_field: 'description', model_name: 'Item', data_id: $item->id, data_value: $item->description);
        if ($module_type == 'ecommerce') {
            $this->addOrUpdateMetaData($request, $item->id);
        }

        return response()->json(['success' => translate('Added successfully')], 200);
    }

    public function view($id)
    {
        $taxData = Helpers::getTaxSystemType();
        $productWiseTax = $taxData['productWiseTax'];
        $relations = ['translations', 'store.storage', 'store.zone', 'store.module', 'module.translations',
            'category.parent', 'tags', 'nutritions', 'allergies', 'generic', 'unit'];
        $product = Item::withoutGlobalScope(StoreScope::class)->withStorage()
            ->with($productWiseTax ? array_merge($relations, ['taxVats.tax']) : $relations)
            ->where(['id' => $id])->firstOrFail();

        $reviews = Review::with(['customer.storage'])->where(['item_id' => $id])->latest()->paginate(config('default_pagination'));
        $pending_request = app(TempProductService::class)->pendingRequestIdFor($product->id);

        return view('admin-views.product.view', compact('product', 'reviews', 'productWiseTax', 'pending_request'));
    }

    public function edit(Request $request, $id)
    {
        $temp_product = false;
        if ($request->temp_product) {
            $product = TempProduct::withoutGlobalScope(StoreScope::class)->withoutGlobalScope('translate')->with('store.storage', 'category', 'module', 'storage', 'translations', 'tags', 'nutritions', 'allergies', 'generic', 'unit', 'pharmacy_item_details', 'ecommerce_item_details.brand', 'seoData')->findOrFail($id);
            $temp_product = true;
        } else {
            $product = Item::withoutGlobalScope(StoreScope::class)->withoutGlobalScope('translate')->withStorage()->with('store.storage', 'category', 'module', 'translations', 'tags', 'nutritions', 'allergies', 'generic', 'unit', 'pharmacy_item_details', 'ecommerce_item_details.brand', 'seoData')->findOrFail($id);
        }
        if (! $product) {
            Toastr::error(translate('No data found'));

            return back();
        }
        $temp = $product->category;
        if ($temp?->position) {
            $sub_category = $temp;
            $category = $temp->loadMissing('parent')->parent;
        } else {
            $category = $temp;
            $sub_category = null;
        }

        $taxData = Helpers::getTaxSystemType();
        $productWiseTax = $taxData['productWiseTax'];
        $taxVats = $taxData['taxVats'];
        $taxVatIds = $productWiseTax ? $product->taxVats()->pluck('tax_id')->toArray() : [];

        $store_categories = Helpers::storeCategoryStatus()
            ? \App\Models\StoreCategory::active()->where('store_id', $product->store_id)->orderBy('priority', 'desc')->get(['id', 'name'])
            : collect();

        return view('admin-views.product.edit', compact('product', 'sub_category', 'category', 'temp_product', 'productWiseTax', 'taxVats', 'taxVatIds', 'store_categories'));
    }

    public function status(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->findOrFail($request->id);
        $product->status = $request->status;
        $product->save();
        Toastr::success(translate('messages.Item status updated'));

        return back();
    }

    public function update(Request $request, $id)
    {
        $minimumPrice = Helpers::getDecimalPlaces();

        $validator = Validator::make($request->all(), array_merge([
            'name' => 'array',
            'name.0' => 'required',
            'name.*' => 'max:191',
            'category_id' => 'required',
            'price' => 'required|numeric|between:' . $minimumPrice . ',999999999999.999',
            'store_id' => 'required',
            'description' => 'array',
            'description.*' => 'max:1000',
            'discount' => 'nullable|numeric|min:0',
            'name.0' => 'required',
            'description.0' => 'required',
        ], $this->productVideoValidationRules()), [
            'description.*.max' => translate('messages.Description is too long.') . ' ' . translate('messages.Character limit') . ': 1000',
            'category_id.required' => translate('messages.Category required'),
            'name.0.required' => translate('Default name is required'),
            'description.0.required' => translate('Default description is required'),
        ]);

        if(!isset($request['discount']) || $request['discount'] == null){
            $request['discount'] = 0;
        }

        if ($request['discount_type'] == 'percent') {
            $dis = ($request['price'] / 100) * $request['discount'];
        } else {
            $dis = $request['discount'];
        }

        if ($dis > 0 && $request['price'] <= $dis) {
            $validator->getMessageBag()->add('unit_price', translate('Discount must be less than the unit price'));
        }

        if (($dis > 0 && $request['price'] <= $dis )|| $validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $item = Item::withoutGlobalScope(StoreScope::class)->with('category')->find($id);
        $oldVideo = $item->video;
        $tempProduct = $item->temp_product;
        $tag_ids = [];
        if ($request->tags != null) {
            $tags = explode(',', $request->tags);
        }
        if (isset($tags)) {
            foreach ($tags as $key => $value) {
                $tag = Tag::firstOrNew(
                    ['tag' => $value]
                );
                $tag->save();
                array_push($tag_ids, $tag->id);
            }
        }
        $nutrition_ids = [];
        if ($request->nutritions != null) {
            $nutritions = $request->nutritions;
        }
        if (isset($nutritions)) {
            foreach ($nutritions as $key => $value) {
                $nutrition = Nutrition::firstOrNew(
                    ['nutrition' => $value]
                );
                $nutrition->save();
                array_push($nutrition_ids, $nutrition->id);
            }
        }
        $allergy_ids = [];
        if ($request->allergies != null) {
            $allergies = $request->allergies;
        }
        if (isset($allergies)) {
            foreach ($allergies as $key => $value) {
                $allergy = Allergy::firstOrNew(
                    ['allergy' => $value]
                );
                $allergy->save();
                array_push($allergy_ids, $allergy->id);
            }
        }

        $generic_ids = [];
        if ($request->generic_name != null) {
            $generic_name = GenericName::firstOrNew(
                ['generic_name' => $request->generic_name]
            );
            $generic_name->save();
            array_push($generic_ids, $generic_name->id);
        }

        $item->name = $request->name[array_search('default', $request->lang)];

        $category = [];
        if ($request->category_id != null) {
            array_push($category, [
                'id' => $request->category_id,
                'position' => 1,
            ]);
        }
        if ($request->sub_category_id != null) {
            array_push($category, [
                'id' => $request->sub_category_id,
                'position' => 2,
            ]);
        }
        if ($request->sub_sub_category_id != null) {
            array_push($category, [
                'id' => $request->sub_sub_category_id,
                'position' => 3,
            ]);
        }

        $images = $item['images'];
        if (! $request?->temp_product) {
            foreach ($item->images as $key => $value) {
                if (in_array(is_array($value) ? $value['img'] : $value, explode(',', $request->removedImageKeys))) {
                    $value = is_array($value) ? $value : ['img' => $value, 'storage' => 'public'];
                    Helpers::check_and_delete('product/', $value['img']);
                    unset($images[$key]);
                }
            }
            $images = array_values($images);
            if ($request->has('item_images')) {
                foreach ($request->item_images as $img) {
                    $image = Helpers::upload('product/', 'png', $img);
                    array_push($images, ['img' => $image, 'storage' => Helpers::getDisk()]);
                }
            }
        }

        $item->category_id = $request->sub_category_id ? $request->sub_category_id : $request->category_id;
        $item->category_ids = json_encode($category);
        $item->store_category_id = $request->filled('store_category_id') ? (int) $request->store_category_id : null;
        $item->description = $request->description[array_search('default', $request->lang)];

        $choice_options = [];
        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_'.$no;
                if ($request[$str][0] == null) {
                    $validator->getMessageBag()->add('name', translate('messages.Attribute choice option value can not be null'));

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $temp['name'] = 'choice_'.$no;
                $temp['title'] = $request->choice[$key];
                $temp['options'] = explode(',', implode('|', preg_replace('/\s+/', ' ', $request[$str])));
                array_push($choice_options, $temp);
            }
        }
        $item->choice_options = $request->has('attribute_id') ? json_encode($choice_options) : json_encode([]);
        $variations = [];
        $options = [];
        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_'.$no;
                $my_str = implode('|', $request[$name]);
                array_push($options, explode(',', $my_str));
            }
        }
        $combinations = Helpers::combinations($options);
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $temp) {
                    if ($k > 0) {
                        $str .= '-'.str_replace(' ', '', $temp);
                    } else {
                        $str .= str_replace(' ', '', $temp);
                    }
                }
                $temp = [];
                $temp['type'] = $str;
                $temp['price'] = abs($request['price_'.str_replace('.', '_', $str)]);

                if ($request->discount_type == 'amount' && $temp['price'] < $request->discount) {
                    $validator->getMessageBag()->add('unit_price', translate('Variation price must be greater than discount amount'));

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $temp['stock'] = abs($request['stock_'.str_replace('.', '_', $str)]);
                array_push($variations, $temp);
            }
        }

        $food_variations = [];
        if (isset($request->options)) {
            foreach (array_values($request->options) as $key => $option) {
                $temp_variation['name'] = $option['name'];
                $temp_variation['type'] = $option['type'];
                $temp_variation['min'] = $option['min'] ?? 0;
                $temp_variation['max'] = $option['max'] ?? 0;
                if ($option['min'] > 0 && $option['min'] > $option['max']) {
                    $validator->getMessageBag()->add('name', translate('messages.Minimum value can not be greater then maximum value'));

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                if (! isset($option['values'])) {
                    $validator->getMessageBag()->add('name', translate('messages.Please add options for').$option['name']);

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                if ($option['max'] > count($option['values'])) {
                    $validator->getMessageBag()->add('name', translate('messages.Please add more options or change the max value for').$option['name']);

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $temp_variation['required'] = $option['required'] ?? 'off';
                $temp_value = [];
                foreach (array_values($option['values']) as $value) {
                    if (isset($value['label'])) {
                        $temp_option['label'] = $value['label'];
                    }
                    $temp_option['optionPrice'] = $value['optionPrice'];
                    array_push($temp_value, $temp_option);
                }
                $temp_variation['values'] = $temp_value;
                array_push($food_variations, $temp_variation);
            }
        }
        $slug = Str::slug($request->name[array_search('default', $request->lang)]);
        $item->slug = $item->slug ? $item->slug : "{$slug}{$item->id}";
        $item->food_variations = json_encode($food_variations);
        $item->variations = $request->has('attribute_id') ? json_encode($variations) : json_encode([]);
        $item->price = $request->price;
        $item->image = $request->has('image') ? Helpers::update('product/', $item->image, 'png', $request->file('image')) : $item->image;
        $videoData = $request?->temp_product
            ? $this->resolvePromotedVideoData($request, $tempProduct)
            : $this->resolvePersistedVideoData($request, $item->video, $item->video_link);
        $item->video = $videoData['video'];
        $item->video_link = $videoData['video_link'];
        $item->available_time_starts = $request->available_time_starts ?? '00:00:00';
        $item->available_time_ends = $request->available_time_ends ?? '23:59:59';

        $item->discount = $request->discount;
        $item->discount_type = $request->discount_type;
        $item->unit_id = $request->unit;
        $item->attributes = $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]);
        $item->add_ons = $request->has('addon_ids') ? json_encode($request->addon_ids) : json_encode([]);
        $item->store_id = $request->store_id;
        $item->maximum_cart_quantity = $request->maximum_cart_quantity;
        $item->stock = $request->current_stock ?? 0;
        $item->is_halal = $request->is_halal ?? 0;
        $item->organic = $request->organic ?? 0;
        $item->veg = $request->veg ?? 0;
        $item->images = $images;
        if (Helpers::get_business_settings('product_approval') && $request?->temp_product) {

            $images = $item->temp_product?->images ?? [];

            if ($request->removedImageKeys) {
                foreach ($images as $key => $value) {
                    if (in_array(is_array($value) ? $value['img'] : $value, explode(',', $request->removedImageKeys))) {
                        unset($images[$key]);
                    }
                }
                $images = array_values($images);
            }

            foreach ($images as $k => $value) {
                $value = is_array($value) ? $value : ['img' => $value, 'storage' => 'public'];
                $oldDisk = $value['storage'];
                $oldPath = "product/{$value['img']}";
                $newFileName = Carbon::now()->toDateString().'-'.uniqid().'.png';
                $newPath = "product/{$newFileName}";
                $dir = 'product/';
                $newDisk = Helpers::getDisk();
                try {
                    if (Storage::disk($oldDisk)->exists($oldPath)) {
                        if (! Storage::disk($newDisk)->exists($dir)) {
                            Storage::disk($newDisk)->makeDirectory($dir);
                        }
                        $fileContents = Storage::disk($oldDisk)->get($oldPath);
                        Storage::disk($newDisk)->put($newPath, $fileContents);
                        unset($images[$k]);
                    }
                } catch (\Exception $e) {
                    Log::warning('admin.item_controller.update_failed', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile().':'.$e->getLine(),
                    ]);
                }
                $images[] = ['img' => $newFileName, 'storage' => Helpers::getDisk()];
            }

            $images = array_values($images);

            if ($request->has('item_images')) {
                foreach ($request->item_images as $img) {
                    $image = Helpers::upload('product/', 'png', $img);
                    array_push($images, ['img' => $image, 'storage' => Helpers::getDisk()]);
                }
            }

            $item->images = $images;

            $item->temp_product?->translations()->delete();
            $item?->pharmacy_item_details()?->delete();
            if ($item->module->module_type == 'pharmacy') {
                DB::table('pharmacy_item_details')->where('temp_product_id', $item->temp_product?->id)->update([
                    'item_id' => $item->id,
                    'temp_product_id' => null,
                ]);
            }
            $item->temp_product?->taxVats()->delete();
            if ($item->module->module_type == 'ecommerce') {
                $itemSeo = ItemSeoData::where('temp_item_id', $item->temp_product?->id)->first();
                if ($itemSeo) {
                    if ($item->seoData) {
                        $item->seoData->delete();
                    }
                    $itemSeo->item_id = $item->id;
                    $itemSeo->temp_item_id = null;
                    $itemSeo->save();
                }
            }
            $item->temp_product?->seoData()->delete();

            $item->temp_product?->delete();
            $item->is_approved = 1;
            try {

                if (SendNotification::channelEnabled('store', 'store_product_approve', 'push_notification_status', $item?->store->id) && $item?->store?->vendor?->firebase_token) {
                    $data = NotificationMessages::productApproved();
                    SendNotification::pushToVendor($item?->store?->vendor_id, $item?->store?->vendor?->firebase_token, $data);
                }

                if (SendNotification::canSendMail('product_approve_mail_status_store', 'store', 'store_product_approve', $item?->store?->id)) {
                    SendNotification::mail($item?->store?->vendor?->getRawOriginal('email'), new \App\Mail\VendorProductMail($item?->store?->name, 'approved'));
                }
            } catch (\Exception $e) {
                Log::error('admin.item_controller.update_failed', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile().':'.$e->getLine(),
                ]);
            }
        }
        $item->save();
        if ($oldVideo && $oldVideo !== $item->video) {
            Helpers::check_and_delete('product/', $oldVideo);
        }
        if ($request?->temp_product && $tempProduct?->video && $tempProduct->video !== $item->video) {
            Helpers::check_and_delete('product/', $tempProduct->video);
        }
        $item->tags()->sync($tag_ids);
        $item->nutritions()->sync($nutrition_ids);
        $item->allergies()->sync($allergy_ids);
        if ($item->module->module_type == 'pharmacy') {
            $item->generic()->sync($generic_ids);
            DB::table('pharmacy_item_details')
                ->updateOrInsert(
                    ['item_id' => $item->id],
                    [
                        'common_condition_id' => $request->condition_id,
                        'is_basic' => $request->basic ?? 0,
                        'is_prescription_required' => $request->is_prescription_required ?? 0,
                        'unit_value' => $request->unit_value,
                        'manufacturer' => $request->manufacturer,
                    ]
                );
        }
        if (in_array($item->module->module_type, ['ecommerce', 'grocery'])) {
            DB::table('ecommerce_item_details')
                ->updateOrInsert(
                    ['item_id' => $item->id],
                    [
                        'brand_id' => $request->brand_id,
                    ]
                );
        }

        if (addon_published_status('TaxModule')) {
            $taxVatIds = $item->taxVats()->pluck('tax_id')->toArray() ?? [];
            $newTaxVatIds = array_map('intval', $request['tax_ids'] ?? []);
            sort($newTaxVatIds);
            sort($taxVatIds);
            if ($newTaxVatIds != $taxVatIds) {
                $item->taxVats()->delete();
                $SystemTaxVat = \Modules\TaxModule\Entities\SystemTaxSetup::where('is_active', 1)->where('is_default', 1)->first();
                if ($SystemTaxVat?->tax_type == 'product_wise') {
                    foreach ($request['tax_ids'] ?? [] as $tax_id) {
                        \Modules\TaxModule\Entities\Taxable::create(
                            [
                                'taxable_type' => Item::class,
                                'taxable_id' => $item->id,
                                'system_tax_setup_id' => $SystemTaxVat->id,
                                'tax_id' => $tax_id,
                            ],
                        );
                    }
                }
            }
        }

        Helpers::add_or_update_translations(request: $request, key_data: 'name', name_field: 'name', model_name: 'Item', data_id: $item->id, data_value: $item->name);
        Helpers::add_or_update_translations(request: $request, key_data: 'description', name_field: 'description', model_name: 'Item', data_id: $item->id, data_value: $item->description);
        if ($item->module->module_type == 'ecommerce') {
            $this->addOrUpdateMetaData($request, $item->id);
        }

        return response()->json(['success' => translate('Updated successfully')], 200);
    }

    public function delete(Request $request)
    {

        if ($request?->temp_product) {
            $product = TempProduct::withoutGlobalScope(StoreScope::class)->find($request->id);
        } else {
            $product = Item::withoutGlobalScope(StoreScope::class)->withoutGlobalScope('translate')->with('translations')->find($request->id);
            if ($product?->temp_product?->video) {
                Helpers::check_and_delete('product/', $product->temp_product->video);
            }
            $product?->temp_product?->translations()?->delete();
            $product?->temp_product()?->delete();
            $product?->carts()?->delete();
        }
        if ($product->image) {
            Helpers::check_and_delete('product/', $product['image']);
        }
        if ($product->video) {
            Helpers::check_and_delete('product/', $product['video']);
        }
        foreach ($product->images as $value) {
            $value = is_array($value) ? $value : ['img' => $value, 'storage' => 'public'];
            Helpers::check_and_delete('product/', $value['img']);
        }
        $product?->translations()->delete();
        $product?->taxVats()->delete();

        $product->delete();
        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function variant_combination(Request $request)
    {
        $options = [];
        $price = $request->price;
        $product_name = $request->name;

        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_'.$no;
                $my_str = implode('', $request[$name]);
                array_push($options, explode(',', $my_str));
            }
        }

        $result = [[]];
        foreach ($options as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property => $property_value]);
                }
            }
            $result = $tmp;
        }

        $data = [];
        foreach ($result as $combination) {
            $str = '';
            foreach ($combination as $key => $item) {
                if ($key > 0) {
                    $str .= '-'.str_replace(' ', '', $item);
                } else {
                    $str .= str_replace(' ', '', $item);
                }
            }

            $price_field = 'price_'.$str;
            $stock_field = 'stock_'.$str;
            $item_price = $request->input($price_field);
            $item_stock = $request->input($stock_field);

            $data[] = [
                'name' => $str,
                'price' => $item_price ?? $price,
                'stock' => $item_stock ?? 1,
            ];
        }
        $combinations = $result;
        $stock = $request->stock == 'true' ? true : false;

        return response()->json([
            'view' => view('admin-views.product.partials._variant-combinations', compact('combinations', 'price', 'product_name', 'stock', 'data'))->render(),
            'length' => count($combinations),
            'stock' => $stock,
            'combinations' => $combinations,
        ]);
    }

    public function variant_price(Request $request)
    {
        if ($request->item_type == 'item') {
            $product = Item::withoutGlobalScope(StoreScope::class)->find($request->id);
        } else {
            $product = ItemCampaign::find($request->id);
        }
        if (isset($product->module_id) && $product->module->module_type == 'food' && $product->food_variations) {
            $price = $product->price;
            $addon_price = 0;
            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price'.$id] * $request['addon-quantity'.$id];
                }
            }
            $product_variations = json_decode($product->food_variations, true);
            if ($request->variations && $product_variations && count($product_variations)) {

                $price += Helpers::food_variation_price($product_variations, $request->variations);
                $price -= Helpers::product_discount_calculate($product, $price, $product->store)['discount_amount'];
            } else {
                $price = $product->price - Helpers::product_discount_calculate($product, $product->price, $product->store)['discount_amount'];
            }
        } else {
            $str = '';
            $quantity = 0;
            $price = 0;
            $addon_price = 0;

            foreach (json_decode($product->choice_options) as $key => $choice) {
                if ($str != null) {
                    $str .= '-'.str_replace(' ', '', $request[$choice->name]);
                } else {
                    $str .= str_replace(' ', '', $request[$choice->name]);
                }
            }

            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price'.$id] * $request['addon-quantity'.$id];
                }
            }

            if ($str != null) {
                $count = count(json_decode($product->variations));
                for ($i = 0; $i < $count; $i++) {
                    if (json_decode($product->variations)[$i]->type == $str) {
                        $price = json_decode($product->variations)[$i]->price - Helpers::product_discount_calculate($product, json_decode($product->variations)[$i]->price, $product->store)['discount_amount'];
                    }
                }
            } else {
                $price = $product->price - Helpers::product_discount_calculate($product, $product->price, $product->store)['discount_amount'];
            }
        }

        return ['price' => Helpers::format_currency(($price * $request->quantity) + $addon_price)];
    }

    public function get_categories(Request $request)
    {
        $key = explode(' ', $request['q'] ?? '');
        $cat = Category::translateOnly('name')
            ->select('id', 'name')
            ->when(isset($request->module_id), function ($query) use ($request) {
                $query->where('module_id', $request->module_id);
            })
            ->when($request->sub_category, function ($query) {
                $query->where('position', '>', '0');
            })
            ->where(['parent_id' => $request->parent_id])
            ->when($request['q'], function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->where('name', 'like', "%{$value}%");
                }
            })
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'text' => $category->name,
                ];
            });

        return response()->json($cat);
    }

    /**
     * Ceiling on the ajax item pickers.
     *
     * Both build <option> markup for a server-rendered select, so the response size is bounded
     * by what a browser can take, not by the query. Unbounded, get_items() hydrated every item
     * in the catalogue with its store and exhausted 512MB before responding. Ids already
     * selected are fetched separately and always included, so editing a record can never lose
     * its own selection to the cap.
     */
    private const ITEM_PICKER_LIMIT = 1000;

    public function get_items(Request $request)
    {
        $selectedIds = array_values(array_filter(array_map('intval', (array) $request->data)));

        $itemQuery = fn () => Item::withoutGlobalScope(StoreScope::class)
            ->translateOnly('name')
            ->without('storeCategory')
            ->select('id', 'name', 'store_id')
            ->with(['store' => function ($query) {
                $query->select('id', 'name')
                    ->translateOnly('name')
                    ->without('storeConfig');
            }])
            ->when($request->zone_id, function ($q) use ($request) {
                $q->whereHas('store', function ($query) use ($request) {
                    $query->where('zone_id', $request->zone_id);
                });
            })
            ->when($request->module_id, function ($q) use ($request) {
                $q->where('module_id', $request->module_id);
            });

        $items = ($selectedIds ? $itemQuery()->whereIn('items.id', $selectedIds)->get() : collect())
            ->concat(
                $itemQuery()
                    ->when($selectedIds, fn ($q) => $q->whereNotIn('items.id', $selectedIds))
                    ->limit(self::ITEM_PICKER_LIMIT)
                    ->get()
            );

        $selected = $request->data ? array_flip(array_map('strval', (array) $request->data)) : [];

        $options = [];
        if ($items->isNotEmpty() && ! $request->data) {
            $options[] = '<option value="0" disabled selected>'.translate('Select').'</option>';
        }

        foreach ($items as $row) {
            $options[] = '<option value="'.$row->id.'" '
                .(isset($selected[(string) $row->id]) ? 'selected ' : '')
                .'>'.e($row->name).' ('.e($row->store?->name ?? '').')</option>';
        }

        return response()->json([
            'options' => implode('', $options),
        ]);
    }

    public function get_items_flashsale(Request $request)
    {
        // Bounded the same way as get_items() -- see ITEM_PICKER_LIMIT.
        $chosenIds = array_values(array_filter(array_map('intval', (array) $request->data)));

        $itemQuery = fn () => Item::withoutGlobalScope(StoreScope::class)
            ->translateOnly('name')
            ->without('storeCategory')
            ->select('id', 'name', 'store_id', 'stock')
            ->with(['store' => function ($query) {
                $query->select('id', 'name')
                    ->translateOnly('name')
                    ->without('storeConfig');
            }])
            ->active()
            ->when($request->zone_id, function ($q) use ($request) {
                $q->whereHas('store', function ($query) use ($request) {
                    $query->where('zone_id', $request->zone_id);
                });
            })
            ->when($request->module_id, function ($q) use ($request) {
                $q->where('module_id', $request->module_id);
            })->whereDoesntHave('flashSaleItems.flashSale', function ($query) {
                $now = now();
                $query->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now);
            });

        $items = ($chosenIds ? $itemQuery()->whereIn('items.id', $chosenIds)->get() : collect())
            ->concat(
                $itemQuery()
                    ->when($chosenIds, fn ($q) => $q->whereNotIn('items.id', $chosenIds))
                    ->limit(self::ITEM_PICKER_LIMIT)
                    ->get()
            );

        $selectedIds = $request->data ? array_flip(array_map('strval', (array) $request->data)) : [];

        $options = [];
        if ($items->isNotEmpty() && ! $request->data) {
            $options[] = '<option value="0" disabled selected>'.translate('Select').'</option>';
        }

        foreach ($items as $row) {
            $storeName = $row->store?->name ?? '';

            $options[] = '<option value="'.e($row->id).'" '
                .(isset($selectedIds[(string) $row->id]) ? 'selected' : '').'>'
                .e($row->name).' ('.translate('stock') . ':'.' '.e($row->stock ?? 0).')'
                .($storeName ? ' ('.e($storeName).')' : '')
                .'</option>';
        }

        return response()->json([
            'options' => implode('', $options),
        ]);
    }

    public function list(Request $request)
    {
        $item_service = app(ItemService::class);
        $filters = $item_service->adminListFilters(array_merge($request->all(), ['module_id' => Config::get('module.current_module_id')]));
        $productWiseTax = Helpers::getTaxSystemType(getTaxVatList: false)['productWiseTax'];

        $items = $item_service->adminList($filters, ['page' => $request['page']], $productWiseTax);
        $summary = $item_service->adminListSummary($filters);
        $filter_count = $item_service->adminListFilterCount($filters);

        $store = $filters['store_id'] ? Store::with('storeConfig')->findOrFail($filters['store_id']) : null;
        $category = $filters['category_id'] ? Category::findOrFail($filters['category_id']) : null;
        $sub_category = $filters['sub_category_id'] ? Category::findOrFail($filters['sub_category_id']) : null;
        $condition = $filters['condition_id'] ? CommonCondition::findOrFail($filters['condition_id']) : null;
        $brand = $filters['brand_id'] ? Brand::findOrFail($filters['brand_id']) : null;

        $store_categories = (Helpers::storeCategoryStatus() && $filters['store_id'])
            ? \App\Models\StoreCategory::active()->where('store_id', $filters['store_id'])->orderBy('priority', 'desc')->get(['id', 'name'])
            : collect();

        $pending_requests = Helpers::get_business_settings('product_approval')
            ? TempProduct::withoutGlobalScope(StoreScope::class)->module(Config::get('module.current_module_id'))->count()
            : 0;

        return view('admin-views.product.list', compact('items', 'summary', 'filters', 'filter_count', 'store', 'category', 'sub_category', 'condition', 'brand', 'productWiseTax', 'store_categories', 'pending_requests'));
    }

    public function remove_image(Request $request)
    {

        if ($request?->temp_product) {
            $item = TempProduct::withoutGlobalScope(StoreScope::class)->find($request['id']);
        } else {
            $item = Item::withoutGlobalScope(StoreScope::class)->find($request['id']);
        }

        if (!$item) {
            Toastr::error(translate('No data found'));

            return back();
        }

        $array = [];
        if (count($item['images']) < 2) {
            Toastr::warning(translate('You cannot delete all images!'));

            return back();
        }

        Helpers::check_and_delete('product/', $request['name']);

        foreach ($item['images'] as $image) {
            if (is_array($image)) {
                if ($image['img'] != $request['name']) {
                    array_push($array, $image);
                }
            } else {
                if ($image != $request['name']) {
                    array_push($array, $image);
                }
            }
        }

        if ($request?->temp_product) {
            TempProduct::withoutGlobalScope(StoreScope::class)->where('id', $request['id'])->update([
                'images' => json_encode($array),
            ]);
        } else {
            Item::withoutGlobalScope(StoreScope::class)->where('id', $request['id'])->update([
                'images' => json_encode($array),
            ]);
        }
        Toastr::success(translate('Deleted successfully'));

        return back();
    }




    public function review_list(Request $request)
    {
        $review_service = app(ReviewService::class);
        $filters = $review_service->adminFilters(array_merge($request->all(), ['module_id' => Config::get('module.current_module_id')]));
        $reviews = $review_service->adminList($filters, ['page' => $request['page']]);
        $summary = $review_service->adminSummary($filters);
        $filter_count = $review_service->adminFilterCount($filters);

        return view('admin-views.product.reviews-list', compact('reviews', 'summary', 'filters', 'filter_count'));
    }

    public function reviews_status(Request $request)
    {
        $review = Review::find($request->id);
        $review->status = $request->status;
        $review->save();
        Toastr::success(translate('messages.Review visibility updated'));

        return back();
    }


    public function reviews_export(Request $request)
    {
        $review_service = app(ReviewService::class);
        $filters = $review_service->adminFilters(array_merge($request->all(), ['module_id' => Config::get('module.current_module_id')]));
        $reviews = $review_service->adminExportQuery($filters);

        $data_count = (clone $reviews)->count();

        $data = [
            'data' => $this->streamExportRows($reviews),
            'data_count' => $data_count,
            'search' => $request['search'] ?? null,
        ];
        $typ = 'Item';
        if (Config::get('module.current_module_type') == 'food') {
            $typ = 'Food';
        }
        if ($request->type == 'csv') {
            return Excel::download(new ItemReviewExport($data), $typ.'Review.csv');
        }

        return Excel::download(new ItemReviewExport($data), $typ.'Review.xlsx');
    }

    public function item_wise_reviews_export(Request $request)
    {
        $reviews = Review::where(['item_id' => $request->id])->latest()->get();
        $Item = Item::where('id', $request->id)->first()?->category_ids;
        $data = [
            'type' => 'single',
            'category' => \App\CentralLogics\Helpers::get_category_name($Item),
            'data' => $reviews,
            'search' => $request['search'] ?? null,
            'store' => $request['store'] ?? null,
        ];
        $typ = 'ItemWise';
        if (Config::get('module.current_module_type') == 'food') {
            $typ = 'FoodWise';
        }
        if ($request->type == 'csv') {
            return Excel::download(new ItemReviewExport($data), $typ.'Review.csv');
        }

        return Excel::download(new ItemReviewExport($data), $typ.'Review.xlsx');
    }

    public function bulk_import_index()
    {
        $module_type = Config::get('module.current_module_type');
        $summary = Helpers::bulkDataSummary(Item::withoutGlobalScope(StoreScope::class)->module(Config::get('module.current_module_id')));

        return view('admin-views.product.bulk-import', compact('module_type', 'summary'));
    }

    public function bulk_import_data(Request $request)
    {
        $request->validate([
            'products_file' => 'required|max:'.(MAX_FILE_SIZE * 1024),
        ]);
        $module_id = Config::get('module.current_module_id');
        $module_type = Config::get('module.current_module_type');

        try {
            $collections = (new FastExcel)->import($request->file('products_file'));
        } catch (\Exception $exception) {
            Toastr::error(translate('messages.You have uploaded a wrong format file'));

            return back();
        }
        if ($request->button == 'import') {
            $data = [];
            try {
                foreach ($collections as $collection) {
                    if ($collection['Id'] === '' || $collection['Name'] === '' || $collection['CategoryId'] === '' || $collection['SubCategoryId'] === '' || $collection['Price'] === '' || $collection['StoreId'] === '' || $collection['ModuleId'] === '' || $collection['Discount'] === '' || $collection['DiscountType'] === '') {
                        Toastr::error(translate('messages.Please fill all required fields'));

                        return back();
                    }
                    if (isset($collection['Price']) && ($collection['Price'] < 0)) {
                        Toastr::error(translate('messages.Price cannot be negative.') . ' ' .'ID'.': '.$collection['Id']);

                        return back();
                    }
                    if (isset($collection['Discount']) && ($collection['Discount'] < 0)) {
                        Toastr::error(translate('messages.Discount must be greater than zero').'. '.'ID'.': '.$collection['Id']);

                        return back();
                    }
                    if (data_get($collection, 'Image') != '' && strlen(data_get($collection, 'Image')) > 30) {
                        Toastr::error(translate('messages.Image name is too long.').' '.translate('messages.Character limit').': 30. '.'ID'.': '.$collection['Id']);

                        return back();
                    }
                    try {
                        $t1 = Carbon::parse($collection['AvailableTimeStarts']);
                        $t2 = Carbon::parse($collection['AvailableTimeEnds']);
                        if ($t1->gt($t2)) {
                            Toastr::error(translate('messages.AvailableTimeEnds must be greater then AvailableTimeStarts on id').' '.$collection['Id']);

                            return back();
                        }
                    } catch (\Exception $e) {
                        info(["line___{$e->getLine()}", $e->getMessage()]);
                        Toastr::error(translate('messages.Invalid AvailableTimeEnds or AvailableTimeStarts on id').' '.$collection['Id']);

                        return back();
                    }
                    array_push($data, [
                        'name' => $collection['Name'],
                        'description' => $collection['Description'],
                        'image' => $collection['Image'],
                        'images' => $collection['Images'] ?? json_encode([]),
                        'category_id' => $collection['SubCategoryId'] ? $collection['SubCategoryId'] : $collection['CategoryId'],
                        'category_ids' => json_encode([['id' => $collection['CategoryId'], 'position' => 1], ['id' => $collection['SubCategoryId'], 'position' => 2]]),
                        'unit_id' => is_int($collection['UnitId']) ? $collection['UnitId'] : null,
                        'stock' => is_numeric($collection['Stock']) ? abs($collection['Stock']) : 0,
                        'price' => $collection['Price'],
                        'discount' => $collection['Discount'],
                        'discount_type' => $collection['DiscountType'],
                        'available_time_starts' => $collection['AvailableTimeStarts'] ?? '00:00:00',
                        'available_time_ends' => $collection['AvailableTimeEnds'] ?? '23:59:59',
                        'variations' => $module_type == 'food' ? json_encode([]) : $collection['Variations'] ?? json_encode([]),
                        'choice_options' => $module_type == 'food' ? json_encode([]) : $collection['ChoiceOptions'] ?? json_encode([]),
                        'food_variations' => $module_type == 'food' ? $collection['Variations'] ?? json_encode([]) : json_encode([]),
                        'add_ons' => $collection['AddOns'] ? ($collection['AddOns'] == '' ? json_encode([]) : $collection['AddOns']) : json_encode([]),
                        'attributes' => $collection['Attributes'] ? ($collection['Attributes'] == '' ? json_encode([]) : $collection['Attributes']) : json_encode([]),
                        'store_id' => $collection['StoreId'],
                        'module_id' => $module_id,
                        'status' => $collection['Status'] == 'active' ? 1 : 0,
                        'veg' => $collection['Veg'] == 'yes' ? 1 : 0,
                        'recommended' => $collection['Recommended'] == 'yes' ? 1 : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                info(["line___{$e->getLine()}", $e->getMessage()]);
                Toastr::error($e->getMessage());

                return back();
            }
            try {
                DB::beginTransaction();
                $chunkSize = 100;
                $chunk_items = array_chunk($data, $chunkSize);
                foreach ($chunk_items as $key => $chunk_item) {
                    $syncItemIds = [];
                    // $data is built one-to-one from $collections -- any row failing
                    // validation aborts the whole import -- so the position within the
                    // chunk maps straight back to the row the sidecar values came from.
                    foreach ($chunk_item as $index => $item) {
                        $insertedId = DB::table('items')->insertGetId($item);
                        $syncItemIds[] = $insertedId;
                        Helpers::updateStorageTable(get_class(new Item), $insertedId, $item['image']);
                        if ($module_type === 'pharmacy') {
                            DB::table('pharmacy_item_details')->insert([
                                'item_id' => $insertedId,
                                'is_prescription_required' =>
                                    $collections[$key * $chunkSize + $index]['IsPrescriptionRequired'] ?? 0,
                                'common_condition_id' =>
                                    $collections[$key * $chunkSize + $index]['CommonConditions'] ?? 0,
                                'is_basic' =>
                                    $collections[$key * $chunkSize + $index]['IsBasic'] ?? 0,
                                'unit_value' =>
                                    $collections[$key * $chunkSize + $index]['UnitValue'] ?? null,
                                'manufacturer' =>
                                    $collections[$key * $chunkSize + $index]['Manufacturer'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        if (in_array($module_type, ['ecommerce', 'grocery'], true)) {
                            DB::table('ecommerce_item_details')->insert([
                                'item_id' => $insertedId,
                                'brand_id' =>
                                    $collections[$key * $chunkSize + $index]['BrandId'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                    // DB::table() writes fire no model events, so ItemObserver did not run
                    // for these rows and both derived columns would stay unset.
                    ItemObserver::syncDerived($syncItemIds);
                    app(BundleService::class)->repriceForItems($syncItemIds);
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                info(["line___{$e->getLine()}", $e->getMessage()]);
                Toastr::error($e->getMessage());

                return back();
            }
            Toastr::success(translate('messages.Product imported successfully'));

            return back();
        }
        $data = [];
        try {
            foreach ($collections as $collection) {
                if ($collection['Id'] === '' || $collection['Name'] === '' || $collection['CategoryId'] === '' || $collection['SubCategoryId'] === '' || $collection['Price'] === '' || $collection['StoreId'] === '' || $collection['ModuleId'] === '' || $collection['Discount'] === '' || $collection['DiscountType'] === '') {
                    Toastr::error(translate('messages.Please fill all required fields'));

                    return back();
                }
                if (isset($collection['Price']) && ($collection['Price'] < 0)) {
                    Toastr::error(translate('messages.Price cannot be negative.') . ' ' .'ID'.': '.$collection['Id']);

                    return back();
                }
                if (isset($collection['Discount']) && ($collection['Discount'] < 0)) {
                    Toastr::error(translate('messages.Discount must be greater than zero').'. '.'ID'.': '.$collection['Id']);

                    return back();
                }
                if (isset($collection['Discount']) && ($collection['Discount'] > 100)) {
                    Toastr::error(translate('messages.Maximum discount').': 100%. '.'ID'.': '.$collection['Id']);

                    return back();
                }
                if (data_get($collection, 'Image') != '' && strlen(data_get($collection, 'Image')) > 30) {
                    Toastr::error(translate('messages.Image name is too long.').' '.translate('messages.Character limit').': 30. '.'ID'.': '.$collection['Id']);

                    return back();
                }
                try {
                    $t1 = Carbon::parse($collection['AvailableTimeStarts']);
                    $t2 = Carbon::parse($collection['AvailableTimeEnds']);
                    if ($t1->gt($t2)) {
                        Toastr::error(translate('messages.AvailableTimeEnds must be greater then AvailableTimeStarts on id').' '.$collection['Id']);

                        return back();
                    }
                } catch (\Exception $e) {
                    info(["line___{$e->getLine()}", $e->getMessage()]);
                    Toastr::error(translate('messages.Invalid AvailableTimeEnds or AvailableTimeStarts on id').' '.$collection['Id']);

                    return back();
                }
                array_push($data, [
                    'id' => $collection['Id'],
                    'name' => $collection['Name'],
                    'description' => $collection['Description'],
                    'image' => $collection['Image'],
                    'images' => $collection['Images'] ?? json_encode([]),
                    'category_id' => $collection['SubCategoryId'] ? $collection['SubCategoryId'] : $collection['CategoryId'],
                    'category_ids' => json_encode([['id' => $collection['CategoryId'], 'position' => 1], ['id' => $collection['SubCategoryId'], 'position' => 2]]),
                    'unit_id' => is_int($collection['UnitId']) ? $collection['UnitId'] : null,
                    'stock' => is_numeric($collection['Stock']) ? abs($collection['Stock']) : 0,
                    'price' => $collection['Price'],
                    'discount' => $collection['Discount'],
                    'discount_type' => $collection['DiscountType'],
                    'available_time_starts' => $collection['AvailableTimeStarts'] ?? '00:00:00',
                    'available_time_ends' => $collection['AvailableTimeEnds'] ?? '23:59:59',
                    'variations' => $module_type == 'food' ? json_encode([]) : $collection['Variations'] ?? json_encode([]),
                    'choice_options' => $module_type == 'food' ? json_encode([]) : $collection['ChoiceOptions'] ?? json_encode([]),
                    'food_variations' => $module_type == 'food' ? $collection['Variations'] ?? json_encode([]) : json_encode([]),
                    'add_ons' => $collection['AddOns'] ? ($collection['AddOns'] == '' ? json_encode([]) : $collection['AddOns']) : json_encode([]),
                    'attributes' => $collection['Attributes'] ? ($collection['Attributes'] == '' ? json_encode([]) : $collection['Attributes']) : json_encode([]),
                    'store_id' => $collection['StoreId'],
                    'module_id' => $module_id,
                    'status' => $collection['Status'] == 'active' ? 1 : 0,
                    'veg' => $collection['Veg'] == 'yes' ? 1 : 0,
                    'recommended' => $collection['Recommended'] == 'yes' ? 1 : 0,
                    'updated_at' => now(),
                ]);
            }
            $id = $collections->pluck('Id')->toArray();
            if (Item::whereIn('id', $id)->doesntExist()) {
                Toastr::error(translate('messages.Item doesnt exist at the database'));

                return back();
            }
        } catch (\Exception $e) {
            info(["line___{$e->getLine()}", $e->getMessage()]);
            Toastr::error($e->getMessage());

            return back();
        }
        try {
            DB::beginTransaction();
            $chunkSize = 100;
            $chunk_items = array_chunk($data, $chunkSize);
            foreach ($chunk_items as $key => $chunk_item) {
                $syncItemIds = [];
                // $data is built one-to-one from $collections -- any row failing
                // validation aborts the whole import -- so the position within the
                // chunk maps straight back to the row the sidecar values came from.
                foreach ($chunk_item as $index => $item) {
                    if (isset($item['id']) && DB::table('items')->where('id', $item['id'])->exists()) {
                        DB::table('items')->where('id', $item['id'])->update($item);
                        $syncItemIds[] = $item['id'];
                        Helpers::updateStorageTable(get_class(new Item), $item['id'], $item['image']);
                    } else {
                        $insertedId = DB::table('items')->insertGetId($item);
                        $syncItemIds[] = $insertedId;
                        Helpers::updateStorageTable(get_class(new Item), $insertedId, $item['image']);
                    }

                    if (in_array($module_type, ['ecommerce', 'grocery'], true)) {
                        $brandId = $collections[$key * $chunkSize + $index]['BrandId'] ?? null;
                        DB::table('ecommerce_item_details')->updateOrInsert(
                            ['item_id' => $item['id']],
                            [
                                'brand_id' => $brandId,
                                'updated_at' => now(),
                            ]
                        );
                    }

                    if ($module_type === 'pharmacy') {

                        $isPrescriptionRequired = $collections[$key * $chunkSize + $index]['IsPrescriptionRequired'] ?? 0;
                        $commonConditionId = $collections[$key * $chunkSize + $index]['CommonConditions'] ?? 0;
                        $isBasic = $collections[$key * $chunkSize + $index]['IsBasic'] ?? 0;
                        $unitValue = $collections[$key * $chunkSize + $index]['UnitValue'] ?? null;
                        $manufacturer = $collections[$key * $chunkSize + $index]['Manufacturer'] ?? null;
                        if (
                            DB::table('pharmacy_item_details')
                                ->where('item_id', $item['id'])
                                ->exists()
                        ) {
                            DB::table('pharmacy_item_details')
                                ->where('item_id', $item['id'])
                                ->update([
                                    'is_prescription_required' => $isPrescriptionRequired,
                                    'common_condition_id' => $commonConditionId,
                                    'is_basic' => $isBasic,
                                    'unit_value' => $unitValue,
                                    'manufacturer' => $manufacturer,
                                    'updated_at' => now(),
                                ]);
                        } else {
                            DB::table('pharmacy_item_details')
                                ->insert([
                                    'item_id' => $item['id'],
                                    'is_prescription_required' => $isPrescriptionRequired,
                                    'common_condition_id' => $commonConditionId,
                                    'is_basic' => $isBasic,
                                    'unit_value' => $unitValue,
                                    'manufacturer' => $manufacturer,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                        }
                    }
                }
                // DB::table() writes fire no model events, so ItemObserver did not run
                // for these rows and both derived columns would stay unset.
                ItemObserver::syncDerived($syncItemIds);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            info(["line___{$e->getLine()}", $e->getMessage()]);
            Toastr::error($e->getMessage());

            return back();
        }
        Toastr::success(translate('messages.Product imported successfully'));

        return back();
    }

    public function bulk_export_index()
    {
        return view('admin-views.product.bulk-export', [
            'summary' => Helpers::bulkDataSummary(Item::withoutGlobalScope(StoreScope::class)->module(Config::get('module.current_module_id'))),
        ]);
    }

    public function bulk_export_data(Request $request)
    {
        $request->validate([
            'type' => 'required',
            'start_id' => 'required_if:type,id_wise',
            'end_id' => 'required_if:type,id_wise',
            'from_date' => 'required_if:type,date_wise',
            'to_date' => 'required_if:type,date_wise',
        ]);
        $module_type = Config::get('module.current_module_type');
        $products = Item::when($request['type'] == 'date_wise', function ($query) use ($request) {
            $query->whereBetween('created_at', [$request['from_date'].' 00:00:00', $request['to_date'].' 23:59:59']);
        })
            ->when($request['type'] == 'id_wise', function ($query) use ($request) {
                $query->whereBetween('id', [$request['start_id'], $request['end_id']]);
            })
            ->module(Config::get('module.current_module_id'))
            ->withoutGlobalScope(StoreScope::class)->get();

        return (new FastExcel(self::formatExportItems(Helpers::Export_generator($products), $module_type)))->download('Items.xlsx');
    }

    public function get_variations(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->find($request['id']);

        if (! $product) {
            return response()->json(['errors' => [['code' => 'item', 'message' => translate('No data found')]]], 404);
        }

        return response()->json([
            'view' => view('admin-views.product.partials._get_stock_data', compact('product'))->render(),
        ]);
    }

    public function get_stock(Request $request)
    {
        $product = Item::withoutGlobalScope(StoreScope::class)->find($request['id']);

        if (! $product) {
            return response()->json(['errors' => [['code' => 'item', 'message' => translate('No data found')]]], 404);
        }

        return response()->json([
            'view' => view('admin-views.product.partials._get_stock_data', compact('product'))->render(),
        ]);
    }

    public function stock_update(Request $request)
    {
        $variations = [];
        $stock_count = $request['current_stock'];
        if ($request->has('type')) {
            foreach ($request['type'] as $key => $str) {
                $item = [];
                $item['type'] = $str;
                $item['price'] = abs($request['price_'.$key.'_'.str_replace('.', '_', $str)]);
                $item['stock'] = abs($request['stock_'.$key.'_'.str_replace('.', '_', $str)]);
                array_push($variations, $item);
            }
        }

        $product = Item::withoutGlobalScope(StoreScope::class)->find($request['product_id']);

        $product->stock = $stock_count ?? 0;
        $product->variations = json_encode($variations);
        $product->save();
        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function search_vendor(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        if ($request->has('store_id')) {

            $foods = Item::withoutGlobalScope(StoreScope::class)
                ->where('store_id', $request->store_id)
                ->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    }
                })->limit(50)->get();

            return response()->json([
                'count' => count($foods),
                'view' => view('admin-views.vendor.view.partials._product', compact('foods'))->render(),
            ]);
        }
        $foods = Item::withoutGlobalScope(StoreScope::class)->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->where('name', 'like', "%{$value}%");
            }
        })->limit(50)->get();

        return response()->json([
            'count' => count($foods),
            'view' => view('admin-views.vendor.view.partials._product', compact('foods'))->render(),
        ]);
    }

    public function store_item_export(Request $request)
    {
        $key = explode(' ', request()->search ?? '');
        $model = app('\\App\\Models\\Item');
        if ($request?->table && $request?->table == 'TempProduct') {
            $model = app('\\App\\Models\\TempProduct');
        }

        $foods = $model->withoutGlobalScope(StoreScope::class)->where('store_id', $request->store_id)
            ->when(request()->search, function ($q) use ($key) {
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    }
                });
            })
            ->when($request?->sub_tab == 'active-items', function ($q) {
                $q->where('status', 1);
            })
            ->when($request?->sub_tab == 'inactive-items', function ($q) {
                $q->where('status', 0);
            })
            ->when($request?->sub_tab == 'pending-items', function ($q) {
                $q->where('is_rejected', 0);
            })
            ->when($request?->sub_tab == 'rejected-items', function ($q) {
                $q->where('is_rejected', 1);
            })
            ->latest()->get();


        $store = Store::where('id', $request->store_id)->select(['name', 'zone_id'])->first();

        if (!$store) {
            Toastr::error(translate('No data found'));

            return back();
        }

        $typ = 'Item';
        if (Config::get('module.current_module_type') == 'food') {
            $typ = 'Food';
        }

        $taxData = Helpers::getTaxSystemType();
        $productWiseTax = $taxData['productWiseTax'];
        $data = [
            'sub_tab' => $request?->sub_tab,
            'data' => $foods,
            'search' => $request['search'] ?? null,
            'zone' => Helpers::get_zones_name($store->zone_id),
            'store_name' => $store->name,
            'productWiseTax' => $productWiseTax,
        ];
        if ($request->type == 'csv') {
            return Excel::download(new StoreItemExport($data), $typ.'List.csv');
        }

        return Excel::download(new StoreItemExport($data), $typ.'List.xlsx');

    }

    public function export(Request $request)
    {
        $store_id = $request->query('store_id', 'all');
        $category_id = $request->query('category_id', 'all');
        $sub_category_id = $request->query('sub_category_id', 'all');
        $zone_id = $request->query('zone_id', 'all');
        $filter = $request->query('filter', 'all');
        $from = $request->query('from');
        $to = $request->query('to');

        $type = $request->query('type', 'all');

        if ($request?->table && $request?->table == 'TempProduct') {
            $temp_product_service = app(TempProductService::class);
            $approval_filters = $temp_product_service->adminApprovalFilters(array_merge($request->all(), ['module_id' => Config::get('module.current_module_id')]));
            $item = $temp_product_service->adminApprovalExportQuery($approval_filters);

            $store_id = $approval_filters['store_id'] ?? 'all';
            $category_id = $approval_filters['category_id'] ?? 'all';
            $zone_id = $approval_filters['zone_id'] ?? 'all';
            $from = $approval_filters['from_date'];
            $to = $approval_filters['to_date'];
            $filter = count($approval_filters['status']) === 1 ? $approval_filters['status'][0] : ($from && $to ? 'custom' : 'all');
        } else {
            $item_service = app(ItemService::class);
            $item = $item_service->adminListExportQuery(
                $item_service->adminListFilters(array_merge($request->all(), ['module_id' => Config::get('module.current_module_id')]))
            );
        }

        $item_count = (clone $item)->count();
        $item = $this->streamExportRows($item);

        $format_type = 'Item';
        if (Config::get('module.current_module_type') == 'food') {
            $format_type = 'Food';
        }

        $taxData = Helpers::getTaxSystemType();
        $productWiseTax = $taxData['productWiseTax'];

        $data = [
            'table' => $request?->table,
            'data' => $item,
            'data_count' => $item_count,
            'search' => $request['search'] ?? null,
            'store' => $store_id != 'all' ? Store::findOrFail($store_id)?->name : null,
            'category' => $category_id != 'all' ? Category::findOrFail($category_id)?->name : null,
            'module_name' => Helpers::get_module_name(Config::get('module.current_module_id')),
            'productWiseTax' => $productWiseTax,
            'zone' => $zone_id != 'all' ? Zone::find($zone_id)?->name : null,
            'filter' => $filter,
            'from' => $from,
            'to' => $to

        ];
        if ($request->type == 'csv') {
            return Excel::download(new ItemListExport($data), $format_type.'List.csv');
        }

        return Excel::download(new ItemListExport($data), $format_type.'List.xlsx');

    }

    public function search_store(Request $request, $store_id)
    {
        $key = explode(' ', $request['search'] ?? '');
        $foods = Item::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store_id)
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->where('name', 'like', "%{$value}%");
                }
            })->limit(50)->get();

        return response()->json([
            'count' => count($foods),
            'view' => view('admin-views.vendor.view.partials._product', compact('foods'))->render(),
        ]);
    }

    public function food_variation_generator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'options' => 'required',
        ]);

        $food_variations = [];
        if (isset($request->options)) {
            foreach (array_values($request->options) as $key => $option) {

                $temp_variation['name'] = $option['name'];
                $temp_variation['type'] = $option['type'];
                $temp_variation['min'] = $option['min'] ?? 0;
                $temp_variation['max'] = $option['max'] ?? 0;
                $temp_variation['required'] = $option['required'] ?? 'off';
                if ($option['min'] > 0 && $option['min'] > $option['max']) {
                    $validator->getMessageBag()->add('name', translate('messages.Minimum value can not be greater then maximum value'));

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                if (! isset($option['values'])) {
                    $validator->getMessageBag()->add('name', translate('messages.Please add options for').$option['name']);

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                if ($option['max'] > count($option['values'])) {
                    $validator->getMessageBag()->add('name', translate('messages.Please add more options or change the max value for').$option['name']);

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $temp_value = [];

                foreach (array_values($option['values']) as $value) {
                    if (isset($value['label'])) {
                        $temp_option['label'] = $value['label'];
                    }
                    $temp_option['optionPrice'] = $value['optionPrice'];
                    array_push($temp_value, $temp_option);
                }
                $temp_variation['values'] = $temp_value;
                array_push($food_variations, $temp_variation);
            }
        }

        return response()->json([
            'variation' => json_encode($food_variations),
        ]);
    }

    public function variation_generator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'choice' => 'required',
        ]);
        $choice_options = [];
        if ($request->has('choice')) {
            foreach ($request->choice_no as $key => $no) {
                $str = 'choice_options_'.$no;
                if ($request[$str][0] == null) {
                    $validator->getMessageBag()->add('name', translate('messages.Attribute choice option value can not be null'));

                    return response()->json(['errors' => Helpers::error_processor($validator)]);
                }
                $temp['name'] = 'choice_'.$no;
                $temp['title'] = $request->choice[$key];
                $temp['options'] = explode(',', implode('|', preg_replace('/\s+/', ' ', $request[$str])));
                array_push($choice_options, $temp);
            }
        }

        $variations = [];
        $options = [];
        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_'.$no;
                $my_str = implode('|', $request[$name]);
                array_push($options, explode(',', $my_str));
            }
        }
        $combinations = Helpers::combinations($options);
        if (count($combinations[0]) > 0) {
            foreach ($combinations as $key => $combination) {
                $str = '';
                foreach ($combination as $k => $temp) {
                    if ($k > 0) {
                        $str .= '-'.str_replace(' ', '', $temp);
                    } else {
                        $str .= str_replace(' ', '', $temp);
                    }
                }
                $temp = [];
                $temp['type'] = $str;
                $temp['price'] = abs($request['price_'.str_replace('.', '_', $str)]);
                $temp['stock'] = abs($request['stock_'.str_replace('.', '_', $str)]);
                array_push($variations, $temp);
            }
        }

        return response()->json([
            'choice_options' => json_encode($choice_options),
            'variation' => json_encode($variations),
            'attributes' => $request->has('attribute_id') ? json_encode($request->attribute_id) : json_encode([]),
        ]);
    }

    public function approval_list(Request $request)
    {
        abort_if(Helpers::get_business_settings('product_approval') != 1, 404);

        $temp_product_service = app(TempProductService::class);
        $filters = $temp_product_service->adminApprovalFilters(array_merge($request->all(), ['module_id' => Config::get('module.current_module_id')]));

        $items = $temp_product_service->adminApprovalList($filters, ['page' => $request['page']]);
        $summary = $temp_product_service->adminApprovalSummary($filters);
        $filter_count = $temp_product_service->adminApprovalFilterCount($filters);

        $store = $filters['store_id'] ? Store::with('storeConfig')->findOrFail($filters['store_id']) : null;
        $category = $filters['category_id'] ? Category::findOrFail($filters['category_id']) : null;
        $sub_category = $filters['sub_category_id'] ? Category::findOrFail($filters['sub_category_id']) : null;

        return view('admin-views.product.approv_list', compact('items', 'summary', 'filters', 'filter_count', 'store', 'category', 'sub_category'));
    }

    public function requested_item_view($id)
    {
        $product = TempProduct::withoutGlobalScope(StoreScope::class)->withoutGlobalScope('translate')
            ->with(['translations', 'store.zone', 'store.storage', 'unit', 'module.translations', 'category.parent', 'storage'])
            ->findOrFail($id);

        $temp_product_service = app(TempProductService::class);
        $is_update = $temp_product_service->replacesLiveItem($product);
        $live_item = $temp_product_service->liveItemFor($product);
        $changes = $temp_product_service->approvalChanges($product, $live_item);

        return view('admin-views.product.requested_product_view', compact('product', 'live_item', 'changes', 'is_update'));
    }

    public function deny(Request $request)
    {
        $data = TempProduct::withoutGlobalScope(StoreScope::class)->findOrfail($request->id);
        $data->is_rejected = 1;
        $data->note = $request->note;
        $data->save();
        Toastr::success(translate('messages.Product denied'));

        try {

            if (SendNotification::channelEnabled('store', 'store_product_reject', 'push_notification_status', $data?->store->id) && $data?->store?->vendor?->firebase_token) {
                $ndata = NotificationMessages::productRejected();
                SendNotification::pushToVendor($data?->store?->vendor_id, $data?->store?->vendor?->firebase_token, $ndata);
            }


            if (SendNotification::canSendMail('product_deny_mail_status_store', 'store', 'store_product_reject', $data?->store?->id)) {
                SendNotification::mail($data?->store?->vendor?->getRawOriginal('email'), new \App\Mail\VendorProductMail($data?->store?->name, 'denied'));
            }
        } catch (\Exception $e) {
            Log::error('admin.item_controller.deny_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }

        return to_route('admin.item.approval_list');
    }

    public function approved(Request $request)
    {
        $data = TempProduct::withoutGlobalScope(StoreScope::class)->findOrfail($request->id);

        $item = Item::withoutGlobalScope(StoreScope::class)->withoutGlobalScope('translate')->with('translations')->findOrfail($data->item_id);

        $item->name = $data->name;
        $item->description = $data->description;

        $oldItemVideo = $item->video;

        if ($item->image && $data->image != null && $item->image !== $data->image) {
            Helpers::check_and_delete('product/', $item['image']);
        }

        foreach ($item->images as $value) {
            $value = is_array($value) ? $value : ['img' => $value, 'storage' => 'public'];
            Helpers::check_and_delete('product/', $value['img']);
        }

        $item->image = $data->image;
        $item->video = $data->video;
        $item->video_link = $data->video_link;
        $item->images = $data->images;
        $item->store_id = $data->store_id;
        $item->module_id = $data->module_id;
        $item->unit_id = $data->unit_id;

        $item->category_id = $data->category_id;
        $item->category_ids = $data->category_ids;
        $item->store_category_id = $data->store_category_id;

        $item->choice_options = $data->choice_options;
        $item->food_variations = $data->food_variations;
        $item->variations = $data->variations;
        $item->add_ons = $data->add_ons;
        $item->attributes = $data->attributes;

        $item->price = $data->price;
        $item->discount = $data->discount;
        $item->discount_type = $data->discount_type;

        $item->available_time_starts = $data->available_time_starts;
        $item->available_time_ends = $data->available_time_ends;
        $item->maximum_cart_quantity = $data->maximum_cart_quantity;
        $item->veg = $data->veg;

        $item->organic = $data->organic;
        $item->is_halal = $data->is_halal;
        $item->stock = $data->stock;
        $item->is_approved = 1;

        $item->save();
        if ($oldItemVideo && $oldItemVideo !== $item->video) {
            Helpers::check_and_delete('product/', $oldItemVideo);
        }
        $item->tags()->sync(json_decode($data->tag_ids));
        $item->nutritions()->sync(json_decode($data->nutrition_ids));
        $item->allergies()->sync(json_decode($data->allergy_ids));
        $item->generic()->sync(json_decode($data->generic_ids));

        $item?->pharmacy_item_details()?->delete();
        $item?->seoData()?->delete();

        if ($item->module->module_type == 'pharmacy') {
            DB::table('pharmacy_item_details')->where('temp_product_id', $data->id)->update([
                'item_id' => $item->id,
                'temp_product_id' => null,
            ]);
        }
        if (in_array($item->module->module_type, ['ecommerce', 'grocery'])) {
            DB::table('ecommerce_item_details')->where('temp_product_id', $data->id)->update([
                'item_id' => $item->id,
                'temp_product_id' => null,
            ]);
        }
        if ($item->module->module_type == 'ecommerce') {
            DB::table('item_seo_data')->where('temp_item_id', $data->id)->update([
                'item_id' => $item->id,
                'temp_item_id' => null,
            ]);

        }

        $item?->translations()?->delete();
        $item?->taxVats()?->delete();
        if (addon_published_status('TaxModule')) {
            $SystemTaxVat = \Modules\TaxModule\Entities\SystemTaxSetup::where('is_active', 1)->where('is_default', 1)->first();
            if ($SystemTaxVat?->tax_type == 'product_wise') {
                \Modules\TaxModule\Entities\Taxable::where('taxable_type', 'App\Models\TempProduct')->where('taxable_id', $data->id)
                    ->update(['taxable_type' => 'App\Models\Item', 'taxable_id' => $item->id]);
            }
        }

        Translation::where('translationable_type', 'App\Models\TempProduct')->where('translationable_id', $data->id)->update([
            'translationable_type' => 'App\Models\Item',
            'translationable_id' => $item->id,
        ]);

        $data->delete();

        try {

            if (SendNotification::channelEnabled('store', 'store_product_approve', 'push_notification_status', $item?->store->id) && $item?->store?->vendor?->firebase_token) {
                $data = NotificationMessages::productApproved();
                SendNotification::pushToVendor($item?->store?->vendor_id, $item?->store?->vendor?->firebase_token, $data);
            }


            if (SendNotification::canSendMail('product_approve_mail_status_store', 'store', 'store_product_approve', $item?->store?->id)) {
                SendNotification::mail($item?->store?->vendor?->getRawOriginal('email'), new \App\Mail\VendorProductMail($item?->store?->name, 'approved'));
            }
        } catch (\Exception $e) {
            Log::error('admin.item_controller.approved_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }
        Toastr::success(translate('messages.Product approved'));

        return to_route('admin.item.approval_list');
    }

    public function product_gallery(Request $request)
    {
        $store_id = $request->query('store_id', 'all');
        $category_id = $request->query('category_id', 'all');
        $type = $request->query('type', 'all');

        $items = Item::withoutGlobalScope(StoreScope::class)
            ->withStorage()->with(['module', 'category.parent', 'unit', 'tags'])
            ->when($request->query('module_id', null), function ($query) use ($request) {
                return $query->module($request->query('module_id'));
            })
            ->when(is_numeric($store_id), function ($query) use ($store_id) {
                return $query->where('store_id', $store_id);
            })
            ->when(is_numeric($category_id), function ($query) use ($category_id) {
                return $query->whereHas('category', function ($q) use ($category_id) {
                    return $q->whereId($category_id)->orWhere('parent_id', $category_id);
                });
            })->search($request['search'])
            ->where('is_approved', 1)
            ->module(Config::get('module.current_module_id'))
            ->type($type)
            ->latest()->paginate(12);

        $store = $store_id != 'all' ? Store::with('storeConfig')->findOrFail($store_id) : null;
        $category = $category_id != 'all' ? Category::findOrFail($category_id) : null;

        return view('admin-views.product.product_gallery', compact('items', 'store', 'category', 'type'));
    }

    private function productVideoValidationRules(): array
    {
        return [
            'video_upload_type' => 'nullable|in:file,link',
            'video' => VideoFile::rules('nullable', $this->productVideoMaxSizeKb()),
            'video_link' => ['nullable', $this->videoLinkRule()],
            'remove_video' => 'nullable|in:0,1',
        ];
    }

    private function productVideoMaxSizeKb(): int
    {
        return Helpers::productVideoMaxUploadSizeMb() * 1024;
    }

    private function videoLinkRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (! $value) {
                return;
            }

            if (! filter_var($value, FILTER_VALIDATE_URL)) {
                $fail('Please enter a valid video link.');
                return;
            }

            $scheme = strtolower(parse_url($value, PHP_URL_SCHEME) ?? '');
            if (! in_array($scheme, ['http', 'https'])) {
                $fail('Please enter a valid video link.');
            }
        };
    }

    private function getRequestedVideoType(Request $request, ?string $video = null, ?string $videoLink = null): string
    {
        if ($request->video_upload_type) {
            return $request->video_upload_type;
        }

        return $videoLink ? 'link' : 'file';
    }

    private function normalizeVideoLink(?string $videoLink): ?string
    {
        $videoLink = trim((string) $videoLink);

        return $videoLink !== '' ? $videoLink : null;
    }

    private function resolvePersistedVideoData(Request $request, ?string $currentVideo = null, ?string $currentVideoLink = null): array
    {
        $type = $this->getRequestedVideoType($request, $currentVideo, $currentVideoLink);

        if ($type === 'link') {
            return [
                'video' => null,
                'video_link' => $this->normalizeVideoLink($request->video_link),
            ];
        }

        if ($request->hasFile('video')) {
            return [
                'video' => $currentVideo
                    ? Helpers::update('product/', $currentVideo, 'mp4', $request->file('video'), Helpers::productVideoMaxUploadSizeMb(), VIDEO_EXTENSION)
                    : Helpers::upload('product/', 'mp4', $request->file('video'), Helpers::productVideoMaxUploadSizeMb(), VIDEO_EXTENSION),
                'video_link' => null,
            ];
        }

        if ((int) $request->input('remove_video', 0) === 1) {
            return [
                'video' => null,
                'video_link' => null,
            ];
        }

        return [
            'video' => $currentVideo,
            'video_link' => null,
        ];
    }

    private function resolveCreateVideoData(Request $request, ?Item $gallerySourceItem = null): array
    {
        if (! $gallerySourceItem || ! $request->item_id || $request?->product_gellary != 1) {
            return $this->resolvePersistedVideoData($request);
        }

        if ($request->hasFile('video') || (int) $request->input('remove_video', 0) === 1) {
            return $this->resolvePersistedVideoData($request);
        }

        if ($request->video_upload_type === 'link' && $this->normalizeVideoLink($request->video_link)) {
            return $this->resolvePersistedVideoData($request);
        }

        $galleryVideoData = Helpers::duplicateProductVideoData($gallerySourceItem);

        return $this->resolvePersistedVideoData($request, $galleryVideoData['video'], $galleryVideoData['video_link']);
    }

    private function resolvePromotedVideoData(Request $request, ?TempProduct $tempProduct): array
    {
        if (! $tempProduct) {
            return $this->resolvePersistedVideoData($request);
        }

        return $this->resolvePersistedVideoData($request, $tempProduct->video, $tempProduct->video_link);
    }

    private function addOrUpdateMetaData(Request $request, $item_id)
    {
        $itemMetaData = ItemSeoData::updateOrCreate([
            'item_id' => $item_id,
        ]);

        $imageFile = $request->hasFile('meta_image') ? $request->file('meta_image') : $itemMetaData->image;
        $originalExtension = $request->hasFile('meta_image') ? $imageFile->getClientOriginalExtension() : 'png';
        if ($request->has('meta_image_deleted') && $request->meta_image_deleted == 1) {
            Helpers::check_and_delete('item_meta_data/', $itemMetaData->image);
            $itemMetaData->image = null;
        }

        $itemMetaData->title = $request->meta_title;
        $itemMetaData->description = $request->meta_description;
        $itemMetaData->image = $request->file('meta_image') ? Helpers::upload(dir: 'item_meta_data/', format: $originalExtension, image: $imageFile) : $itemMetaData->image;
        $itemMetaData->meta_data = Helpers::formatMetaData($request->all(), $itemMetaData->meta_data);

        $itemMetaData->save();

        return true;
    }


    public function gallery_item_view(Request $request, $id)
    {
        $item = Item::withoutGlobalScope(StoreScope::class)->withStorage()->with(['category.parent', 'module', 'unit', 'tags'])->find($id);

        return response()->json([
            'view' => view('admin-views.product.partials._view_gallery_item', compact('item'))->render(),
        ]);
    }


}
