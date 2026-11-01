<?php

namespace App\Services\Order;

use App\Mail\RefundRequest;
use App\Models\Refund;
use App\Services\Admin\AdminService;
use App\Traits\System\UploadsImageCollectionTrait;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\Notification\SendNotification;

class RefundService extends BaseService
{
    use UploadsImageCollectionTrait;

    private const IMAGE_DIR = 'refund/';

    public function findRefundable(array $filters = []): mixed
    {
        return app(OrderService::class)->findRefundable($filters['user_id'] ?? null, $filters['order_id'] ?? null);
    }

    public function create(mixed $order, array $data = []): Refund
    {
        $refund = new Refund;
        $refund->order_id = $order->id;
        $refund->user_id = $order->user_id;
        $refund->order_status = $order->order_status;
        $refund->refund_status = 'pending';
        $refund->refund_method = $data['refund_method'] ?? 'wallet';
        $refund->customer_reason = $data['customer_reason'] ?? null;
        $refund->customer_note = $data['customer_note'] ?? null;
        // No delivery-related charge is refunded — base, surge (folded into delivery_charge) and
        // the express/slightly-delay premium (its own delivery_type_charge column) alike.
        // Deducting delivery_charge alone left delivery_type_charge untouched, so an express
        // order's refund came out too HIGH by exactly the premium the customer paid for and
        // received (TC_357) — the two live in separate columns and the old formula only read one.
        $refund->refund_amount = round(
            $order->order_amount - $order->delivery_charge - $order->delivery_type_charge - $order->dm_tips,
            config('round_up_to_digit')
        );
        $refund->image = json_encode($this->uploadImageCollection($data['images'] ?? [], self::IMAGE_DIR));

        $order->order_status = 'refund_requested';
        $order->refund_requested = now();

        DB::transaction(function () use ($refund, $order) {
            $refund->save();
            $order->save();
        });

        $this->notifyAdmin($order);

        return $refund;
    }



    private function notifyAdmin(mixed $order): void
    {
        $admin = app(AdminService::class)->findSuperAdmin();

        if (! config('mail.status') || ! $admin?->email) {
            return;
        }

        if (! SendNotification::mailTemplateEnabled('refund_request_mail_status_admin')) {
            return;
        }

        if (! SendNotification::channelEnabled('admin', 'order_refund_request', 'mail_status')) {
            return;
        }

        try {
            SendNotification::mail($admin->getRawOriginal('email'), new RefundRequest($order->id));
        } catch (\Exception $exception) {
            Log::error('Refund request mail failed', ['order_id' => $order->id, 'message' => $exception->getMessage()]);
        }
    }
}
