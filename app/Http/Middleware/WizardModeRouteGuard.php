<?php

namespace App\Http\Middleware;

use App\Http\Controllers\InstallController;
use App\Http\Controllers\UpdateController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the installer or updater owns routing, serve the wizard and nothing else.
 *
 * The wizard's RouteServiceProvider groups only routes/install.php or routes/update.php, so core's
 * routes/web.php, routes/admin.php and routes/vendor.php are absent. Every module's routes are
 * still registered, though -- module providers are package-discovered and register independently of
 * which provider core is running -- which leaves around 600 admin/* URLs live and answered by real
 * admin controllers. Their views are ordinary panel views: the module sidebars link to
 * route('admin.dashboard'), the auth redirect names route('home'), and neither exists. The request
 * dies as "Route [admin.dashboard] not defined" instead of showing the wizard, and routes/update.php's
 * own Route::fallback never gets the chance to redirect, because a module route matched first.
 *
 * Route::fallback cannot fix this and neither can registration order: for a concrete URI the
 * earliest matching route wins, so the module route is chosen however late the wizard registers.
 * The check therefore happens after routing instead -- this sits in the `web` group, which every
 * module's web routes go through.
 */
class WizardModeRouteGuard
{
    /**
     * The controllers that are the wizard itself. Everything else is panel or add-on code.
     */
    private const WIZARD_CONTROLLERS = [
        InstallController::class,
        UpdateController::class,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // The panel's own dashboard is the cheapest proof that routes/admin.php was loaded. When it
        // is there, this is an ordinary request and the check costs one array lookup.
        if (Route::has('admin.dashboard')) {
            return $next($request);
        }

        $entry = $this->wizardEntryRoute();

        // No wizard route either, so the panel's absence is not the wizard's doing -- a missing
        // routes file, a cached route table. Passing through leaves that failure visible rather
        // than redirecting it into a wizard that is not running.
        if ($entry === null) {
            return $next($request);
        }

        if ($this->isWizardRequest($request)) {
            return $next($request);
        }

        return redirect()->route($entry);
    }

    private function wizardEntryRoute(): ?string
    {
        foreach (['update-software', 'step0'] as $name) {
            if (Route::has($name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Matched by controller rather than by route name, so adding a step to either wizard needs no
     * change here. An unmatched route -- the wizard files' own Route::fallback closure -- is left to
     * the redirect below, which is what that closure does anyway.
     */
    private function isWizardRequest(Request $request): bool
    {
        $action = $request->route()?->getAction('controller');

        if (! is_string($action)) {
            return false;
        }

        foreach (self::WIZARD_CONTROLLERS as $controller) {
            if (str_starts_with($action, $controller)) {
                return true;
            }
        }

        return false;
    }
}
