<?php

/*
|--------------------------------------------------------------------------
| Builder addon configuration (host-owned)
|--------------------------------------------------------------------------
|
| This file is owned by the HOST project, not the Builder module. The module
| stays identical across every project and only READS these keys via
| config('builder.*') (each read carries an inline default, so a missing key
| never fatals). Define / override the addon's behaviour for THIS project here.
|
| Laravel auto-loads every file in this directory, so no service-provider
| merge is needed — these values are authoritative.
|
*/

return [
    'cms_dashboard_route' => env('BUILDER_CMS_DASHBOARD_ROUTE', 'vendor.dashboard'),
    'default_platform_name'  => '6amMart',

    /*
     * Extra CKEditor plugins the vendor-panel rich-text editor must load from
     * public/assets/admin/ckeditor/plugins/. 6amMart's CKEditor build doesn't
     * compile Justify/Font/color buttons in, so they're loaded here; installs
     * whose build already includes them leave this empty. Merged with the
     * always-on 'uploadimage' plugin inside RichTextEditor.
     */
    'rich_text_editor' => [
        'extra_plugins' => 'justify,font,colorbutton,panelbutton',
    ],

    /*
     * Master switch for storefront wallet-family features: wallet payment,
     * partial payment, loyalty points, referral, and wallet cashback. When
     * false, the storefront hides all of those UI affordances and the
     * matching endpoints return 404. Host wallet logic is untouched —
     * balances stay in the database and re-appear if the flag is flipped
     * back on. Admin / vendor / mobile API V1 are unaffected.
     */
    'wallet_features_enabled' => false,

    /*
     * Storefront social login (Google / Facebook / Apple). Disabled for now:
     * the providers gate by registered origin / redirect-URI, which doesn't
     * work across arbitrary vendor sub-domains / custom domains without a
     * central auth broker. When false, the storefront hides all social buttons
     * and the `storefront.auth.social` endpoint 404s. Email/phone + OTP login
     * are unaffected. Flip back to true once the broker flow exists.
     */
    'social_login_enabled' => false,

    /*
     * Master switch for ALL outbound email triggered by a storefront request
     * (customer registration, email-verification OTP, password reset, order
     * placement / verification, wallet & refund notifications, …). When false,
     * the `SuppressStorefrontMail` middleware cancels every mail sent during a
     * storefront request (all 6amMart mailables are synchronous, so nothing
     * escapes to a queue worker) — so no storefront email goes out.
     *
     * Scope is the storefront ONLY: admin, vendor panel, and mobile API mail
     * are untouched (their routes don't carry this middleware). Flip to false
     * to run storefronts silently (e.g. white-label sites that handle their own
     * transactional email, or staging domains that shouldn't email real users).
     */
    'storefront_mail_enabled' => true,

    /*
     * Host capability manifest — the single place a host declares WHICH features
     * the storefront + builder should render and enforce, so the same addon
     * adapts per project. Read via Modules\Builder\Contracts\CapabilityProvider
     * (host adapter may also DERIVE data-driven flags). Every value here is the
     * 6amMart baseline = its current implicit behavior; other hosts override.
     *
     * Read in PHP:  app(CapabilityProvider)->capabilities($scope)->enabled('location.map')
     * Gate a route: ->middleware(RequireCapability::class.':features.wallet')
     * Read in JS:   useCapability('payment.cod')
     */
    'capabilities' => [
        'schemaVersion' => 1,

        'profile' => [
            'phoneEditable' => false,
            'emailEditable' => true,
        ],

        'modules' => ['mode' => 'multi', 'switcher' => true, 'itemPresentation' => 'auto'],

        'currency' => ['mode' => 'single', 'switcher' => false],

        'location' => [
            'enabled' => true, 'map' => true, 'currentLocation' => true,
            'zoneBased' => true, 'savedAddresses' => true,
            'addressEmail' => false,
            'addressFields' => [
                ['key' => 'road',  'label' => 'address_form_street', 'half' => false],
                ['key' => 'house', 'label' => 'address_form_house',  'half' => true],
                ['key' => 'floor', 'label' => 'address_form_floor',  'half' => true],
            ],
        ],

        'checkout' => [
            'deliveryTypes' => ['home', 'takeaway', 'schedule'],
            'tips' => true, 'tipPresets' => [10, 15, 20, 40],
            'extraPackaging' => true, 'coupon' => true,
            'unavailableNote' => true, 'deliveryInstruction' => true,
            'orderNote' => false, 'savedAddress' => true,
        ],

        'payment' => [
            'cod' => true, 'digital' => true, 'offline' => true,
            'wallet' => true, 'partial' => true,
            'timing' => 'after', 'retryReminder' => true,
        ],

        'features' => [
            'wallet' => true, 'loyaltyPoint' => true, 'referral' => true,
            'reviews' => true, 'inbox' => true, 'pushNotif' => true,
            'guestCheckout' => true, 'reorder' => true, 'wishlist' => true, 'blog' => false,
            'buyNow' => false,
            'deliveryManChat' => true, 'deliveryManCall' => true,
        ],

        'auth' => [
            'manual' => true, 'otp' => true, 'otpChannel' => 'sms',
            'social' => ['google' => true, 'facebook' => true, 'apple' => true],
            'forgotPassword' => ['status' => true, 'modes' => ['phone', 'email']],
        ],
    ],
];
