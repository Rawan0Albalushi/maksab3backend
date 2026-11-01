<?php

return [
    'module_type'=>[
        'grocery', 'food', 'pharmacy', 'ecommerce','parcel','rental','ride-share','service'
    ],

    /*
     * Presentation only — the glyph and badge tone each module type is drawn
     * with in the admin panel (list badge, type picker, edit summary). Kept
     * beside the type list so there is one place to extend when a type is
     * added. Views fall back to a neutral icon/tone when a key is missing, so
     * an unknown type degrades rather than breaking the page.
     */
    'module_type_meta'=>[
        'grocery'    => ['icon'=>'tio-shopping-basket-outlined', 'tone'=>'success'],
        'food'       => ['icon'=>'tio-fastfood',                 'tone'=>'warning'],
        'pharmacy'   => ['icon'=>'tio-pharmacy-outlined',        'tone'=>'info'],
        'ecommerce'  => ['icon'=>'tio-shopping-cart-outlined',   'tone'=>'primary'],
        'parcel'     => ['icon'=>'tio-truck',                    'tone'=>'dark'],
        'rental'     => ['icon'=>'tio-car',                      'tone'=>'secondary'],
        'ride-share' => ['icon'=>'tio-taxi',                     'tone'=>'secondary'],
        'service'    => ['icon'=>'tio-tools',                    'tone'=>'info'],
    ],

    'grocery'=>[
        'order_status'=>['accepted'=>false],
        'order_place_to_schedule_interval'=>true,
        'add_on'=>false,
        'stock'=>true,
        'veg_non_veg'=>false,
        'unit'=>true,
        'order_attachment'=>false,
        'always_open'=>false,
        'all_zone_service'=>false,
        'item_available_time'=>false,
        'show_restaurant_text'=>false,
        'is_parcel'=>false,
        'organic'=>true,
        'cutlery'=>false,
        'common_condition'=>false,
        'nutrition'=>true,
        'allergy'=>true,
        'basic'=>false,
        'halal'=>true,
        'brand'=>true,
        'generic_name'=>false,
        'description'=>'In this type, You can set delivery slot start after x minutes from current time, No available time for items and has stock for items.',
        // Whether Happy Hour and BOGO may run in this type. Both need a line-item cart
        // with priced products: parcel carries nothing to bundle or discount, and rental,
        // ride-share and service have no per-item price for a store-wide percentage to
        // apply to. Read by the sidebar, the route guard and the promotion panels alike,
        // so the capability is stated once rather than repeated as a literal list.
        //
        // Turning this on for a type the promotion engine cannot reach is not enough on its
        // own. The engine reads `carts` and `order_details`; rental and service use neither
        // (they carry RentalCart/Trips and ServiceBooking/ServiceBookingDetails), so serving
        // either means writing a second implementation of the cart and order-line side, not
        // flipping a flag. That is why the excluded types are excluded here rather than being
        // carried as an abstraction the core pays for and nothing uses.
        'promotions'=>true,
        // The admin "Other banners" menu ("Promotional banners" for parcel) and the
        // customer API that serves what it saves. Pharmacy dropped it in 4.2.1: its
        // menu, pages and API all answer as if the type never had one.
        'other_banners'=>true,
        // Whether a (zone, module) pair can carry an ETA configuration.
        //
        // An ETA answers "how long until this reaches you", which only means something for an
        // order a deliveryman carries from a store to an address. Rental, ride-share and service
        // do not have that shape: a ride quotes its own arrival from the driver's position, a
        // rental is booked for a period rather than delivered, and a service is scheduled for a
        // slot the provider attends. None of them reads EtaService, and none has anywhere to put
        // a delivery window.
        //
        // Gates the ETA setup's module picker AND the zone readiness rule. Both matter: a zone
        // connected to rental or service could otherwise never be switched on, because Z3 asks
        // every connected module for an ETA and those three can never have one.
        'eta'=>true,
        // Whether a (zone, module) pair can carry a surge price.
        //
        // A surge is a percentage or amount added to the DELIVERY CHARGE, and only the core order
        // pipeline applies it — `CheckoutSummaryTrait` and `PlaceNewOrderTrait`, through
        // DeliveryChargeService. Rental, ride-share and service price their own trips and
        // bookings and never reach it, so a surge configured for them would change nothing.
        //
        // Kept separate from `eta` rather than folded into one "delivery setup" flag: they happen
        // to exclude the same three types today, but they answer different questions and a type
        // could plausibly want one without the other.
        'surge'=>true,
        // Whether a (zone, module) pair can carry a free-delivery setup and an additional
        // delivery charge (the saver / express / slightly-delayed options).
        //
        // Both describe the DELIVERY CHARGE of an order the platform delivers: one waives it, the
        // other moves it for a faster or slower option. Only the core order pipeline reads them —
        // DeliveryFeeTrait and OrderFromCartTrait for free delivery, PlaceNewOrderTrait and
        // POSDeliveryTypeTrait for the options. Rental, ride-share and service price their own
        // trips and bookings and never reach either, so a setup on them would never apply.
        //
        // One flag for the pair because they are the same question asked twice; kept apart from
        // `eta` and `surge` for the reason stated there.
        'delivery_charge_setup'=>true,
        // Whether a (zone, module) pair can carry a delivery rule.
        //
        // A delivery rule PRICES the delivery — area, zip, distance or fixed — and only
        // DeliveryChargeService::quote() reads it, which nothing outside the core order pipeline
        // calls. Rental, ride-share and service price their own trips and bookings.
        //
        // Separate from `delivery_charge_setup` because it is a different question: that one asks
        // whether the charge can be WAIVED or SHIFTED, this asks whether there is a charge to set
        // in the first place.
        'delivery_rule'=>true,
        'is_rental'=>false,
    ],

    'food'=>[
        'order_status'=>['accepted'=>true],
        'order_place_to_schedule_interval'=>false,
        'add_on'=>true,
        'stock'=>false,
        'veg_non_veg'=>true,
        'unit'=>false,
        'order_attachment'=>false,
        'always_open'=>false,
        'all_zone_service'=>false,
        'item_available_time'=>true,
        'show_restaurant_text'=>true,
        'is_parcel'=>false,
        'organic'=>false,
        'cutlery'=>true,
        'common_condition'=>false,
        'nutrition'=>true,
        'allergy'=>true,
        'basic'=>false,
        'halal'=>true,
        'brand'=>false,
        'generic_name'=>false,
        'description'=>'In this type, you can set item available time, no stock management for items and has option to add add-on.',
        'promotions'=>true,
        'other_banners'=>true,
        // Whether a (zone, module) pair can carry an ETA configuration.
        //
        // An ETA answers "how long until this reaches you", which only means something for an
        // order a deliveryman carries from a store to an address. Rental, ride-share and service
        // do not have that shape: a ride quotes its own arrival from the driver's position, a
        // rental is booked for a period rather than delivered, and a service is scheduled for a
        // slot the provider attends. None of them reads EtaService, and none has anywhere to put
        // a delivery window.
        //
        // Gates the ETA setup's module picker AND the zone readiness rule. Both matter: a zone
        // connected to rental or service could otherwise never be switched on, because Z3 asks
        // every connected module for an ETA and those three can never have one.
        'eta'=>true,
        // Whether a (zone, module) pair can carry a surge price.
        //
        // A surge is a percentage or amount added to the DELIVERY CHARGE, and only the core order
        // pipeline applies it — `CheckoutSummaryTrait` and `PlaceNewOrderTrait`, through
        // DeliveryChargeService. Rental, ride-share and service price their own trips and
        // bookings and never reach it, so a surge configured for them would change nothing.
        //
        // Kept separate from `eta` rather than folded into one "delivery setup" flag: they happen
        // to exclude the same three types today, but they answer different questions and a type
        // could plausibly want one without the other.
        'surge'=>true,
        // Whether a (zone, module) pair can carry a free-delivery setup and an additional
        // delivery charge (the saver / express / slightly-delayed options).
        //
        // Both describe the DELIVERY CHARGE of an order the platform delivers: one waives it, the
        // other moves it for a faster or slower option. Only the core order pipeline reads them —
        // DeliveryFeeTrait and OrderFromCartTrait for free delivery, PlaceNewOrderTrait and
        // POSDeliveryTypeTrait for the options. Rental, ride-share and service price their own
        // trips and bookings and never reach either, so a setup on them would never apply.
        //
        // One flag for the pair because they are the same question asked twice; kept apart from
        // `eta` and `surge` for the reason stated there.
        'delivery_charge_setup'=>true,
        // Whether a (zone, module) pair can carry a delivery rule.
        //
        // A delivery rule PRICES the delivery — area, zip, distance or fixed — and only
        // DeliveryChargeService::quote() reads it, which nothing outside the core order pipeline
        // calls. Rental, ride-share and service price their own trips and bookings.
        //
        // Separate from `delivery_charge_setup` because it is a different question: that one asks
        // whether the charge can be WAIVED or SHIFTED, this asks whether there is a charge to set
        // in the first place.
        'delivery_rule'=>true,
        'is_rental'=>false,
    ],

    'pharmacy'=>[
        'order_status'=>['accepted'=>false],
        'order_place_to_schedule_interval'=>false,
        'add_on'=>false,
        'stock'=>true,
        'veg_non_veg'=>false,
        'unit'=>true,
        'order_attachment'=>true,
        'always_open'=>false,
        'all_zone_service'=>false,
        'item_available_time'=>false,
        'show_restaurant_text'=>false,
        'is_parcel'=>false,
        'organic'=>false,
        'cutlery'=>false,
        'common_condition'=>true,
        'nutrition'=>false,
        'allergy'=>false,
        'basic'=>true,
        'halal'=>false,
        'brand'=>false,
        'generic_name'=>true,
        'description'=>'In this type, Customer can upload prescription when place order, No available time for items and has stock for items.',
        'promotions'=>true,
        'other_banners'=>false,
        // Whether a (zone, module) pair can carry an ETA configuration.
        //
        // An ETA answers "how long until this reaches you", which only means something for an
        // order a deliveryman carries from a store to an address. Rental, ride-share and service
        // do not have that shape: a ride quotes its own arrival from the driver's position, a
        // rental is booked for a period rather than delivered, and a service is scheduled for a
        // slot the provider attends. None of them reads EtaService, and none has anywhere to put
        // a delivery window.
        //
        // Gates the ETA setup's module picker AND the zone readiness rule. Both matter: a zone
        // connected to rental or service could otherwise never be switched on, because Z3 asks
        // every connected module for an ETA and those three can never have one.
        'eta'=>true,
        // Whether a (zone, module) pair can carry a surge price.
        //
        // A surge is a percentage or amount added to the DELIVERY CHARGE, and only the core order
        // pipeline applies it — `CheckoutSummaryTrait` and `PlaceNewOrderTrait`, through
        // DeliveryChargeService. Rental, ride-share and service price their own trips and
        // bookings and never reach it, so a surge configured for them would change nothing.
        //
        // Kept separate from `eta` rather than folded into one "delivery setup" flag: they happen
        // to exclude the same three types today, but they answer different questions and a type
        // could plausibly want one without the other.
        'surge'=>true,
        // Whether a (zone, module) pair can carry a free-delivery setup and an additional
        // delivery charge (the saver / express / slightly-delayed options).
        //
        // Both describe the DELIVERY CHARGE of an order the platform delivers: one waives it, the
        // other moves it for a faster or slower option. Only the core order pipeline reads them —
        // DeliveryFeeTrait and OrderFromCartTrait for free delivery, PlaceNewOrderTrait and
        // POSDeliveryTypeTrait for the options. Rental, ride-share and service price their own
        // trips and bookings and never reach either, so a setup on them would never apply.
        //
        // One flag for the pair because they are the same question asked twice; kept apart from
        // `eta` and `surge` for the reason stated there.
        'delivery_charge_setup'=>true,
        // Whether a (zone, module) pair can carry a delivery rule.
        //
        // A delivery rule PRICES the delivery — area, zip, distance or fixed — and only
        // DeliveryChargeService::quote() reads it, which nothing outside the core order pipeline
        // calls. Rental, ride-share and service price their own trips and bookings.
        //
        // Separate from `delivery_charge_setup` because it is a different question: that one asks
        // whether the charge can be WAIVED or SHIFTED, this asks whether there is a charge to set
        // in the first place.
        'delivery_rule'=>true,
        'is_rental'=>false,
    ],

    'ecommerce'=>[
        'order_status'=>['accepted'=>false],
        'order_place_to_schedule_interval'=>false,
        'add_on'=>false,
        'stock'=>true,
        'veg_non_veg'=>false,
        'unit'=>true,
        'order_attachment'=>false,
        'always_open'=>true,
        'all_zone_service'=>true,
        'item_available_time'=>false,
        'show_restaurant_text'=>false,
        'is_parcel'=>false,
        'organic'=>false,
        'cutlery'=>false,
        'common_condition'=>false,
        'nutrition'=>false,
        'allergy'=>false,
        'basic'=>false,
        'halal'=>false,
        'brand'=>true,
        'generic_name'=>false,
        'description'=>'In this type, No opening and closing time for store, no available time for items and has stock for items.',
        'promotions'=>true,
        'other_banners'=>true,
        // Whether a (zone, module) pair can carry an ETA configuration.
        //
        // An ETA answers "how long until this reaches you", which only means something for an
        // order a deliveryman carries from a store to an address. Rental, ride-share and service
        // do not have that shape: a ride quotes its own arrival from the driver's position, a
        // rental is booked for a period rather than delivered, and a service is scheduled for a
        // slot the provider attends. None of them reads EtaService, and none has anywhere to put
        // a delivery window.
        //
        // Gates the ETA setup's module picker AND the zone readiness rule. Both matter: a zone
        // connected to rental or service could otherwise never be switched on, because Z3 asks
        // every connected module for an ETA and those three can never have one.
        'eta'=>true,
        // Whether a (zone, module) pair can carry a surge price.
        //
        // A surge is a percentage or amount added to the DELIVERY CHARGE, and only the core order
        // pipeline applies it — `CheckoutSummaryTrait` and `PlaceNewOrderTrait`, through
        // DeliveryChargeService. Rental, ride-share and service price their own trips and
        // bookings and never reach it, so a surge configured for them would change nothing.
        //
        // Kept separate from `eta` rather than folded into one "delivery setup" flag: they happen
        // to exclude the same three types today, but they answer different questions and a type
        // could plausibly want one without the other.
        'surge'=>true,
        // Whether a (zone, module) pair can carry a free-delivery setup and an additional
        // delivery charge (the saver / express / slightly-delayed options).
        //
        // Both describe the DELIVERY CHARGE of an order the platform delivers: one waives it, the
        // other moves it for a faster or slower option. Only the core order pipeline reads them —
        // DeliveryFeeTrait and OrderFromCartTrait for free delivery, PlaceNewOrderTrait and
        // POSDeliveryTypeTrait for the options. Rental, ride-share and service price their own
        // trips and bookings and never reach either, so a setup on them would never apply.
        //
        // One flag for the pair because they are the same question asked twice; kept apart from
        // `eta` and `surge` for the reason stated there.
        'delivery_charge_setup'=>true,
        // Whether a (zone, module) pair can carry a delivery rule.
        //
        // A delivery rule PRICES the delivery — area, zip, distance or fixed — and only
        // DeliveryChargeService::quote() reads it, which nothing outside the core order pipeline
        // calls. Rental, ride-share and service price their own trips and bookings.
        //
        // Separate from `delivery_charge_setup` because it is a different question: that one asks
        // whether the charge can be WAIVED or SHIFTED, this asks whether there is a charge to set
        // in the first place.
        'delivery_rule'=>true,
        'is_rental'=>false,
    ],

    'parcel'=>[
        'order_status'=>['accepted'=>false],
        'order_place_to_schedule_interval'=>false,
        'add_on'=>false,
        'stock'=>false,
        'veg_non_veg'=>false,
        'unit'=>false,
        'order_attachment'=>false,
        'always_open'=>true,
        'all_zone_service'=>false,
        'item_available_time'=>false,
        'show_restaurant_text'=>false,
        'is_parcel'=>true,
        'organic'=>false,
        'cutlery'=>false,
        'common_condition'=>false,
        'nutrition'=>false,
        'allergy'=>false,
        'basic'=>false,
        'halal'=>false,
        'brand'=>false,
        'generic_name'=>false,
        'description'=>'',
        'promotions'=>false,
        'other_banners'=>true,
        // Whether a (zone, module) pair can carry an ETA configuration.
        //
        // An ETA answers "how long until this reaches you", which only means something for an
        // order a deliveryman carries from a store to an address. Rental, ride-share and service
        // do not have that shape: a ride quotes its own arrival from the driver's position, a
        // rental is booked for a period rather than delivered, and a service is scheduled for a
        // slot the provider attends. None of them reads EtaService, and none has anywhere to put
        // a delivery window.
        //
        // Gates the ETA setup's module picker AND the zone readiness rule. Both matter: a zone
        // connected to rental or service could otherwise never be switched on, because Z3 asks
        // every connected module for an ETA and those three can never have one.
        'eta'=>true,
        // Whether a (zone, module) pair can carry a surge price.
        //
        // A surge is a percentage or amount added to the DELIVERY CHARGE, and only the core order
        // pipeline applies it — `CheckoutSummaryTrait` and `PlaceNewOrderTrait`, through
        // DeliveryChargeService. Rental, ride-share and service price their own trips and
        // bookings and never reach it, so a surge configured for them would change nothing.
        //
        // Kept separate from `eta` rather than folded into one "delivery setup" flag: they happen
        // to exclude the same three types today, but they answer different questions and a type
        // could plausibly want one without the other.
        'surge'=>true,
        // Whether a (zone, module) pair can carry a free-delivery setup and an additional
        // delivery charge (the saver / express / slightly-delayed options).
        //
        // Both describe the DELIVERY CHARGE of an order the platform delivers: one waives it, the
        // other moves it for a faster or slower option. Only the core order pipeline reads them —
        // DeliveryFeeTrait and OrderFromCartTrait for free delivery, PlaceNewOrderTrait and
        // POSDeliveryTypeTrait for the options. Rental, ride-share and service price their own
        // trips and bookings and never reach either, so a setup on them would never apply.
        //
        // One flag for the pair because they are the same question asked twice; kept apart from
        // `eta` and `surge` for the reason stated there.
        'delivery_charge_setup'=>true,
        // Whether a (zone, module) pair can carry a delivery rule.
        //
        // A delivery rule PRICES the delivery — area, zip, distance or fixed — and only
        // DeliveryChargeService::quote() reads it, which nothing outside the core order pipeline
        // calls. Rental, ride-share and service price their own trips and bookings.
        //
        // Separate from `delivery_charge_setup` because it is a different question: that one asks
        // whether the charge can be WAIVED or SHIFTED, this asks whether there is a charge to set
        // in the first place.
        'delivery_rule'=>true,
        'is_rental'=>false,
    ],
    'rental'=>[
        'order_status'=>['accepted'=>false],
        'order_place_to_schedule_interval'=>false,
        'add_on'=>false,
        'stock'=>false,
        'veg_non_veg'=>false,
        'unit'=>false,
        'order_attachment'=>false,
        'always_open'=>false,
        'all_zone_service'=>false,
        'item_available_time'=>false,
        'show_restaurant_text'=>false,
        'is_parcel'=>false,
        'organic'=>false,
        'cutlery'=>false,
        'common_condition'=>false,
        'nutrition'=>false,
        'allergy'=>false,
        'basic'=>false,
        'halal'=>false,
        'brand'=>false,
        'generic_name'=>false,
        'description'=>'',
        'promotions'=>false,
        'other_banners'=>false,
        // Whether a (zone, module) pair can carry an ETA configuration.
        //
        // An ETA answers "how long until this reaches you", which only means something for an
        // order a deliveryman carries from a store to an address. Rental, ride-share and service
        // do not have that shape: a ride quotes its own arrival from the driver's position, a
        // rental is booked for a period rather than delivered, and a service is scheduled for a
        // slot the provider attends. None of them reads EtaService, and none has anywhere to put
        // a delivery window.
        //
        // Gates the ETA setup's module picker AND the zone readiness rule. Both matter: a zone
        // connected to rental or service could otherwise never be switched on, because Z3 asks
        // every connected module for an ETA and those three can never have one.
        'eta'=>false,
        // Whether a (zone, module) pair can carry a surge price.
        //
        // A surge is a percentage or amount added to the DELIVERY CHARGE, and only the core order
        // pipeline applies it — `CheckoutSummaryTrait` and `PlaceNewOrderTrait`, through
        // DeliveryChargeService. Rental, ride-share and service price their own trips and
        // bookings and never reach it, so a surge configured for them would change nothing.
        //
        // Kept separate from `eta` rather than folded into one "delivery setup" flag: they happen
        // to exclude the same three types today, but they answer different questions and a type
        // could plausibly want one without the other.
        'surge'=>false,
        // Whether a (zone, module) pair can carry a free-delivery setup and an additional
        // delivery charge (the saver / express / slightly-delayed options).
        //
        // Both describe the DELIVERY CHARGE of an order the platform delivers: one waives it, the
        // other moves it for a faster or slower option. Only the core order pipeline reads them —
        // DeliveryFeeTrait and OrderFromCartTrait for free delivery, PlaceNewOrderTrait and
        // POSDeliveryTypeTrait for the options. Rental, ride-share and service price their own
        // trips and bookings and never reach either, so a setup on them would never apply.
        //
        // One flag for the pair because they are the same question asked twice; kept apart from
        // `eta` and `surge` for the reason stated there.
        'delivery_charge_setup'=>false,
        // Whether a (zone, module) pair can carry a delivery rule.
        //
        // A delivery rule PRICES the delivery — area, zip, distance or fixed — and only
        // DeliveryChargeService::quote() reads it, which nothing outside the core order pipeline
        // calls. Rental, ride-share and service price their own trips and bookings.
        //
        // Separate from `delivery_charge_setup` because it is a different question: that one asks
        // whether the charge can be WAIVED or SHIFTED, this asks whether there is a charge to set
        // in the first place.
        'delivery_rule'=>false,
        'is_rental'=>true,
    ],
    'ride-share'=>[
        'order_status'=>['accepted'=>false],
        'order_place_to_schedule_interval'=>false,
        'add_on'=>false,
        'stock'=>false,
        'veg_non_veg'=>false,
        'unit'=>false,
        'order_attachment'=>false,
        'always_open'=>false,
        'all_zone_service'=>false,
        'item_available_time'=>false,
        'show_restaurant_text'=>false,
        'is_parcel'=>false,
        'organic'=>false,
        'cutlery'=>false,
        'common_condition'=>false,
        'nutrition'=>false,
        'allergy'=>false,
        'basic'=>false,
        'halal'=>false,
        'brand'=>false,
        'generic_name'=>false,
        'description'=>'',
        'promotions'=>false,
        'other_banners'=>false,
        // Whether a (zone, module) pair can carry an ETA configuration.
        //
        // An ETA answers "how long until this reaches you", which only means something for an
        // order a deliveryman carries from a store to an address. Rental, ride-share and service
        // do not have that shape: a ride quotes its own arrival from the driver's position, a
        // rental is booked for a period rather than delivered, and a service is scheduled for a
        // slot the provider attends. None of them reads EtaService, and none has anywhere to put
        // a delivery window.
        //
        // Gates the ETA setup's module picker AND the zone readiness rule. Both matter: a zone
        // connected to rental or service could otherwise never be switched on, because Z3 asks
        // every connected module for an ETA and those three can never have one.
        'eta'=>false,
        // Whether a (zone, module) pair can carry a surge price.
        //
        // A surge is a percentage or amount added to the DELIVERY CHARGE, and only the core order
        // pipeline applies it — `CheckoutSummaryTrait` and `PlaceNewOrderTrait`, through
        // DeliveryChargeService. Rental, ride-share and service price their own trips and
        // bookings and never reach it, so a surge configured for them would change nothing.
        //
        // Kept separate from `eta` rather than folded into one "delivery setup" flag: they happen
        // to exclude the same three types today, but they answer different questions and a type
        // could plausibly want one without the other.
        'surge'=>false,
        // Whether a (zone, module) pair can carry a free-delivery setup and an additional
        // delivery charge (the saver / express / slightly-delayed options).
        //
        // Both describe the DELIVERY CHARGE of an order the platform delivers: one waives it, the
        // other moves it for a faster or slower option. Only the core order pipeline reads them —
        // DeliveryFeeTrait and OrderFromCartTrait for free delivery, PlaceNewOrderTrait and
        // POSDeliveryTypeTrait for the options. Rental, ride-share and service price their own
        // trips and bookings and never reach either, so a setup on them would never apply.
        //
        // One flag for the pair because they are the same question asked twice; kept apart from
        // `eta` and `surge` for the reason stated there.
        'delivery_charge_setup'=>false,
        // Whether a (zone, module) pair can carry a delivery rule.
        //
        // A delivery rule PRICES the delivery — area, zip, distance or fixed — and only
        // DeliveryChargeService::quote() reads it, which nothing outside the core order pipeline
        // calls. Rental, ride-share and service price their own trips and bookings.
        //
        // Separate from `delivery_charge_setup` because it is a different question: that one asks
        // whether the charge can be WAIVED or SHIFTED, this asks whether there is a charge to set
        // in the first place.
        'delivery_rule'=>false,
        'is_rental'=>true,
    ],
    'service'=>[
        'order_status'=>['accepted'=>false],
        'order_place_to_schedule_interval'=>false,
        'add_on'=>false,
        'stock'=>false,
        'veg_non_veg'=>false,
        'unit'=>false,
        'order_attachment'=>false,
        'always_open'=>false,
        'all_zone_service'=>false,
        'item_available_time'=>false,
        'show_restaurant_text'=>false,
        'is_parcel'=>false,
        'organic'=>false,
        'cutlery'=>false,
        'common_condition'=>false,
        'nutrition'=>false,
        'allergy'=>false,
        'basic'=>false,
        'halal'=>false,
        'brand'=>false,
        'generic_name'=>false,
        'description'=>'',
        'promotions'=>false,
        'other_banners'=>true,
        // Whether a (zone, module) pair can carry an ETA configuration.
        //
        // An ETA answers "how long until this reaches you", which only means something for an
        // order a deliveryman carries from a store to an address. Rental, ride-share and service
        // do not have that shape: a ride quotes its own arrival from the driver's position, a
        // rental is booked for a period rather than delivered, and a service is scheduled for a
        // slot the provider attends. None of them reads EtaService, and none has anywhere to put
        // a delivery window.
        //
        // Gates the ETA setup's module picker AND the zone readiness rule. Both matter: a zone
        // connected to rental or service could otherwise never be switched on, because Z3 asks
        // every connected module for an ETA and those three can never have one.
        'eta'=>false,
        // Whether a (zone, module) pair can carry a surge price.
        //
        // A surge is a percentage or amount added to the DELIVERY CHARGE, and only the core order
        // pipeline applies it — `CheckoutSummaryTrait` and `PlaceNewOrderTrait`, through
        // DeliveryChargeService. Rental, ride-share and service price their own trips and
        // bookings and never reach it, so a surge configured for them would change nothing.
        //
        // Kept separate from `eta` rather than folded into one "delivery setup" flag: they happen
        // to exclude the same three types today, but they answer different questions and a type
        // could plausibly want one without the other.
        'surge'=>false,
        // Whether a (zone, module) pair can carry a free-delivery setup and an additional
        // delivery charge (the saver / express / slightly-delayed options).
        //
        // Both describe the DELIVERY CHARGE of an order the platform delivers: one waives it, the
        // other moves it for a faster or slower option. Only the core order pipeline reads them —
        // DeliveryFeeTrait and OrderFromCartTrait for free delivery, PlaceNewOrderTrait and
        // POSDeliveryTypeTrait for the options. Rental, ride-share and service price their own
        // trips and bookings and never reach either, so a setup on them would never apply.
        //
        // One flag for the pair because they are the same question asked twice; kept apart from
        // `eta` and `surge` for the reason stated there.
        'delivery_charge_setup'=>false,
        // Whether a (zone, module) pair can carry a delivery rule.
        //
        // A delivery rule PRICES the delivery — area, zip, distance or fixed — and only
        // DeliveryChargeService::quote() reads it, which nothing outside the core order pipeline
        // calls. Rental, ride-share and service price their own trips and bookings.
        //
        // Separate from `delivery_charge_setup` because it is a different question: that one asks
        // whether the charge can be WAIVED or SHIFTED, this asks whether there is a charge to set
        // in the first place.
        'delivery_rule'=>false,
        'is_rental'=>false,
    ],
];
