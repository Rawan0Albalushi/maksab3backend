<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpStoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $stores = Store::withoutGlobalScopes()
            ->with(['module:id,module_name', 'zone:id,name', 'vendor:id,f_name,l_name,email,phone'])
            ->select([
                'id', 'name', 'phone', 'email', 'logo', 'cover_photo',
                'address', 'latitude', 'longitude',
                'module_id', 'zone_id', 'vendor_id',
                'comission', 'status', 'active',
                'rating', 'total_order', 'order_count',
                'created_at',
            ])
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json($stores);
    }

    public function show(int $id): JsonResponse
    {
        $store = Store::withoutGlobalScopes()
            ->with(['module:id,module_name', 'zone:id,name', 'vendor:id,f_name,l_name,email,phone'])
            ->findOrFail($id);

        return response()->json(['data' => $store]);
    }

    public function count(): JsonResponse
    {
        $row = Store::withoutGlobalScopes()
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN status <> 1 OR status IS NULL THEN 1 ELSE 0 END) AS pending')
            ->first();

        return response()->json([
            'total' => (int) ($row->total ?? 0),
            'approved' => (int) ($row->approved ?? 0),
            'pending' => (int) ($row->pending ?? 0),
        ]);
    }
}
