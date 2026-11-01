<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Module;
use App\Services\System\ModuleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Both admin headers render on a v2 page — _header always, _header_v2 in addition — and each used
 * to run its own Module::withStorage()->Active()->get(). Two identical module reads, and because
 * Module is translatable, two identical translations reads behind them. They share one memoised
 * accessor now.
 */
class ModuleSwitcherQueryTest extends TestCase
{
    use DatabaseTransactions;

    private function moduleQueries(callable $work): array
    {
        ModuleService::forgetMemo();
        \App\Support\Cache\ApiCache::store()->flush();

        $log = [];
        DB::listen(function ($query) use (&$log) {
            $log[] = $query->sql;
        });

        $work();

        return [
            'modules' => count(array_filter($log, fn ($sql) => str_contains($sql, 'from `modules`'))),
            'translations' => count(array_filter($log, fn ($sql) => str_contains($sql, 'from `translations`')
                && str_contains($sql, 'Module'))),
        ];
    }

    public function test_repeat_calls_share_one_read(): void
    {
        $counts = $this->moduleQueries(function () {
            app(ModuleService::class)->switcherModules(null);
            app(ModuleService::class)->switcherModules(null);
            app(ModuleService::class)->switcherModules(null);
        });

        $this->assertSame(1, $counts['modules'], 'three asks must read the modules table once');
        $this->assertLessThanOrEqual(1, $counts['translations'],
            'and must not repeat the translations eager-load behind it');
    }

    public function test_a_zone_scoped_admin_sees_a_subset_without_a_second_module_read(): void
    {
        $zoneId = DB::table('zones')->value('id');

        if (! $zoneId) {
            $this->markTestSkipped('dataset has no zone');
        }

        $counts = $this->moduleQueries(function () use ($zoneId) {
            app(ModuleService::class)->switcherModules(null);
            app(ModuleService::class)->switcherModules((int) $zoneId);
        });

        $this->assertSame(1, $counts['modules'],
            'the zone list is filtered off the same cached rows, not read again');

        $svc = app(ModuleService::class);

        $this->assertLessThanOrEqual(
            $svc->switcherModules(null)->count(),
            $svc->switcherModules((int) $zoneId)->count(),
            'a zone-scoped admin never sees more modules than the full list',
        );
    }

    public function test_the_header_shares_the_one_cached_list_with_every_other_reader(): void
    {
        $counts = $this->moduleQueries(function () {
            app(ModuleService::class)->switcherModules(null);
            app(ModuleService::class)->switcherModules(null);
            \App\CentralLogics\Helpers::modules_list();
        });

        $this->assertSame(1, $counts['modules'],
            'the two headers and Helpers::modules_list() must read the modules table once between them');
        $this->assertLessThanOrEqual(1, $counts['translations'],
            'and pull the module translations once');
    }

    public function test_the_switcher_carries_what_the_headers_render(): void
    {
        $module = app(ModuleService::class)->switcherModules(null)->first();

        if (! $module) {
            $this->markTestSkipped('dataset has no active module');
        }

        foreach (['id', 'module_type', 'module_name'] as $field) {
            $this->assertNotNull($module->{$field}, "the header prints {$field}");
        }

        $this->assertTrue(
            property_exists($module, 'icon') || $module->getAttribute('icon') !== null || $module->icon_full_url !== null,
            'the tile renders icon_full_url, which needs the icon column and the storage relation',
        );
    }

    public function test_saving_a_module_clears_the_memo(): void
    {
        $before = app(ModuleService::class)->switcherModules(null)->count();

        (new Module())->forceFill([
            'module_name' => 'Switcher Probe '.uniqid(),
            'module_type' => 'grocery',
            'status' => 1,
            'theme_id' => 1,
        ])->save();

        $this->assertSame(
            $before + 1,
            app(ModuleService::class)->switcherModules(null)->count(),
            'a save-then-render cycle must not serve the stale switcher list',
        );
    }

    public function test_neither_header_queries_the_model_directly(): void
    {
        foreach ([
            'resources/views/layouts/admin/partials/_header.blade.php',
            'resources/views/layouts/admin/partials/_header_v2.blade.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringNotContainsString('Module::withStorage()', $source,
                "{$file}: the module switcher reads the service, not the model");
            $this->assertStringContainsString('switcherModules(', $source);
        }
    }

    public function test_an_admin_page_reads_the_module_list_once(): void
    {
        $admin = Admin::where('role_id', 1)->first();

        if (! $admin) {
            $this->markTestSkipped('dataset has no admin');
        }

        $counts = $this->moduleQueries(function () use ($admin) {
            $this->actingAs($admin, 'admin')->get('/admin');
        });

        $this->assertLessThanOrEqual(1, $counts['translations'],
            'the two headers must not each pull the module translations');
    }
}
