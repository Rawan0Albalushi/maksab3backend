<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpDeliveryManController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $deliveryMen = DeliveryMan::withoutGlobalScopes()
            ->with(['zone:id,name', 'vehicle:id,type'])
            ->select([
                'id', 'f_name', 'l_name', 'email', 'phone', 'image',
                'zone_id', 'vehicle_id', 'store_id',
                'status', 'active', 'application_status',
                'earning', 'current_orders', 'order_count',
                'created_at',
            ])
            ->whereNull('store_id')
            ->where('earning', 0)
            ->withCount(['reviews'])
            ->withAvg('reviews', 'rating')
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json($deliveryMen);
    }

    public function show(int $id): JsonResponse
    {
        $deliveryMan = DeliveryMan::withoutGlobalScopes()
            ->with(['zone:id,name', 'vehicle:id,type'])
            ->withCount(['reviews'])
            ->withAvg('reviews', 'rating')
            ->findOrFail($id);

        return response()->json(['data' => $deliveryMan]);
    }

    public function count(): JsonResponse
    {
        $count = DeliveryMan::withoutGlobalScopes()
            ->whereNull('store_id')
            ->where('earning', 0)
            ->count();

        return response()->json([
            'total' => $count,
            'employees' => $count,
        ]);
    }
}
