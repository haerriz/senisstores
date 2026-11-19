define([
    'uiRegistry',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/customer-data',
    'domReady!'
], function (registry, $, quote, customerData) {
    'use strict';

    return function () {

    var lastRatedPostcode = '',
        lastLookupPostcode = '',
        lookupRequest = null;

    function requestRates(address) {
        require([
            'Magento_Checkout/js/model/shipping-rate-processor/new-address',
            'Magento_Checkout/js/model/shipping-rate-registry'
        ], function (rateProcessor, rateRegistry) {
            if (typeof address.getCacheKey === 'function') {
                rateRegistry.set(address.getCacheKey(), null);
            }
            rateProcessor.getRates(address);
        });
    }

    function syncPostcodeAndRequestRates(checkoutProvider, postcode, countryId, force) {
        var address = quote.shippingAddress(),
            oldCacheKey;

        checkoutProvider.set('shippingAddress.postcode', postcode);
        checkoutProvider.set('shippingAddress.country_id', countryId);

        if (!address) {
            return;
        }

        if (!force && postcode === lastRatedPostcode && address.postcode === postcode) {
            return;
        }

        oldCacheKey = typeof address.getCacheKey === 'function' ? address.getCacheKey() : null;
        address.postcode = postcode;
        address.countryId = countryId;
        lastRatedPostcode = postcode;

        require(['Magento_Checkout/js/model/shipping-rate-registry'], function (rateRegistry) {
            if (oldCacheKey) {
                rateRegistry.set(oldCacheKey, null);
            }
            requestRates(address);
        });
    }

    function findRegionId(regionComponent, stateName) {
        var options = typeof regionComponent.options === 'function' ?
                regionComponent.options() : regionComponent.options,
            normalizedState = stateName.toLowerCase(),
            regionId = '';

        $.each(options || [], function (index, option) {
            var label = option.label ? option.label.trim().toLowerCase() : '';

            if (label === normalizedState || label.substring(0, 4) === normalizedState.substring(0, 4)) {
                regionId = option.value;
                return false;
            }
        });

        return regionId;
    }

    function autofillCityAndRegion(checkoutProvider, postcode, postOffice) {
        registry.get(
            'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset.region_id',
            function (regionComponent) {
                var stateName = postOffice.State,
                    regionId = findRegionId(regionComponent, stateName),
                    address = quote.shippingAddress();

                checkoutProvider.set('shippingAddress.city', postOffice.District);
                checkoutProvider.set('shippingAddress.region', stateName);
                if (regionId) {
                    checkoutProvider.set('shippingAddress.region_id', regionId);
                }

                if (address && address.postcode === postcode) {
                    address.city = postOffice.District;
                    address.region = stateName;
                    if (regionId) {
                        address.regionId = regionId;
                    }
                }
            }
        );
    }

    function lookupPostcode(checkoutProvider, postcode) {
        var $postcode = $('input[name="postcode"]');

        if (postcode === lastLookupPostcode) {
            return;
        }

        lastLookupPostcode = postcode;
        if (lookupRequest) {
            lookupRequest.abort();
        }

        $postcode.css('border-color', '#3498db');
        lookupRequest = $.ajax({
            url: 'https://api.postalpincode.in/pincode/' + encodeURIComponent(postcode),
            dataType: 'json',
            timeout: 5000
        }).done(function (response) {
            if (response && response[0] && response[0].Status === 'Success' && response[0].PostOffice) {
                autofillCityAndRegion(checkoutProvider, postcode, response[0].PostOffice[0]);
                $postcode.css('border-color', '#2ecc71');
            } else {
                $postcode.css('border-color', '');
            }
        }).fail(function (xhr, status) {
            if (status !== 'abort') {
                $postcode.css('border-color', '');
            }
        }).always(function () {
            lookupRequest = null;
        });
    }

    function handlePostcodeChange(value) {
        var postcode = value ? value.toString().replace(/\D/g, '') : '';

        if (postcode.length !== 6) {
            lastRatedPostcode = '';
            lastLookupPostcode = '';
            $('input[name="postcode"]').css('border-color', '');
            return;
        }

        registry.get('checkoutProvider', function (checkoutProvider) {
            var countryId = $('select[name="country_id"]').val();

            if (countryId !== 'IN') {
                return;
            }

            // Shipping calculation must not depend on the optional third-party autofill service.
            syncPostcodeAndRequestRates(checkoutProvider, postcode, countryId, false);
            lookupPostcode(checkoutProvider, postcode);
        });
    }

    customerData.get('cart').subscribe(function () {
        registry.get('checkoutProvider', function (checkoutProvider) {
            var formAddress = checkoutProvider.get('shippingAddress') || {},
                countryId = $('select[name="country_id"]').val();

            if (countryId === 'IN' && /^\d{6}$/.test(formAddress.postcode || '')) {
                syncPostcodeAndRequestRates(checkoutProvider, formAddress.postcode, countryId, true);
            }
        });
    });

    $(document).on('input change', 'input[name="postcode"]', function () {
        handlePostcodeChange($(this).val());
    });
    };
});
