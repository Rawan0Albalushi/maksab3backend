<?php

namespace Modules\AI\app\Http\Controllers\Api\V1\Vendor\Product;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Modules\AI\app\Exceptions\AIServiceException;
use Modules\AI\app\Http\Requests\Vendor\Product\GeneralAndPriceDataRequest;
use Modules\AI\app\Http\Requests\Vendor\Product\ImageAnalysisRequest;
use Modules\AI\app\Http\Requests\Vendor\Product\TitleAndDescriptionRequest;
use Modules\AI\app\Http\Requests\Vendor\Product\TitleSuggestionsRequest;
use Modules\AI\app\Http\Requests\Vendor\Product\VariationDataRequest;
use Modules\AI\app\Http\Resources\Vendor\Product\GenerationResource;
use Modules\AI\app\Services\Products\Action\AiUsageService;
use Modules\AI\app\Services\Products\Action\ProductAutoFillService;
use Modules\AI\app\Services\Products\Response\ProductResponse;
use Modules\AI\app\Traits\ConversationTrait;
use App\Support\Storage\FileStorage;

class ProductAutoFillController extends BaseApiController
{
    use ConversationTrait;

    private const LIMIT_CODE = 'order';

    private const SERVICE_CODE = 'ai_service';

    private const IMAGE_DIR = 'product/ai_product_image';

    private const FOOD_MODULE = 'food';

    public function __construct(
        private readonly ProductAutoFillService $productAutoFillService,
        private readonly ProductResponse $productResponse,
        private readonly AiUsageService $aiUsageService,
    ) {
    }

    public function generateTitleAndDescription(TitleAndDescriptionRequest $request): JsonResponse
    {
        $payload = $request->payload();

        return $this->generate($request->usageContext(), 2, fn () => [
            'title' => $this->productAutoFillService->titleAutoFill($payload['name'], $payload['lang_code'], $payload['module_type']),
            'description' => $this->productAutoFillService->descriptionAutoFill($payload['name'], $payload['lang_code'], $payload['module_type']),
        ]);
    }

    public function generateGeneralAndPriceData(GeneralAndPriceDataRequest $request): JsonResponse
    {
        $payload = $request->payload();

        return $this->generate($request->usageContext(), 3, fn () => [
            'generalData' => $this->productResponse->productGeneralSetupAutoFill(
                $this->productAutoFillService->generalSetupAutoFill($payload['name'], $payload['description'], $payload['store_id'], $payload['module_type']),
                $payload['store_id'],
                $payload['module_type'],
                $payload['module_id']
            ),
            'priceData' => $this->productResponse->productPriceOthersAutoFillApi(
                $this->productAutoFillService->PriceOthersAutoFill($payload['name'], $payload['description'])
            ),
        ]);
    }

    public function generateVariationData(VariationDataRequest $request): JsonResponse
    {
        $payload = $request->payload();

        return $this->generate(
            $request->usageContext(),
            1,
            fn () => $this->productResponse->variationSetupAutoFill($this->variations($payload)),
            1
        );
    }

    public function generateTitleSuggestions(TitleSuggestionsRequest $request): JsonResponse
    {
        return $this->generate(
            $request->usageContext(),
            1,
            fn () => $this->productResponse->generateTitleSuggestions(
                $this->productAutoFillService->generateTitleSuggestions($request->keywords())
            )
        );
    }

    public function generateTitleFromImage(ImageAnalysisRequest $request): JsonResponse
    {
        // Counted as one image use against the token's store; it ran with no store and no type, so
        // the image quota was never checked or spent.
        $usage = ['store_id' => $request->input('vendor')?->stores[0]?->id, 'request_type' => 'image'];

        return $this->generate($usage, 0, function () use ($request) {
            $imageName = FileStorage::upload(dir: self::IMAGE_DIR, image: $request->file('image'));

            try {
                return ['title' => $this->productAutoFillService->imageAnalysisAutoFill(
                    imageUrl: $this->ai_product_image_full_path($imageName)
                )];
            } finally {
                FileStorage::delete(dir: self::IMAGE_DIR.'/', old_image: $imageName);
            }
        }, 1);
    }

    private function generate(array $usage, int $sectionUses, callable $work, int $imageUses = 0): JsonResponse
    {
        if ($this->demoLimitReached('restricted_ip_')) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('Demo mode allows limited uses of this feature, then it is disabled.') . ' ' . translate('Usage limit') . ': 10',
                self::LIMIT_CODE
            );
        }

        $storeId = $usage['store_id'] ?? null;
        $requestType = $usage['request_type'] ?? null;

        if ($message = $this->aiUsageService->limitMessage($storeId, $requestType)) {
            return $this->errorResponse(config('response.forbidden_403'), $message, self::LIMIT_CODE);
        }

        try {
            $content = $work();
        } catch (AIServiceException|InvalidArgumentException $exception) {
            return $this->errorResponse(
                ['message' => $exception->getMessage()] + config('response.unprocessable_entity_422'),
                $exception->getMessage(),
                self::SERVICE_CODE
            );
        }

        $this->aiUsageService->recordUsage($storeId, $requestType, $sectionUses, $imageUses);

        return $this->responseFormatter(config('response.default_200'), new GenerationResource($content));
    }

    private function variations(array $payload): string
    {
        return $payload['module_type'] === self::FOOD_MODULE
            ? $this->productAutoFillService->variationSetupAutoFill($payload['name'], $payload['description'])
            : $this->productAutoFillService->otherVariationSetupAutoFill($payload['name'], $payload['description'], $payload['module_type']);
    }

}
