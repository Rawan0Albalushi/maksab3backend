<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Schema;
use App\Rules\ImageFile;
use App\Rules\PhoneNumber;
use App\Rules\EmailAddress;
use App\Rules\StrongPassword;
use App\CentralLogics\Helpers;
use App\Exports\DisbursementHistoryExport;
use App\Exports\StoreCashTransactionExport;
use App\Exports\StoreListExport;
use App\Exports\StoreOrderTransactionExport;
use App\Exports\StoreWiseItemReviewExport;
use App\Exports\StoreWiseWithdrawTransactionExport;
use App\Exports\StoreWithdrawTransactionExport;
use App\Http\Controllers\Controller;
use App\Mail\WithdrawRequestMail;
use App\Models\AccountTransaction;
use App\Models\AddOn;
use App\Models\Conversation;
use App\Models\DataSetting;
use App\Models\DisbursementDetails;
use App\Models\Item;
use App\Models\Message;
use App\Models\Module;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Store;
use App\Models\StoreConfig;
use App\Models\StoreSchedule;
use App\Models\StoreWallet;
use App\Models\SubscriptionPackage;
use App\Models\TempProduct;
use App\Models\UserInfo;
use App\Models\Vendor;
use App\Models\WithdrawRequest;
use App\Models\Zone;
use App\Observers\StoreObserver;
use App\Scopes\StoreScope;
use App\Services\Store\StoreService;
use App\Traits\Report\ExportRowStreamTrait;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\Rental\Emails\ProviderWithdrawRequestMail;
use Modules\Service\Emails\ProviderWithdrawRequestMail as ServiceProviderWithdrawRequestMail;
use Modules\Rental\Entities\TripTransaction;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\Log;

class VendorController extends Controller
{
    use ExportRowStreamTrait;

    /**
     * Whether getStoreReelsFilteredQuery() narrowed the reel list beyond the store scope.
     * Set there, read to decide if the store-wide total can be taken from the paginator.
     */
    private bool $storeReelsAreFiltered = false;

    public function index()
    {
        return view('admin-views.vendor.index');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'name.0' => 'required',
            'name.*' => 'max:191',
            'address.0' => 'required',
            'address.*' => 'max:1000',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'email' => EmailAddress::rules('required', 'vendors'),
            'phone' => PhoneNumber::rules('required', 'vendors'),
            'minimum_delivery_time' => 'required',
            'maximum_delivery_time' => 'required',
            'delivery_time_type' => 'required',
            'password' => StrongPassword::rules('required'),
            'zone_id' => 'required',
            'logo' => ImageFile::rules('required'),
            'cover_photo' => ImageFile::rules('nullable'),

        ], [
            'f_name.required' => translate('messages.First name is required'),
            'name.0.required' => translate('Default name is required'),
            'address.0.required' => translate('Default address is required'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        if ($request->zone_id) {
            $zone = Zone::query()
                ->whereContains('coordinates', new Point($request->latitude, $request->longitude, POINT_SRID))
                ->where('id', $request->zone_id)
                ->first();
            if (! $zone) {
                $validator->getMessageBag()->add('latitude', translate('messages.Please select a location within the selected zone.'));

                return response()->json(['errors' => Helpers::error_processor($validator)]);
            }
        }

        if ($request->delivery_time_type == 'min') {
            $minimum_delivery_time = (int) $request->input('minimum_delivery_time');
            if ($minimum_delivery_time < 10) {
                $validator->getMessageBag()->add('minimum_delivery_time', translate('messages.Minimum delivery time') . ': ' . \Carbon\CarbonInterval::minutes(10)->forHumans());

                return response()->json(['errors' => Helpers::error_processor($validator)]);
            }
        }

        $vendor = new Vendor;
        $vendor->f_name = $request->f_name;
        $vendor->l_name = $request->l_name;
        $vendor->email = $request->email;
        $vendor->phone = $request->phone;
        $vendor->password = bcrypt($request->password);
        $vendor->save();

        $store = new Store;
        $store->name = $request->name[array_search('default', $request->lang)];
        $store->phone = $request->phone;
        $store->email = $request->email;
        $store->logo = Helpers::upload('store/', 'png', $request->file('logo'));
        $store->cover_photo = Helpers::upload('store/cover/', 'png', $request->file('cover_photo'));
        $store->address = $request->address[array_search('default', $request->lang)];
        $store->latitude = $request->latitude;
        $store->longitude = $request->longitude;
        $store->vendor_id = $vendor->id;
        $store->zone_id = $request->zone_id;
        $store->tin = $request->tin;
        $store->tin_expire_date = $request->tin_expire_date;
        $extension = $request->has('tin_certificate_image') ? $request->file('tin_certificate_image')->getClientOriginalExtension() : 'png';
        $store->tin_certificate_image = Helpers::upload('store/', $extension, $request->file('tin_certificate_image'));
        $store->delivery_time = $request->minimum_delivery_time.'-'.$request->maximum_delivery_time.' '.$request->delivery_time_type;
        $store->module_id = Config::get('module.current_module_id');
        try {
            $store->save();
            if (config('module.' . $store->module->module_type . '.always_open', false)) {
                app(StoreService::class)->createSchedule($store->id);
            }

            Helpers::add_or_update_translations(request: $request, key_data: 'name', name_field: 'name', model_name: 'Store', data_id: $store->id, data_value: $store->name);
            Helpers::add_or_update_translations(request: $request, key_data: 'address', name_field: 'address', model_name: 'Store', data_id: $store->id, data_value: $store->address);

        } catch (\Exception $ex) {
            Log::error('admin.vendor_controller.store_failed', [
                'error' => $ex->getMessage(),
                'file' => $ex->getFile().':'.$ex->getLine(),
            ]);
            $validator->getMessageBag()->add('store_add', $ex->getMessage());

            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        return response()->json(['message' => translate('Added successfully'), 'redirect' => route('admin.store.list')]);
    }

    public function edit($id)
    {
        if (getEnvMode() == 'demo' && $id == 2) {
            Toastr::warning(translate('messages.You can not edit this store please add a new store to edit'));

            return back();
        }
        $store = Store::withoutGlobalScope('translate')->withStorage()->with(['translations', 'vendor.storage', 'zone', 'module.storage'])->findOrFail($id);

        return view('admin-views.vendor.edit', compact('store'));
    }

    public function update(Request $request, Store $store)
    {
        $validator = Validator::make($request->all(), [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'name.0' => 'required',
            'name.*' => 'max:191',
            'address.0' => 'required',
            'address.*' => 'max:1000',
            'email' => EmailAddress::rules('required', 'vendors,email,'.$store->vendor->id),
            'phone' => PhoneNumber::rules('required', 'vendors,phone,'.$store->vendor->id),
            'zone_id' => 'required',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'password' => StrongPassword::rules('nullable'),
            'minimum_delivery_time' => 'required',
            'maximum_delivery_time' => 'required',
            'delivery_time_type' => 'required',
            'logo' => ImageFile::rules('nullable'),
            'cover_photo' => ImageFile::rules('nullable'),
        ], [
            'f_name.required' => translate('messages.First name is required'),
            'name.0.required' => translate('Default name is required'),
            'address.0.required' => translate('Default address is required'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);

        }

        if ($request->zone_id) {
            $zone = Zone::query()
                ->whereContains('coordinates', new Point($request->latitude, $request->longitude, POINT_SRID))
                ->where('id', $request->zone_id)
                ->first();
            if (! $zone) {
                $validator->getMessageBag()->add('latitude', translate('messages.Coordinates out of zone'));

                return response()->json(['errors' => Helpers::error_processor($validator)]);
            }
        }
        if ($request->delivery_time_type == 'min') {
            $minimum_delivery_time = (int) $request->input('minimum_delivery_time');
            if ($minimum_delivery_time < 10) {
                $validator->getMessageBag()->add('minimum_delivery_time', translate('messages.Minimum delivery time') . ': ' . \Carbon\CarbonInterval::minutes(10)->forHumans());

                return response()->json(['errors' => Helpers::error_processor($validator)]);
            }
        }

        $vendor = Vendor::findOrFail($store->vendor->id);
        $vendor->f_name = $request->f_name;
        $vendor->l_name = $request->l_name;
        $vendor->email = $request->email;
        $vendor->phone = $request->phone;
        $vendor->password = strlen($request->password) > 1 ? bcrypt($request->password) : $store->vendor->password;
        $vendor->save();

        $slug = Str::slug($request->name[array_search('default', $request->lang)]);
        $store->slug = $store->slug ? $store->slug : "{$slug}{$store->id}";
        $store->email = $request->email;
        $store->phone = $request->phone;
        $store->logo = $request->has('logo') ? Helpers::update('store/', $store->logo, 'png', $request->file('logo')) : $store->logo;
        $store->cover_photo = $request->has('cover_photo') ? Helpers::update('store/cover/', $store->cover_photo, 'png', $request->file('cover_photo')) : $store->cover_photo;
        $store->name = $request->name[array_search('default', $request->lang)];
        $store->address = $request->address[array_search('default', $request->lang)];
        $store->latitude = $request->latitude;
        $store->longitude = $request->longitude;
        $store->zone_id = $request->zone_id;
        $store->tin = $request->tin;
        $store->tin_expire_date = $request->tin_expire_date;
        $extension = $request->has('tin_certificate_image') ? $request->file('tin_certificate_image')->getClientOriginalExtension() : 'png';
        $store->tin_certificate_image = $request->has('tin_certificate_image') ? Helpers::update('store/', $store->tin_certificate_image, $extension, $request->file('tin_certificate_image')) : $store->tin_certificate_image;
        $store->delivery_time = $request->minimum_delivery_time.'-'.$request->maximum_delivery_time.' '.$request->delivery_time_type;
        $store->save();

        Helpers::add_or_update_translations(request: $request, key_data: 'name', name_field: 'name', model_name: 'Store', data_id: $store->id, data_value: $store->name);
        Helpers::add_or_update_translations(request: $request, key_data: 'address', name_field: 'address', model_name: 'Store', data_id: $store->id, data_value: $store->address);

        if ($vendor->userinfo) {
            $userinfo = $vendor->userinfo;
            $userinfo->f_name = $store->name;
            $userinfo->l_name = '';
            $userinfo->email = $store->email;
            $userinfo->image = $store->logo;
            $userinfo->save();
        }

        if ($request->approve_vendor == 1) {
            $request->merge([
                'status' => 1,
                'id' => $store->id,
            ]);
            $this->updateVendorApplication($request);
        }

        return response()->json([
            'message' => translate('Updated successfully'),
            'redirect' => route('admin.store.list'),
        ]);
    }

    public function destroy(Request $request, Store $store)
    {
        if (getEnvMode() == 'demo' && $store->id == 2) {
            Toastr::warning(translate('messages.You can not delete this store please add a new store to delete'));

            return back();
        }
        if (Order::where('store_id', $store->id)->whereIn('order_status', ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'])->exists()) {
            Toastr::warning(translate('messages.You can not delete this store Please complete the ongoing and accepted orders'));

            return back();
        }

        Helpers::check_and_delete('vendor/', $store->vendor['image']);

        Helpers::check_and_delete('store/', $store->logo);

        Helpers::check_and_delete('store/cover/', $store->cover_photo);

        foreach ($store->deliverymen as $dm) {

            Helpers::check_and_delete('delivery-man/', $dm['image']);

            foreach (json_decode($dm['identity_image'], true) as $img) {

                Helpers::check_and_delete('delivery-man/', $img);

            }
        }

        $store?->deliverymen()?->delete();
        $store?->discount()?->delete();
        $store?->schedules()?->delete();
        $store?->storeConfig()?->delete();
        $store?->translations()?->delete();
        $store?->vendor?->userinfo()?->delete();
        $store?->vendor()?->delete();
        $store?->delete();

        Toastr::success(translate('messages.Store removed'));

        return back();
    }

    public function view(Request $request, $store_id, $tab = null, $sub_tab = 'cash')
    {
        $filter = $request?->filter;
        $key = explode(' ', request()->search ?? '');
        // The meta-data tab needs the untranslated row; fetching it here instead of
        // re-querying inside that branch keeps the store to a single hydration, which in
        // turn keeps its vendor and module relations to one load each for the whole page.
        $store = Store::withStorage()->when($tab == 'meta-data', function ($query) {
            $query->withoutGlobalScope('translate')->with('translations');
        })->when($tab == 'discount', function ($query) {
            $query->with('discount');
        })->when($tab == 'settings', function ($query) {
            $query->with('schedules');
        })->with(['vendor.storage', 'vendor.wallet', 'zone', 'module.storage', 'store_sub_update_application', 'store_sub_update_application.package', 'store_sub.package'])->findOrFail($store_id);

        if (addon_published_status('Rental') && $store->module_type == 'rental') {
            return to_route('admin.rental.provider.details', ['id' => $store_id, 'tab' => $tab]);
        }

        $wallet = $store->vendor->wallet;
        if (! $wallet) {
            $wallet = new StoreWallet;
            $wallet->vendor_id = $store->vendor->id;
            $wallet->total_earning = 0.0;
            $wallet->total_withdrawn = 0.0;
            $wallet->pending_withdraw = 0.0;
            $wallet->created_at = now();
            $wallet->updated_at = now();
            $wallet->save();
        }
        if ($tab == 'settings') {
            if ($store->module->module_type == 'ecommerce' && ! StoreSchedule::where('store_id', $store->id)->exists()) {
                app(StoreService::class)->createSchedule($store->id);
            }
            $admin_website_builder_status = Helpers::get_business_settings('admin_website_builder_status');

            return view('admin-views.vendor.view.settings', compact('store', 'admin_website_builder_status'));
        } elseif ($tab == 'order') {
            $orders = Order::with('customer')->where('store_id', $store->id)->latest()
                ->when(request()->search, function ($q) use ($key) {
                    $q->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('id', 'like', "%{$value}%");
                        }
                    });
                })
                ->when(isset($filter) && $filter == 'scheduled_orders', function ($q) {
                    $q->Scheduled();
                })
                ->when(isset($filter) && $filter == 'pending_orders', function ($q) {
                    $q->where(['order_status' => 'pending'])->OrderScheduledIn(30);
                })
                ->when(isset($filter) && $filter == 'delivered_orders', function ($q) {
                    $q->where(['order_status' => 'delivered']);
                })
                ->when(isset($filter) && $filter == 'canceled_orders', function ($q) {
                    $q->where(['order_status' => 'canceled']);
                })
                ->StoreOrder()
                ->Notpos()->paginate(10);

            return view('admin-views.vendor.view.order', compact('store', 'orders'));
        } elseif ($tab == 'item') {
            $taxData = Helpers::getTaxSystemType(getTaxVatList: false);
            $productWiseTax = $taxData['productWiseTax'];

            if ($sub_tab == 'pending-items' || $sub_tab == 'rejected-items') {

                $foods = TempProduct::withoutGlobalScope(\App\Scopes\StoreScope::class)->withStorage()
                    ->when($productWiseTax, function ($q) {
                        $q->with('taxVats.tax');
                    })
                    ->where('store_id', $store->id)
                    ->when(request()->search, function ($q) use ($key) {
                        $q->where(function ($q) use ($key) {
                            foreach ($key as $value) {
                                $q->where('name', 'like', "%{$value}%");
                            }
                        });
                    })
                    ->when($sub_tab == 'pending-items', function ($q) {
                        $q->where('is_rejected', 0);
                    })
                    ->when($sub_tab == 'rejected-items', function ($q) {
                        $q->where('is_rejected', 1);
                    })
                    ->latest()->paginate(25);
            } else {

                $foods = Item::withoutGlobalScope(\App\Scopes\StoreScope::class)->withStorage()
                    ->when($productWiseTax, function ($q) {
                        $q->with('taxVats.tax');
                    })
                    ->where('store_id', $store->id)
                    ->where('is_approved', 1)
                    ->when(request()->search, function ($q) use ($key) {
                        $q->where(function ($q) use ($key) {
                            foreach ($key as $value) {
                                $q->where('name', 'like', "%{$value}%");
                            }
                        });
                    })
                    ->when($sub_tab == 'active-items', function ($q) {
                        $q->where('status', 1);
                    })
                    ->when($sub_tab == 'inactive-items', function ($q) {
                        $q->where('status', 0);
                    })
                    ->latest()->paginate(25);
            }

            return view('admin-views.vendor.view.product', compact('store', 'foods', 'sub_tab', 'productWiseTax'));
        } elseif ($tab == 'discount') {
            return view('admin-views.vendor.view.discount', compact('store'));
        } elseif ($tab == 'transaction') {
            $vendorId = $store->vendor->id;
            $transactionQueries = [
                'cash' => AccountTransaction::where('from_type', 'store')->where('type', 'collected')->where('from_id', $vendorId),
                'digital' => OrderTransaction::where('vendor_id', $vendorId)->latest(),
                'withdraw' => WithdrawRequest::where('vendor_id', $vendorId)->latest(),
            ];
            $sub_tab = array_key_exists($sub_tab, $transactionQueries) ? $sub_tab : 'cash';

            // The active sub-tab's paginator already counts its own rows — reuse that total
            // for the tab label instead of running the same count a second time.
            $transactions = $transactionQueries[$sub_tab]->paginate(25);
            $transactionCounts = [$sub_tab => $transactions->total()];
            foreach ($transactionQueries as $name => $query) {
                if ($name !== $sub_tab) {
                    $transactionCounts[$name] = $query->count();
                }
            }

            return view('admin-views.vendor.view.transaction', compact('store', 'sub_tab', 'transactions', 'transactionCounts'));
        } elseif ($tab == 'reviews') {
            $ratings = $store->rating ?: [0, 0, 0, 0, 0];
            $storeRating = app(StoreService::class)->calculateRating($ratings);
            $user_rating = $storeRating['rating'];
            $total_reviews = $storeRating['total'];
            [$five, $four, $three, $two, $one] = $ratings;
            $total_rating = ($one + $two + $three + $four + $five) ?: 1;

            $reviews = $store->reviews()
                ->with([
                    'item' => fn ($query) => $query->withoutGlobalScope(StoreScope::class)->withStorage(),
                    'customer',
                ])
                ->latest()->paginate(25);

            return view('admin-views.vendor.view.review', compact('store', 'sub_tab', 'reviews',
                'user_rating', 'total_reviews', 'five', 'four', 'three', 'two', 'one', 'total_rating'));
        } elseif ($tab == 'reels') {
            if (!$this->canAccessStoreReelsTab($store)) {
                Toastr::error(translate('messages.Unknown tab'));

                return back();
            }

            $filteredQuery = $this->getStoreReelsFilteredQuery($request, $store->id);
            $reels = $this->applyStoreReelSorting(clone $filteredQuery, $request)
                ->paginate(config('default_pagination'))
                ->appends($request->query());

            // Every reel here belongs to the store being viewed — hand it the instance we
            // already have rather than letting the view resolve a second one.
            $reels->getCollection()->each(fn ($reel) => $reel->setRelation('store', $store));

            // Unfiltered, the paginator has already counted every reel for this store.
            $unfilteredReelCount = $this->storeReelsAreFiltered
                ? $filteredQuery->getModel()->newQuery()->where('store_id', $store->id)->count()
                : $reels->total();

            $overview = $this->getStoreReelsOverview($store->id, $unfilteredReelCount);
            $filterCount = $this->getStoreReelFilterCount($request);

            return view('reelsmodule::admin.vendor-view.reels', compact('store', 'reels', 'overview', 'filterCount'));

        } elseif ($tab == 'conversations') {
            // toBase() keeps this to the id: hydrating a UserInfo would repeat the storage
            // read the conversation list below already performs for the same row.
            $userInfoId = UserInfo::where(['vendor_id' => $store->vendor->id])->toBase()->value('id');
            if ($userInfoId) {
                // UserInfo appends image_full_url, which resolves through the user or
                // delivery_man the row points at. The list always renders the non-vendor
                // side, so those two are what it reads — eager-load them rather than
                // lazy loading one per conversation.
                $conversations = Conversation::with([
                    'sender.user', 'sender.delivery_man',
                    'receiver.user', 'receiver.delivery_man',
                    'last_message',
                ])->WhereUser($userInfoId)->paginate(8);
            } else {
                $conversations = [];
            }

            return view('admin-views.vendor.view.conversations', compact('store', 'sub_tab', 'conversations'));
        } elseif ($tab == 'meta-data') {
            return view('admin-views.vendor.view.meta-data', compact('store', 'sub_tab'));
        } elseif ($tab == 'disbursements') {
            $disbursements = DisbursementDetails::with(['store.vendor', 'withdraw_method'])->where('store_id', $store->id)
                ->when(request()->search, function ($q) use ($key) {
                    $q->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('disbursement_id', 'like', "%{$value}%")
                                ->orWhere('status', 'like', "%{$value}%");
                        }
                    });
                })
                ->latest()->paginate(config('default_pagination'));

            return view('admin-views.vendor.view.disbursement', compact('store', 'disbursements'));
        } elseif ($tab == 'business_plan') {

            $store->loadMissing([
                'store_sub_update_application.package', 'vendor', 'store_sub_update_application.last_transcations', 'module:id,module_type',
            ])->loadCount('items');
            if ($store->module_type == 'rental') {
                $store->loadCount('vehicles as items_count');
            } elseif ($store->module_type == 'service') {
                $store->loadCount('services as items_count');
            }
            $packages = SubscriptionPackage::where('status', 1)
                ->where('module_type', Helpers::subscriptionPackageType($store))
                ->latest()->get();
            $admin_commission = Helpers::get_business_settings('admin_commission', false);
            $business_name = Helpers::get_business_settings('business_name', false);
            try {
                $index = $store->store_business_model == 'commission' ? 0 : 1 + array_search($store?->store_sub_update_application?->package_id ?? 1, array_column($packages->toArray(), 'id'));
            } catch (\Throwable $th) {
                $index = 2;
            }

            return view('admin-views.vendor.view.subscription', compact('store', 'packages', 'business_name', 'admin_commission', 'index'));

        }

        $subscribedPackage = $store->store_sub_update_application?->package ?? $store->store_sub?->package;

        if (! $store->package_id) {
            $store->setRelation('package', null);
        } elseif ($subscribedPackage && $subscribedPackage->id == $store->package_id) {
            $store->setRelation('package', $subscribedPackage);
        } else {
            $store->loadMissing('package');
        }

        return view('admin-views.vendor.view.index', compact('store', 'wallet'));
    }

    public function disbursement_export(Request $request, $id, $type)
    {
        $key = explode(' ', $request['search'] ?? '');

        $store = Store::find($id);
        $disbursements = DisbursementDetails::with(['store.vendor', 'withdraw_method'])->where('store_id', $store->id)
            ->when($request['search'], function ($q) use ($key) {
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('disbursement_id', 'like', "%{$value}%")
                            ->orWhere('status', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()->get();
        $data = [
            'disbursements' => $disbursements,
            'search' => $request->search ?? null,
            'store' => $store->name,
            'type' => 'store',
            'is_provider' => $request->provider_id ?? null,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new DisbursementHistoryExport($data), 'Disbursementlist.xlsx');
        } elseif ($request->type == 'csv') {
            return Excel::download(new DisbursementHistoryExport($data), 'Disbursementlist.csv');
        }
    }

    public function view_tab(Store $store)
    {

        Toastr::error(translate('messages.Unknown tab'));

        return back();
    }

    private function canAccessStoreReelsTab(Store $store): bool
    {
        return addon_published_status('ReelsModule')
            && class_exists('Modules\\ReelsModule\\Entities\\Reel')
            && class_exists('Modules\\ReelsModule\\Entities\\ReelEngagement')
            && in_array($store->module_type, ['grocery', 'food', 'ecommerce', 'pharmacy'], true);
    }

    private function getStoreReelsFilteredQuery(Request $request, int $storeId)
    {
        $reelModel = 'Modules\\ReelsModule\\Entities\\Reel';
        $reelEngagementModel = 'Modules\\ReelsModule\\Entities\\ReelEngagement';
        $keywords = array_filter(explode(' ', (string) $request->input('search', '')));
        $reelStatuses = array_values(array_filter((array) $request->input('reel_status', [])));
        $today = Carbon::today()->toDateString();

        // 'store' is deliberately not eager-loaded: this list is scoped to one store, which
        // the caller already holds. Loading it here would hydrate a second Store and repeat
        // its config/storage/translation reads.
        $query = $reelModel::with(['storage'])
            ->withCount([
                'engagements as total_views' => fn (Builder $builder) => $builder->where('type', $reelEngagementModel::TYPE_VIEW),
                'engagements as total_likes' => fn (Builder $builder) => $builder->where('type', $reelEngagementModel::TYPE_LIKE),
                'engagements as total_store_visits' => fn (Builder $builder) => $builder->where('type', $reelEngagementModel::TYPE_VISIT),
            ])
            ->where('store_id', $storeId);

        $storeOnlyWheres = count($query->getQuery()->wheres);

        $query->when($request->filled('status_filter'), function ($builder) use ($request) {
                $builder->where('status', $request->status_filter === 'active' ? 1 : 0);
            })
            ->when(!empty($keywords), function ($builder) use ($keywords) {
                foreach ($keywords as $value) {
                    $builder->where(function ($subQuery) use ($value) {
                        $subQuery->where('id', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%")
                            ->orWhereHas('store', function ($storeQuery) use ($value) {
                                $storeQuery->where('name', 'like', "%{$value}%");
                            })
                            ->orWhereHas('translations', function ($translationQuery) use ($value) {
                                $translationQuery->where('key', 'description')
                                    ->where('value', 'like', "%{$value}%");
                            });
                    });
                }
            });

        $this->applyStoreReelStatusFilter($query, $reelStatuses, $today);
        $this->applyStoreReelUploadDateFilter($query, $request);

        // Every filter above adds at least one where clause, so comparing the count against
        // the store-only baseline answers "was the list narrowed?" without maintaining a
        // second list of filter inputs that a future filter could drift out of sync with.
        $this->storeReelsAreFiltered = count($query->getQuery()->wheres) > $storeOnlyWheres;

        return $query;
    }

    private function applyStoreReelSorting($query, Request $request)
    {
        return match ($request->input('sort_by')) {
            'most_viewed' => $query->orderByDesc('total_views')->latest('id'),
            'most_liked' => $query->orderByDesc('total_likes')->latest('id'),
            'most_store_visit' => $query->orderByDesc('total_store_visits')->latest('id'),
            default => $query->latest(),
        };
    }

    private function applyStoreReelStatusFilter($query, array $statuses, string $today): void
    {
        $statuses = array_values(array_diff($statuses, ['all']));
        if (empty($statuses)) {
            return;
        }

        $query->where(function ($builder) use ($statuses, $today) {
            foreach ($statuses as $status) {
                if ($status === 'deactivated') {
                    $builder->orWhere('status', 0);
                }

                if ($status === 'live') {
                    $builder->orWhere(function ($subQuery) use ($today) {
                        $subQuery->where('status', 1)
                            ->where(function ($liveQuery) use ($today) {
                                $liveQuery->where('is_always_visible', 1)
                                    ->orWhere(function ($dateQuery) use ($today) {
                                        $dateQuery->where('is_always_visible', 0)
                                            ->whereDate('start_date', '<=', $today)
                                            ->whereDate('end_date', '>=', $today);
                                    });
                            });
                    });
                }

                if ($status === 'upcoming') {
                    $builder->orWhere(function ($subQuery) use ($today) {
                        $subQuery->where('status', 1)
                            ->where('is_always_visible', 0)
                            ->whereDate('start_date', '>', $today);
                    });
                }

                if ($status === 'expired') {
                    $builder->orWhere(function ($subQuery) use ($today) {
                        $subQuery->where('status', 1)
                            ->where('is_always_visible', 0)
                            ->whereDate('end_date', '<', $today);
                    });
                }
            }
        });
    }

    private function applyStoreReelUploadDateFilter($query, Request $request): void
    {
        $filterDate = $request->input('filter_date', 'all_time');

        match ($filterDate) {
            'this_week' => $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
            'this_month' => $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]),
            'custom' => $this->applyStoreReelCustomDateFilter($query, $request),
            default => null,
        };
    }

    private function applyStoreReelCustomDateFilter($query, Request $request): void
    {
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
    }

    private function getStoreReelsOverview(int $storeId, int $unfilteredReelCount): array
    {
        $reelModel = 'Modules\\ReelsModule\\Entities\\Reel';
        $reelEngagementModel = 'Modules\\ReelsModule\\Entities\\ReelEngagement';

        // One grouped aggregate instead of a count per engagement type — they all read the
        // same rows.
        $engagementTotals = $reelEngagementModel::query()
            ->whereHas('reel', fn (Builder $builder) => $builder->where('store_id', $storeId))
            ->selectRaw('type, COUNT(*) as total_count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $countOf = fn (string $type) => (int) ($engagementTotals->get($type)?->total_count ?? 0);

        return [
            'total_reels' => $unfilteredReelCount,
            'total_views' => $countOf($reelEngagementModel::TYPE_VIEW),
            'total_likes' => $countOf($reelEngagementModel::TYPE_LIKE),
            'total_store_visits' => $countOf($reelEngagementModel::TYPE_VISIT),
        ];
    }

    private function getStoreReelFilterCount(Request $request): int
    {
        $count = 0;

        if ($request->filled('status_filter')) {
            $count++;
        }

        if (!empty(array_diff(array_filter((array) $request->input('reel_status', [])), ['all']))) {
            $count++;
        }

        if ($request->filled('sort_by') && $request->input('sort_by') !== 'all') {
            $count++;
        }

        if ($request->filled('filter_date') && $request->input('filter_date') !== 'all_time') {
            $count++;
        }

        if ($request->filled('search')) {
            $count++;
        }

        return $count;
    }

    public function list(Request $request)
    {

        $data = Store::selectRaw('
        SUM(CASE WHEN EXISTS (
        SELECT 1 FROM vendors WHERE vendors.id = stores.vendor_id AND vendors.status = 1
        ) THEN 1 ELSE 0 END) as total_store,

        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_stores,

        SUM(CASE WHEN status = 0 AND EXISTS (
            SELECT 1 FROM vendors WHERE vendors.id = stores.vendor_id AND vendors.status = 1
        ) THEN 1 ELSE 0 END) as inactive_stores,

        SUM(CASE WHEN created_at >= ? AND EXISTS (
            SELECT 1 FROM vendors WHERE vendors.id = stores.vendor_id AND vendors.status = 1
        ) THEN 1 ELSE 0 END) as recent_stores
        ', [now()->subDays(30)->toDateTimeString()])
            ->where('module_id', Config::get('module.current_module_id'))
            ->first();
        $total_store = $data->total_store;
        $active_stores = $data->active_stores;
        $inactive_stores = $data->inactive_stores;
        $recent_stores = $data->recent_stores;

        $key = explode(' ', $request['search'] ?? '');

        $zone_id = $request->query('zone_id', 'all');
        $type = $request->query('type', 'all');
        $module_id = $request->query('module_id', 'all');
        $stores = Store::withStorage()->with(['vendor', 'module', 'zone:id,name', 'package:id,package_name', 'store_sub_update_application.package:id,package_name'])->whereHas('vendor', function ($query) {
            return $query->where('status', 1);
        })
            ->when(is_numeric($zone_id), function ($query) use ($zone_id) {
                return $query->where('zone_id', $zone_id);
            })
            ->when(is_numeric($module_id), function ($query) use ($request) {
                return $query->module($request->query('module_id'));
            })
            ->when($request['search'], function ($query) use ($key, $request) {
                return $query->where(function ($query) use ($key) {
                    $query->orWhereHas('vendor', function ($q) use ($key) {
                        $q->where(function ($q) use ($key) {
                            foreach ($key as $value) {
                                $q->orWhere('f_name', 'like', "%{$value}%")
                                    ->orWhere('l_name', 'like', "%{$value}%")
                                    ->orWhere('email', 'like', "%{$value}%")
                                    ->orWhere('phone', 'like', "%{$value}%");
                            }
                        });
                    })->orWhere(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('name', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%")
                                ->orWhere('phone', 'like', "%{$value}%");
                        }
                    });
                })->orderByRaw('FIELD(name, ?) DESC', [$request->search]);
            })
            ->module(Config::get('module.current_module_id'))
            ->type($type)->latest()->paginate(config('default_pagination'));
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;

        $result = OrderTransaction::where('module_id', Config::get('module.current_module_id'))
            ->selectRaw('COUNT(*) as total_transaction, SUM(admin_commission) as commission_earned')
            ->NotRefunded()
            ->first();

        $total_transaction = $result->total_transaction;
        $comission_earned = max(0, $result->commission_earned);

        $store_withdraws = WithdrawRequest::wherehas('store', function ($query) {
            $query->where('module_id', Config::get('module.current_module_id'));
        })
            ->where(['approved' => 1])
            ->sum('amount');

        return view('admin-views.vendor.list', compact('stores', 'zone', 'type', 'total_store', 'active_stores', 'inactive_stores', 'recent_stores', 'total_transaction', 'comission_earned', 'store_withdraws'));
    }

    public function pending_requests(Request $request)
    {
        $stores = $this->getNewStores($request, null);
        $zone_id = $request->query('zone_id', 'all');
        $type = $request->query('type', 'all');
        $search_by = $request->query('search_by');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;

        return view('admin-views.vendor.pending_requests', compact('stores', 'zone', 'type', 'search_by'));
    }

    private function getNewStores($request, $storeApproveStatus)
    {

        $zone_id = $request->query('zone_id', 'all');
        $search_by = $request->query('search_by');
        $key = explode(' ', $search_by ?? '');
        $type = $request->query('type', 'all');
        $module_id = $request->query('module_id', 'all');

        $stores = Store::withStorage()->with(['vendor:id,f_name,l_name,email,status,rejection_note', 'zone:id,name', 'package:id,package_name', 'store_sub_update_application.package:id,package_name'])->whereHas('vendor', function ($query) use ($storeApproveStatus) {
            return $query->where('status', $storeApproveStatus);
        })
            ->when(is_numeric($zone_id), function ($query) use ($zone_id) {
                return $query->where('zone_id', $zone_id);
            })
            ->when(is_numeric($module_id), function ($query) use ($request) {
                return $query->module($request->query('module_id'));
            })
            ->when($search_by, function ($query) use ($key) {
                return $query->where(function ($query) use ($key) {
                    $query->orWhereHas('vendor', function ($q) use ($key) {
                        $q->where(function ($q) use ($key) {
                            foreach ($key as $value) {
                                $q->orWhereAny(['f_name', 'l_name', 'email', 'phone'], 'like', "%{$value}%");
                            }
                        });
                    })->orWhere(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhereAny(['name', 'email', 'phone'], 'like', "%{$value}%");
                        }
                    });
                });
            })
            ->module(Config::get('module.current_module_id'))
            ->type($type)->latest()->paginate(config('default_pagination'));

        return $stores;
    }

    public function deny_requests(Request $request)
    {
        $search_by = $request->query('search_by');
        $zone_id = $request->query('zone_id', 'all');
        $type = $request->query('type', 'all');
        $stores = $this->getNewStores($request, 0);
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;

        return view('admin-views.vendor.deny_requests', compact('stores', 'zone', 'type', 'search_by'));
    }

    public function export(Request $request)
    {

        $key = explode(' ', $request['search'] ?? '');

        $zone_id = $request->query('zone_id', 'all');
        $module_id = $request->query('module_id', 'all');
        $stores = Store::whereHas('vendor', function ($query) {
            return $query->where('status', 1);
        })
            ->when(is_numeric($zone_id), function ($query) use ($zone_id) {
                return $query->where('zone_id', $zone_id);
            })
            ->when(is_numeric($module_id), function ($query) use ($request) {
                return $query->module($request->query('module_id'));
            })
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($query) use ($key) {
                    $query->orWhereHas('vendor', function ($q) use ($key) {
                        $q->where(function ($q) use ($key) {
                            foreach ($key as $value) {
                                $q->orWhere('f_name', 'like', "%{$value}%")
                                    ->orWhere('l_name', 'like', "%{$value}%")
                                    ->orWhere('email', 'like', "%{$value}%")
                                    ->orWhere('phone', 'like', "%{$value}%");
                            }
                        });
                    })->orWhere(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('name', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%")
                                ->orWhere('phone', 'like', "%{$value}%");
                        }
                    });
                });
            })
            ->module(Config::get('module.current_module_id'))
            ->with('vendor', 'module')
            ->orderBy('id', 'DESC')
            ->withCount(['items', 'trips', 'orders as store_orders_count' => fn ($query) => $query->StoreOrder()]);

        $stores_count = (clone $stores)->count();
        $stores = $this->streamExportRows($stores);

        $data = [
            'data' => $stores,
            'data_count' => $stores_count,
            'zone' => is_numeric($zone_id) ? Helpers::get_zones_name($zone_id) : null,
            'module' => request('module_id') ? Helpers::get_module_name(Config::get('module.current_module_id')) : null,
            'search' => $request['search'] ?? null,
            'is_rental' => $request['is_rental'] ?? 0,
        ];

        $fileName = $request->is_rental == 1 ? 'Providers' : 'Stores';

        if ($request->type == 'csv') {
            return Excel::download(new StoreListExport($data), $fileName.'.csv');
        }

        return Excel::download(new StoreListExport($data), $fileName.'.xlsx');

    }

    public function get_stores(Request $request)
    {
        $zone_ids = array_values(array_filter((array) $request->zone_ids, function ($id) {
            return is_numeric($id);
        }));

        $exclude_ids = array_values(array_filter((array) $request->exclude_ids, function ($id) {
            return is_numeric($id);
        }));

        $includeAddonProviders = $request->boolean('include_addon_providers');

        $hiddenModuleTypes = [];
        if (! ($includeAddonProviders && addon_published_status('Rental'))) {
            $hiddenModuleTypes[] = 'rental';
        }
        if (! ($includeAddonProviders && addon_published_status('Service'))) {
            $hiddenModuleTypes[] = 'service';
        }

        $data = Store::translateOnly('name')
            ->select('stores.id', 'stores.name', 'stores.zone_id', 'stores.module_id')
            ->with([
                'storeConfig',
                'zone' => fn ($query) => $query->select('id', 'name')->translateOnly('name'),
            ])
            ->when(! empty($hiddenModuleTypes), function ($query) use ($hiddenModuleTypes) {
                $query->whereHas('module', function ($q) use ($hiddenModuleTypes) {
                    $q->whereNotIn('module_type', $hiddenModuleTypes);
                });
            })
            ->when($zone_ids, function ($query) use ($zone_ids) {
                $query->whereIn('stores.zone_id', $zone_ids);
            })
            ->when($exclude_ids, function ($query) use ($exclude_ids) {
                $query->whereNotIn('stores.id', $exclude_ids);
            })
            ->when($request->module_id, function ($query) use ($request) {
                $query->where('module_id', $request->module_id);
            })
            ->when($request->show_active == 1, function ($query)  {
                $query->active();
            })
            ->when($request->module_type, function ($query) use ($request) {
                $query->whereHas('module', function ($q) use ($request) {
                    $q->where('module_type', $request->module_type);
                });
            })
            ->where('stores.name', 'like', '%'.$request->q.'%')
            ->limit(8)->get()
            ->map(function ($store) {
                return [
                    'id' => $store->id,
                    'text' => $store->name.' ('.$store->zone?->name.')',
                    'verified' => $store->verified_seller,
                ];
            });


         if (isset($request->all)) {
            $allOption = (object) [
            'id'   => $request->all  ? "all" : false,
            'text' => translate('All')
            ];

            $data->prepend($allOption);
        }

        return response()->json($data);
    }

    public function get_providers(Request $request)
    {
        $zone_ids = isset($request->zone_ids) ? (count($request->zone_ids) > 0 ? $request->zone_ids : []) : [];

        $data = Store::translateOnly('name')
            ->without('storeConfig')
            ->select('id', 'name', 'zone_id')
            ->with(['zone' => fn ($query) => $query->select('id', 'name')->translateOnly('name')])
            ->wherehas('vendor', function ($query) {
                $query->where('status', 1);
            })
            ->when(count($zone_ids) > 0, function ($query) use ($zone_ids) {
                $query->whereIn('zone_id', $zone_ids);
            })
            ->when($request->module_id, function ($query) use ($request) {
                $query->where('module_id', $request->module_id);
            })
            ->whereHas('module', function ($q) {
                $q->where('module_type', 'rental');
            })
            ->where('name', 'like', '%'.$request->q.'%')
            ->limit(8)
            ->get()
            ->map(function ($store) {
                return [
                    'id' => $store->id,
                    'text' => $store->name.' ('.$store->zone?->name.')',
                ];
            });

        if (isset($request->all)) {
            $data[] = (object) ['id' => 'all', 'text' => translate('All')];
        }

        return response()->json($data);
    }

    public function status(Store $store, Request $request)
    {
        $store->status = $request->status;
        $store->save();
        $vendor = $store->vendor;

        try {
            if ($request->status == 0) {
                $vendor->auth_token = null;
                if (isset($vendor->firebase_token) && SendNotification::channelEnabled('store', 'store_account_block', 'push_notification_status', $store?->id)) {
                    $data = NotificationMessages::accountSuspended();
                    SendNotification::pushToVendor($vendor->id, $vendor->firebase_token, $data);
                }

                if (SendNotification::canSendMail('suspend_mail_status_store', 'store', 'store_account_block', $store?->id)) {
                    SendNotification::mail($vendor?->getRawOriginal('email'), new \App\Mail\VendorStatus('suspended', $vendor?->f_name.' '.$vendor?->l_name));
                }
            } else {

                if (SendNotification::channelEnabled('store', 'store_account_unblock', 'push_notification_status', $store?->id) && isset($vendor->firebase_token)) {
                    $data = NotificationMessages::storeAccountActivated();
                    SendNotification::pushToVendor($vendor->id, $vendor->firebase_token, $data);
                }

                if (SendNotification::canSendMail('unsuspend_mail_status_store', 'store', 'store_account_unblock', $store?->id)) {
                    SendNotification::mail($vendor?->getRawOriginal('email'), new \App\Mail\VendorStatus('unsuspended', $vendor?->f_name.' '.$vendor?->l_name));
                }
            }

        } catch (\Exception $e) {
            Toastr::warning(translate('messages.Push notification failed'));
        }

        Toastr::success(translate('messages.Store status updated'));

        return back();
    }

    public function verifiedSeller(Store $store)
    {
        $status = Helpers::toggle_verified_seller($store);

        Toastr::success($status ? translate('Verified badge given') : translate('Removed verified badge'));

        return back();
    }

    public function verifiedSellerAll()
    {
        $storeIds = collect(Helpers::get_verified_seller_eligible_stores(countOnly: false, moduleId: config('module.current_module_id')))
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();

        if ($storeIds->isEmpty()) {
            Toastr::warning(translate('No data found'));

            return back();
        }

        Store::whereIn('id', $storeIds)->get()->each(function ($store) {
            Helpers::toggle_verified_seller($store, 1);
        });
        
        Helpers::deleteCacheData('verified_seller_eligible_providers_');
        Helpers::deleteCacheData('verified_seller_eligible_stores_');

        Toastr::success(translate('Verified badge given'));

        return back();
    }

    public function store_status(Store $store, Request $request)
    {
        if ($request->menu == 'schedule_order' && ! Helpers::schedule_order()) {
            Toastr::warning(translate('messages.Schedule order disabled warning'));

            return back();
        }

        if ((($request->menu == 'delivery' && $store->take_away == 0) || ($request->menu == 'take_away' && $store->delivery == 0)) && $request->status == 0) {
            Toastr::warning(translate('messages.Can not disable both take away and delivery'));

            return back();
        }

        if ((($request->menu == 'veg' && $store->non_veg == 0) || ($request->menu == 'non_veg' && $store->veg == 0)) && $request->status == 0) {
            Toastr::warning(translate('messages.Veg non veg disable warning'));

            return back();
        }
        if ($request->menu == 'self_delivery_system' && $request->status == '0') {
            $store['free_delivery'] = 0;
        }

        if (in_array($request->menu, ['halal_tag_status', 'can_edit_order', 'can_edit_booking', 'manage_service_setup', 'show_reviews_provider_panel', 'service_booking_status', 'choose_service_location', 'serviceman_permission'])) {
            $conf = StoreConfig::firstOrNew(
                ['store_id' => $store->id]
            );
            $conf[$request->menu] = $request->status;
            $conf->save();
            Toastr::success(translate('messages.Store settings updated'));

            return back();
        }

        if (!$request->menu || !Schema::hasColumn($store->getTable(), $request->menu)) {
            Toastr::error(translate('messages.Invalid store setting'));

            return back();
        }

        $store[$request->menu] = $request->status;
        $store->save();
        Toastr::success(translate('messages.Vendor settings updated'));

        return back();
    }

    public function website_builder_status(Store $store, Request $request)
    {
        $store->storeConfig()->updateOrInsert(
            [
                'store_id' => $store->id,
            ],
            [
                'website_builder_status' => $request->status,
            ]
        );
        Toastr::success(translate('messages.Vendor settings updated'));

        return back();
    }

    public function discountSetup(Store $store, Request $request)
    {
        $message = $store->discount ? translate('Updated successfully') : translate('Added successfully');
        $store->discount()->updateOrinsert(
            [
                'store_id' => $store->id,
            ],
            [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'min_purchase' => $request->min_purchase != null ? $request->min_purchase : 0,
                'max_discount' => $request->max_discount != null ? $request->max_discount : 0,
                'discount' => $request->discount_type == 'amount' ? $request->discount : $request['discount'],
                'discount_type' => 'percent',
            ]
        );

        // updateOrinsert() above is a raw query-builder upsert -- it never touches an Eloquent
        // Discount instance, so Discount's own InvalidatesCacheTrait boot hook never fires for
        // this, the only place a store-wide discount is actually written. Without this, every
        // cache tagged 'store' (api.items_popular, api.service_popular, api.stores_top_offer,
        // etc.) keeps serving whatever discount state was true when it was first cached, for up
        // to its full TTL, regardless of what the vendor just changed.
        ApiCache::bust('store');

        return response()->json(['message' => $message], 200);
    }

    public function updateStoreSettings(Store $store, Request $request)
    {
        if ($request?->tab == 'business_plan') {
            $store->comission = $request->comission_status ? $request->comission : null;
            $store->save();
            Toastr::success(translate('messages.Commission updated'));

            return back();
        }
        $request->validate([
            'minimum_order' => 'required|numeric|min:0.01',
            'minimum_delivery_time' => 'required|min:1|max:2',
            'maximum_delivery_time' => 'required|min:1|max:2|gt:minimum_delivery_time',
        ]);

        $store->minimum_order = $request->minimum_order;
        $store->order_place_to_schedule_interval = $request->order_place_to_schedule_interval;
        $store->delivery_time = $request->minimum_delivery_time.'-'.$request->maximum_delivery_time.' '.$request->delivery_time_type;
        $store->veg = (bool) ($request->veg_non_veg == 'veg' || $request->veg_non_veg == 'both');
        $store->non_veg = (bool) ($request->veg_non_veg == 'non_veg' || $request->veg_non_veg == 'both');

        $store->save();
        Toastr::success(translate('messages.Store settings updated'));

        return back();
    }

    public function updateStoreMetaData(Store $store, Request $request)
    {
        if ($request->has('meta_image_deleted') && $request->meta_image_deleted == 1) {
            Helpers::check_and_delete('store/', $store->meta_image);
            $store->meta_image = null;
        }

        $store->meta_image = $request->has('meta_image') ? Helpers::update('store/', $store->meta_image, 'png', $request->file('meta_image')) : $store->meta_image;

        $store->meta_title = $request->meta_title;
        $store->meta_description = $request->meta_description;
        $store->meta_data = Helpers::formatMetaData($request->all(), $store->meta_data);
        $store->save();

        Toastr::success(translate('messages.Meta data updated'));

        return back();
    }

    public function update_application(Request $request)
    {
        $this->updateVendorApplication($request);
        Toastr::success(translate('Updated successfully'));

        return redirect(route('admin.store.pending-requests'));
    }


     private function updateVendorApplication($request)
    {
        $store = Store::findOrFail($request->id);
        $store->vendor->status = $request->status;
        $store->vendor->rejection_note = $request->rejection_note;
        $store->vendor->save();
        if ($request->status) {
            $store->status = 1;
        }

        $add_days = 1;
        if ($store?->store_sub_update_application) {
            if ($store?->store_sub_update_application && $store?->store_sub_update_application->is_trial == 1) {
                $add_days = Helpers::get_business_settings('subscription_free_trial_days', false) ?? 1;
            } elseif ($store?->store_sub_update_application && $store?->store_sub_update_application->is_trial == 0) {
                $add_days = $store?->store_sub_update_application->validity;
            }
            $store?->store_sub_update_application->update([
                'expiry_date' => Carbon::now()->addDays((int) $add_days)->format('Y-m-d'),
                'status' => 1,
            ]);
            $store->store_business_model = 'subscription';
        }
        $store->save();
        try {
            if ($request->status == 1) {
                if (SendNotification::canSendMail('approve_mail_status_store', 'store', 'store_registration_approval')) {
                    SendNotification::mail($store?->vendor?->getRawOriginal('email'), new \App\Mail\VendorSelfRegistration('approved', $store->vendor->f_name.' '.$store->vendor->l_name));
                }
            } else {
                if (SendNotification::canSendMail('deny_mail_status_store', 'store', 'store_registration_deny')) {
                    SendNotification::mail($store?->vendor?->getRawOriginal('email'), new \App\Mail\VendorSelfRegistration('denied', $store->vendor->f_name.' '.$store->vendor->l_name));
                }
            }
        } catch (\Exception $ex) {
            Log::error('admin.vendor_controller.update_vendor_application_failed', [
                'error' => $ex->getMessage(),
                'file' => $ex->getFile().':'.$ex->getLine(),
            ]);
        }

        return true;
    }



    public function cleardiscount(Store $store)
    {
        $store->discount->delete();
        Toastr::success(translate('messages.Store discount cleared'));

        return back();
    }

    public function withdraw(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];
        // The status used to live in one session key, `withdraw_status_filter`,
        // shared by all three withdraw queues — so filtering the vendor list to
        // "denied" silently filtered the delivery man and rider lists too, and
        // the URL never said which view you were looking at. It is a query
        // parameter now, like every other list screen.
        $status = $request->query('status', 'all');
        $approved_map = ['pending' => 0, 'approved' => 1, 'denied' => 2];

        // `.stores.storage` because the list now shows each store's logo, and
        // `logo_full_url` reads the `storage` relation — without it that is one
        // extra query per row.
        $withdraw_req = WithdrawRequest::with(['vendor.stores.storage', 'vendor.wallet', 'method', 'disbursementMethod'])
            ->when(isset($approved_map[$status]), function ($query) use ($approved_map, $status) {
                return $query->where('approved', $approved_map[$status]);
            })
            ->when(isset($request['search']), function ($query) use ($key) {
                return $query->whereHas('vendor', function ($query) use ($key) {
                    $query->whereHas('stores', function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('name', 'like', "%{$value}%");
                        }
                    });
                });
            })
            ->whereNotNull('vendor_id')
            ->latest()
            ->paginate(config('default_pagination'))
            ->appends($request->except('page'));

        if (! Helpers::module_permission_check('withdraw_list')) {
            return view('admin-views.wallet.withdraw-dashboard');
        }

        return view('admin-views.wallet.withdraw', [
            'withdraw_req' => $withdraw_req,
            'status' => $status,
            'summary' => $this->withdrawSummary(),
        ]);
    }

    /**
     * Request counts and money per `approved` value, for the summary strip and
     * the tab counters. One grouped query rather than a count() per tile, and
     * it deliberately ignores the status tab and the search box — those are
     * what the table itself is showing.
     *
     * The base filter matches the list above, so the tiles always
     * agree with what is on screen.
     */
    private function withdrawSummary()
    {
        return WithdrawRequest::selectRaw('approved, COUNT(*) as requests, SUM(amount) as amount')
            ->whereNotNull('vendor_id')
            ->groupBy('approved')
            ->get()
            ->keyBy('approved');
    }

    public function withdraw_export(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];
        // The status used to live in one session key, `withdraw_status_filter`,
        // shared by all three withdraw queues — so filtering the vendor list to
        // "denied" silently filtered the delivery man and rider lists too, and
        // the URL never said which view you were looking at. It is a query
        // parameter now, like every other list screen.
        $status = $request->query('status', 'all');
        $approved_map = ['pending' => 0, 'approved' => 1, 'denied' => 2];

        $withdraw_req = WithdrawRequest::with(['vendor'])
            ->when(isset($approved_map[$status]), function ($query) use ($approved_map, $status) {
                return $query->where('approved', $approved_map[$status]);
            })
            ->when(isset($request['search']), function ($query) use ($key) {
                return $query->whereHas('vendor', function ($query) use ($key) {
                    $query->whereHas('stores', function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->where('name', 'like', "%{$value}%");
                        }
                    });
                });
            })
            ->whereNotNull('vendor_id')
            ->latest()->get();

        $data = [
            'withdraw_requests' => $withdraw_req,
            'search' => $request->search ?? null,
            'request_status' => $status === 'all' ? null : $status,

        ];

        if ($request->type == 'excel') {
            return Excel::download(new StoreWithdrawTransactionExport($data), 'WithdrawRequests.xlsx');
        } elseif ($request->type == 'csv') {
            return Excel::download(new StoreWithdrawTransactionExport($data), 'WithdrawRequests.csv');
        }
    }

    public function getWithdrawDetails(Request $request)
    {
        $withdraw = WithdrawRequest::with(['vendor.stores', 'vendor.wallet', 'method', 'disbursementMethod'])->where(['id' => $request->withdraw_id])->first();

        if (! $withdraw) {
            return response()->json(['errors' => [['code' => 'withdraw', 'message' => translate('No data found')]]], 404);
        }

        return response()->json([
            'view' => view('admin-views.wallet.partials._side_view', compact('withdraw'))->render(),
        ]);
    }

    public function withdraw_search(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        $withdraw_req = WithdrawRequest::whereNotNull('vendor_id')->whereHas('vendor', function ($query) use ($key) {
            $query->whereHas('stores', function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->where('name', 'like', "%{$value}%");
                }
            });
        })->get();
        $total = $withdraw_req->count();

        return response()->json([
            'view' => view('admin-views.wallet.partials._table', compact('withdraw_req'))->render(), 'total' => $total,
        ]);
    }

    public function withdraw_view($withdraw_id, $seller_id)
    {
        $wr = WithdrawRequest::with(['vendor.stores', 'vendor.wallet', 'method'])->where(['id' => $withdraw_id])->first();

        if (! $wr) {
            Toastr::warning(translate('No data found'));

            return back();
        }

        $vendor = $wr->vendor?->stores?->first()?->module_type == 'rental' ? 'Provider' : 'store';

        return view('admin-views.wallet.withdraw-view', compact('wr', 'vendor'));
    }

    public function status_filter(Request $request)
    {
        session()->put('withdraw_status_filter', $request['withdraw_status_filter']);

        return response()->json(session('withdraw_status_filter'));
    }

    public function withdrawStatus(Request $request, $id)
    {
        $request->validate([
            'note' => 'max:200',
        ]);
        $withdraw = WithdrawRequest::findOrFail($id);
        $withdraw->approved = $request->approved;
        $withdraw->transaction_note = $request['note'];

        $wallet = StoreWallet::where('vendor_id', $withdraw->vendor_id)->first();
        if ((string) $wallet->total_earning < (string) ($wallet->total_withdrawn + $wallet->pending_withdraw)) {
            Toastr::error(translate('messages.Blalnce mismatched total earning is too low'));

            return redirect()->route('admin.transactions.store.withdraw_list');
        }

        $vendor = $withdraw->vendor;
        $store = $withdraw->vendor?->stores[0];
        $moduleType = $store?->module->module_type;

        if ($request->approved == 1) {
            $wallet->increment('total_withdrawn', $withdraw->amount);
            $wallet->decrement('pending_withdraw', $withdraw->amount);
            $withdraw->save();
            [$pushGate, $mailGate, $audience, $key, $template] = $this->withdrawNotificationSpec($moduleType, true);

            $push_notification_status = SendNotification::$pushGate($audience, $key, 'push_notification_status', $store->id);
            $push_notification_status = $push_notification_status == 1 && $vendor?->firebase_token ? 1 : 0;

            $mail_status = SendNotification::$mailGate($template, $audience, $key, $store->id);

            $this->sentWithdrawRequestNotification($withdraw, $vendor->firebase_token, $vendor->getRawOriginal('email'), 'approved', $moduleType, $push_notification_status, $mail_status);

            Toastr::success(translate('messages.Vendor withdraw request approved'));

            return redirect()->route('admin.transactions.store.withdraw_list');
        } elseif ($request->approved == 2) {
            $wallet->decrement('pending_withdraw', $withdraw->amount);
            $withdraw->save();

            [$pushGate, $mailGate, $audience, $key, $template] = $this->withdrawNotificationSpec($moduleType, false);

            $push_notification_status = SendNotification::$pushGate($audience, $key, 'push_notification_status', $store->id);
            $push_notification_status = $push_notification_status == 1 && $vendor?->firebase_token ? 1 : 0;

            $mail_status = SendNotification::$mailGate($template, $audience, $key, $store->id);

            $this->sentWithdrawRequestNotification($withdraw, $vendor->firebase_token,  $vendor->getRawOriginal('email'), 'denied', $moduleType, $push_notification_status, $mail_status);

            Toastr::info(translate('messages.Vendor withdraw request denied'));

            return redirect()->route('admin.transactions.store.withdraw_list');
        } else {
            Toastr::error(translate('No data found'));

            return back();
        }
    }

    private function withdrawNotificationSpec(mixed $moduleType, bool $approved): array
    {
        return match (true) {
            $moduleType == 'rental' => $approved
                ? ['rentalChannelEnabled', 'canSendRentalMail', 'provider', 'provider_withdraw_approve', 'rental_withdraw_approve_mail_status_provider']
                : ['rentalChannelEnabled', 'canSendRentalMail', 'provider', 'provider_withdraw_rejaction', 'rental_withdraw_deny_mail_status_provider'],
            $moduleType == 'service' => $approved
                ? ['serviceChannelEnabled', 'canSendServiceMail', 'provider', 'service_provider_withdraw_approve', 'service_withdraw_approve_mail_status_provider']
                : ['serviceChannelEnabled', 'canSendServiceMail', 'provider', 'service_provider_withdraw_rejaction', 'service_withdraw_deny_mail_status_provider'],
            default => $approved
                ? ['channelEnabled', 'canSendMail', 'store', 'store_withdraw_approve', 'withdraw_approve_mail_status_store']
                : ['channelEnabled', 'canSendMail', 'store', 'store_withdraw_rejaction', 'withdraw_deny_mail_status_store'],
        };
    }

    private function sentWithdrawRequestNotification($withdraw, $token, $email, $type = 'approved', $module_type = 'all', $push_notification_status = '1', $mail_status = '1')
    {
        try {
            if ($push_notification_status == 1) {
                $data = NotificationMessages::withdrawRequestProcessed($type);
                SendNotification::pushToVendor($withdraw->vendor_id, $token, $data);
            }

            if ($mail_status == 1) {
                SendNotification::mail($email, $module_type == 'rental' && addon_published_status('Rental') ? new ProviderWithdrawRequestMail($type, $withdraw) : ($module_type == 'service' && service_addon_active() ? new ServiceProviderWithdrawRequestMail($type, $withdraw) : new WithdrawRequestMail($type, $withdraw)));
            }
        } catch (\Exception $e) {
            Log::error('admin.vendor_controller.sent_withdraw_request_notification_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }

        return true;
    }

    public function get_addons(Request $request)
    {
        $cat = AddOn::withoutGlobalScope(StoreScope::class)
            ->translateOnly('name')
            ->select('id', 'name')
            ->where(['store_id' => $request->store_id])
            ->active()
            ->get();

        $selectedIds = array_flip(array_map('strval', (array) $request->data));

        $options = [];
        foreach ($cat as $row) {
            $options[] = '<option value="'.$row->id.'"'
                .(isset($selectedIds[(string) $row->id]) ? ' selected' : '').'>'
                .e($row->name).'</option>';
        }

        return response()->json([
            'options' => implode('', $options),
        ]);
    }

    public function get_store_data(Store $store)
    {
        $store->loadMissing('storage');

        return response()->json($store);
    }

    public function store_filter($id)
    {
        if ($id == 'all') {
            if (session()->has('store_filter')) {
                session()->forget('store_filter');
            }
        } else {
            session()->put('store_filter', Store::where('id', $id)->first(['id', 'name']));
        }

        return back();
    }

    public function get_account_data(Store $store)
    {
        $store->loadMissing('vendor.wallet');

        $wallet = $store->vendor->wallet;
        $cash_in_hand = 0;
        $balance = 0;

        if ($wallet) {
            $cash_in_hand = $wallet->collected_cash;
            $balance = $wallet->total_earning;
        }

        return response()->json(['cash_in_hand' => $cash_in_hand, 'earning_balance' => $balance], 200);

    }

    public function bulk_import_index()
    {
        return view('admin-views.vendor.bulk-import', [
            'summary' => Helpers::bulkDataSummary($this->moduleVendorQuery()),
        ]);
    }

    public function bulk_import_data(Request $request)
    {
        $request->validate([
            'products_file' => 'required|max:'.(MAX_FILE_SIZE * 1024),
        ]);
        try {
            $collections = (new FastExcel)->import($request->file('products_file'));
        } catch (\Exception $exception) {
            Toastr::error(translate('messages.You have uploaded a wrong format file'));

            return back();
        }
        $duplicate_phones = $collections->duplicates('phone');
        $duplicate_emails = $collections->duplicates('email');

        if ($duplicate_emails->isNotEmpty()) {
            Toastr::error(translate('messages.Duplicate data on column') . ': ' . translate('messages.email'));

            return back();
        }

        if ($duplicate_phones->isNotEmpty()) {
            Toastr::error(translate('messages.Duplicate data on column') . ': ' . translate('Phone'));

            return back();
        }

        $email = $collections->pluck('email')->toArray();
        $phone = $collections->pluck('phone')->toArray();

        if ($request->button == 'import') {

            if ($collections->isEmpty()) {
                Toastr::error(translate('Please upload a file with valid data'));
                return back();
            }

            if (Store::whereIn('email', $email)->orWhereIn('phone', $phone)->exists()
            ) {
                Toastr::error(translate('messages.Email or phone exists'));

                return back();
            }

            $vendors = [];
            $stores = [];
            $vendor = Vendor::orderBy('id', 'desc')->first('id');
            $vendor_id = $vendor ? $vendor->id : 0;
            $store = Store::orderBy('id', 'desc')->first('id');
            $store_id = $store ? $store->id : 0;
            $store_ids = [];
            foreach ($collections as $key => $collection) {
                if ($collection['ownerFirstName'] === '' || $collection['storeName'] === '' || $collection['phone'] === ''
                || $collection['email'] === '' || $collection['latitude'] === '' || $collection['longitude'] === ''
                || $collection['zone_id'] === '' || $collection['DeliveryTime'] === '' || $collection['logo'] === '') {
                    Toastr::error(translate('messages.Please fill all required fields'));

                    return back();
                }
                if (isset($collection['DeliveryTime']) && explode('-', (string) $collection['DeliveryTime'])[0] > explode('-', (string) $collection['DeliveryTime'])[1]) {
                    Toastr::error('messages.max_delivery_time_must_be_greater_than_min_delivery_time');

                    return back();
                }
                if (isset($collection['Comission']) && ($collection['Comission'] < 0 || $collection['Comission'] > 100)) {
                    Toastr::error('messages.Comission_must_be_in_0_to_100');

                    return back();
                }

                if (isset($collection['latitude']) && ($collection['latitude'] < -90 || $collection['latitude'] > 90)) {
                    Toastr::error('messages.latitude_must_be_in_-90_to_90');

                    return back();
                }
                if (isset($collection['longitude']) && ($collection['longitude'] < -180 || $collection['longitude'] > 180)) {
                    Toastr::error('messages.longitude_must_be_in_-180_to_180');

                    return back();
                }
                if (isset($collection['MinimumDeliveryFee']) && ($collection['MinimumDeliveryFee'] < 0)) {
                    Toastr::error('messages.Enter_valid_Minimum_Delivery_Fee');

                    return back();
                }
                if (isset($collection['MinimumOrderAmount']) && ($collection['MinimumOrderAmount'] < 0)) {
                    Toastr::error('messages.Enter_valid_Minimum_Order_Amount');

                    return back();
                }
                if (isset($collection['PerKmDeliveryFee']) && ($collection['PerKmDeliveryFee'] < 0)) {
                    Toastr::error('messages.Enter_valid_Per_Km_Delivery_Fee');

                    return back();
                }
                if (isset($collection['MaximumDeliveryFee']) && ($collection['MaximumDeliveryFee'] < 0)) {
                    Toastr::error('messages.Enter_valid_Maximum_Delivery_Fee');

                    return back();
                }

                array_push($vendors, [
                    'id' => $vendor_id + $key + 1,
                    'f_name' => $collection['ownerFirstName'],
                    'l_name' => $collection['ownerLastName'],
                    'password' => bcrypt(12345678),
                    'phone' => $collection['phone'],
                    'email' => $collection['email'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                array_push($stores, [
                    'name' => $collection['storeName'],
                    'phone' => $collection['phone'],
                    'email' => $collection['email'],
                    'logo' => $collection['logo'],
                    'cover_photo' => $collection['CoverPhoto'],
                    'latitude' => $collection['latitude'],
                    'longitude' => $collection['longitude'],
                    'address' => $collection['Address'],
                    'zone_id' => $collection['zone_id'],
                    'module_id' => $collection['module_id'],
                    'minimum_order' => $collection['MinimumOrderAmount'],
                    'comission' => $collection['Comission'],

                    'delivery_time' => (isset($collection['DeliveryTime']) && preg_match('([0-9]+[\-][0-9]+\s[min|hours|days])', $collection['DeliveryTime'])) ? $collection['DeliveryTime'] : '30-40 min',
                    'minimum_shipping_charge' => $collection['MinimumDeliveryFee'],
                    'per_km_shipping_charge' => $collection['PerKmDeliveryFee'],
                    'maximum_shipping_charge' => $collection['MaximumDeliveryFee'],
                    'schedule_order' => $collection['ScheduleOrder'] == 'yes' ? 1 : 0,
                    'status' => $collection['Status'] == 'active' ? 1 : 0,
                    'self_delivery_system' => $collection['SelfDeliverySystem'] == 'active' ? 1 : 0,
                    'veg' => $collection['Veg'] == 'yes' ? 1 : 0,
                    'non_veg' => $collection['NonVeg'] == 'yes' ? 1 : 0,
                    'free_delivery' => $collection['FreeDelivery'] == 'yes' ? 1 : 0,
                    'take_away' => $collection['TakeAway'] == 'yes' ? 1 : 0,
                    'delivery' => $collection['Delivery'] == 'yes' ? 1 : 0,
                    'reviews_section' => $collection['ReviewsSection'] == 'active' ? 1 : 0,
                    'pos_system' => $collection['PosSystem'] == 'active' ? 1 : 0,
                    'active' => $collection['storeOpen'] == 'yes' ? 1 : 0,
                    'featured' => $collection['FeaturedStore'] == 'yes' ? 1 : 0,
                    'vendor_id' => $vendor_id + $key + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($module = Module::select('module_type')->where('id', $collection['module_id'])->first()) {
                    if (config('module.'.$module->module_type)) {
                        $store_ids[] = $store_id + $key + 1;
                    }
                }

            }


            try {
                DB::beginTransaction();

                $chunkSize = 100;
                $chunk_stores = array_chunk($stores, $chunkSize);
                $chunk_vendors = array_chunk($vendors, $chunkSize);

                foreach ($chunk_stores as $key => $chunk_store) {
                    DB::table('vendors')->insert($chunk_vendors[$key]);
                    foreach ($chunk_store as $store) {
                        $insertedId = DB::table('stores')->insertGetId($store);
                        Helpers::updateStorageTable(get_class(new Store), $insertedId, $store['logo']);
                        Helpers::updateStorageTable(get_class(new Store), $insertedId, $store['cover_photo']);
                        app(StoreService::class)->createSchedule($insertedId);
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                info(["line___{$e->getLine()}", $e->getMessage()]);
                Toastr::error(translate('messages.Failed to import data'));

                return back();
            }

            Toastr::success(translate('messages.Store imported successfully'));

            return back();
        }

        if (Store::whereIn('email', $email)->orWhereIn('phone', $phone)->doesntExist()
        ) {
            Toastr::error(translate('messages.Email or phone doesnt exist at the database'));

            return back();
        }

        $vendors = [];
        $stores = [];
        $vendor = Vendor::orderBy('id', 'desc')->first('id');
        $vendor_id = $vendor ? $vendor->id : 0;
        $store = Store::orderBy('id', 'desc')->first('id');
        $store_id = $store ? $store->id : 0;
        $store_ids = [];
        foreach ($collections as $key => $collection) {
            if ($collection['id'] === '' || $collection['ownerId'] === '' || $collection['ownerFirstName'] === '' || $collection['storeName'] === '' || $collection['phone'] === ''
            || $collection['email'] === '' || $collection['latitude'] === '' || $collection['longitude'] === ''
            || $collection['zone_id'] === '' || $collection['DeliveryTime'] === '' || $collection['logo'] === '') {
                Toastr::error(translate('messages.Please fill all required fields'));

                return back();
            }
            if (isset($collection['DeliveryTime']) && explode('-', (string) $collection['DeliveryTime'])[0] > explode('-', (string) $collection['DeliveryTime'])[1]) {
                Toastr::error('messages.max_delivery_time_must_be_greater_than_min_delivery_time');

                return back();
            }
            if (isset($collection['Comission']) && ($collection['Comission'] < 0 || $collection['Comission'] > 100)) {
                Toastr::error('messages.Comission_must_be_in_0_to_100');

                return back();
            }

            if (isset($collection['latitude']) && ($collection['latitude'] < -90 || $collection['latitude'] > 90)) {
                Toastr::error('messages.latitude_must_be_in_-90_to_90');

                return back();
            }
            if (isset($collection['longitude']) && ($collection['longitude'] < -180 || $collection['longitude'] > 180)) {
                Toastr::error('messages.longitude_must_be_in_-180_to_180');

                return back();
            }
            if (isset($collection['MinimumDeliveryFee']) && ($collection['MinimumDeliveryFee'] < 0)) {
                Toastr::error('messages.Enter_valid_Minimum_Delivery_Fee');

                return back();
            }
            if (isset($collection['MinimumOrderAmount']) && ($collection['MinimumOrderAmount'] < 0)) {
                Toastr::error('messages.Enter_valid_Minimum_Order_Amount');

                return back();
            }
            if (isset($collection['PerKmDeliveryFee']) && ($collection['PerKmDeliveryFee'] < 0)) {
                Toastr::error('messages.Enter_valid_Per_Km_Delivery_Fee');

                return back();
            }
            if (isset($collection['MaximumDeliveryFee']) && ($collection['MaximumDeliveryFee'] < 0)) {
                Toastr::error('messages.Enter_valid_Maximum_Delivery_Fee');

                return back();
            }

            array_push($vendors, [
                'id' => $collection['ownerId'],
                'f_name' => $collection['ownerFirstName'],
                'l_name' => $collection['ownerLastName'],
                'password' => bcrypt(12345678),
                'phone' => $collection['phone'],
                'email' => $collection['email'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            array_push($stores, [
                'id' => $collection['id'],
                'name' => $collection['storeName'],
                'phone' => $collection['phone'],
                'email' => $collection['email'],
                'logo' => $collection['logo'],
                'cover_photo' => $collection['CoverPhoto'],
                'latitude' => $collection['latitude'],
                'longitude' => $collection['longitude'],
                'address' => $collection['Address'],
                'zone_id' => $collection['zone_id'],
                'module_id' => $collection['module_id'],
                'minimum_order' => $collection['MinimumOrderAmount'],
                'comission' => $collection['Comission'],

                'delivery_time' => (isset($collection['DeliveryTime']) && preg_match('([0-9]+[\-][0-9]+\s[min|hours|days])', $collection['DeliveryTime'])) ? $collection['DeliveryTime'] : '30-40 min',
                'minimum_shipping_charge' => $collection['MinimumDeliveryFee'],
                'per_km_shipping_charge' => $collection['PerKmDeliveryFee'],
                'maximum_shipping_charge' => $collection['MaximumDeliveryFee'],
                'schedule_order' => $collection['ScheduleOrder'] == 'yes' ? 1 : 0,
                'status' => $collection['Status'] == 'active' ? 1 : 0,
                'self_delivery_system' => $collection['SelfDeliverySystem'] == 'active' ? 1 : 0,
                'veg' => $collection['Veg'] == 'yes' ? 1 : 0,
                'non_veg' => $collection['NonVeg'] == 'yes' ? 1 : 0,
                'free_delivery' => $collection['FreeDelivery'] == 'yes' ? 1 : 0,
                'take_away' => $collection['TakeAway'] == 'yes' ? 1 : 0,
                'delivery' => $collection['Delivery'] == 'yes' ? 1 : 0,
                'reviews_section' => $collection['ReviewsSection'] == 'active' ? 1 : 0,
                'pos_system' => $collection['PosSystem'] == 'active' ? 1 : 0,
                'active' => $collection['storeOpen'] == 'yes' ? 1 : 0,
                'featured' => $collection['FeaturedStore'] == 'yes' ? 1 : 0,
                'vendor_id' => $collection['id'],
                'updated_at' => now(),
            ]);
        }

        try {
            $chunkSize = 100;
            $chunk_stores = array_chunk($stores, $chunkSize);
            $chunk_vendors = array_chunk($vendors, $chunkSize);

            DB::beginTransaction();

            foreach ($chunk_stores as $key => $chunk_store) {
                $syncStoreIds = [];
                DB::table('vendors')->upsert($chunk_vendors[$key], ['id', 'email', 'phone'], ['f_name', 'l_name', 'password']);
                foreach ($chunk_store as $store) {
                    if (isset($store['id']) && DB::table('stores')->where('id', $store['id'])->exists()) {
                        DB::table('stores')->where('id', $store['id'])->update($store);
                        $syncStoreIds[] = $store['id'];
                        Helpers::updateStorageTable(get_class(new Store), $store['id'], $store['logo']);
                        Helpers::updateStorageTable(get_class(new Store), $store['id'], $store['cover_photo']);
                    } else {
                        $insertedId = DB::table('stores')->insertGetId($store);
                        Helpers::updateStorageTable(get_class(new Store), $insertedId, $store['logo']);
                        Helpers::updateStorageTable(get_class(new Store), $insertedId, $store['cover_photo']);
                    }
                }
                // DB::table() writes fire no model events, so StoreObserver did not run; an
                // imported zone change would otherwise leave the store's items in the old zone.
                StoreObserver::syncItemZones($syncStoreIds);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            info(["line___{$e->getLine()}", $e->getMessage()]);
            Toastr::error(translate('messages.Failed to import data'));

            return back();
        }

        Toastr::success(translate('messages.Store imported successfully'));

        return back();
    }

    public function bulk_export_index()
    {
        return view('admin-views.vendor.bulk-export', [
            'summary' => Helpers::bulkDataSummary($this->moduleVendorQuery()),
        ]);
    }

    /**
     * Vendors that own a store in the module being worked in — the same set the
     * bulk export writes out, so the id and date bounds on the form match it.
     */
    private function moduleVendorQuery()
    {
        return Vendor::whereHas('stores', function ($query) {
            $query->where('module_id', Config::get('module.current_module_id'));
        });
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
        if($request->type == 'id_wise'){
            $vendors = Vendor::with(['stores'])->whereBetween('id', [$request['start_id'], $request['end_id']])->whereHas('stores', function ($q) {
                return $q->where('module_id', Config::get('module.current_module_id'));
            })->get();
            if($vendors->isEmpty()){
                Toastr::error(translate('Please provide valid ID range'));
                return back();
            }
        }
        $vendors = Vendor::query()
            ->with(['stores'])
            ->when($request['type'] == 'date_wise', function ($query) use ($request) {
                $query->whereBetween('created_at', [$request['from_date'].' 00:00:00', $request['to_date'].' 23:59:59']);
            })
            ->when($request['type'] == 'id_wise', function ($query) use ($request) {
                $query->whereBetween('id', [$request['start_id'], $request['end_id']]);
            })->whereHas('stores', function ($q) {
                return $q->where('module_id', Config::get('module.current_module_id'));
            })
            ->get();

        return (new FastExcel(app(StoreService::class)->getExportData(Helpers::Export_generator($vendors))))->download('Stores.xlsx');
    }

    public function add_schedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'store_id' => 'required',
        ], [
            'end_time.after' => translate('messages.End time must be after the start time'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $temp = StoreSchedule::where('day', $request->day)->where('store_id', $request->store_id)
            ->where(function ($q) use ($request) {
                return $q->where(function ($query) use ($request) {
                    return $query->where('opening_time', '<=', $request->start_time)->where('closing_time', '>=', $request->start_time);
                })->orWhere(function ($query) use ($request) {
                    return $query->where('opening_time', '<=', $request->end_time)->where('closing_time', '>=', $request->end_time);
                });
            })
            ->first();

        if (isset($temp)) {
            return response()->json(['errors' => [
                ['code' => 'time', 'message' => translate('messages.Schedule overlapping warning')],
            ]]);
        }

        $store = Store::find($request->store_id);
        $store_schedule = app(StoreService::class)->createSchedule($request->store_id, [$request->day], $request->start_time, $request->end_time.':59');

        return response()->json([
            'view' => view('admin-views.vendor.view.partials._schedule', compact('store'))->render(),
        ]);
    }

    public function remove_schedule($store_schedule)
    {
        $schedule = StoreSchedule::find($store_schedule);
        if (! $schedule) {
            return response()->json([], 404);
        }
        $store = $schedule->store;
        $schedule->delete();

        return response()->json([
            'view' => view('admin-views.vendor.view.partials._schedule', compact('store'))->render(),
        ]);
    }

    public function featured(Request $request)
    {
        $store = Store::findOrFail($request->store);
        $store->featured = $request->status;
        $store->save();
        Toastr::success(translate('messages.Store featured status updated'));

        return back();
    }

    public function conversation_list(Request $request)
    {

        $user = UserInfo::where('vendor_id', $request->user_id)->first();

        $conversations = Conversation::with(['sender', 'receiver', 'last_message'])->WhereUser($user->id);

        if ($request->query('key') != null) {
            $key = explode(' ', $request->input('key'));
            $conversations = $conversations->where(function ($qu) use ($key) {

                $qu->whereHas('sender', function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->where('f_name', 'like', "%{$value}%")->orWhere('l_name', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%");
                    }
                })->orWhereHas('receiver', function ($query1) use ($key) {
                    foreach ($key as $value) {
                        $query1->where('f_name', 'like', "%{$value}%")->orWhere('l_name', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%");
                    }
                });
            });
        }

        $conversations = $conversations->paginate(8);

        $view = view('admin-views.vendor.view.partials._conversation_list', compact('conversations'))->render();

        return response()->json(['html' => $view]);
    }

    public function conversation_view($conversation_id, $user_id)
    {
        $convs = Message::where(['conversation_id' => $conversation_id])->get();
        $conversation = Conversation::find($conversation_id);
        $user = UserInfo::find($user_id);

        if (! $conversation || ! $user) {
            return response()->json(['errors' => [['code' => 'conversation', 'message' => translate('No data found')]]], 404);
        }

        $receiver = UserInfo::find($conversation->receiver_id);

        return response()->json([
            'view' => view('admin-views.vendor.view.partials._conversations', compact('convs', 'user', 'receiver'))->render(),
        ]);
    }

    public function cash_export(Request $request, $type, $store_id)
    {
        $store = Store::with('vendor')->find($store_id);
        $account = AccountTransaction::where('from_type', 'store')->where('from_id', $store->id)->where('type', 'collected')->get();
        $data = [
            'data' => $account,
            'search' => $request['search'] ?? null,
            'is_provider' => $request['provider_id'] ?? null,
        ];
        if ($type == 'csv') {
            return Excel::download(new StoreCashTransactionExport($data), 'CashTransaction.csv');
        }

        return Excel::download(new StoreCashTransactionExport($data), 'CashTransaction.xlsx');
    }

    public function order_export(Request $request, $type, $store_id)
    {
        $store = Store::with('vendor')->find($store_id);

        if ($request['provider_id']) {
            $fileName = 'Trip';
            $account = TripTransaction::where('provider_id', $store->vendor->id)->latest()->get();
        } else {
            $fileName = 'Order';
            $account = OrderTransaction::where('vendor_id', $store->vendor->id)->latest()->get();
        }

        $data = [
            'data' => $account,
            'search' => $request['search'] ?? null,
            'is_provider' => $request['provider_id'] ?? null,
        ];

        if ($type == 'csv') {
            return Excel::download(new StoreOrderTransactionExport($data), $fileName.'Transaction.csv');
        }

        return Excel::download(new StoreOrderTransactionExport($data), $fileName.'Transaction.xlsx');
    }

    public function withdraw_trans_export(Request $request, $type, $store_id)
    {
        $store = Store::with('vendor')->find($store_id);
        $account = WithdrawRequest::where('vendor_id', $store->vendor->id)->get();

        $data = [
            'data' => $account,
            'search' => $request['search'] ?? null,
            'is_provider' => $request['provider_id'] ?? null,
        ];
        if ($type == 'csv') {
            return Excel::download(new StoreWiseWithdrawTransactionExport($data), 'WithdrawTransaction.csv');
        }

        return Excel::download(new StoreWiseWithdrawTransactionExport($data), 'WithdrawTransaction.xlsx');

    }

    public function store_wise_reviwe_export(Request $request)
    {
        $store = Store::where('id', $request->id)->first();

        if (!$store) {
            Toastr::error(translate('No data found'));

            return back();
        }

        $reviews = $store->reviews()->with([
            'item' => fn ($query) => $query->withoutGlobalScope(StoreScope::class),
            'customer',
        ])->latest()->get();
        $store_reviews = app(\App\Services\Store\StoreService::class)->calculateRating($store['rating']);
        $data = [
            'store_name' => $store->name,
            'store_id' => $store->id,
            'rating' => $store_reviews['rating'],
            'total_reviews' => $store_reviews['total'],
            'data' => $reviews,
        ];
        if ($request->type == 'csv') {
            return Excel::download(new StoreWiseItemReviewExport($data), 'StoreWiseItemReview.csv');
        }

        return Excel::download(new StoreWiseItemReviewExport($data), 'StoreWiseItemReview.xlsx');
    }

    public function recommended_store()
    {
        $key = explode(' ', request()->search ?? '');
        $stores = Store::withStorage()->withcount(['orders', 'items'])->with('storeConfig', 'zone:id,name')->where('module_id', Config::get('module.current_module_id'))
            ->wherehas('storeConfig', function ($q) {
                $q->where('is_recommended_deleted', 0);
            })
            ->when(request()->search, function ($q) use ($key) {
                $q->where(function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->where('name', 'like', "%{$value}%");
                    }
                    $query->orWhereHas('translations', function ($query) use ($key) {
                        $query->where(function ($q) use ($key) {
                            foreach ($key as $value) {
                                $q->where('value', 'like', "%{$value}%");
                            }
                        });
                    });
                });
            })
            ->paginate(config('default_pagination'));

        $shuffle_recommended_store = DataSetting::where(['key' => 'shuffle_recommended_store', 'type' => Config::get('module.current_module_id')])?->first()?->value;

        return view('admin-views.vendor.recommended_store_list', compact('stores', 'shuffle_recommended_store'));
    }

    public function recommended_store_add(Request $request)
    {
        $request->validate([
            'selected_store_ids' => 'required',
        ], [
            'selected_store_ids.required' => translate('Please select a store'),
        ]);
        $ids = explode(',', $request['selected_store_ids']);
        $ids = array_unique($ids);

        foreach ($ids as $id) {
            StoreConfig::updateOrInsert(['store_id' => $id], [
                'is_recommended' => 1,
                'is_recommended_deleted' => 0,
            ]);
        }
        Toastr::success(translate('Added successfully'));

        return back();
    }

    public function recommended_store_remove($id)
    {
        StoreConfig::updateOrInsert(['store_id' => $id], [
            'is_recommended_deleted' => 1,
        ]);
        Toastr::success(translate('messages.Store is removed from the recommended list'));

        return back();
    }

    public function recommended_store_status($id, $status)
    {
        StoreConfig::updateOrInsert(['store_id' => $id], [
            'is_recommended' => $status,
        ]);
        Toastr::success(translate('messages.Store recommendation status updated'));

        return back();
    }

    public function get_all_stores(Request $request)
    {
        $stores = Store::withStorage()->withcount(['orders', 'items'])->where('module_id', Config::get('module.current_module_id'))
            ->when($request->boolean('exclude_recommended'), function ($q) {
                $q->whereDoesntHave('storeConfig', function ($sub) {
                    $sub->where('is_recommended', 1)->where('is_recommended_deleted', 0);
                });
            })
            ->search($request['name'], ['translations' => 'value'])
            ->take(6)
            ->get()
            ->map(function ($stores) {
                $stores->ratings = app(StoreService::class)->calculateRating($stores['rating']);
                unset($stores['rating']);

                return $stores;
            });

        return response()->json([
            'result' => view('admin-views.vendor.partials._search_store', compact('stores'))->render(),
        ]);
    }

    public function selected_stores(Request $request)
    {
        $id = $request->id ?? [];
        $id = array_unique($id);

        $stores = Store::withStorage()->whereIn('id', $id)->where('module_id', Config::get('module.current_module_id'))
            ->get(['id', 'name', 'rating', 'logo'])
            ->map(function ($stores) {
                $stores->ratings = app(StoreService::class)->calculateRating($stores['rating']);
                unset($stores['rating']);

                return $stores;
            });

        return response()->json([
            'result' => view('admin-views.vendor.partials._selected_store', compact('stores'))->render(),
        ]);
    }

    public function shuffle_recommended_store($status)
    {
        $data = DataSetting::firstOrNew(
            ['key' => 'shuffle_recommended_store',
                'type' => Config::get('module.current_module_id')],
        );
        $data->value = $status == 1 ? 0 : 1;
        $data->save();

        Toastr::success(translate('messages.Store shuffle status updated'));

        return back();
    }

    public function get_store_ratings(Request $request)
    {

        $data = ['review' => 4.7, 'rating' => 2];

        if (! $request->store_id) {
            return response()->json($data);
        }

        $store = Store::where('id', $request->store_id)->first();
        if (! $store) {
            return response()->json($data);
        }
        $review = (int) $store->reviews_comments()->count();
        $reviewsInfo = $store->reviews()
            ->selectRaw('avg(reviews.rating) as average_rating, count(reviews.id) as total_reviews, items.store_id')
            ->groupBy('items.store_id')
            ->first();

        $rating = (float) $reviewsInfo?->average_rating ?? 0;

        $data = ['review' => round($review,1), 'rating' => round($rating,1)];

        return response()->json($data);
    }
}
