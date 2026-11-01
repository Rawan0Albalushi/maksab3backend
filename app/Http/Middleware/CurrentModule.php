<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use App\Models\Module;
use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CurrentModule
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (request()->input('module_id')) {
            session()->put('current_module',request()->input('module_id'));
            Config::set('module.current_module_id', request()->input('module_id'));
        }else{
            Config::set('module.current_module_id', session()->get('current_module'));
        }

        $module_id = Config::get('module.current_module_id');
        $module_id = is_array($module_id)?null:$module_id;
        $module = self::markTranslationsResolved(isset($module_id)?self::currentModuleQuery()->find($module_id):self::currentModuleQuery()->active()->first());

        if ($module) {
            Config::set('module.current_module_id', $module->id);
            Config::set('module.current_module_type', $module->module_type);
            Config::set('module.current_module_name', $module->module_name);
            Config::set('module.current_module_icon', self::moduleIconUrl($module));
        }else{
            self::clearCurrentModule('settings');
        }
        if (Request::is('admin/users*')) {
            self::clearCurrentModule('users');
        }
        if (Request::is('admin/transactions*')) {
            self::clearCurrentModule('transactions');
        }
        if (Request::is('admin/dispatch*')) {
            self::clearCurrentModule('dispatch');
        }
        // `delivery-management` is a Settings path that does not sit under `business-settings`.
        // It is what picks the settings sidebar in layouts/admin/app.blade.php, so the section's
        // screens would otherwise render the module sidebar after their URLs moved (2026-09-09).
        if (Request::isAny(['admin/business-settings/*', 'admin/delivery-management/*', 'taxvat/*', 'admin/pro-customer*'])) {
            self::clearCurrentModule('settings');
        }

        $detailModuleType = null;
        $detailModuleId   = null;
        if (Request::isAny(['admin/transactions/parcel/*', 'admin/parcel/*'])) {
            $detailModuleType = 'parcel';
        } elseif (Request::isAny(['admin/transactions/rental/trip/*', 'admin/rental/*'])) {
            $detailModuleType = 'rental';
        } elseif (Request::isAny(['admin/service/*', 'admin/service'])) {
            $detailModuleType = 'service';
        } elseif (
            Request::isAny(['admin/ride-share/*', 'admin/ride-share'])
            || (Request::is('admin/transactions/ride-share/*')
                && ! Request::isAny(['admin/transactions/ride-share/report/*', 'admin/transactions/ride-share/transaction*']))
        ) {
            $detailModuleType = 'ride-share';
        }

        if ($detailModuleType) {
            $matched = ($module && $module->module_type === $detailModuleType && (int) $module->status === 1)
                ? $module
                : self::markTranslationsResolved(self::currentModuleQuery()->where('modules.module_type', $detailModuleType)->where('modules.status', 1)->first());
            if ($matched) {
                Config::set('module.current_module_id', $matched->id);
                Config::set('module.current_module_type', $matched->module_type);
                Config::set('module.current_module_name', $matched->module_name);
                Config::set('module.current_module_icon', self::moduleIconUrl($matched));
                session()->put('current_module', $matched->id);
            } else {
                Config::set('module.current_module_type', $detailModuleType);
            }
        }

        return $next($request);
    }

    private static function markTranslationsResolved(?Module $module): ?Module
    {
        return $module?->setRelation('translations', new Collection());
    }

    private static function currentModuleQuery(): Builder
    {
        return Module::withoutGlobalScopes()
            ->leftJoin('storages', function ($join) {
                $join->on('storages.data_id', '=', 'modules.id')
                    ->where('storages.data_type', Module::class)
                    ->where('storages.key', 'icon');
            })
            ->leftJoin('translations', function ($join) {
                $join->on('translations.translationable_id', '=', 'modules.id')
                    ->where('translations.translationable_type', Module::class)
                    ->where('translations.key', 'module_name')
                    ->where('translations.locale', app()->getLocale());
            })
            ->select(
                'modules.id',
                'modules.module_type',
                'modules.status',
                'modules.icon',
                'storages.value as icon_disk',
                DB::raw('COALESCE(translations.value, modules.module_name) as module_name')
            );
    }

    private static function clearCurrentModule(string $type): void
    {
        Config::set('module.current_module_id', null);
        Config::set('module.current_module_type', $type);
        Config::set('module.current_module_name', null);
        Config::set('module.current_module_icon', null);
    }

    private static function moduleIconUrl(Module $module): string
    {
        return Helpers::get_full_url(path: 'module', data: $module->icon, type: $module->icon_disk ?? 'public');
    }
}
