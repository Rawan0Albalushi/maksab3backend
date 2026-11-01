<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\CentralLogics\Helpers;
use Brian2694\Toastr\Facades\Toastr;

class Subscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next,$module): Response
    {
        if (auth('vendor_employee')->check() || auth('vendor')->check()) {
            $store= Helpers::get_store_data();
            if($store->store_business_model== 'commission'){
                return $next($request);
            }


            elseif($store->store_business_model == 'unsubscribed') {
                Toastr::error(translate('messages.Your subscription is expired. You can only process your on going orders.'));
                return back();
            }
            elseif($store->store_business_model == 'none') {
                Toastr::error(translate('Please chose a business plan to continue your services'));
                return back();
            }


            elseif($store->store_business_model == 'subscription') {
                    if($store->store_sub == null){
                        Toastr::error(translate('messages.You are not subscribed to any package'));
                        return back();
                    } else {
                    $store_sub=$store?->store_sub;

                    $modulePermissons = [
                        'reviews' => $store_sub?->review,
                        'pos' => $store_sub?->pos,
                        'deliveryman' => $store_sub?->self_delivery,
                        'chat' => $store_sub?->chat,
                    ];
                    if (in_array($module,['reviews','pos','deliveryman','chat']) ) {
                        if ($modulePermissons[$module] == 1) {
                            return $next($request);
                        } else {
                            Toastr::error(translate('messages.Your package does not include this section'));
                            return back();
                        }
                    }
                }
            }


        }
        return $next($request);
    }
}
