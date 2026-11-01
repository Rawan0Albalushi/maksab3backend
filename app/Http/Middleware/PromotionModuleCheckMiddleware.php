<?php

namespace App\Http\Middleware;

use App\CentralLogics\Helpers;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Refuses the Happy Hour and BOGO screens outside a module type that can run them.
 *
 * Both features need a line-item cart with priced products, so they apply to grocery, food,
 * pharmacy and ecommerce only. Parcel carries nothing to bundle or discount, and rental,
 * ride-share and service have no per-item price for a store-wide percentage to apply to.
 *
 * The sidebar already hides the entries, but hiding a link is not a rule: an admin who switches
 * module with a promotion screen open -- or anyone who types the URL -- would otherwise reach a
 * form that can only produce an offer no customer could be served.
 *
 * The two panels answer "which module am I in?" differently, and the guard has to ask both. The
 * admin panel is scoped by the header's switcher, which CurrentModule middleware puts into config;
 * that middleware is applied to admin routes only, so on the vendor side there is nothing in
 * config at all and the module comes from the store's own row.
 *
 * The capability itself lives in config/module.php beside stock and add_on, where every other
 * per-type rule is stated.
 */
class PromotionModuleCheckMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $moduleType = $this->moduleType();

        if ($moduleType && config('module.'.$moduleType.'.promotions')) {
            return $next($request);
        }

        return abort(404);
    }

    private function moduleType(): ?string
    {
        if ($type = Config::get('module.current_module_type')) {
            return $type;
        }

        // The API sets a different key. ModuleCheckMiddleware resolves the customer's moduleId
        // header into current_module_data, and VendorTokenIsValid puts the store's own module
        // there for a bearer-token vendor -- neither writes current_module_type, which only the
        // admin panel's CurrentModule middleware does. Without this branch every API caller fell
        // through to the vendor guard below, which is never logged in on a token request, and
        // every promotion endpoint 404'd.
        if ($type = Config::get('module.current_module_data.module_type')) {
            return $type;
        }

        if (! auth('vendor')->check()) {
            return null;
        }

        return Helpers::get_store_data()?->module?->module_type;
    }
}
