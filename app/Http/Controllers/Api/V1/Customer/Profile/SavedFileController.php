<?php

namespace App\Http\Controllers\Api\V1\Customer\Profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Profile\PrescriptionStoreRequest;
use App\Http\Resources\Customer\Profile\SavedFileResource;
use App\Services\Customer\UserFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedFileController extends BaseApiController
{
    private const FILE_ERROR_CODE = 'saved_images';

    public function __construct(
        private readonly UserFileService $userFileService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $files = $this->userFileService->getList(
            filters: ['user_id' => $request->user()->id],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => SavedFileResource::collection($files),
            'pagination' => $this->paginateFormatter($files),
        ]);
    }

    public function store(PrescriptionStoreRequest $request): JsonResponse
    {
        $userId = $request->user()->id;
        $incoming = $request->savedImages();
        $existing = $this->userFileService->prescriptionCount($userId);

        if (($existing + count($incoming)) > UserFileService::MAX_PRESCRIPTION_FILES) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                [
                    'code' => self::FILE_ERROR_CODE,
                    'message' => translate('You can save maximum ' . UserFileService::MAX_PRESCRIPTION_FILES . ' prescription files'),
                ],
            ]);
        }

        try {
            $saved = $this->userFileService->storePrescriptions($userId, $incoming);
        } catch (\Throwable $exception) {
            return $this->responseFormatter(config('response.default_500'), errors: [
                ['code' => 'file_upload_failed', 'message' => translate('messages.Something went wrong')],
            ]);
        }

        return $this->responseFormatter(config('response.default_store_201'), SavedFileResource::collection($saved));
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $this->userFileService->deletePrescriptions($request->user()->id);

        return $this->responseFormatter(config('response.default_delete_200'));
    }
}
