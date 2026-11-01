<?php

namespace App\Http\Middleware;

use App\CentralLogics\Helpers;
use App\Navigation\VendorNav;
use Brian2694\Toastr\Facades\Toastr;
use Closure;

class ModulePermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next, $module)
    {
        if (auth('admin')->check() && Helpers::module_permission_check($module)) {
            return $next($request);
        }
        else if (auth('vendor_employee')->check() || auth('vendor')->check()) {
            // VendorNav::can() adds the unit's own conditions (module type, store
            // flags, business settings) on top of the role grant, so a page hidden
            // from the sidebar is not reachable by typing its URL either.
            if(VendorNav::can($module))
            {
                return $next($request);
            }
        }

        Toastr::error(translate('messages.Access denied'));

        if (url()->previous() == $request->fullUrl() || url()->previous() == url()->current()) {
            $fallback = auth('admin')->check() ? 'admin.dashboard' : 'vendor.dashboard';
            return redirect()->route($fallback);
        }

        return back();
    }
}
