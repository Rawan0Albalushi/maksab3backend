<?php

namespace App\Http\Controllers\Api\V1\Common\Parcel;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\System\ReasonResource;
use App\Services\Parcel\ParcelCancellationReasonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancellationReasonController extends BaseApiController
{
    public function __construct(
        private readonly ParcelCancellationReasonService $parcelCancellationReasonService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $reasons = $this->parcelCancellationReasonService->getList(
            filters: $this->filters($request),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ReasonResource::collection($reasons),
            'pagination' => $this->paginateFormatter($reasons),
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'user_type' => $request->query('user_type'),
            'cancellation_type' => $request->query('cancellation_type'),
        ];
    }
}
