<?php

namespace App\Services\System;

use App\CentralLogics\Helpers;
use App\Navigation\VendorNav;
use App\Services\BaseService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Builds the breadcrumb trail for the current admin or vendor request.
 *
 * The trail is derived, not declared per page: config/navigation-map.php is
 * generated from the v2 sidebars (see BreadcrumbMapBuild), so a page inherits
 * the same hierarchy the sidebar already shows it under. config/breadcrumbs.php
 * holds the hand-written parts — the workspace roots, suppressions, and trails
 * for pages that have no sidebar entry.
 *
 * Resolution is described in config/breadcrumbs.php; the last tier always
 * matches, so every page gets a trail.
 */
class BreadcrumbService extends BaseService
{
    /** @var array<string, array> Per-request memo, keyed by "panel|path|title". */
    private array $resolved = [];

    /**
     * @return array<int, array{label:string, url:?string, panel:?string, current:bool}>
     */
    public function trail(?string $pageTitle = null): array
    {
        if (! config('breadcrumbs.enabled', true)) {
            return [];
        }

        $path = trim(request()->path(), '/');
        $panel = Str::startsWith($path, 'vendor-panel') ? 'vendor' : 'admin';
        $key = $panel.'|'.$path.'|'.$pageTitle;

        return $this->resolved[$key] ??= $this->build($panel, $path, $pageTitle);
    }

    private function build(string $panel, string $path, ?string $pageTitle): array
    {
        if ($this->matchesAny(config('breadcrumbs.except', []), $path)) {
            return [];
        }

        // On the dashboard itself the trail would be a single crumb pointing at
        // the page already open. Compare against the route rather than the home
        // crumb's URL, which moves with the admin's own permissions.
        if ($path === $this->routePath($panel === 'admin' ? 'admin.dashboard' : 'vendor.dashboard')) {
            return [];
        }

        $crumbs = $this->home($panel);

        if ($panel === 'admin') {
            $crumbs = array_merge($crumbs, $this->workspace($path));
        }

        $crumbs = array_merge($crumbs, $this->body($panel, $path));

        $crumbs = $this->appendLeaf($crumbs, $pageTitle);

        return $this->finalise($crumbs, $path);
    }

    /**
     * The trail below the root: either a hand-written override or whatever the
     * sidebar map knows about this path.
     */
    private function body(string $panel, string $path): array
    {
        foreach (config('breadcrumbs.overrides', []) as $pattern => $trail) {
            if (Str::is($pattern, $path)) {
                return array_map(fn ($crumb) => $this->crumb(
                    $crumb['label'] ?? '',
                    $this->url($crumb['route'] ?? null, $crumb['args'] ?? []),
                    $crumb['panel'] ?? null
                ), $trail);
            }
        }

        $node = $this->node($panel, $path);

        if (! $node) {
            return [];
        }

        $crumbs = [];

        $section = config('navigation-map.sections.'.$node['section']);
        $section_label = $section ? $this->key($section['label'] ?? null) : null;

        if ($section_label) {
            // A section is a sidebar panel, not a page, so it gets a control
            // that reopens that panel rather than a link to an arbitrary child.
            $crumbs[] = $this->crumb($section_label, null, $section['panel'] ?? null);
        }

        $group = $this->key($node['group'] ?? null);
        $label = $this->key($node['label'] ?? null);

        // A sidebar item whose label is computed at render time (an order
        // status, a store name) has no stable key, so its group heading stands
        // in for it — "Trips" rather than nothing.
        if (! $label) {
            $label = $group;
            $group = null;
        }

        // Otherwise the group only earns a crumb when it says something the
        // section and the item do not: "Catalog › Items › List" is worth the
        // extra step, "Withdraw Management › Withdraw Requests › Vendor
        // Withdraw Requests" is the same word three times.
        if ($group && ! $this->overlaps($group, $section_label) && ! $this->overlaps($group, $label)) {
            $crumbs[] = $this->crumb($group, $this->groupUrl($node));
        }

        if ($label) {
            $crumbs[] = $this->crumb($label, $this->url($node['route'] ?? null, $node['args'] ?? []));
        }

        return $crumbs;
    }

    /**
     * Where a group heading should go.
     *
     * A sidebar group is a collapsible `<button>`, so it has no href of its own
     * and the crumb had nowhere to point — "Directory" and "Orders" rendered as
     * dead text above the very lists they name. The group's own first item is
     * that list: shortest pattern wins, the same rule the sibling search uses,
     * because the most general entry is the one the heading stands for.
     */
    private function groupUrl(array $node): ?string
    {
        $siblings = array_filter(
            config('navigation-map.nodes', []),
            fn ($other) => $other['section'] === $node['section']
                && $other['group'] === $node['group']
                && ! empty($other['route'])
                // Same reason as the sibling search: "Directory" is the store
                // list, not the form that adds a store, and `admin/store/add`
                // would otherwise win on length.
                && ! $this->isForm($other['pattern'])
        );

        if ($siblings === []) {
            return null;
        }

        usort($siblings, fn ($a, $b) => strlen($a['pattern']) <=> strlen($b['pattern']));

        $first = reset($siblings);

        return $this->url($first['route'], $first['args'] ?? []);
    }

    /**
     * A generated label is either a translation key or, where the sidebar names
     * the same thing differently per module, a ['module', 'then', 'else'] triple.
     */
    private function key(array|string|null $label): ?string
    {
        if (! is_array($label)) {
            return $label;
        }

        return Config::get('module.current_module_type') === $label['module']
            ? $label['then']
            : $label['else'];
    }

    /**
     * Whether two headings share a significant word, ignoring case, plurals and
     * punctuation. Used to spot a crumb that only restates its neighbour.
     */
    private function overlaps(?string $a, ?string $b): bool
    {
        if (! $a || ! $b) {
            return false;
        }

        $tokens = function ($value) {
            $value = strtolower(preg_replace('/^messages\./', '', (string) $value));
            $words = preg_split('/[^a-z0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return array_map(fn ($word) => rtrim($word, 's'), $words);
        };

        return (bool) array_intersect($tokens($a), $tokens($b));
    }

    /**
     * The sidebar node this page hangs off, searched within the page's own
     * workspace before the panel at large.
     */
    private function node(string $panel, string $path): ?array
    {
        $nodes = array_filter(
            config('navigation-map.nodes', []),
            fn ($node) => str_starts_with($node['scope'], 'vendor') === ($panel === 'vendor')
        );

        // Three sidebars serve the vendor panel and the layout picks between
        // them on the store's module type. They route several of the same
        // paths under section names of their own, so a store vendor opening
        // their shop must not be handed the service panel's heading.
        if ($panel === 'vendor') {
            $module = VendorNav::context()['module_type'] ?? '';
            $scope = in_array($module, ['rental', 'service'], true) ? 'vendor:'.$module : 'vendor';

            return $this->search(
                array_filter($nodes, fn ($node) => $node['scope'] === $scope),
                $path
            );
        }

        // The workspace crumb sitting above this one is decided by the path
        // alone, and a node's scope is the sidebar it came from — the same
        // vocabulary. Searching every admin sidebar at once lets a page land
        // under a section belonging to a workspace it is not in: "Reports ›
        // Tax & Compliance › Stock Update". Try the page's own workspace
        // first, and only widen when that workspace owns nothing nearby.
        if ($panel === 'admin') {
            $workspace = Helpers::admin_workspace_for_path($path);

            $scoped = array_filter($nodes, fn ($node) => $node['scope'] === $workspace);

            if ($scoped !== [] && $hit = $this->search($scoped, $path)) {
                return $hit;
            }
        }

        return $this->search($nodes, $path);
    }

    /**
     * Longest matching sidebar pattern, then the same search against each
     * parent path — so `admin/item/edit/12` falls back to the Item List node
     * even though no sidebar entry names it.
     */
    private function search(array $nodes, string $path): ?array
    {
        foreach ($nodes as $node) {
            if (Str::is($node['pattern'], $path)) {
                return $node;
            }
        }

        $segments = explode('/', $path);

        while (count($segments) > 1) {
            array_pop($segments);
            $parent = implode('/', $segments);

            foreach ($nodes as $node) {
                if (Str::is($node['pattern'], $parent)) {
                    return $node;
                }
            }

            // Nothing owns the parent path either, so look for what lives
            // under it: `admin/order/details/55` has no sidebar entry, but
            // `admin/order/list/all` sits below `admin/order` and is the list
            // that detail page came from. Shortest pattern wins — it is the
            // most general of the siblings, so an order detail lands on "All"
            // rather than on whichever status happens to sort first.
            // Never from the panel root itself — everything lives under
            // `admin/` and `vendor-panel/`, so that would match at random.
            $candidates = count($segments) < 2 ? [] : array_filter(
                $nodes,
                fn ($node) => str_starts_with($node['pattern'], $parent.'/')
            );

            // A create form is a leaf — nothing hangs off it — so it is never
            // the sibling another page came from. Without this `admin/store/
            // add` wins on length over `admin/store/list` and the denied
            // stores end up filed under the form that adds one.
            $lists = array_filter($candidates, fn ($node) => ! $this->isForm($node['pattern']));

            $candidates = $lists ?: $candidates;

            if ($candidates) {
                usort($candidates, fn ($a, $b) => strlen($a['pattern']) <=> strlen($b['pattern']));

                return reset($candidates);
            }
        }

        return null;
    }

    /**
     * Whether a sidebar pattern names a form that creates a record. Matched on
     * the last literal segment, so `admin/store/add` counts and
     * `admin/business-settings/addon-activation` does not.
     */
    private function isForm(string $pattern): bool
    {
        $last = rtrim((string) last(explode('/', $pattern)), '*');

        return in_array($last, ['add', 'add-new', 'new', 'create', 'store'], true);
    }

    private function home(string $panel): array
    {
        $home = config('breadcrumbs.home.'.$panel, []);

        $url = $panel === 'admin'
            ? Helpers::admin_landing_url()
            : $this->url($home['route'] ?? null);

        return [$this->crumb($home['label'] ?? 'messages.Dashboard', $url)];
    }

    private function workspace(string $path): array
    {
        if ($this->matchesAny(config('breadcrumbs.no_workspace', []), $path)) {
            return [];
        }

        $key = Helpers::admin_workspace_for_path($path);
        $label = config('breadcrumbs.workspaces.'.$key.'.label');

        if (! $label) {
            return [];
        }

        // The active module's own name orients better than the word "Module",
        // and it is data — so it bypasses translate().
        $module_name = $key === 'module' ? Config::get('module.current_module_name') : null;

        // Only a workspace with a dashboard of its own gets a link; the rest
        // are labels naming where the page sits. See the note in
        // config/breadcrumbs.php. A workspace the admin cannot open is still
        // shown for orientation, just not as a link.
        $url = config('breadcrumbs.workspaces.'.$key.'.dashboard')
            && Helpers::admin_can_access_workspace($key)
            ? $this->safely(fn () => Helpers::workspace_landing_url($key))
            : null;

        return [$this->crumb($module_name ?: $label, $url, null, $module_name ?: null)];
    }

    /**
     * The page title is the only thing that tells a detail or edit screen apart
     * from the list it hangs off, so it becomes the leaf — unless it just
     * repeats the crumb above it.
     */
    private function appendLeaf(array $crumbs, ?string $pageTitle): array
    {
        $title = trim((string) $pageTitle);

        if ($title === '') {
            return $crumbs;
        }

        // The dashboards title themselves with the business name, which names
        // the whole panel rather than the page.
        if ($this->sameLabel($title, Helpers::get_business_settings('business_name', false))) {
            return $crumbs;
        }

        // The title often restates a crumb further up — the Business Settings
        // page sits under a panel already called "Business Setup" — so check
        // the whole trail, not just the crumb before it.
        foreach ($crumbs as $crumb) {
            if ($this->sameLabel($crumb['label'], $title)) {
                return $crumbs;
            }
        }

        $last = end($crumbs);

        // "Items › List" plus the title "Item List" is one idea written twice.
        // Keep the fuller wording, and keep the crumb's link with it.
        if ($last && $this->contains($last['label'], $title)) {
            $crumbs[count($crumbs) - 1]['label'] = strlen($title) > strlen($last['label'])
                ? $title
                : $last['label'];

            return $crumbs;
        }

        $crumbs[] = $this->crumb($title, null, null, $title);

        return $crumbs;
    }

    /** Whether either label reads as a shortening of the other. */
    private function contains(?string $a, ?string $b): bool
    {
        $normalise = fn ($v) => ' '.trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower((string) $v))).' ';

        $a = $normalise($a);
        $b = $normalise($b);

        if (trim($a) === '' || trim($b) === '') {
            return false;
        }

        return str_contains($a, $b) || str_contains($b, $a);
    }

    /**
     * Drop the link from the crumb the admin is already on, mark it current,
     * and collapse a crumb that repeats its neighbour.
     */
    private function finalise(array $crumbs, string $path): array
    {
        $current = '/'.$path;
        $out = [];
        $targets = [];

        foreach ($crumbs as $crumb) {
            if ($crumb['url'] && parse_url($crumb['url'], PHP_URL_PATH) === $current) {
                $crumb['url'] = null;
            }

            // Two crumbs may land on one page: "Dashboard" is the panel
            // dashboard, and "Grocery" is that same dashboard for the module
            // already selected — `/admin` and `/admin?module_id=1`. Offering
            // the link twice promises the second goes somewhere else. Compared
            // on path alone for that reason: the query string is what makes
            // them look different and it selects the module already in
            // session. The earlier crumb — the one carrying the home icon —
            // keeps the link, and the later becomes a label.
            if ($crumb['url']) {
                $target = parse_url($crumb['url'], PHP_URL_PATH);

                if (in_array($target, $targets, true)) {
                    $crumb['url'] = null;
                } else {
                    $targets[] = $target;
                }
            }

            // A label repeated anywhere in the trail is the same idea twice —
            // the Stores panel above the Stores list above the Store List page.
            // Keep the first position and let it collect whichever link or
            // panel handle the later one was carrying.
            $duplicate = null;
            foreach ($out as $index => $existing) {
                if ($this->sameLabel($existing['label'], $crumb['label'])) {
                    $duplicate = $index;
                    break;
                }
            }

            if ($duplicate !== null) {
                $out[$duplicate]['url'] ??= $crumb['url'];
                $out[$duplicate]['panel'] ??= $crumb['panel'];

                continue;
            }

            $out[] = $crumb;
        }

        if (! empty($out)) {
            $last = count($out) - 1;
            $out[$last]['url'] = null;
            $out[$last]['panel'] = null;
            $out[$last]['current'] = true;
        }

        return $out;
    }

    private function crumb(string $label, ?string $url = null, ?string $panel = null, ?string $literal = null): array
    {
        return [
            // A crumb sourced from a page title is already translated copy;
            // running it back through translate() would file it as a new key.
            'label' => $literal ?? translate($label),
            'url' => $url,
            'panel' => $panel,
            'current' => false,
        ];
    }

    private function routePath(string $route): ?string
    {
        $definition = Route::getRoutes()->getByName($route);

        return $definition ? trim($definition->uri(), '/') : null;
    }

    private function url(?string $route, array $args = []): ?string
    {
        if (! $route || ! Route::has($route)) {
            return null;
        }

        return $this->safely(fn () => route($route, $args));
    }

    private function safely(callable $callback): ?string
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function matchesAny(array $patterns, string $path): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function sameLabel(?string $a, ?string $b): bool
    {
        $normalise = fn ($v) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $v));

        return $normalise($a) !== '' && $normalise($a) === $normalise($b);
    }
}
