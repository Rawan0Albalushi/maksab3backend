<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Regenerates config/navigation-map.php from the v2 sidebar partials.
 *
 * The sidebars are the only place that already knows the panel/group/item
 * hierarchy and the URL patterns each item is active for, so they are the
 * source of truth for breadcrumbs. Run this after editing a sidebar:
 *
 *     php artisan breadcrumbs:build
 *
 * Hand-written entries belong in config/breadcrumbs.php, which the service
 * consults first — this file is overwritten wholesale on every run.
 */
class BreadcrumbMapBuild extends Command
{
    protected $signature = 'breadcrumbs:build {--dry-run : Print a summary without writing the config}';

    protected $description = 'Rebuild the breadcrumb navigation map from the v2 sidebar partials';

    /** Sidebar-local variables holding a label, per file. */
    private array $symbols = [];

    public function handle(): int
    {
        $files = $this->sidebarFiles();

        if (empty($files)) {
            $this->error('No v2 sidebar partials found.');

            return self::FAILURE;
        }

        $nodes = [];
        $sections = [];

        foreach ($files as $file) {
            $this->parse($file, $nodes, $sections);
        }

        // Longest, most literal pattern wins. A request path is matched against
        // this list in order, so `admin/store/view/*` has to be tried before
        // `admin/store*` or every store page would resolve to the list.
        // Deepest path first, then exact before wildcard, then longest. The
        // wildcard rank matters: `admin/service/booking/list` and
        // `admin/service/booking/list*` are different sidebar entries, and the
        // exact one is the page a bare request path actually asked for.
        usort($nodes, function ($a, $b) {
            return [substr_count($b['pattern'], '/'), $a['wildcards'], strlen($b['pattern'])]
                <=> [substr_count($a['pattern'], '/'), $b['wildcards'], strlen($a['pattern'])];
        });

        $seen = [];
        $nodes = array_values(array_filter($nodes, function ($n) use (&$seen) {
            $key = $n['scope'].'|'.$n['pattern'];
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;

            return true;
        }));

        $unlabelled = count(array_filter($nodes, fn ($n) => ! $n['label'] && ! $n['group']));

        $this->info(count($nodes).' patterns across '.count($sections).' sections from '.count($files).' sidebars.');
        if ($unlabelled) {
            $this->warn($unlabelled.' pattern(s) resolve to a section only — their sidebar label is computed at render time.');
        }

        if ($this->option('dry-run')) {
            $this->table(['scope', 'pattern', 'section', 'group', 'label'], array_map(fn ($n) => [
                $n['scope'], $n['pattern'], $n['section'] ?? '-', $n['group'] ? $this->show($n['group']) : '-',
                $n['label'] ? $this->show($n['label']) : '-',
            ], array_slice($nodes, 0, 40)));

            return self::SUCCESS;
        }

        file_put_contents(config_path('navigation-map.php'), $this->render($nodes, $sections));
        $this->info('Wrote config/navigation-map.php');

        return self::SUCCESS;
    }

    private function sidebarFiles(): array
    {
        return array_merge(
            glob(resource_path('views/layouts/admin/partials/_sidebar_v2*.blade.php')) ?: [],
            glob(resource_path('views/layouts/vendor/partials/_sidebar_v2*.blade.php')) ?: [],
            glob(base_path('Modules/*/Resources/views/admin/partials/_sidebar_v2*.blade.php')) ?: [],
            glob(base_path('Modules/*/Resources/views/vendor/partials/_sidebar_v2*.blade.php')) ?: [],
            glob(base_path('Modules/*/Resources/views/provider/partials/_sidebar_v2*.blade.php')) ?: []
        );
    }

    private function parse(string $file, array &$nodes, array &$sections): void
    {
        $src = file_get_contents($file);

        preg_match('/data-workspace="([^"]*)"/', $src, $m);
        $workspace = $m[1] ?? '';
        // The store shell interpolates the module type into data-workspace, so
        // it is the general vendor scope. The rental and service shells name
        // theirs literally (`vendor::service`) and are kept apart as
        // `vendor:service` — those sidebars route the same paths under their
        // own section names, and pooling them lets a store vendor's shop page
        // pick up the service panel's heading.
        $scope = str_contains($workspace, '{{')
            ? 'vendor'
            : (str_starts_with($workspace, 'vendor')
                ? rtrim('vendor:'.substr($workspace, strlen('vendor::')), ':')
                : ($workspace ?: 'module'));

        // Panel keys repeat across sidebars ("catalog" exists in food, rental
        // and service), so namespace them by the file they came from.
        $namespace = preg_replace('/^_sidebar_|\.blade\.php$/', '', basename($file));

        $this->symbols = $this->labelVariables($src);

        $lines = explode("\n", $src);
        $panel = $panelKey = $panelLabel = $group = null;
        $awaitingPanelTitle = $awaitingGroupTitle = false;
        $buffer = null;

        // A `.v2-nav-parent` is a collapsible heading over `.v2-nav-children`,
        // so for its children it plays the same role as a group header. It is
        // closed by the `</div>` sitting at the children wrapper's own indent.
        $parentLabel = null;
        $childrenIndent = null;
        $awaitingParentTitle = false;

        foreach ($lines as $line) {
            if (preg_match('/data-panel="([^"]+)"/', $line, $mm)) {
                $panel = $mm[1];
                $panelKey = $namespace.'::'.$panel;
                $panelLabel = null;
                $group = null;
                $awaitingPanelTitle = true;
            }

            if ($awaitingPanelTitle && preg_match('/class="name">\s*\{\{(.+?)\}\}/', $line, $mm)) {
                $panelLabel = $this->literal($mm[1]);
                $awaitingPanelTitle = false;
                if ($panelKey && $panelLabel && ! isset($sections[$panelKey])) {
                    // 'panel' is the rail button this section belongs to — the
                    // breadcrumb uses it to reopen that panel rather than link
                    // to a page, because a panel is a grouping, not a URL.
                    $sections[$panelKey] = ['scope' => $scope, 'panel' => $panel, 'label' => $panelLabel];
                }
            }

            // The group heading is sometimes on the button's own line and
            // sometimes on the next one.
            if (str_contains($line, 'v2-group-header')) {
                $group = $parentLabel = null;
                $awaitingGroupTitle = true;
                $awaitingParentTitle = false;
            } elseif (preg_match('/class="v2-group"/', $line)) {
                $group = $parentLabel = null;
                $awaitingGroupTitle = $awaitingParentTitle = false;
            }

            if ($awaitingGroupTitle && preg_match('/<span>\s*\{\{(.+?)\}\}\s*<\/span>/', $line, $mm)) {
                $group = $this->literal($mm[1]);
                $awaitingGroupTitle = false;
            }

            if (str_contains($line, 'v2-nav-parent')) {
                $parentLabel = $this->firstLiteral($line);
                $awaitingParentTitle = $parentLabel === null;
                $childrenIndent = null;
            }

            if ($awaitingParentTitle && str_contains($line, 'class="v2-label">')) {
                $parentLabel = $this->firstLiteral($line);
                $awaitingParentTitle = false;
            }

            if ($parentLabel !== null && str_contains($line, 'v2-nav-children')) {
                $childrenIndent = strlen($line) - strlen(ltrim($line));
            }

            if ($childrenIndent !== null && str_starts_with(ltrim($line), '</div>')
                && strlen($line) - strlen(ltrim($line)) === $childrenIndent) {
                $parentLabel = null;
                $childrenIndent = null;
            }

            // Nav items built from a PHP array of ['pat' => …, 'label' => …].
            if (preg_match("/'pat'\s*=>\s*'([^']+)'/", $line, $pm)) {
                preg_match("/'label'\s*=>\s*(translate\((?:'[^']*'|\"[^\"]*\")\))/", $line, $lm);

                // The order status lists are all one route with the status as
                // its only argument, so dropping the arguments left every one
                // of them unable to build a URL — and a crumb with no URL
                // renders as dead text.
                [$route, $args] = $this->arrayRoute($line);

                $this->push($nodes, [$pm[1]], [
                    'scope' => $scope,
                    'section' => $panelKey,
                    'group' => $parentLabel ?: $group,
                    'label' => isset($lm[1]) ? $this->literal($lm[1]) : null,
                    'route' => $route,
                    'args' => $args,
                ]);

                continue;
            }

            if (str_contains($line, 'v2-nav-item')) {
                $buffer = '';
            }

            if ($buffer === null) {
                continue;
            }

            $buffer .= $line."\n";

            if (! str_contains($line, '</a>')) {
                continue;
            }

            $item = $buffer;
            $buffer = null;

            preg_match('/class="v2-label">(.*?)<\/span>/s', $item, $lm);
            $label = isset($lm[1]) ? $this->firstLiteral($lm[1]) : null;

            // Some sidebar entries share one path and are told apart by the
            // query string (`?scheduled=1`, `?status=ongoing`). Their label
            // describes a filter the breadcrumb cannot see from the path, so it
            // is dropped and the group heading stands in — "Booking List"
            // rather than "Scheduled Bookings" on every booking page.
            if (preg_match('/class="v2-nav-item\s*\{\{(.*?)\}\}/s', $item, $cm)
                && str_contains($cm[1], 'request()')) {
                $label = null;
            }

            [$route, $args] = $this->route($item);

            $patterns = $this->patterns($item);

            // Items whose active state is computed elsewhere still get a
            // breadcrumb — fall back to the URI their own route resolves to.
            if (empty($patterns) && $route) {
                $uri = $this->uriFor($route);
                if ($uri) {
                    $patterns = [$uri];
                }
            }

            $this->push($nodes, $patterns, [
                'scope' => $scope,
                'section' => $panelKey,
                'group' => $parentLabel ?: $group,
                'label' => $label,
                'route' => $route,
                'args' => $args,
            ]);
        }
    }

    /**
     * The URL patterns a nav item is active for.
     *
     * Two forms are in use. Most sidebars test one pattern at a time with the
     * local `$is('…')` / `$sidebar->is('…')` closure. The reports sidebar also
     * defines `$any([...])` for items that stay lit across several paths — a
     * report and its sub-tab reports — and every one of those patterns is a
     * page that needs the same trail, so all of them are harvested.
     *
     * `$any($some_variable)` is deliberately not followed: those calls decide
     * which panel opens, not which item is active, and the paths they name
     * already belong to items of their own.
     *
     * @return list<string>
     */
    private function patterns(string $item): array
    {
        preg_match_all("/(?:\\\$sidebar->is|\\\$is)\(\s*'([^']+)'\s*\)/", $item, $pm);

        $patterns = $pm[1] ?? [];

        if (preg_match_all("/\\\$any\(\s*\[(.*?)\]\s*\)/s", $item, $am)) {
            foreach ($am[1] as $list) {
                preg_match_all("/'([^']+)'/", $list, $lm);
                $patterns = array_merge($patterns, $lm[1]);
            }
        }

        return array_values(array_unique($patterns));
    }

    private function push(array &$nodes, array $patterns, array $node): void
    {
        // A group heading that just restates the item is noise in a trail.
        if ($node['group'] !== null && $node['group'] === $node['label']) {
            $node['group'] = null;
        }

        foreach ($patterns as $pattern) {
            $pattern = ltrim($pattern, '/');

            if ($pattern === '') {
                continue;
            }

            $nodes[] = $node + ['pattern' => $pattern, 'wildcards' => substr_count($pattern, '*')];
        }
    }

    /**
     * A few sidebar labels are two interpolations glued together
     * (`{{ $vendor_label }} {{ translate('list') }}`). There is no single key
     * for that, so the first part that resolves becomes the crumb — the page
     * title carries the rest.
     */
    private function firstLiteral(string $html): array|string|null
    {
        if (! preg_match_all('/\{\{(.+?)\}\}/s', $html, $m)) {
            return null;
        }

        foreach ($m[1] as $chunk) {
            $resolved = $this->literal($chunk);

            if ($resolved) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * Labels the sidebar assigns to a local variable before using them, e.g.
     * `$vendor_label = $is_food ? translate('…restaurants') : translate('…Stores');`
     */
    private function labelVariables(string $src): array
    {
        preg_match_all('/\$([a-z_][a-z0-9_]*)\s*=\s*([^;]*translate\([^;]*);/i', $src, $m, PREG_SET_ORDER);

        $symbols = [];
        foreach ($m as $match) {
            $symbols[$match[1]] = trim($match[2]);
        }

        return $symbols;
    }

    /**
     * Reduce a blade expression to something re-translatable at render time.
     *
     * Returns a translation key, or — for the two module-dependent labels the
     * sidebars use — a ['module' => …, 'then' => …, 'else' => …] triple the
     * service resolves against the active module. Anything else (a model
     * attribute, a loop variable) has no stable key and resolves to null, at
     * which point the trail falls back to the group heading.
     */
    private function literal(string $expr, int $depth = 0): array|string|null
    {
        $expr = trim($expr);

        if ($depth > 3) {
            return null;
        }

        if (preg_match('/^translate\(\s*[\'"]([^\'"]+)[\'"]\s*\)$/', $expr, $m)) {
            return $m[1];
        }

        if (preg_match('/^(.+?)\s*\?\s*(translate\([^)]*\))\s*:\s*(translate\([^)]*\))$/', $expr, $m)) {
            $then = $this->literal($m[2], $depth + 1);
            $else = $this->literal($m[3], $depth + 1);

            if (! $then || ! $else) {
                return $else ?: $then;
            }

            foreach (['food' => '/is_?food/i', 'parcel' => '/is_?parcel/i'] as $module => $test) {
                if (preg_match($test, $m[1])) {
                    return ['module' => $module, 'then' => $then, 'else' => $else];
                }
            }

            return $else;
        }

        if (preg_match('/^\$([a-z_][a-z0-9_]*)$/i', $expr, $m) && isset($this->symbols[$m[1]])) {
            return $this->literal($this->symbols[$m[1]], $depth + 1);
        }

        return null;
    }

    private function route(string $item): array
    {
        if (! preg_match('/href="\{\{\s*route\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(.+?))?\)\s*\}\}/s', $item, $m)) {
            return [null, []];
        }

        $name = $m[1];

        if (! app('router')->has($name)) {
            return [null, []];
        }

        $args = [];
        if (! empty($m[2])) {
            $raw = trim($m[2]);
            // Only literal arguments survive: anything with a variable in it
            // depends on page state the breadcrumb has no access to.
            if (str_contains($raw, '$') || str_contains($raw, '(')) {
                return [$name, []];
            }
            if (preg_match_all("/'([^']*)'/", $raw, $am)) {
                $args = $am[1];
            }
        }

        return [$name, $args];
    }

    /**
     * The same extraction for a `'route' => route('name', ['arg'])` entry in a
     * PHP array of nav items, which has no href to read.
     *
     * @return array{0: ?string, 1: list<string>}
     */
    private function arrayRoute(string $line): array
    {
        if (! preg_match("/'route'\s*=>\s*route\(\s*'([^']+)'\s*(?:,\s*(.+?))?\)\s*,/", $line, $m)) {
            return [null, []];
        }

        $name = $m[1];

        if (! app('router')->has($name)) {
            return [null, []];
        }

        $raw = trim($m[2] ?? '');

        if ($raw === '' || str_contains($raw, '$') || str_contains($raw, '(')) {
            return [$name, []];
        }

        preg_match_all("/'([^']*)'/", $raw, $am);

        return [$name, $am[1] ?? []];
    }

    private function uriFor(string $name): ?string
    {
        $route = app('router')->getRoutes()->getByName($name);

        if (! $route) {
            return null;
        }

        $uri = $route->uri();

        return str_contains($uri, '{') ? preg_replace('/\{[^}]+\}/', '*', $uri) : $uri;
    }

    private function show(array|string $label): string
    {
        return is_array($label) ? $label['else'].' / '.$label['then'] : $label;
    }

    private function render(array $nodes, array $sections): string
    {
        $export = function ($value, int $indent) use (&$export) {
            $pad = str_repeat(' ', $indent);
            if (is_array($value)) {
                if (empty($value)) {
                    return '[]';
                }
                $isList = array_keys($value) === range(0, count($value) - 1);
                $out = "[\n";
                foreach ($value as $k => $v) {
                    $out .= $pad.'    '.($isList ? '' : var_export($k, true).' => ').$export($v, $indent + 4).",\n";
                }

                return $out.$pad.']';
            }

            return var_export($value, true);
        };

        $body = "<?php\n\n"
            ."/*\n"
            ."|--------------------------------------------------------------------------\n"
            ."| Breadcrumb navigation map — GENERATED FILE, DO NOT EDIT\n"
            ."|--------------------------------------------------------------------------\n"
            ."|\n"
            ."| Produced by `php artisan breadcrumbs:build` from the v2 sidebar partials.\n"
            ."| Every edit here is lost on the next run. Hand-written trails, overrides\n"
            ."| and suppressions belong in config/breadcrumbs.php instead.\n"
            ."|\n"
            ."| 'sections' maps a namespaced panel key to its heading and the rail button\n"
            ."| that reopens it. 'nodes' is an ordered list — the first pattern matching\n"
            ."| the request path wins, so it runs from most to least specific.\n"
            ."|\n"
            ."| A 'label' or 'group' may be a ['module' => …, 'then' => …, 'else' => …]\n"
            ."| triple where the sidebar names the same thing differently per module\n"
            ."| (\"Restaurants\" under food, \"Stores\" everywhere else).\n"
            ."|\n"
            ."*/\n\n"
            ."return [\n\n";

        $body .= "    'sections' => ".$export($sections, 4).",\n\n";
        $body .= "    'nodes' => ".$export(array_map(fn ($n) => [
            'pattern' => $n['pattern'],
            'scope' => $n['scope'],
            'section' => $n['section'],
            'group' => $n['group'],
            'label' => $n['label'],
            'route' => $n['route'],
            'args' => $n['args'],
        ], $nodes), 4).",\n\n];\n";

        return $body;
    }
}
