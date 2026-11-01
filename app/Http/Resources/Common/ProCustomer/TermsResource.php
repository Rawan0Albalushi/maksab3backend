<?php

namespace App\Http\Resources\Common\ProCustomer;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TermsResource extends BaseResource
{
    private const IMAGE_DIR = 'pro_customer_terms';

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'page_status' => (int) $this->resource['page_status'],
            'page_title' => $this->resource['page_title'],
            'page_description' => $this->resource['page_description'],
            'page_image_full_url' => $this->resource['page_image']
                ? Helpers::get_full_url(self::IMAGE_DIR, $this->resource['page_image'], $this->resource['page_image_disk'])
                : null,
        ]);
    }
}
