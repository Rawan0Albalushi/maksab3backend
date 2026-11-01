<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpRefundController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $refunds = Refund::with([
            'order:id,user_id,store_id,order_amount,order_status',
            'order.customer:id,f_name,l_name,email,phone',
            'order.store:id,name',
        ])
            ->select([
                'id', 'order_id', 'user_id',
                'refund_amount', 'refund_status', 'refund_method',
                'customer_reason', 'customer_note',
                'admin_note', 'image',
                'created_at', 'updated_at',
            ])
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return response()->json($refunds);
    }

    public function show(int $id): JsonResponse
    {
        $refund = Refund::with([
            'order:id,user_id,store_id,order_amount,order_status',
            'order.customer:id,f_name,l_name,email,phone',
            'order.store:id,name',
        ])
            ->findOrFail($id);

        return response()->json(['data' => $refund]);
    }

    public function count(): JsonResponse
    {
        return response()->json([
            'total' => Refund::count(),
        ]);
    }
}
