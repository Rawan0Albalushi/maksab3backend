<?php

namespace App\Http\Controllers\Api\V1\Common\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Item\NameResource;
use App\Services\Item\AllergyService;
use App\Services\Item\GenericNameService;
use App\Services\Item\NutritionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NameListController extends BaseApiController
{
    public function __construct(
        private readonly GenericNameService $genericNameService,
        private readonly AllergyService $allergyService,
        private readonly NutritionService $nutritionService
    ) {
    }

    public function generics(Request $request): JsonResponse
    {
        return $this->nameListResponse(
            $this->genericNameService->getList($this->filters($request), $this->paginate($request)),
            'generic_name'
        );
    }

    public function allergies(Request $request): JsonResponse
    {
        return $this->nameListResponse(
            $this->allergyService->getList($this->filters($request), $this->paginate($request)),
            'allergy'
        );
    }

    public function nutritions(Request $request): JsonResponse
    {
        return $this->nameListResponse(
            $this->nutritionService->getList($this->filters($request), $this->paginate($request)),
            'nutrition'
        );
    }

    private function nameListResponse(LengthAwarePaginator $names, string $nameColumn): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'data' => collect($names->items())
                ->map(fn ($row) => (new NameResource($row, $nameColumn))->resolve())
                ->values()
                ->all(),
            'pagination' => $this->paginateFormatter($names),
        ]);
    }

    private function filters(Request $request): array
    {
        return ['search' => $request->query('search')];
    }

    private function paginate(Request $request): array
    {
        return ['per_page' => $this->perPage($request), 'page' => $this->page($request)];
    }
}
