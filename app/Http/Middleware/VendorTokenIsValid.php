<?php

namespace App\Http\Middleware;

use App\Support\ApiEnvelope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\Vendor;
use App\Models\VendorEmployee;

class VendorTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = (string) $request->bearerToken();
        if(strlen($token)<1)
        {
            return response()->json(
                ApiEnvelope::make(config('response.unauthorized_401'), null, ApiEnvelope::singleError('auth-001', 'Unauthorized.')),
                401
            );
        }
        if (!$request->hasHeader('vendorType')) {
            $errors = [];
            array_push($errors, ['code' => 'vendor_type', 'message' => translate('messages.Vendor type required')]);
            return response()->json(ApiEnvelope::make(config('response.forbidden_403'), null, $errors), 403);
        }
        $vendor_type= $request->header('vendorType');
        if($vendor_type == 'owner'){
            $vendor = Vendor::where('auth_token', $token)->first();
            if(!isset($vendor))
            {
                return response()->json(
                    ApiEnvelope::make(config('response.unauthorized_401'), null, ApiEnvelope::singleError('auth-001', 'Unauthorized.')),
                    401
                );
            }
            $vendor->loadMissing(['stores.module.storage', 'stores.storage', 'stores.store_sub', 'stores.store_sub_update_application', 'stores.discount', 'wallet']);
            if($vendor->stores->isEmpty())
            {
                return response()->json(
                    ApiEnvelope::make(config('response.unauthorized_401'), null, ApiEnvelope::singleError('auth-001', 'Unauthorized.')),
                    401
                );
            }
            $request['vendor']=$vendor;
            Config::set('module.current_module_data', $vendor->stores[0]->module);
        }elseif($vendor_type == 'employee'){
            $vendor = VendorEmployee::where('auth_token', $token)->first();
            if(!isset($vendor))
            {
                return response()->json(
                    ApiEnvelope::make(config('response.unauthorized_401'), null, ApiEnvelope::singleError('auth-001', 'Unauthorized.')),
                    401
                );
            }
            if(!isset($vendor->vendor) || $vendor->vendor->stores->isEmpty())
            {
                return response()->json(
                    ApiEnvelope::make(config('response.unauthorized_401'), null, ApiEnvelope::singleError('auth-001', 'Unauthorized.')),
                    401
                );
            }
            $request['vendor']=$vendor->vendor;
            $request['vendor_employee']=$vendor;
            Config::set('module.current_module_data', $vendor->vendor->stores[0]->module);
        }else{
            // Any other vendorType used to fall through to $next() with no vendor resolved at
            // all -- a made-up header and any non-empty token reached every vendor endpoint, and
            // those that skip a store filter when the store is null acted on every store.
            return response()->json(
                ApiEnvelope::make(config('response.unauthorized_401'), null, ApiEnvelope::singleError('auth-001', 'Unauthorized.')),
                401
            );
        }
        return $next($request);
    }
}
