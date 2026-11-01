<?php

namespace App\Http\Controllers\Vendor;

use App\Rules\PhoneNumber;
use App\Rules\EmailAddress;
use App\Traits\Item\ItemStockTrait;
use App\Models\Item;
use App\Models\User;
use App\Models\Order;
use App\Mail\PlaceOrder;
use App\Models\AddOn;
use App\Models\Category;
use App\Models\DMVehicle;
use App\Scopes\StoreScope;
use App\Models\OrderDetail;
use App\Traits\Order\PlaceNewOrderTrait;
use App\Traits\Order\POSDeliveryTypeTrait;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use  Illuminate\Support\Facades\Session;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\Log;
use App\Services\Zone\DeliveryRuleService;
class POSController extends Controller
{
    use ItemStockTrait;

    use PlaceNewOrderTrait;
    use POSDeliveryTypeTrait;

    private const ITEM_DETAIL_RELATIONS = [
        'module',
        'nutritions',
        'allergies',
        'generic',
        'storage',
    ];

    public function getDeliveryTypes(Request $request)
    {
        $store    = Helpers::get_store_data();
        $moduleId = (int) ($store?->module_id ?? 0);
        $zoneId   = (int) ($store?->zone_id ?? 0);
        $selfDelivery = $store ? (bool) $store->sub_self_delivery : null;

        $deliveryFee = $request->filled('delivery_fee')
            ? (float) $request->query('delivery_fee')
            : (float) (session('address.delivery_fee') ?? 0);

        return response()->json($this->loadDeliveryTypes($moduleId, $zoneId, $deliveryFee, $selfDelivery, $store?->delivery_time));
    }

    public function setDeliveryType(Request $request)
    {
        $this->storeDeliveryType($request);
        return response()->json(['success' => true]);
    }
    public function index(Request $request)
    {
        if (Helpers::get_store_data()?->module_type == 'service') {
            abort(404);
        }
        $category = $request->query('category_id', 0);
        $categories = Category::active()->module(Helpers::get_store_data()->module_id)->get();
        $search = $request->query('search', false);
        $key = explode(' ', $search ?? '');
        $products = Item::active()->withStorage()->where('store_id', Helpers::get_store_id())
        ->when($category, function($query)use($category){
            $query->whereHas('category',function($q)use($category){
                return $q->whereId($category)->orWhere('parent_id', $category);
            });
        })
        ->when($search, function($query)use($key){
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            });
        })
        ->latest()->paginate(10);

        if ($request->session()->has('cart') && $this->cartHasOtherStoreItems($request->session()->get('cart'))) {
            session()->forget([
                'cart',
                'tax_amount',
                'tax_included',
                'address',
                'cart_product_ids',
                'customer_id',
            ]);
        }

        $hasNavParams = $request->filled('search')
            || $request->filled('category_id')
            || $request->filled('page');

        if (!$hasNavParams && (empty(session('cart')) || count(session('cart')) === 0)) {
            session()->forget([
                'tax_amount',
                'tax_included',
                'customer_id',
                'address',
                'delivery_type',
                'delivery_type_charge',
                'pos_pro_discount',
                'pos_pro_benefit_type',
                'pos_pro_delivery_offer_type',
                'pos_pro_delivery_percentage',
                'pos_pro_min_order_amount',
                'pos_pro_min_order_status',
            ]);
        }

        $customer = null;
        if (Session::get('customer_id')) {
            $customer = User::withStorage()->find(Session::get('customer_id'));
        }

        $last_order = session('last_order') ? Order::find(session('last_order')) : null;
        if ($last_order) {
            session(['last_order' => false]);
        }

        $address_view = $this->pos_address_view_data(Session::get('address'));

        return view('vendor-views.pos.index', compact('categories', 'products', 'category', 'search', 'customer', 'last_order', 'address_view'));
    }

    public function single_items(Request $request)
    {
        $category = $request->category_id ?? 0;
        $search = $request->input('search', $request->input('keyword')) ?: false;
        $key = explode(' ', (string) $search);
        $per_page = min(max((int) $request->input('per_page', 10), 10), 200);

        $products = Item::active()->withStorage()->where('store_id', Helpers::get_store_id())
        ->when($category, function($query)use($category){
            $query->whereHas('category',function($q)use($category){
                return $q->whereId($category)->orWhere('parent_id', $category);
            });
        })
        ->when($search, function($query)use($key){
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            });
        })
        ->latest()->paginate($per_page);

        return view('vendor-views.pos._single_product_list', compact('products'));
    }

    public function quick_view(Request $request)
    {
        $product = $this->storeItem($request->product_id, self::ITEM_DETAIL_RELATIONS);
        $selected_item = ['id' => $product->id];

        if ($product->module->module_type != 'food') {
            foreach (json_decode($product->choice_options) ?: [] as $choice) {
                $selected_item[$choice->name] = $choice->options[0] ?? null;
            }
        }

        $stock = $this->get_stocks($product, $selected_item);
        $addons = AddOn::whereIn('id', json_decode($product->add_ons) ?: [])->active()->get();
        $store_discount = Helpers::get_store_discount($product->store);

        return response()->json([
            'success' => 1,
            'view' => view('vendor-views.pos._item-stock-view', compact('product', 'selected_item', 'stock', 'addons', 'store_discount'))->render(),
        ]);
    }

    public function quick_view_card_item(Request $request)
    {
        $product = $this->storeItem($request->product_id, self::ITEM_DETAIL_RELATIONS);
        $item_key = $request->item_key;
        $cart_item = $this->cartLine($item_key);

        if (!$cart_item) {
            return response()->json([
                'success' => 0,
                'message' => translate('messages.This item is no longer in the cart'),
            ]);
        }

        $addons = AddOn::whereIn('id', json_decode($product->add_ons) ?: [])->active()->get();
        $store_discount = Helpers::get_store_discount($product->store);

        return response()->json([
            'success' => 1,
            'view' => view('vendor-views.pos._quick-view-cart-item', compact('product', 'cart_item', 'item_key', 'addons', 'store_discount'))->render(),
        ]);
    }

    public function item_stock_view(Request $request)
    {
        $product = $this->storeItem($request->id, self::ITEM_DETAIL_RELATIONS);
        $selected_item = $request->all();
        $stock = $this->get_stocks($product, $selected_item);
        $addons = AddOn::whereIn('id', json_decode($product->add_ons) ?: [])->active()->get();
        $store_discount = Helpers::get_store_discount($product->store);

        return response()->json([
            'view' => view('vendor-views.pos._item-stock-view', compact('product', 'selected_item', 'stock', 'addons', 'store_discount'))->render(),
        ]);
    }

    public function item_stock_view_update(Request $request)
    {
        $product = $this->storeItem($request->id, self::ITEM_DETAIL_RELATIONS);
        $selected_item = $request->all();
        $item_key = $request->cart_item_key;
        $cart_item = $this->cartLine($item_key);

        if (!$cart_item) {
            return response()->json([
                'success' => 0,
                'message' => translate('messages.This item is no longer in the cart'),
            ]);
        }

        $stock = $this->get_stocks($product, $selected_item);
        $addons = AddOn::whereIn('id', json_decode($product->add_ons) ?: [])->active()->get();
        $store_discount = Helpers::get_store_discount($product->store);

        return response()->json([
            'success' => 1,
            'view' => view('vendor-views.pos._quick-view-cart-item', compact('product', 'cart_item', 'item_key', 'stock', 'selected_item', 'addons', 'store_discount'))->render(),
        ]);
    }

    private function cartLine($item_key): ?array
    {
        if ($item_key === null || $item_key === '') {
            return null;
        }

        $cart = session()->get('cart');
        $line = $cart instanceof \Illuminate\Support\Collection
            ? $cart->get($item_key)
            : ($cart[$item_key] ?? null);

        return is_array($line) ? $line : null;
    }

    private function storeItem($id, array $relations = []): Item
    {
        $store = Helpers::get_store_data();

        $product = Item::with($relations)->where('store_id', $store?->id)->findOrFail($id);
        $product->setRelation('store', $store);

        return $product;
    }

    private function cartHasOtherStoreItems($cart): bool
    {
        $cart_item_ids = collect($cart)
            ->filter(fn ($cart_item) => is_array($cart_item))
            ->pluck('id')
            ->filter()
            ->unique();

        return $cart_item_ids->isNotEmpty()
            && Item::whereIn('id', $cart_item_ids)
                ->where('store_id', '!=', Helpers::get_store_id())
                ->exists();
    }

    public function variant_price(Request $request)
    {
        $product = $this->storeItem($request->id);
        if($product->module->module_type == 'food' && $product->food_variations){
            $price = $product->price;
            $addon_price = 0;
            if ($request['addon_id']) {
                foreach ($request['addon_id'] as $id) {
                    $addon_price += $request['addon-price' . $id] * $request['addon-quantity' . $id];
                }
            }
            $product_variations = json_decode($product->food_variations, true);
            if ($request->variations && $product_variations && count($product_variations)) {

                $price_total =  $price + Helpers::food_variation_price($product_variations, $request->variations);
                $price= $price_total - Helpers::product_discount_calculate($product, $price_total, $product->store)['discount_amount'];
            } else {
                $price = $product->price - Helpers::product_discount_calculate($product, $product->price, $product->store)['discount_amount'];
            }
        }else{

            $str = '';
            $quantity = 0;
            $price = 0;
            $addon_price = 0;

            foreach (json_decode($product->choice_options) as $key => $choice) {
                if ($str != null) {
                    $str .= '-' . str_replace(' ', '', $request[$choice->name]);
                } else {
                    $str .= str_replace(' ', '', $request[$choice->name]);
                }
            }

            if($request['addon_id'])
            {
                foreach($request['addon_id'] as $id)
                {
                    $addon_price+= $request['addon-price'.$id]*$request['addon-quantity'.$id];
                }
            }

            if ($str != null) {
                $count = count(json_decode($product->variations));
                for ($i = 0; $i < $count; $i++) {
                    if (json_decode($product->variations)[$i]->type == $str) {
                        $price = json_decode($product->variations)[$i]->price - Helpers::product_discount_calculate($product, json_decode($product->variations)[$i]->price,Helpers::get_store_data())['discount_amount'];
                    }
                }
            } else {
                $price = $product->price - Helpers::product_discount_calculate($product, $product->price,Helpers::get_store_data())['discount_amount'];
            }
        }

        return array('price' => Helpers::format_currency(($price * $request->quantity)+$addon_price));
    }

    public function addDeliveryInfo(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'contact_person_name' => 'required',
            'contact_person_number' => PhoneNumber::rules(),
            'longitude' => 'required',
            'latitude' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        // The one thing the map cannot price: refuse the save outright when the zone's active
        // delivery rule prices by area or zip code and neither was posted. A client that skipped
        // the picker (or bypassed it entirely) must not be able to save an address the engine
        // can only price at the rule's bare floor.
        $storeForAddress = Helpers::get_store_data();

        if ($storeForAddress && !$request->area_id && !$request->zip_code_id) {
            $coverage = app(DeliveryRuleService::class)->coverageForZone($storeForAddress->zone_id, $storeForAddress->module_id);
            if (!empty($coverage['coverage'])) {
                return response()->json(['errors' => [[
                    'code' => $coverage['type'] === 'zip_code_wise' ? 'zip_code_id' : 'area_id',
                    'message' => $coverage['type'] === 'zip_code_wise'
                        ? translate('messages.Please select a zip code to calculate the delivery fee')
                        : translate('messages.Please select an area to calculate the delivery fee'),
                ]]]);
            }
        }

        $address = [
            'contact_person_name' => $request->contact_person_name,
            'contact_person_number' => $request->contact_person_number,
            'address_type' => 'delivery',
            'address' => $request->address,
            'floor' => $request->floor,
            'road' => $request->road,
            'house' => $request->house,
            'distance' => $request->distance??0,
            'delivery_fee' => $request->delivery_fee?:0,
            'longitude' => (string)$request->longitude,
            'latitude' => (string)$request->latitude,
            // Only meaningful for a zone whose active delivery rule prices by area or zip code;
            // DeliveryChargeService ignores whichever one doesn't match the active rule.
            'area_id' => $request->area_id ?: null,
            'zip_code_id' => $request->zip_code_id ?: null,
        ];

        $request->session()->put('address', $address);

        // Re-price server-side against the store's zone/module now that a delivery address
        // exists, so a surge active on this zone is reflected in the fee and its tooltip note
        // rather than only appearing after the next customer-change recompute.
        if ($storeForAddress) {
            $this->refreshPosAddressDeliveryFee($storeForAddress, $request->user_id ?: Session::get('customer_id'));
            $address = Session::get('address');
        }

        return response()->json([
            'data' => $address,
            'view' => view('vendor-views.pos._address', $this->pos_address_view_data($address))->render(),
        ]);
    }

    private function pos_address_view_data($address): array
    {
        $address = is_array($address) ? $address : null;

        return [
            'address' => $address,
            'address_extra' => collect([
                translate('messages.House') => $address['house'] ?? null,
                translate('messages.Road') => $address['road'] ?? null,
                translate('messages.Floor') => $address['floor'] ?? null,
            ])->filter(fn ($value) => filled($value))->all(),
        ];
    }

    private function get_stocks($product,$selected_item){
        try {
            if($product->module->module_type == 'food'){
                return null;
            }
            $choice_options=   json_decode($product?->choice_options, true);
            $variation=  json_decode($product?->variations, true);

            if(is_array($choice_options) && is_array($variation)  &&  count($choice_options) == 0 && count($variation) == 0 ){
                return $product->stock ?? null ;
            }

            $choiceNames = array_column($choice_options, 'name');
            $variations = array_map(function ($choiceName) use ($selected_item) {
                return str_replace(' ', '', $selected_item[$choiceName]);
            }, $choiceNames);
            $resultString = implode('-', $variations);
            $stockVariations = json_decode($product->variations, true);
            foreach ($stockVariations as $variation) {
                if ($variation['type'] == $resultString) {
                    $stock = $variation['stock'];
                    break;
                }
            }
        } catch (\Throwable $th) {
            Log::error('vendor.pos_controller.get_stocks_failed', [
                'error' => $th->getMessage(),
                'file' => $th->getFile().':'.$th->getLine(),
            ]);
        }

        return $stock ?? null ;
    }
    public function addToCart(Request $request)
    {
        $product = $this->storeItem($request->id);

        if ($request->session()->has('cart') && $this->cartHasOtherStoreItems($request->session()->get('cart'))) {
            return response()->json([
                'data' => -1
            ]);
        }

        if($product->module->module_type == 'food' && $product->food_variations){
        $data = array();
        $data['id'] = $product->id;
        $str = '';
        $variations = [];
        $price = 0;
        $addon_price = 0;
        $variation_price=0;

        $product_variations = json_decode($product->food_variations, true);
        if ($request->variations && $product_variations && count($product_variations)) {
            foreach($request->variations  as $key=> $value ){

                if($value['required'] == 'on' &&  isset($value['values']) == false){
                    return response()->json([
                        'data' => 'variation_error',
                        'message' => translate('Selection required') . ': ' . $value['name'],
                    ]);
                }
                if(isset($value['values'])  && $value['min'] != 0 && $value['min'] > count($value['values']['label'])){
                    return response()->json([
                        'data' => 'variation_error',
                        'message' => translate('Please select minimum').' '.$value['min'].' '.translate('For').' '.$value['name'].'.',
                    ]);
                }
                if(isset($value['values']) && $value['max'] != 0 && $value['max'] < count($value['values']['label'])){
                    return response()->json([
                        'data' => 'variation_error',
                        'message' => translate('Please select maximum').' '.$value['max'].' '.translate('For').' '.$value['name'].'.',
                    ]);
                }
            }
            $variation_data = Helpers::get_varient($product_variations, $request->variations);
            $variation_price = $variation_data['price'];
            $variations = $request->variations;
        }

        $data['variations'] = $variations;
        $data['variant'] = $str;

        $price = $product->price + $variation_price;
        $data['variation_price'] = $variation_price;

        $data['quantity'] = $request['quantity'];
        $data['price'] = $price;
        $data['name'] = $product->name;
        $data['discount'] = Helpers::product_discount_calculate($product, $price,Helpers::get_store_data())['discount_amount'];
        $data['image'] = $product->image;
        $data['image_full_url'] = $product->image_full_url;
        $data['storage'] = $product->storage?->toArray();
        $data['add_ons'] = [];
        $data['add_on_qtys'] = [];
        $data['maximum_cart_quantity'] = $product->maximum_cart_quantity;

        if($request['addon_id'])
        {
            foreach($request['addon_id'] as $id)
            {
                $addon_price+= $request['addon-price'.$id]*$request['addon-quantity'.$id];
                $data['add_on_qtys'][]=$request['addon-quantity'.$id];
            }
            $data['add_ons'] = $request['addon_id'];
        }

        $data['addon_price'] = $addon_price;

        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            if(isset($request->cart_item_key))
            {
                $cart[$request->cart_item_key] = $data;
                $data = 2;
            }
            else
            {
                $cart->push($data);
            }

        } else {
            $cart = collect([$data]);
            $request->session()->put('cart', $cart);
        }
    }else{

        $data = array();
        $data['id'] = $product->id;
        $str = '';
        $variations = [];
        $price = 0;
        $addon_price = 0;


            $selected_item = $request->all();
            $stock= $this->get_stocks($product,$selected_item);
            if($product?->maximum_cart_quantity > 0){
            if(((isset($stock) && min($stock, $product?->maximum_cart_quantity) < $request->quantity )||  $product?->maximum_cart_quantity <  $request->quantity  ) ){
                    return response()->json([
                        'data' => 0
                    ]);
                }
            }


        foreach (json_decode($product->choice_options) as $key => $choice) {
            $data[$choice->name] = $request[$choice->name];
            $variations[$choice->title] = $request[$choice->name];
            if ($str != null) {
                $str .= '-' . str_replace(' ', '', $request[$choice->name]);
            } else {
                $str .= str_replace(' ', '', $request[$choice->name]);
            }
        }
        $data['variations'] = $variations;
        $data['variant'] = $str;
        if ($request->session()->has('cart') && !isset($request->cart_item_key)) {
            if (count($request->session()->get('cart')) > 0) {
                foreach ($request->session()->get('cart') as $key => $cartItem) {
                    if (is_array($cartItem) && $cartItem['id'] == $request['id'] && $cartItem['variant'] == $str) {
                        return response()->json([
                            'data' => 1
                        ]);
                    }
                }

            }
        }
        if ($str != null) {
            $count = count(json_decode($product->variations));
            for ($i = 0; $i < $count; $i++) {
                if (json_decode($product->variations)[$i]->type == $str) {
                    $price = json_decode($product->variations)[$i]->price;
                    $data['variations'] = json_decode($product->variations, true)[$i];
                }
            }
        } else {
            $price = $product->price;
        }

        $data['quantity'] = $request['quantity'];
        $data['price'] = $price;
        $data['name'] = $product->name;
        $data['discount'] = Helpers::product_discount_calculate($product, $price,Helpers::get_store_data())['discount_amount'];
        $data['image'] = $product->image;
        $data['image_full_url'] = $product->image_full_url;
        $data['storage'] = $product->storage?->toArray();
        $data['add_ons'] = [];
        $data['add_on_qtys'] = [];
        $data['maximum_cart_quantity'] = $product->maximum_cart_quantity;

        if($request['addon_id'])
        {
            foreach($request['addon_id'] as $id)
            {
                $addon_price+= $request['addon-price'.$id]*$request['addon-quantity'.$id];
                $data['add_on_qtys'][]=$request['addon-quantity'.$id];
            }
            $data['add_ons'] = $request['addon_id'];
        }

        $data['addon_price'] = $addon_price;

        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            if(isset($request->cart_item_key))
            {
                $cart[$request->cart_item_key] = $data;
                $data = 2;
            }
            else
            {
                $cart->push($data);
            }

        } else {
            $cart = collect([$data]);
            $request->session()->put('cart', $cart);
        }
    }

        $this->setPosCalculatedTax($product->store);
        return response()->json([
            'data' => $data
        ]);
    }

    public function cart_items()
    {
        return view('vendor-views.pos._cart');
    }

    public function removeFromCart(Request $request)
    {
        if ($request->session()->has('cart')) {
            $cart = $request->session()->get('cart', collect([]));
            $item_id = $cart[$request->key]['id'];
            $cart->forget($request->key);
            $request->session()->put('cart', $cart);
        }

        $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->find($item_id);
        if ($product && $product->store) {
            $this->setPosCalculatedTax($product->store);
        }

        return response()->json([],200);
    }

    public function updateQuantity(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart = $cart->map(function ($object, $key) use ($request) {
            if ($key == $request->key) {
                $object['quantity'] = $request->quantity;
            }
            return $object;
        });

        $request->session()->put('cart', $cart);

        try {
            $product_id = $cart[$request->key]['id'];
            $product = Item::withoutGlobalScope(StoreScope::class)->with('store')->find($product_id);
            if ($product && $product->store) {
                $this->setPosCalculatedTax($product->store);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to recalculate tax after quantity update: ' . $e->getMessage());
        }

        return response()->json([],200);
    }

    public function emptyCart(Request $request)
    {
        session()->forget([
            'cart',
            'tax_amount',
            'tax_included',
            'tax_include',
            'extra_discount_amount',
            'extra_discount_type',
            'extra_discount',
            'address',
            'cart_product_ids',
            'customer_id',
            'delivery_type',
            'delivery_type_charge',
            'pos_pro_discount',
            'pos_pro_benefit_type',
            'pos_pro_delivery_offer_type',
            'pos_pro_delivery_percentage',
            'pos_pro_min_order_amount',
            'pos_pro_min_order_status',
        ]);
        return response()->json([],200);
    }

    public function getUserData(Request $request)
    {
        if (!$request->customer_id) {
            return response()->json([], 200);
        }

        $user = User::withStorage()->where('id', $request->customer_id)->first();
        if (!$user) {
            return response()->json([], 200);
        }

        $previousCustomerId = (int) (Session::get('customer_id') ?? 0);
        $newCustomerId      = (int) $request->customer_id;

        if ($previousCustomerId !== $newCustomerId) {
            Session::forget([
                'address',
                'delivery_type',
                'delivery_type_charge',
                'pos_pro_discount',
                'pos_pro_benefit_type',
                'pos_pro_delivery_offer_type',
                'pos_pro_delivery_percentage',
                'pos_pro_min_order_amount',
                'pos_pro_min_order_status',
            ]);
        }

        Session::put('customer_id', $newCustomerId);

        $contactName  = trim($user->f_name . ' ' . $user->l_name);
        $contactPhone = (string) ($user->phone ?? '');
        $sessionAddress = Session::get('address');
        if (is_array($sessionAddress)) {
            $sessionAddress['contact_person_name']   = $contactName;
            $sessionAddress['contact_person_number'] = $contactPhone;
        } else {
            $sessionAddress = [
                'contact_person_name'   => $contactName,
                'contact_person_number' => $contactPhone,
                'address_type'          => 'delivery',
                'address'               => '',
                'floor'                 => '',
                'road'                  => '',
                'house'                 => '',
                'distance'              => 0,
                'delivery_fee'          => 0,
                'longitude'             => '',
                'latitude'              => '',
            ];
        }
        Session::put('address', $sessionAddress);

        $store = Helpers::get_store_data();
        if ($store) {
            $this->setPosCalculatedTax($store);
            $this->refreshPosAddressDeliveryFee($store, $newCustomerId);
        }

        return response()->json([
            'id'              => $user->id,
            'customer_name'   => $user->f_name . ' ' . $user->l_name,
            'customer_phone'  => $user->phone,
            'customer_wallet' => Helpers::format_currency($user->wallet_balance),
            'customer_image'  => $user->image_full_url,
            'view'            => view('vendor-views.pos._address', $this->pos_address_view_data(Session::get('address')))->render(),
        ], 200);
    }


    public function update_discount(Request $request)
    {
        $subtotal = 0;
        $addon_price = 0;
        $discount_on_product = 0;

        $cart = session()->get('cart', []);

        foreach ($cart as $cartItem) {

            if (is_array($cartItem)) {

                $subtotal += $cartItem['price'] * $cartItem['quantity'];
                $addon_price += $cartItem['addon_price'] ?? 0;
                $discount_on_product += ($cartItem['discount'] ?? 0) * $cartItem['quantity'];
            }
        }

        $total = ($subtotal + $addon_price) - $discount_on_product;

        $delivery_fee = session()->get('address')['delivery_fee'] ?? 0;
        $total += $delivery_fee;

        $tax_amount = session()->get('tax_amount') ?? 0;
        $tax_included = session()->get('tax_included') ?? 0;

        if ($tax_included != 1) {
            $total += $tax_amount;
        }

        if($total < $request->discount){
            Toastr::error(translate('The extra discount cannot exceed the total payable amount'));
            return back();
        }
        $this->updateExtraDiscount($request->type,$request->discount);
        $this->setPosCalculatedTax(Helpers::get_store_data());
        return back();
    }

    public function update_paid(Request $request)
    {
        $cart = $request->session()->get('cart', collect([]));
        $cart['paid'] = $request->paid;
        $request->session()->put('cart', $cart);
        return back();
    }

    public function get_customers(Request $request){
        $key = explode(' ', $request['q'] ?? '');
        $data = User::query()
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                ->orWhere('l_name', 'like', "%{$value}%")
                ->orWhere('phone', 'like', "%{$value}%");
            }
        })
        ->limit(8)
        ->get([DB::raw('id, CONCAT(f_name, " ", l_name, " (", phone ,")") as text')])
        ->makeHidden('image_full_url');

        $data[]=(object)['id'=>false, 'text'=>translate('messages.Walk in customer')];

        $reversed = $data->toArray();

        $data = array_reverse($reversed);


        return response()->json($data);
    }

    public function place_order(Request $request)
    {
        if(!$request->type){
            Toastr::error(translate('No payment method selected'));
            return back();
        }

        if($request->session()->has('cart') && isset($request->session()->get('cart')[0]))
        {
            if(count($request->session()->get('cart')) < 1)
            {
                Toastr::error(translate('messages.Cart empty warning'));
                return back();
            }
        }
        else
        {
            Toastr::error(translate('messages.Cart empty warning'));
            return back();
        }
        if ($request->session()->has('address')) {
            if(!$request->user_id){
                Toastr::error(translate('messages.No customer selected'));
                return back();
            }
            $address = $request->session()->get('address');
        }

        $hasDeliveryAddress = isset($address)
            && !empty($address['latitude'])
            && !empty($address['longitude']);

        $distance_data = $hasDeliveryAddress ? $address['distance'] : 0;

        $store = Helpers::get_store_data();

        $self_delivery_status = $store->self_delivery_system;
        $store_sub=$store?->store_sub;
        if ($store->is_valid_subscription) {

            $self_delivery_status = $store_sub->self_delivery;

            if($store_sub->max_order != "unlimited" && $store_sub->max_order <= 0){
                Toastr::error(translate('messages.You have reached the maximum number of orders'));
                return back();
            }
        } elseif($store->store_business_model == 'unsubscribed'){
            Toastr::error(translate('messages.You are not subscribed or subscription has expired'));
            return back();
        }


        $extra_charges = 0;
        $vehicle_id = null;


        if($self_delivery_status != 1){

            $data =  DMVehicle::where(function ($query) use ($distance_data) {
                $query->where('starting_coverage_area', '<=', $distance_data)->where('maximum_coverage_area', '>=', $distance_data)
                ->orWhere(function ($query) use ($distance_data) {
                    $query->where('starting_coverage_area', '>=', $distance_data);
                });
            })
            ->active()
                ->orderBy('starting_coverage_area')->first();

            $extra_charges = (float) (isset($data) ? $data->extra_charges  : 0);
            $vehicle_id = (isset($data) ? $data->id  : null);
        }


        $cart = $request->session()->get('cart');

        $total_addon_price = 0;
        $product_price = 0;
        $store_discount_amount = 0;

        $order_details = [];
        $product_data = [];

        $lastId = Order::max('id') ?? 99999;
        $order = new Order();
        $order->id = $lastId + 1;
        $order->payment_status = $hasDeliveryAddress?'unpaid':'paid';
        if($request->user_id && $hasDeliveryAddress){

            $order->order_status = 'confirmed';
            $order->order_type = 'delivery';
        }else{
            $order->order_status = 'delivered';
            $order->order_type = 'take_away';
        }
        $order->is_pos = 1;
        if($order->order_type == 'take_away'){
            $order->delivered = now();
        }
        $order->distance = $hasDeliveryAddress ? $address['distance'] : 0;
        $order->payment_method = $request->type;
        $order->store_id = $store->id;
        $order->module_id = Helpers::get_store_data()->module_id;
        $order->user_id = $request->user_id;

        $order->delivery_charge          = 0;
        $order->original_delivery_charge = 0;
        $order->delivery_address = isset($address)?json_encode($address):null;
        $order->dm_vehicle_id = $vehicle_id;
        $order->checked = 1;
        $order->created_at = now();
        $order->schedule_at = now();
        $order->updated_at = now();
        $order->zone_id = $store->zone_id;
        $order->otp = rand(1000, 9999);

        $additionalCharges = [];
        $settings = Helpers::get_business_settings_many([
            'additional_charge_status',
            'additional_charge',
            'extra_packaging_data',
        ]);

        $additional_charge_status  = $settings['additional_charge_status'] ?? null;
        $additional_charge         = $settings['additional_charge'] ?? null;


        $order_details = $this->makePosOrderDetails($cart, null, $store);

        if (data_get($order_details, 'status_code') === 403) {
            DB::rollBack();
            return response()->json([
                'errors' => [
                    ['code' => data_get($order_details, 'code'), 'message' => data_get($order_details, 'message')]
                ]
            ], data_get($order_details, 'status_code'));
        }

        $total_addon_price = $order_details['total_addon_price'];
        $product_price = $order_details['product_price'];
        $store_discount_amount = $order_details['store_discount_amount'];
        $flash_sale_admin_discount_amount = $order_details['flash_sale_admin_discount_amount'];
        $flash_sale_vendor_discount_amount = $order_details['flash_sale_vendor_discount_amount'];
        $product_data = $order_details['product_data'];
        $order_details = $order_details['order_details'];

        $extra_discount_amount=session()->get('extra_discount_amount') ?? 0;



        $total_price = $product_price + $total_addon_price - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount - $extra_discount_amount;
        $totalDiscount = $store_discount_amount + $flash_sale_admin_discount_amount + $flash_sale_vendor_discount_amount + $extra_discount_amount;

        $order->extra_discount_amount = $extra_discount_amount;


        $posCustomer         = $request->user_id ? User::find($request->user_id) : null;
        $isProCustomer       = $posCustomer && (int) $posCustomer->pro_status === 1;
        $pro_discount_amount = 0.0;
        $pro_offer           = ['status' => false, 'benefit' => null];
        if ($isProCustomer) {
            $proApply            = $this->applyProCustomerDiscount(
                $posCustomer->id,
                $product_price + $total_addon_price,
                $total_price,
                $store?->module?->module_type,
            );
            $pro_offer           = $proApply['offer'];
            $pro_discount_amount = (float) $proApply['discount'];
            $total_price         = (float) $proApply['total_price'];
            $totalDiscount      += $pro_discount_amount;
        }


        // Undiscounted base+surge -- a Pro customer's delivery-fee benefit is applied later,
        // after applySaverToOrder(), as the last step against whatever the charge has become by
        // then. See calculatePosDeliveryFee()'s own docblock for why: applying it here, before
        // Express/Slightly Delay's own adjustment, composed the two in the wrong order.
        $pro_delivery_savings = 0.0;
        if ($hasDeliveryAddress) {
            $pos_delivery_calc = $this->calculatePosDeliveryFee(
                $store,
                $order->distance,
                $address['area_id'] ?? null,
                $address['zip_code_id'] ?? null,
            );
            $order->delivery_charge          = $pos_delivery_calc['original_delivery_charge'];
            $order->original_delivery_charge = $pos_delivery_calc['original_delivery_charge'];
            $order->surge_amount             = round((float) $pos_delivery_calc['surge_amount'], config('round_up_to_digit'));
        } else {
            $order->delivery_charge          = 0;
            $order->original_delivery_charge = 0;
            $order->surge_amount             = 0;
        }

        $finalCalculatedTax =  Helpers::getFinalCalculatedTax($order_details, $additionalCharges, $totalDiscount, $total_price, $store->id);
        $order->flash_admin_discount_amount = round($flash_sale_admin_discount_amount, config('round_up_to_digit'));
        $order->flash_store_discount_amount = round($flash_sale_vendor_discount_amount, config('round_up_to_digit'));

        $tax_amount = $finalCalculatedTax['tax_amount'];
        $tax_status = $finalCalculatedTax['tax_status'];
        $taxMap = $finalCalculatedTax['taxMap'];
        $orderTaxIds = data_get($finalCalculatedTax ,'taxData.orderTaxIds',[] );
        $taxType=  data_get($finalCalculatedTax ,'taxType');
        $order->tax_type = $taxType;
        $order->tax_status = $tax_status;

        try {
            $order->store_discount_amount= $store_discount_amount;
            $order->tax_percentage = 0;
            $order->total_tax_amount = $tax_amount;
            if ($hasDeliveryAddress) {
                $pos_eligible_amount = max(0, $product_price + $total_addon_price - $store_discount_amount - ($flash_sale_admin_discount_amount ?? 0) - ($flash_sale_vendor_discount_amount ?? 0) - $extra_discount_amount);
                $pos_effective_delivery = $this->effectiveFee(
                    (float) $order->delivery_charge,
                    $store,
                    $pos_eligible_amount,
                    $this->resolveCouponCodeFromSession(),
                );
                if ($pos_effective_delivery['is_free']) {

                    $order->delivery_charge  = 0;
                    $order->surge_amount     = 0;
                    $order->free_delivery_by = $pos_effective_delivery['free_by'];
                    $pro_delivery_savings    = 0.0;
                }
            }

            $order->order_amount = $total_price + $tax_amount + $order->delivery_charge;
            $this->applySaverToOrder($order, (int) $order->module_id, (int) $order->zone_id, (float) $order->delivery_charge, (bool) $self_delivery_status);

            // Pro customer's delivery-fee benefit, applied last: against the delivery fee as the
            // vendor actually set it up -- Express premium or Slightly Delay reduction already
            // folded in, not the pre-saver base -- so a Slightly Delay order isn't discounted as
            // if the customer were still being charged the undiscounted fee, matching what the
            // address modal and delivery-type picker showed the vendor. applyProCustomerDeliveryFee()
            // itself no-ops when the benefit type isn't 'delivery_fee' or the charge is already 0.
            if ($isProCustomer) {
                $netDeliveryFee = (float) $order->delivery_charge + match ($order->delivery_type) {
                    \App\Models\ModuleZoneDeliveryOption::TYPE_EXPRESS => (float) $order->delivery_type_charge,
                    \App\Models\ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY => -(float) $order->delivery_type_charge,
                    default => 0.0,
                };
                $proDeliveryApply = $this->applyProCustomerDeliveryFee(
                    $pro_offer,
                    $netDeliveryFee,
                    $total_price,
                    $order->free_delivery_by,
                    $store?->module?->module_type,
                );
                $pro_delivery_savings = (float) ($proDeliveryApply['savings'] ?? 0);
                if ($pro_delivery_savings > 0) {
                    // Savings taken off the pre-saver base, NOT off $netDeliveryFee's own
                    // returned figure -- delivery_type_charge stays a separate, untouched amount
                    // that applySaverToOrder() has already folded into order_amount above, so
                    // subtracting the discount from the net fee here would double-count it.
                    $order->delivery_charge  = (float) $order->delivery_charge - $pro_delivery_savings;
                    $order->free_delivery_by = $proDeliveryApply['free_delivery_by'] ?? $order->free_delivery_by;
                    $order->order_amount    -= $pro_delivery_savings;
                }
            }

            if($request->type == 'card'){

                $order->adjusment = 0;
            }else{
                $order->adjusment = $request->amount - ((float) $order->order_amount);

            }
            $order->payment_method = $request->type;
            $order->save();


            if ($isProCustomer && ($pro_offer['status'] ?? false)) {
                $amountSaved = $pro_discount_amount > 0 ? $pro_discount_amount : $pro_delivery_savings;
                if ($amountSaved > 0) {
                    $this->recordOrderProDiscount(
                        orderId: (int) $order->id,
                        userId: (int) $posCustomer->id,
                        proOffer: $pro_offer,
                        amountSaved: (float) $amountSaved,
                        originalDeliveryCharge: $order->original_delivery_charge ?? null,
                        moduleType: $store?->module?->module_type,
                    );
                }
            }

            if ($request->order_type !== 'parcel') {
                $taxMapCollection = collect($taxMap);
                foreach ($order_details as $key => $item) {
                    $order_details[$key]['order_id'] = $order->id;

                    if ($item['item_id']) {
                        $item_id = $item['item_id'];
                    } else {
                        $item_id = $item['item_campaign_id'];
                    }
                    $index = $taxMapCollection->search(function ($tax) use ($item_id) {
                        return $tax['product_id'] == $item_id;
                    });
                    if ($index !== false) {
                        $matchedTax = $taxMapCollection->pull($index);
                        $order_details[$key]['tax_status'] = $matchedTax['include'] == 1 ? 'included' : 'excluded';
                        $order_details[$key]['tax_amount'] = $matchedTax['totalTaxamount'];
                    }
                }

                OrderDetail::insert($order_details);
                if (count($orderTaxIds)) {
                    \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                        orderId: $order->id,
                        orderTaxIds: $orderTaxIds,
                    );
                }
                if (count($product_data) > 0) {
                    foreach ($product_data as $item) {
                        self::updateItemStock($item['item'], $item['quantity'], $item['variant'])->save();
                        self::updateFlashSaleStock($item['item'], $item['quantity'])?->save();
                    }
                }
                $store->increment('total_order');
            }

            session()->forget([
                'cart',
                'tax_amount',
                'tax_included',
                'tax_include',
                'extra_discount_amount',
                'extra_discount_type',
                'extra_discount',
                'address',
                'cart_product_ids',
                'customer_id',
                'delivery_type',
                'delivery_type_charge',
                'pos_pro_discount',
                'pos_pro_benefit_type',
                'pos_pro_delivery_offer_type',
                'pos_pro_delivery_percentage',
                'pos_pro_min_order_amount',
                'pos_pro_min_order_status',
            ]);
            session(['last_order' => $order->id]);
            if($order->order_status=='confirmed' && $order->user){
                SendNotification::sendOrderNotifications($order);
                $mail_status = SendNotification::mailTemplateEnabled('place_order_mail_status_user');
                try{
                    if($order->order_status == 'pending' && config('mail.status') && $mail_status && SendNotification::channelEnabled('customer','customer_order_notification','mail_status'))
                    {
                        SendNotification::mail($order->customer?->getRawOriginal('email'), new PlaceOrder($order->id));
                    }
                }catch (\Exception $ex) {
                    Log::error('vendor.pos_controller.place_order_failed', [
                        'error' => $ex->getMessage(),
                        'file' => $ex->getFile().':'.$ex->getLine(),
                    ]);
                }
            }

            if ($store?->is_valid_subscription && $store_sub->max_order != "unlimited" && $store_sub->max_order > 0 ) {
                $store_sub->decrement('max_order' , 1);
            }

            Toastr::success(translate('messages.Order placed successfully'));
            return back();
        } catch (\Exception $e) {
            Log::error('vendor.pos_controller.place_order_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }
        Toastr::warning(translate('messages.Failed to place order'));
        return back();
    }



    public function customer_store(Request $request)
    {
        $request->validate([
            'f_name' => 'required',
            'l_name' => 'required',
            'email' => EmailAddress::rules('required', 'users'),
            'phone' => PhoneNumber::rules('required', 'users'),
        ]);
        User::create([
            'f_name' => $request['f_name'],
            'l_name' => $request['l_name'],
            'email' => $request['email'],
            'phone' => $request['phone'],
            'password' => bcrypt('password'),
            'is_from_pos' => 1
        ]);
        try {
            if (SendNotification::canSendMail('pos_registration_mail_status_user', 'customer', 'customer_pos_registration') && $request->email) {
                SendNotification::mail($request->email, new \App\Mail\CustomerRegistrationPOS($request->f_name . ' ' . $request->l_name,$request['email'],'password'));
                Toastr::success(translate('Mail sent to the user'));
            }
        } catch (\Exception $ex) {
            Log::error('vendor.pos_controller.customer_store_failed', [
                'error' => $ex->getMessage(),
                'file' => $ex->getFile().':'.$ex->getLine(),
            ]);
        }
        Toastr::success(translate('Added successfully'));
        return back();
    }

    public function extra_charge(Request $request)
    {
        $distance_data = $request->distancMileResult ?? 1;
        $store         = Helpers::get_store_data();

        $delivery_calc = $this->calculatePosDeliveryFee(
            $store,
            $distance_data,
            $request->area_id,
            $request->zip_code_id,
        );

        // The plain base+surge quote — a Pro customer's discount is applied once, as the last
        // step, only in the cart summary and at order placement (see
        // PlaceNewOrderTrait::calculatePosDeliveryFee()'s own docblock).
        return response()->json($delivery_calc['original_delivery_charge'], 200);
    }

    /**
     * The area/zip picker for this vendor's own store's zone, when its active delivery rule
     * prices by one of them. `type` is null and `coverage` empty for a distance/flat-priced
     * zone, the same shape the Builder storefront's coverage picker reads, so the front end
     * just hides the picker when `coverage` comes back empty rather than branching on `type`.
     */
    public function getDeliveryCoverage(Request $request)
    {
        $store = Helpers::get_store_data();

        if (!$store) {
            return response()->json(['type' => null, 'coverage' => []]);
        }

        return response()->json(app(DeliveryRuleService::class)->coverageForZone($store->zone_id, $store->module_id));
    }
}
