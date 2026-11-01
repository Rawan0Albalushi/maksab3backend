<?php

/*
|--------------------------------------------------------------------------
| Breadcrumbs
|--------------------------------------------------------------------------
|
| Hand-maintained half of the breadcrumb trail. The other half —
| config/navigation-map.php — is generated from the v2 sidebar partials by
| `php artisan breadcrumbs:build` and must not be edited.
|
| Resolution order for a request path, first hit wins:
|
|   1. 'except'    — render nothing at all
|   2. 'overrides' — an explicit trail written below
|   3. the generated map, exact pattern match
|   4. the generated map, walking up one path segment at a time
|   5. the workspace root alone, with the page <title> as the leaf
|
| Step 5 always resolves, so every page ends up with a trail.
|
| Labels are translation keys, resolved through translate() at render time —
| see the key standard in CLAUDE.md §8. Never call translate() in this file:
| it is loaded before the locale is known and may be config-cached.
|
*/

return [

    'enabled' => true,

    /*
    | Paths that render the panel chrome but should carry no trail.
    |
    | Empty on purpose, and checked rather than assumed: every candidate was
    | probed for the panel shell. The print sheets (`layouts.admin.print`) and
    | the service invoice (`service::layouts.invoice`) render their own layout,
    | which never includes the breadcrumb partial — so there is nothing there to
    | suppress. Everything else — POS, the in-panel invoice preview, the file
    | manager — is a page reached from the sidebar and is better off with a
    | trail than without one.
    |
    | Add a Str::is pattern here only for a page that renders the panel shell
    | and is genuinely worse for having a breadcrumb.
    */
    'except' => [],

    /*
    | Root crumb per panel. 'route' is resolved lazily; for admin the URL comes
    | from Helpers::admin_landing_url() instead, so a restricted admin lands
    | somewhere they can actually open.
    */
    'home' => [
        'admin' => ['label' => 'messages.Dashboard'],
        'vendor' => ['label' => 'messages.Dashboard', 'route' => 'vendor.dashboard'],
    ],

    /*
    | Second crumb in the admin panel — the workspace tab the page sits under.
    | Keys match Helpers::admin_workspace_for_path(). The 'module' workspace
    | uses the active module's own name when there is one, since "Grocery"
    | orients better than "Module".
    |
    | 'dashboard' says whether the workspace has a landing page of its own, and
    | it decides whether this crumb is a link or a plain label. Only three do:
    |
    |   module    admin.dashboard, per module_id
    |   users     admin.users.dashboard        (admin/users)
    |   dispatch  admin.dispatch.dashboard     (admin/dispatch)
    |
    | Finance, Reports and Settings have none. Helpers::workspace_landing_url()
    | still answers for them — the header's tabs need somewhere to go, and it
    | picks the first page the admin is allowed to open. That is the right
    | answer for a tab and the wrong one for a breadcrumb: an ancestor crumb
    | promises "up", and Finance pointing at the vendor withdraw list sends you
    | sideways to a page no more parent than the one you are on. Worse, it went
    | dead on that page and stayed a link everywhere else, so the same crumb
    | looked clickable or not depending on where you stood.
    |
    | admin/transactions is not a counter-example. It routes to
    | DashboardController@transaction_dashboard, which renders the *module*
    | dashboard view, and no sidebar or header links it.
    */
    'workspaces' => [
        'module' => ['label' => 'Module', 'dashboard' => true],
        'users' => ['label' => 'Users', 'dashboard' => true],
        'finance' => ['label' => 'Finance', 'dashboard' => false],
        'reports' => ['label' => 'Reports', 'dashboard' => false],
        'dispatch' => ['label' => 'dispatch', 'dashboard' => true],
        'settings' => ['label' => 'messages.Settings', 'dashboard' => false],
    ],

    /*
    | Paths that carry no workspace crumb at all.
    |
    | admin_workspace_for_path() answers 'module' for anything it does not
    | recognise, which is right for the header's module tab but wrong in a
    | trail: the admin's own profile and the chat inbox belong to no module —
    | neither reads config('module.current_module_id') — yet both were named
    | after whichever module the header switcher last selected, so the same
    | page said "Parcel" one visit and "Service" the next.
    |
    | Only the workspace crumb is dropped; the rest of the trail is unaffected.
    */
    'no_workspace' => [
        'admin/settings*',
        'admin/message*',
    ],

    /*
    | Trails for pages the sidebars do not list. Patterns use Str::is syntax and
    | are tried in the order written, so put the specific ones first.
    |
    | Each crumb is ['label' => key, 'route' => name (optional), 'args' => []].
    | The page <title> is still appended as the leaf when it says something the
    | last crumb does not.
    */
    'overrides' => [

        'admin/settings*' => [
            ['label' => 'My profile', 'route' => 'admin.settings'],
        ],

        'vendor-panel/profile*' => [
            ['label' => 'My profile', 'route' => 'vendor.profile.view'],
        ],

        'admin/message*' => [
            ['label' => 'messages.Chat', 'route' => 'admin.message.list'],
        ],

        'vendor-panel/message*' => [
            ['label' => 'messages.Chat', 'route' => 'vendor.message.list'],
        ],

        /*
        | An order or trip detail reached from a transaction stays in the module
        | workspace (Helpers::admin_workspace_for_path agrees), but its path sits
        | under admin/transactions, where walking up lands on the tax reports.
        */
        'admin/transactions/rental/trip/*' => [
            ['label' => 'messages.Trips'],
            ['label' => 'messages.Trip list', 'route' => 'admin.rental.trip.list'],
        ],

        'admin/transactions/parcel/order/details/*' => [
            ['label' => 'Sales', 'panel' => 'sales'],
            ['label' => 'messages.Orders'],
            ['label' => 'messages.All', 'route' => 'admin.parcel.orders', 'args' => ['all']],
        ],

        // The dispatch lists are one route with the module and status in the
        // path, so the page title is what names them.
        'admin/dispatch/*list*' => [
            ['label' => 'Operations'],
        ],

        /*
        | No entry for vendor-panel/builder*: the storefront builder answers
        | with Inertia::render against resources/views/app.blade.php, a bare
        | React shell with neither sidebar nor header, so the breadcrumb
        | partial never runs there. The trail written for it here rendered on
        | nothing. If the builder ever gains the panel chrome, it needs a real
        | entry rather than this one restored.
        */

        'admin/product/*-auto-fill' => [
            ['label' => 'Catalog', 'panel' => 'catalog'],
        ],

        /*
        | Service module pages the sidebar does not list — it links the service
        | list, not the edit and detail screens that hang off it.
        */
        'admin/service/edit/*' => [
            ['label' => 'Catalog', 'panel' => 'catalog'],
            ['label' => 'messages.Service management'],
            ['label' => 'messages.list', 'route' => 'admin.service.list'],
        ],

        'admin/service/request-details/*' => [
            ['label' => 'Catalog', 'panel' => 'catalog'],
            ['label' => 'messages.Service management'],
            ['label' => 'messages.New service request', 'route' => 'admin.service.request-list'],
        ],

        'admin/service/report*' => [
            ['label' => 'messages.Report section'],
        ],

        'admin/pro-customer*' => [
            ['label' => 'Subscription Management'],
            ['label' => 'Pro customer', 'route' => 'admin.pro-customer.benefits-setup'],
        ],

        'taxvat/*' => [
            ['label' => 'Finance & Tax'],
            ['label' => 'Create Taxes', 'route' => 'taxvat.index'],
        ],

        /*
        | The settings sidebar lights one nav item for the whole ride-share
        | block — its active test is `ride-fare*` OR `ride-share*` — so five
        | separate settings pages all inherited the fare page's name. Only the
        | fare page is that page; the others take the section and their own
        | title.
        */
        'admin/business-settings/ride-fare/penalty*' => [
            ['label' => 'Ride Share Settings', 'panel' => 'safety'],
            ['label' => 'Ride Fare Penalty & Charges', 'route' => 'admin.business-settings.ride-fare.penalty'],
        ],

        'admin/business-settings/ride-fare*' => [
            ['label' => 'Ride Share Settings', 'panel' => 'safety'],
        ],

        'admin/business-settings/ride-share*' => [
            ['label' => 'Ride Share Settings', 'panel' => 'safety'],
        ],

        /*
        | Reports with no nav item of their own. Left to the sibling tier each
        | landed on an unrelated neighbour — the stock report under
        | Disbursement, the service earning report under Booking Report.
        */
        'admin/transactions/report/low-stock-report*' => [
            ['label' => 'Performance Reports', 'panel' => 'performance'],
        ],

        'admin/transactions/service/report/earning-report*' => [
            ['label' => 'Earning Reports', 'panel' => 'earning'],
        ],

        /*
        | One nav item covers both earning reports, so its label — the
        | deliveryman one — was appearing above the rider report.
        */
        'admin/transactions/ride-share/report/rider-earning-report*' => [
            ['label' => 'Earning Reports', 'panel' => 'earning'],
        ],

        /*
        | A package's transactions belong to the package, not to the
        | subscription settings page the sibling tier reached for.
        */
        'admin/business-settings/subscription/transaction/*' => [
            ['label' => 'Subscription Management', 'panel' => 'subs'],
            ['label' => 'Subscription Packages', 'route' => 'admin.business-settings.subscriptionackage.index'],
        ],

        /*
        | The landing-page editors hang off their own settings screen; the
        | sibling tier reached sideways for Social Media Links.
        */
        'admin/business-settings/pages/flutter-landing-page-settings/*' => [
            ['label' => 'Website, Pages & Content', 'panel' => 'pages'],
            ['label' => 'Flutter web landing page', 'route' => 'admin.business-settings.flutter-landing-page-settings'],
        ],
    ],
];
