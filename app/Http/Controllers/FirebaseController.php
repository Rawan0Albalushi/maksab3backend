<?php

namespace App\Http\Controllers;

use App\CentralLogics\Helpers;
use Illuminate\Http\Request;

class FirebaseController extends Controller
{
    protected $messaging;

    public function __construct()
    {
        $this->messaging = app('firebase.messaging');
    }

    public function subscribeToTopic(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'topic' => 'required|string',
        ]);

        $token = $request->input('token');
        $topic = $request->input('topic');

        if (!in_array($topic, $this->allowedTopics(), true)) {
            return response()->json(['message' => 'Forbidden topic'], 403);
        }

        try {
            if($this->messaging){
                $this->messaging->subscribeToTopic($topic, $token);
                return response()->json(['message' => 'Successfully subscribed to topic'], 200);
            }
            return response()->json(['message' => 'Unauthorized'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Topics are derived from the logged-in identity instead of being trusted from
     * the request body, so a vendor cannot subscribe their device to admin_message
     * or to another store's topic.
     */
    private function allowedTopics(): array
    {
        if (auth('admin')->check()) {
            $topics = ['admin_message'];
            if (addon_published_status('RideShare')) {
                $topics[] = 'admin_safety_alert_notification';
            }
            return $topics;
        }

        if (auth('vendor')->check() || auth('vendor_employee')->check()) {
            return ['store_panel_' . Helpers::get_store_id() . '_message'];
        }

        return [];
    }
}
