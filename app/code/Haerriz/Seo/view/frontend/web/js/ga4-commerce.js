define(['jquery', 'Magento_Customer/js/customer-data'], function ($, customerData) {
    'use strict';

    function normaliseItem(item) {
        return {
            item_id: String(item.item_id || item.product_sku || item.product_id || ''),
            item_name: String(item.item_name || item.product_name || ''),
            price: Number(item.price || item.product_price_value || 0),
            quantity: Number(item.quantity || item.qty || 1)
        };
    }

    window.senisCartPayload = function () {
        var cart = customerData.get('cart')() || {}, items = (cart.items || []).map(normaliseItem);
        return {
            currency: String(cart.currencyCode || 'INR'),
            value: Number(cart.subtotalAmount || 0),
            items: items
        };
    };

    window.senisGa4 = window.senisGa4 || function (eventName, params) {
        var payload = params || {};
        window.dataLayer = window.dataLayer || [];

        // A stable custom event keeps the payload GTM-ready without causing a
        // second GA4 hit until the Senis GTM container is published.
        window.dataLayer.push({ecommerce: null});
        window.dataLayer.push({
            event: 'senis_ecommerce',
            senis_event: eventName,
            ecommerce: payload
        });

        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    };

    (function trackCartRemovals() {
        var previous = null, observable = customerData.get('cart');

        function capture(cart) {
            var current = (cart && cart.items ? cart.items : []).map(normaliseItem), currentIds = {};
            current.forEach(function (item) { currentIds[item.item_id] = true; });
            if (previous !== null) {
                previous.filter(function (item) {
                    return !currentIds[item.item_id];
                }).forEach(function (item) {
                    window.senisGa4('remove_from_cart', {
                        currency: String((cart && cart.currencyCode) || 'INR'),
                        value: Number(item.price * item.quantity),
                        items: [item]
                    });
                });
            }
            previous = current;
        }

        capture(observable());
        observable.subscribe(capture);
    }());

    $(document).on('ajax:addToCart', function (_event, data) {
        var item = $('#product_addtocart_form').data('senisGa4Item');
        if (!item && data && data.productIds && data.productIds.length) {
            item = {
                currency: 'INR',
                items: [{item_id: String(data.productIds[0]), quantity: 1}]
            };
        }
        if (item) {
            window.senisGa4('add_to_cart', item);
        }
    });

    $(document).on('ajaxComplete', function (_event, xhr, settings) {
        if (!settings || !settings.url || !/checkout\/cart\/add/i.test(settings.url) || xhr.status < 200 || xhr.status >= 300) {
            return;
        }
        // Some themes do not emit ajax:addToCart. Avoid duplicates when they do.
        var form = $('#product_addtocart_form'), item = form.data('senisGa4Item');
        if (item && !form.data('senisGa4AjaxEventSeen')) {
            window.senisGa4('add_to_cart', item);
        }
    });

    $(document).on('ajax:addToCart', function () {
        $('#product_addtocart_form').data('senisGa4AjaxEventSeen', true);
        window.setTimeout(function () {
            $('#product_addtocart_form').removeData('senisGa4AjaxEventSeen');
        }, 2000);
    });

    return function () {};
});
