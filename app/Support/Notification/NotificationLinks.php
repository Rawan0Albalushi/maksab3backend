<?php

namespace App\Support\Notification;

class NotificationLinks
{
    public static function vendorPanel(string $path): string
    {
        return url('/').'/vendor-panel/'.ltrim($path, '/');
    }

    public static function adminPanel(string $path): string
    {
        return url('/').'/admin/'.ltrim($path, '/');
    }

    public static function vendorOrders(): string
    {
        return self::vendorPanel('order/list/all');
    }

    public static function vendorBookings(): string
    {
        return self::vendorPanel('booking/list/all');
    }

    public static function vendorServiceBookings(): string
    {
        return self::vendorPanel('service/booking/list');
    }

    public static function vendorCustomRequestBids(): string
    {
        return self::vendorPanel('service/custom-request/my-bids');
    }

    public static function vendorTrips(): string
    {
        return self::vendorPanel('trip/list/all');
    }

    public static function adminOrders(): string
    {
        return self::adminPanel('order/list/all');
    }

}
