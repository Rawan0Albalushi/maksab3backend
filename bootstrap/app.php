<?php

use App\Http\Middleware\ActivationCheckMiddleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AdminRentalModuleCheckMiddleware;
use App\Http\Middleware\AdminServiceModuleCheckMiddleware;
// Core Laravel web middleware
use App\Http\Middleware\APIGuestMiddleware;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CurrentModule;
use App\Http\Middleware\DmTokenIsValid;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\Erp\ErpTokenIsValid;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\Localization;
// Custom middleware
use App\Http\Middleware\LocalizationMiddleware;
use App\Http\Middleware\ModuleCheckMiddleware;
use App\Http\Middleware\PromotionModuleCheckMiddleware;
use App\Http\Middleware\ModulePermissionMiddleware;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\ProviderRentalModuleCheckMiddleware;
use App\Http\Middleware\ProviderServiceModuleCheckMiddleware;
use App\Http\Middleware\ReactValid;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SanitizeBearerToken;
use App\Http\Middleware\Subscription;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\VendorMiddleware;
use App\Http\Middleware\VendorTokenIsValid;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use League\OAuth2\Server\Exception\OAuthServerException;

// Before any provider registers, and before anything can touch app/Builder/. Core's adapters
// declare `implements Modules\Builder\Contracts\X`, which ships with the Builder add-on; an update
// package carries core only, so those interfaces can legitimately be absent for a while. Declaring a
// class whose interface is missing is a hard PHP error, so the missing contract is given an empty
// placeholder and the adapter compiles. See the class for why this guards the symbol and not the
// caller.
\App\Support\Builder\ContractFallback::register();

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        // commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {

        $middleware->web(prepend: [
            // Ahead of the whole group, so a request the wizard must not serve is turned away before
            // StartSession, VerifyCsrfToken and above all SubstituteBindings -- route model binding
            // would query for a module route's {id} while the migrations are still running.
            \App\Http\Middleware\WizardModeRouteGuard::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->use([
            // Runs first: send www↔apex of the panel host to the canonical
            // APP_HOST_DOMAIN so its domain-constrained routes match.
            \App\Http\Middleware\RedirectToCanonicalHost::class,
            // Also dropped by replacing the default stack. Behind a TLS-terminating proxy, without it
            // X-Forwarded-Proto is ignored, the request reads as http, and url()->full() hands the
            // panel's ajax regions http:// URLs the browser blocks as mixed content. Which proxies
            // to believe is config('trustedproxy.proxies'), set from TRUSTED_PROXIES.
            \Illuminate\Http\Middleware\TrustProxies::class,
            // Required for defer() — this custom global stack replaces Laravel's default,
            // which normally includes it. Without it deferred callbacks never fire.
            \Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks::class,
            \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \App\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->group('web', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            Localization::class,
            // Last, and inside StartSession: it reads the Toastr line the
            // controller just flashed. Only acts on requests carrying an inline
            // update header (`X-Status-Toggle` / `X-Inline-Update`).
            \App\Http\Middleware\StatusToggleResponse::class,
            \App\Http\Middleware\AjaxActionResponse::class,
        ]);

        $middleware->group('api', [
            // Outermost of the api group, so it sees whatever the controllers return.
            \App\Http\Middleware\EnforceApiEnvelope::class,
            SanitizeBearerToken::class,
            SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,

            'admin' => AdminMiddleware::class,
            'vendor' => VendorMiddleware::class,
            'vendor.api' => VendorTokenIsValid::class,
            'dm.api' => DmTokenIsValid::class,
            'erp.api' => ErpTokenIsValid::class,
            'serviceman.api' => \Modules\Service\Http\Middleware\ServicemanTokenIsValid::class,
            'module' => ModulePermissionMiddleware::class,
            'installation-check' => InstallationMiddleware::class,
            'actch' => ActivationCheckMiddleware::class,
            'localization' => LocalizationMiddleware::class,
            'subscription' => Subscription::class,
            'react' => ReactValid::class,
            'apiGuestCheck' => APIGuestMiddleware::class,
            'auth.basic' => AuthenticateWithBasicAuth::class,
            'cache.headers' => SetCacheHeaders::class,
            'can' => Authorize::class,
            'password.confirm' => RequirePassword::class,
            'signed' => ValidateSignature::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
            'module-check' => ModuleCheckMiddleware::class,
            'current-module' => CurrentModule::class,
            'admin-rental-module' => AdminRentalModuleCheckMiddleware::class,
            'provider-rental-module' => ProviderRentalModuleCheckMiddleware::class,
            'admin-service-module' => AdminServiceModuleCheckMiddleware::class,
            'provider-service-module' => ProviderServiceModuleCheckMiddleware::class,
            'promotion-module' => PromotionModuleCheckMiddleware::class,
            'maintenance' => MaintenanceMode::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport(OAuthServerException::class);

        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson() || $request->wantsJson();
        });

        // Every api/* failure — including a 500 — answers with the standard envelope.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            return \App\Exceptions\ApiExceptionRenderer::render($e, $request);
        });
    })

    ->create();

// $requestUri = $_SERVER['REQUEST_URI'] ?? '';
// if (!str_starts_with($requestUri, '/image-proxy')) {
//     header('Access-Control-Allow-Origin: *');
// }
// header('Access-Control-Allow-Methods: *');
// header('Access-Control-Allow-Headers: *');
