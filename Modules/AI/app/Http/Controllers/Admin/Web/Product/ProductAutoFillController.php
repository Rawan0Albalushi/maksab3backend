<?php

namespace Modules\AI\app\Http\Controllers\Admin\Web\Product;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\StoreConfig;
use App\Rules\ImageFile;
use Illuminate\Http\Request;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\Validator;
use Modules\AI\app\Services\Products\Action\ProductAutoFillService;
use Modules\AI\app\Services\Products\Response\ProductResponse;
use Modules\AI\app\Traits\ConversationTrait;

class ProductAutoFillController extends Controller
{
    use ConversationTrait;

    public function __construct(
        private ProductAutoFillService $productAutoFillService,
        private ProductResponse $productResponse,
    ) {
        // The vendor panel's product form posts to these admin URLs too (module:item admits
        // vendors), with store_id and requestType in the form. For a vendor both are decided here:
        // its own store's quota, and the image quota for image work -- otherwise another store's
        // quota could be spent, or a request left without store_id or with another type ran free.
        $this->middleware(function ($request, $next) {
            if (auth('vendor')->check() || auth('vendor_employee')->check()) {
                $image = $request->route()?->getActionMethod() === 'analyzeImageAutoFill'
                    || $request->input('requestType') === 'image';
                $request->merge(['store_id' => Helpers::get_store_id(), 'requestType' => $image ? 'image' : 'vendor']);
            }

            return $next($request);
        });

        if (getEnvMode() == 'demo') {
            $ip = request()->header('x-forwarded-for');
            $cacheKey = 'restricted_ip_'.$ip;

            $hits = (int) ApiCache::get('demo_throttle', $cacheKey, 0);

            if ($hits >= 10) {
                abort(403, translate('Demo mode allows limited uses of this feature, then it is disabled.') . ' ' . translate('Usage limit') . ': 10');
            }

            ApiCache::put('demo_throttle', $cacheKey, $hits + 1);
        }
    }

    public function titleAutoFill(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string',
            'langCode' => 'nullable|string|max:20',
        ], [
            'name.required' => translate('Please provide a product name so the AI can generate a suitable title or description'),
            'name.max' => translate('The product name is too long.') . ' ' . translate('Character limit') . ': 255',
        ]);
        $increment = false;
        if ($request->requestType == 'vendor' && $request->store_id) {
            $StoreConfig = StoreConfig::firstOrNew(['store_id' => $request->store_id]);

            $section_wise_ai_limit = Helpers::get_business_settings('section_wise_ai_limit');
            if (($section_wise_ai_limit == 0 || $section_wise_ai_limit == null) || ($section_wise_ai_limit <= $StoreConfig?->section_wise_ai_use_count)) {
                abort(403, translate('You have reached the limit of AI usage.'));
            }
            $increment = true;
        }

        $result = $this->productAutoFillService->titleAutoFill(
            $request->name,
            $request->langCode,
            $request->module_type
        );

        if ($increment == true) {
            if ($StoreConfig->exists) {
                $StoreConfig->increment('section_wise_ai_use_count');
            } else {
                $StoreConfig->section_wise_ai_use_count = 1;
                $StoreConfig->save();
            }
        }

        return $this->productResponse->titleAutoFill($result);
    }

    public function descriptionAutoFill(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'langCode' => 'nullable|string|max:20',
        ], [
            'name.required' => translate('Please provide a product name so the AI can generate a suitable title or description'),
            'name.max' => translate('The product name is too long.') . ' ' . translate('Character limit') . ': 255',
        ]);

        $increment = false;
        if ($request->requestType == 'vendor' && $request->store_id) {
            $StoreConfig = StoreConfig::firstOrNew(['store_id' => $request->store_id]);

            $section_wise_ai_limit = Helpers::get_business_settings('section_wise_ai_limit');
            if (($section_wise_ai_limit == 0 || $section_wise_ai_limit == null) || ($section_wise_ai_limit <= $StoreConfig?->section_wise_ai_use_count)) {
                abort(403, translate('You have reached the limit of AI usage.'));
            }
            $increment = true;
        }

        $result = $this->productAutoFillService->descriptionAutoFill(
            $request->name,
            $request->langCode,
            $request->module_type
        );

        if ($increment == true) {
            if ($StoreConfig->exists) {
                $StoreConfig->increment('section_wise_ai_use_count');
            } else {
                $StoreConfig->section_wise_ai_use_count = 1;
                $StoreConfig->save();
            }
        }

        return $this->productResponse->discriptionAutoFill($result);
    }

    public function GeneralSetupAutoFill(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
        ], [
            'name.required' => translate('Please provide a default name so the AI can generate data.'),
            'name.max' => translate('The product name is too long.') . ' ' . translate('Character limit') . ': 255',
            'description.required' => translate('Please provide a default description so the AI can generate data.'),
        ]);

        $increment = false;
        if ($request->requestType == 'vendor' && $request->store_id) {
            $StoreConfig = StoreConfig::firstOrNew(['store_id' => $request->store_id]);

            $section_wise_ai_limit = Helpers::get_business_settings('section_wise_ai_limit');
            if (($section_wise_ai_limit == 0 || $section_wise_ai_limit == null) || ($section_wise_ai_limit <= $StoreConfig?->section_wise_ai_use_count)) {
                abort(403, translate('You have reached the limit of AI usage.'));
            }
            $increment = true;
        }

        $result = $this->productAutoFillService->generalSetupAutoFill(
            $request->name,
            $request->description,
            $request->store_id,
            $request->module_type
        );

        if ($increment == true) {
            if ($StoreConfig->exists) {
                $StoreConfig->increment('section_wise_ai_use_count');
            } else {
                $StoreConfig->section_wise_ai_use_count = 1;
                $StoreConfig->save();
            }
        }

        return $this->productResponse->productGeneralSetupAutoFill($result, $request->store_id, $request->module_type, $request->input('module_id'));
    }

    public function PriceOthersAutoFill(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ],
            [
                'name.required' => translate('Please provide a default name so the AI can generate data.'),
                'name.max' => translate('The product name is too long.') . ' ' . translate('Character limit') . ': 255',
                'description' => translate('Please provide a default description so the AI can generate data.'),
            ]);

        $increment = false;
        if ($request->requestType == 'vendor' && $request->store_id) {
            $StoreConfig = StoreConfig::firstOrNew(['store_id' => $request->store_id]);

            $section_wise_ai_limit = Helpers::get_business_settings('section_wise_ai_limit');
            if (($section_wise_ai_limit == 0 || $section_wise_ai_limit == null) || ($section_wise_ai_limit <= $StoreConfig?->section_wise_ai_use_count)) {
                abort(403, translate('You have reached the limit of AI usage.'));
            }
            $increment = true;
        }

        $result = $this->productAutoFillService->PriceOthersAutoFill(
            $request->name,
            $request->description,
        );

        if ($increment == true) {
            if ($StoreConfig->exists) {
                $StoreConfig->increment('section_wise_ai_use_count');
            } else {
                $StoreConfig->section_wise_ai_use_count = 1;
                $StoreConfig->save();
            }
        }

        return $this->productResponse->productPriceOthersAutoFill($result);
    }

    public function seoSectionAutoFill(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $increment = false;
        if ($request->requestType == 'vendor' && $request->store_id) {
            $StoreConfig = StoreConfig::firstOrNew(['store_id' => $request->store_id]);

            $section_wise_ai_limit = Helpers::get_business_settings('section_wise_ai_limit');
            if (($section_wise_ai_limit == 0 || $section_wise_ai_limit == null) || ($section_wise_ai_limit <= $StoreConfig?->section_wise_ai_use_count)) {
                abort(403, translate('You have reached the limit of AI usage.'));
            }
            $increment = true;
        }
        $result = $this->productAutoFillService->seoSectionAutoFill(
            $request->name,
            $request->description,
        );

        if ($increment == true) {
            if ($StoreConfig->exists) {
                $StoreConfig->increment('section_wise_ai_use_count');
            } else {
                $StoreConfig->section_wise_ai_use_count = 1;
                $StoreConfig->save();
            }
        }

        return $this->productResponse->productseoAutoFill($result);
    }

    public function variationSetupAutoFill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required',
        ], [
            'name.required' => translate('Please provide a default name so the AI can generate data.'),
            'name.max' => translate('The product name is too long.') . ' ' . translate('Character limit') . ': 255',
            'description.required' => translate('Please provide a default description so the AI can generate data.'),
        ]);
        $increment = false;
        if ($request->requestType == 'vendor' && $request->store_id) {
            $StoreConfig = StoreConfig::firstOrNew(['store_id' => $request->store_id]);

            $section_wise_ai_limit = Helpers::get_business_settings('section_wise_ai_limit');
            if (($section_wise_ai_limit == 0 || $section_wise_ai_limit == null) || ($section_wise_ai_limit <= $StoreConfig?->section_wise_ai_use_count)) {
                abort(403, translate('You have reached the limit of AI usage.'));
            }
            $increment = true;
        }

        $description = $request->input('description');
        $this->descriptionEmptyValidation($description, $validator);
        if ($validator->fails()) {
            return response()->json(
                $this->inputValidationErrors($validator->errors()->toArray()),
                422
            );
        }

        $result = $this->productAutoFillService->variationSetupAutoFill(
            $request->name,
            $request->description,
        );

        if ($increment == true) {
            if ($StoreConfig->exists) {
                $StoreConfig->increment('section_wise_ai_use_count');
            } else {
                $StoreConfig->section_wise_ai_use_count = 1;
                $StoreConfig->save();
            }
        }

        return $this->productResponse->variationSetupAutoFill($result);
    }

    public function analyzeImageAutoFill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => ImageFile::rules('required', 1024),
        ], [
        ]);
        if ($validator->fails()) {
            return response()->json(
                $this->inputValidationErrors($validator->errors()->toArray()),
                422
            );
        }

        $increment = false;
        if ($request->requestType == 'image' && $request->store_id) {
            $StoreConfig = StoreConfig::firstOrNew(['store_id' => $request->store_id]);

            $image_upload_limit_for_ai = Helpers::get_business_settings('image_upload_limit_for_ai');
            if (($image_upload_limit_for_ai == 0 || $image_upload_limit_for_ai == null) || ($image_upload_limit_for_ai <= $StoreConfig?->image_wise_ai_use_count)) {
                abort(403, translate('You have reached the limit of AI usage via image.'));
            }
            $increment = true;
        }

        if ($increment == true) {
            if ($StoreConfig->exists) {
                $StoreConfig->increment('section_wise_ai_use_count');
            } else {
                $StoreConfig->section_wise_ai_use_count = 1;
                $StoreConfig->save();
            }
        }

        $extension = $request->image->getClientOriginalExtension();
        $imageName = Helpers::upload(dir: 'product/ai_product_image', format: $extension, image: $request->image);

        $imageUrl = $this->ai_product_image_full_path($imageName);

        $result = $this->productAutoFillService->imageAnalysisAutoFill(
            imageUrl: $imageUrl,
        );

        Helpers::check_and_delete(dir: 'product/ai_product_image/', old_image: $imageName);

        return $this->productResponse->analyzeImageAutoFill($result);
    }

    public function generateTitleSuggestions(Request $request)
    {
        $validated = $request->validate([
            'keywords' => 'required|string|max:255',
        ]);
        $increment = false;
        if ($request->requestType == 'vendor' && $request->store_id) {
            $StoreConfig = StoreConfig::firstOrNew(['store_id' => $request->store_id]);

            $section_wise_ai_limit = Helpers::get_business_settings('section_wise_ai_limit');
            if (($section_wise_ai_limit == 0 || $section_wise_ai_limit == null) || ($section_wise_ai_limit <= $StoreConfig?->section_wise_ai_use_count)) {
                abort(403, translate('You have reached the limit of AI usage.'));
            }
            $increment = true;
        }
        $keywords = array_map('trim', explode(',', $request->keywords));
        $result = $this->productAutoFillService->generateTitleSuggestions($keywords);

        if ($increment == true) {
            if ($StoreConfig->exists) {
                $StoreConfig->increment('section_wise_ai_use_count');
            } else {
                $StoreConfig->section_wise_ai_use_count = 1;
                $StoreConfig->save();
            }
        }

        return $this->productResponse->generateTitleSuggestions($result);
    }

    public function getOtherVariationData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'description' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $increment_image = false;
        $increment = false;
        if ($request->store_id) {
            $StoreConfig = StoreConfig::firstOrNew(['store_id' => $request->store_id]);

            if ($request->requestType == 'image') {
                $image_upload_limit_for_ai = Helpers::get_business_settings('image_upload_limit_for_ai');
                if (($image_upload_limit_for_ai == 0 || $image_upload_limit_for_ai == null) || ($image_upload_limit_for_ai <= $StoreConfig?->image_wise_ai_use_count)) {

                    return response()->json([
                        'errors' => [
                            ['code' => 'order', 'message' => translate('You have reached the limit of AI usage via image.')],
                        ],
                    ], 403);
                }
                $increment_image = true;
            } else {
                $section_wise_ai_limit = Helpers::get_business_settings('section_wise_ai_limit');
                if (($section_wise_ai_limit == 0 || $section_wise_ai_limit == null) || ($section_wise_ai_limit <= $StoreConfig?->section_wise_ai_use_count)) {

                    return response()->json([
                        'errors' => [
                            ['code' => 'order', 'message' => translate('You have reached the limit of AI usage.')],
                        ],
                    ], 403);
                }
                $increment = true;
            }
        }

        $description = $request->input('description');
        $this->descriptionEmptyValidation($description, $validator);
        if ($validator->fails()) {
            return response()->json(
                $this->inputValidationErrors($validator->errors()->toArray()),
                422
            );
        }

        $result = $this->productAutoFillService->otherVariationSetupAutoFill(
            $request->name,
            $request->description,
            $request->module_type
        );

        if ($increment == true) {
            if ($StoreConfig->exists) {
                $StoreConfig->increment('section_wise_ai_use_count');
            } else {
                $StoreConfig->section_wise_ai_use_count = 1;
                $StoreConfig->save();
            }
        } elseif ($increment_image == true) {
            if ($StoreConfig->exists) {
                $StoreConfig->increment('image_wise_ai_use_count');
            } else {
                $StoreConfig->image_wise_ai_use_count = 1;
                $StoreConfig->save();
            }
        }

        return $this->productResponse->variationSetupAutoFill($result);
    }
}
