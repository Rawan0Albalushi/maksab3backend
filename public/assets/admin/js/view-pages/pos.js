"use strict";

function posCartAlert(type, text) {
    let lang = window.posCartLang || {};
    return Swal.fire({
        type: type,
        title: lang.title || "Cart",
        text: text || "",
        confirmButtonText: lang.confirm || "OK",
        showCloseButton: true,
        closeButtonAriaLabel: lang.close || "Close",
        customClass: "pos-swal pos-swal-" + type,
        customContainerClass: "pos-swal-shell",
    });
}

$("#order_place").on("keydown", function (e) {
    if (e.keyCode === 13) {
        e.preventDefault();
    }
});
$("#insertPayableAmount").on("keydown", function (e) {
    if (e.keyCode === 13) {
        e.preventDefault();
    }
});

$(document).on("click", ".print-Div", function () {
    let area = document.getElementById("printableArea");

    if (!area) {
        return;
    }

    let head = "";
    document
        .querySelectorAll('link[rel="stylesheet"], style')
        .forEach(function (node) {
            head += node.outerHTML;
        });

    let frame = document.createElement("iframe");
    frame.className = "pos-print-frame";
    frame.setAttribute("aria-hidden", "true");
    frame.onload = function () {
        frame.contentWindow.focus();
        frame.contentWindow.print();
        setTimeout(function () {
            frame.remove();
        }, 1000);
    };
    document.body.appendChild(frame);

    let doc = frame.contentWindow.document;
    doc.open();
    doc.write(
        '<!doctype html><html dir="' +
            (document.documentElement.getAttribute("dir") || "ltr") +
            '"><head><meta charset="utf-8">' +
            head +
            "<style>@page{size:auto;margin:0}body{margin:0}</style></head><body>" +
            area.outerHTML +
            "</body></html>"
    );
    doc.close();
});

$(document).on("click", ".addon-quantity-input-toggle", function (event) {
    let cb = $(event.target);
    if (cb.is(":checked")) {
        cb.siblings(".addon-quantity-input").css({ visibility: "visible" });
    } else {
        cb.siblings(".addon-quantity-input").css({ visibility: "hidden" });
    }
});

function cartQuantityInitialize() {
    $(".btn-number").click(function (e) {
        e.preventDefault();

        let fieldName = $(this).attr("data-field");
        let type = $(this).attr("data-type");
        let input = $("input[name='" + fieldName + "']");
        let currentVal = parseInt(input.val());

        if (!isNaN(currentVal)) {
            if (type === "minus") {
                if (currentVal > input.attr("min")) {
                    input.val(currentVal - 1).change();
                }
                if (parseInt(input.val()) === input.attr("min")) {
                    $(this).attr("disabled", true);
                }
            } else if (type === "plus") {
                if (currentVal < input.attr("max")) {
                    input.val(currentVal + 1).change();
                }
                if (parseInt(input.val()) === input.attr("max")) {
                    $(this).attr("disabled", true);
                }
            }
        } else {
            input.val(0);
        }
    });

    $(".input-number").focusin(function () {
        $(this).data("oldValue", $(this).val());
    });

    $(".input-number").change(function () {
        let minValue = parseInt($(this).attr("min"));
        let maxValue = parseInt($(this).attr("max"));
        let valueCurrent = parseInt($(this).val());
        let name = $(this).attr("name");
        if (valueCurrent >= minValue) {
            $(
                ".btn-number[data-type='minus'][data-field='" + name + "']"
            ).removeAttr("disabled");
        } else {
            posCartAlert("error", (window.posCartLang || {}).minValue);
            $(this).val($(this).data("oldValue"));
        }
        if (valueCurrent <= maxValue) {
            $(
                ".btn-number[data-type='plus'][data-field='" + name + "']"
            ).removeAttr("disabled");
        } else {
            posCartAlert("error", (window.posCartLang || {}).stockLimit);
            $(this).val($(this).data("oldValue"));
        }
    });
    $(".input-number").keydown(function (e) {
        if (
            $.inArray(e.keyCode, [46, 8, 9, 27, 13, 190]) !== -1 ||
            (e.keyCode === 65 && e.ctrlKey === true) ||
            (e.keyCode >= 35 && e.keyCode <= 39)
        ) {
            return;
        }
        if (
            (e.shiftKey || e.keyCode < 48 || e.keyCode > 57) &&
            (e.keyCode < 96 || e.keyCode > 105)
        ) {
            e.preventDefault();
        }
    });
}

function getUrlParameter(sParam) {
    let sPageURL = window.location.search.substring(1);
    let sURLVariables = sPageURL.split("&");
    for (let i = 0; i < sURLVariables.length; i++) {
        let sParameterName = sURLVariables[i].split("=");
        if (sParameterName[0] === sParam) {
            return sParameterName[1];
        }
    }
}

$(document).on("click", ".decrease-button", function () {
    let addonId = $(this).data("id");
    let addon_quantity_input = $('input[name="addon-quantity' + addonId + '"]');
    let currentValue = parseInt(addon_quantity_input.val(), 10);
    if (currentValue > 1) {
        addon_quantity_input.val(currentValue - 1);
        getVariantPrice();
    }
});

$(document).on("click", ".increase-button", function () {
    let addonId = $(this).data("id");
    let addon_quantity_input = $('input[name="addon-quantity' + addonId + '"]');
    let currentValue = parseInt(addon_quantity_input.val(), 10);
    addon_quantity_input.val(currentValue + 1);
    getVariantPrice();
});

$(document).on("click", ".decrease-button-cart", function () {
    let addon_quantity_input = $('input[name="quantity"]');
    let currentValue = parseInt(addon_quantity_input.val(), 10);
    if (currentValue > 1) {
        addon_quantity_input.val(currentValue - 1);
        getVariantPrice();
    }
});

$(document).on("click", ".increase-button-cart", function () {
    let addon_quantity_input = $('input[name="quantity"]');
    let currentValue = parseInt(addon_quantity_input.val(), 10);
    let maxValue = parseInt(addon_quantity_input.attr("max"));
    if (maxValue - 1 >= currentValue) {
        addon_quantity_input.val(currentValue + 1);
        getVariantPrice();
    } else {
        posCartAlert("error", (window.posCartLang || {}).stockLimit);
    }
});

$(".js-select2-custom").each(function () {
    let select2 = $.HSCore.components.HSSelect2.init($(this));
});
$("#delivery_address").on("click", function () {
    initMap();
});
$("#customer").change(function () {
    if ($(this).val()) {
        $("#customer_id").val($(this).val());
    }
});

$("#payment_card").on("change", function () {
    $("#paid_section").hide();
});
$("#payment_cash").on("change", function () {
    $("#paid_section").show();
});

$(document).on("change", "#discount_input_type", function () {
    let discountInput = $("#discount_input");
    let discountInputType = $(this);
    let maxLimit = discountInputType.val() === "percent" ? 100 : 1000000000;
    discountInput.attr("max", maxLimit);
});

function handleLocationError(browserHasGeolocation, infoWindow, pos, mapInstance, messages) {
    messages = messages || {};
    let content = browserHasGeolocation
        ? messages.geolocationFailed || "The Geolocation service failed"
        : messages.noGeolocationSupport || "Your browser doesn`t support geolocation";
    infoWindow.setPosition(pos);
    infoWindow.setContent("Error: " + content + ".");
    if (mapInstance) {
        infoWindow.open(mapInstance);
    }
}

function posCalculateDeliveryDistance(options) {
    const origins = options.origins;
    const destinations = options.destinations;
    const geocodedAddress = options.geocodedAddress;
    const extraChargeUrl = options.extraChargeUrl;
    const storeId = options.storeId;
    const currencySymbol = options.currencySymbol || "";
    const warningMessage = options.warningMessage || "";
    const toggleLoading =
        typeof options.toggleLoading === "function"
            ? options.toggleLoading
            : function () {};

    function applyDeliveryDistance(distanceMeters, resolvedAddress) {
        let distanceKm = distanceMeters / 1000;
        let distancMileResult =
            Math.round((distanceKm + Number.EPSILON) * 100) / 100;
        document.getElementById("distance").value = distancMileResult;
        document.getElementById("address").value = resolvedAddress;

        let requestData = {
            distancMileResult: distancMileResult,
            customer_id: document.getElementById("customer")?.value || "",
        };
        if (storeId !== undefined && storeId !== null && storeId !== "") {
            requestData.store_id = storeId;
        }
        // An area/zip-priced zone's coverage pick has to survive a new map pin too. Clicking a
        // new point re-quotes the fee from scratch here, and without the pick this fell through
        // to DeliveryRuleChargeService::chargeForArea()/chargeForZipCode()'s own "nothing
        // selected" case (a bare 0, floored back up to just the rule's bare minimum) -- silently
        // discarding whatever the admin had already chosen in the coverage picker above and
        // replacing a real area/zip charge with the zone's floor price.
        const $coveragePicker = $("#coverage_picker_select");
        const coverageFieldName = $coveragePicker.attr("name");
        const coverageValue = $coveragePicker.val();
        if (coverageFieldName && coverageValue) {
            requestData[coverageFieldName] = coverageValue;
        }

        $.get({
            url: extraChargeUrl,
            dataType: "json",
            data: requestData,
            success: function (data) {
                let deliveryCharge =
                    Math.round((data + Number.EPSILON) * 100) / 100;
                document.getElementById("delivery_fee").value = deliveryCharge;
                $("#delivery_fee")
                    .siblings("strong")
                    .html(deliveryCharge + currencySymbol);
            },
            error: function () {
                let deliveryCharge = 0;
                document.getElementById("delivery_fee").value = deliveryCharge;
                $("#delivery_fee")
                    .siblings("strong")
                    .html(deliveryCharge + currencySymbol);
            },
            complete: function () {
                toggleLoading(false);
            },
        });
    }

    function deliveryDistanceFailed() {
        toggleLoading(false);
        document.getElementById("distance").value = "";
        toastr.warning(warningMessage, {
            CloseButton: true,
            ProgressBar: true,
        });
    }

    function legacyDistanceMatrix() {
        const service = new google.maps.DistanceMatrixService();
        service
            .getDistanceMatrix({
                origins: origins,
                destinations: destinations,
                travelMode: google.maps.TravelMode.DRIVING,
                unitSystem: google.maps.UnitSystem.METRIC,
                avoidHighways: false,
                avoidTolls: false,
            })
            .then(function (response) {
                let element = response.rows[0].elements[0];
                if (element.status !== "OK" || !element.distance) {
                    deliveryDistanceFailed();
                    return;
                }
                applyDeliveryDistance(
                    element.distance["value"],
                    response.destinationAddresses[1]
                );
            })
            .catch(function () {
                deliveryDistanceFailed();
            });
    }

    if (window.posUseRouteMatrix === false) {
        legacyDistanceMatrix();
        return;
    }

    google.maps
        .importLibrary("routes")
        .then(function (lib) {
            return lib.RouteMatrix.computeRouteMatrix({
                origins: origins,
                destinations: destinations,
                travelMode: "DRIVING",
            });
        })
        .then(function (result) {
            let element = result.matrix.rows[0].items[0];
            if (
                !element ||
                element.condition !== "ROUTE_EXISTS" ||
                element.distanceMeters == null
            ) {
                deliveryDistanceFailed();
                return;
            }
            applyDeliveryDistance(element.distanceMeters, geocodedAddress);
        })
        .catch(function () {
            window.posUseRouteMatrix = false;
            legacyDistanceMatrix();
        });
}

// The area/zip picker for a zone whose active delivery rule prices by one of them, rather than
// by distance. Fetched once per store selection (the store cannot change without reopening the
// page) and left hidden -- with the map/distance flow untouched -- for every other zone.
//
// storeId is omitted entirely on the vendor panel: a vendor's POS is always their own one store,
// so getDeliveryCoverage() resolves it server-side and never reads a store_id from the request.
function posInitCoveragePicker(options) {
    const coverageUrl = options.coverageUrl;
    const extraChargeUrl = options.extraChargeUrl;
    const storeId = options.storeId || null;
    const currencySymbol = options.currencySymbol || "";
    const areaLabel = options.areaLabel || "Select Area";
    const zipLabel = options.zipLabel || "Select Zip Code";

    const $wrap = $("#coverage_picker_wrap");
    const $select = $("#coverage_picker_select");
    const $labelText = $("#coverage_picker_label_text");

    $.get({
        url: coverageUrl,
        dataType: "json",
        data: storeId ? { store_id: storeId } : {},
        success: function (data) {
            const coverage = data.coverage || [];
            if (!coverage.length) {
                return;
            }

            const isZip = data.type === "zip_code_wise";
            const fieldName = isZip ? "zip_code_id" : "area_id";
            const label = isZip ? zipLabel : areaLabel;

            $select.attr("name", fieldName);
            $labelText.text(label);
            $select.empty().append($("<option>").val("").text(label));
            coverage.forEach(function (row) {
                $select.append($("<option>").val(row.id).text(row.name));
            });

            $wrap.removeClass("d-none");
        },
    });

    $(document).on("change", "#coverage_picker_select", function () {
        const value = $(this).val();
        const fieldName = $(this).attr("name");

        if (!value) {
            document.getElementById("delivery_fee").value = 0;
            $("#delivery_fee").siblings("strong").html(0 + currencySymbol);
            return;
        }

        const requestData = {
            customer_id: document.getElementById("customer")?.value || "",
        };
        if (storeId) {
            requestData.store_id = storeId;
        }
        requestData[fieldName] = value;

        $.get({
            url: extraChargeUrl,
            dataType: "json",
            data: requestData,
            success: function (fee) {
                let deliveryCharge = Math.round((fee + Number.EPSILON) * 100) / 100;
                document.getElementById("delivery_fee").value = deliveryCharge;
                $("#delivery_fee").siblings("strong").html(deliveryCharge + currencySymbol);
            },
            error: function () {
                document.getElementById("delivery_fee").value = 0;
                $("#delivery_fee").siblings("strong").html(0 + currencySymbol);
            },
        });
    });
}

// True once the picker is showing (the zone's active rule prices by area or zip) and nothing is
// picked yet -- the one case where the map's distance can't produce a real delivery fee, so the
// address save has to be blocked rather than silently pricing at the rule's bare floor.
function posCoverageSelectionMissing() {
    return !$("#coverage_picker_wrap").hasClass("d-none") && !$("#coverage_picker_select").val();
}

function posInjectPlaceSearchStyles() {
    if (document.getElementById("pos-place-autocomplete-styles")) {
        return;
    }
    const style = document.createElement("style");
    style.id = "pos-place-autocomplete-styles";
    style.textContent =
        ".pos-place-autocomplete-card{margin:9px 8px 0;}" +
        ".pos-place-autocomplete-card gmp-place-autocomplete{color-scheme:light;width:180px;max-width:calc(100vw - 32px);height:32px;font-size:11px;line-height:1;color:#4b566b;background-color:#fff;border:1px solid #fbc1c1;border-radius:8px;box-shadow:none;}";
    document.head.appendChild(style);
}

function posInitPlaceSearch(options) {
    const map = options.map;
    const inputId = options.inputId || "pac-input";
    const outOfCoverageMessage = options.outOfCoverageMessage || "";
    const onLocationSelected =
        typeof options.onLocationSelected === "function"
            ? options.onLocationSelected
            : function () {};
    const getZonePolygon =
        typeof options.getZonePolygon === "function"
            ? options.getZonePolygon
            : function () {
                  return null;
              };

    let markers = [];
    let geocoder = null;

    function clearMarkers() {
        markers.forEach(function (marker) {
            marker.map = null;
        });
        markers = [];
    }

    function notifyLocationSelected(location, displayName) {
        if (!geocoder) {
            geocoder = new google.maps.Geocoder();
        }
        geocoder.geocode({ location: location }, function (results, status) {
            let address = displayName || "";
            if (
                status === google.maps.GeocoderStatus.OK &&
                results &&
                results[1]
            ) {
                address = results[1].formatted_address;
            }
            onLocationSelected(location, address);
        });
    }

    function handleSelectedLocation(location, displayName, viewport) {
        const zonePolygon = getZonePolygon();
        if (
            zonePolygon &&
            !google.maps.geometry.poly.containsLocation(location, zonePolygon)
        ) {
            toastr.error(outOfCoverageMessage, {
                CloseButton: true,
                ProgressBar: true,
            });
            return;
        }

        document.getElementById("latitude").value = location.lat();
        document.getElementById("longitude").value = location.lng();

        clearMarkers();
        const { AdvancedMarkerElement } = google.maps.marker;
        markers.push(
            new AdvancedMarkerElement({
                map: map,
                title: displayName,
                position: location,
            })
        );

        if (viewport) {
            map.fitBounds(viewport);
        } else {
            map.setCenter(location);
            map.setZoom(17);
        }

        notifyLocationSelected(location, displayName);
    }

    function initLegacySearchBox() {
        const input = document.getElementById(inputId);
        if (!input) {
            return;
        }
        input.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
            }
        });
        input.style.display = "";
        const searchBox = new google.maps.places.SearchBox(input);
        map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
        searchBox.addListener("places_changed", function () {
            const places = searchBox.getPlaces();
            if (!places || places.length === 0) {
                return;
            }
            places.forEach(function (place) {
                if (!place.geometry || !place.geometry.location) {
                    return;
                }
                handleSelectedLocation(
                    place.geometry.location,
                    place.name,
                    place.geometry.viewport
                );
            });
        });
    }

    if (window.posUsePlaceAutocomplete === false) {
        initLegacySearchBox();
        return;
    }

    let placeAutocomplete;
    try {
        placeAutocomplete = new google.maps.places.PlaceAutocompleteElement();
    } catch (e) {
        placeAutocomplete = null;
    }

    if (!placeAutocomplete) {
        window.posUsePlaceAutocomplete = false;
        initLegacySearchBox();
        return;
    }

    const oldInput = document.getElementById(inputId);
    if (oldInput) {
        oldInput.style.display = "none";
        if (oldInput.placeholder) {
            placeAutocomplete.placeholder = oldInput.placeholder;
        }
    }

    posInjectPlaceSearchStyles();

    if (!window.posSearchEnterGuard) {
        window.posSearchEnterGuard = true;
        let lastSearchEnter = 0;
        const isSearchNode = function (node) {
            if (!node || !node.closest) {
                return false;
            }
            return (
                node.id === inputId ||
                !!node.closest(".pos-place-autocomplete-card")
            );
        };
        document.addEventListener(
            "keydown",
            function (event) {
                if (
                    event.key === "Enter" &&
                    (isSearchNode(event.target) ||
                        isSearchNode(document.activeElement))
                ) {
                    event.preventDefault();
                    lastSearchEnter = Date.now();
                }
            },
            true
        );
        document.addEventListener(
            "submit",
            function (event) {
                if (
                    isSearchNode(document.activeElement) ||
                    Date.now() - lastSearchEnter < 700
                ) {
                    event.preventDefault();
                }
            },
            true
        );
    }

    const cardId = inputId + "-autocomplete-card";
    const existingCard = document.getElementById(cardId);
    if (existingCard && existingCard.remove) {
        existingCard.remove();
    }

    const card = document.createElement("div");
    card.id = cardId;
    card.className = "pos-place-autocomplete-card";
    card.appendChild(placeAutocomplete);

    map.controls[google.maps.ControlPosition.TOP_CENTER].push(card);

    card.addEventListener(
        "keydown",
        function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
            }
        },
        true
    );

    let fallenBack = false;
    placeAutocomplete.addEventListener("gmp-error", function () {
        if (fallenBack) {
            return;
        }
        fallenBack = true;
        window.posUsePlaceAutocomplete = false;
        if (card.remove) {
            card.remove();
        }
        initLegacySearchBox();
    });

    placeAutocomplete.addEventListener("gmp-select", function (event) {
        const place = event.placePrediction.toPlace();
        place
            .fetchFields({
                fields: ["displayName", "location", "viewport"],
            })
            .then(function () {
                if (!place.location) {
                    return;
                }
                handleSelectedLocation(
                    place.location,
                    place.displayName,
                    place.viewport
                );
            })
            .catch(function () {});
    });
}
