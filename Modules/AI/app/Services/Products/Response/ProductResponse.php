<?php

namespace Modules\AI\app\Services\Products\Response;

use Modules\AI\app\Services\Products\Resource\ProductResource;
use Modules\AI\app\Traits\ConversationTrait;

class ProductResponse
{
    use ConversationTrait;
    private const PRICE_OTHERS_FIELDS = [
        'unit_price',
        'minimum_order_quantity',
        'discount_amount',
    ];
    private const SEO_FIELDS = [
        'meta_title',
        'meta_description',
        'meta_index',
        'meta_no_follow',
        'meta_no_image_index',
        'meta_no_archive',
        'meta_no_snippet',
        'meta_max_snippet',
        'meta_max_snippet_value',
        'meta_max_video_preview',
        'meta_max_video_preview_value',
        'meta_max_image_preview',
        'meta_max_image_preview_value',
    ];
    protected ProductResource $ProductResource;
    public function __construct()
    {
        $this->ProductResource = new ProductResource();
    }
    public function titleAutoFill(string $result)
    {
        $response["data"]["title"] = $result;
        return response()->json($response);
    }
    public function discriptionAutoFill(string $result)
    {
        $response["data"]["description"] = $result;
        return response()->json($response);
    }
    public function productGeneralSetupAutoFill(string $result, $storeId, $moduleType = null, mixed $moduleId = null)
    {
        $resource = $this->ProductResource->productGeneralSetupData($storeId, $moduleType, $moduleId);

        $data = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        if (empty($data['category_name']) || !is_string($data['category_name'])) {
            throw new \InvalidArgumentException('The "category_name" field is required and must be a non-empty string.');
        }

        $processedData = self::productGeneralSetconvertNamesToIds($data, $resource);
        if (!$processedData['success']) {
            return $processedData;
        }
        $data = $processedData['data'];

        $fields = [
            'sub_category_name',
            'addon',
            'addonsNames',
            'nutrition',
            'allergy',
            'product_type',
            'search_tags'
        ];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                $data[$field] = null;
            }
        }


        $response['data'] = $data;
        return  $response;
    }
    public function productPriceOthersAutoFill($result)
    {
        return $this->validatedJsonResponse($result, self::PRICE_OTHERS_FIELDS);
    }
    public function productPriceOthersAutoFillApi($result)
    {
        return $this->validatedJsonPayload($result, self::PRICE_OTHERS_FIELDS);
    }
    public function productseoAutoFill($result)
    {
        return $this->validatedJsonResponse($result, self::SEO_FIELDS);
    }
    public function productseoAutoFillApi($result)
    {
        return $this->validatedJsonPayload($result, self::SEO_FIELDS);
    }
    public function variationSetupAutoFill(string $result)
    {
        $result = preg_replace('/```[a-z]*\n?|\n?```/', '', trim($result));

        return [
            'data' => json_decode($result, true),
            'status' => 'success',
        ];
    }
    public function analyzeImageAutoFill(string $result)
    {
        return $this->titleAutoFill($result);
    }
    public function generateTitleSuggestions(string $result)
    {
        return ['data' => json_decode($result, true)];
    }
    private function validatedJsonResponse($result, array $fields)
    {
        [$data, $errors] = $this->decodeAndValidate($result, $fields);

        if (!empty($errors)) {
            return response()->json(
                $this->formatAIGenerationValidationErrors($errors),
                422
            );
        }

        return response()->json(['data' => $data]);
    }
    private function validatedJsonPayload($result, array $fields)
    {
        [$data, $errors] = $this->decodeAndValidate($result, $fields);

        if (!empty($errors)) {
            return  $this->formatAIGenerationValidationErrors($errors);
        }

        return $data;
    }
    private function decodeAndValidate($result, array $fields): array
    {
        $data = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        $errors = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                $errors[$field] = "$field is required.";
            }
        }

        return [$data, $errors];
    }
}
