define([
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/quote',
    'Haerriz_Seo/js/ga4-commerce'
], function (customerData, quote) {
    'use strict';

    return function () {
        var cart = customerData.get('cart'), beginFired = false;

        function payload(extra) {
            return Object.assign(window.senisCartPayload(), extra || {});
        }

        function begin(cartData) {
            if (beginFired || !cartData || !cartData.items || !cartData.items.length) {
                return;
            }
            beginFired = true;
            window.senisGa4('begin_checkout', payload());
        }

        begin(cart());
        cart.subscribe(begin);
        customerData.reload(['cart'], false);

        if (quote.shippingMethod && quote.shippingMethod.subscribe) {
            quote.shippingMethod.subscribe(function (method) {
                if (!method) {
                    return;
                }
                window.senisGa4('add_shipping_info', payload({
                    shipping_tier: String(method.carrier_title || method.carrier_code || '') +
                        (method.method_title ? ' - ' + method.method_title : '')
                }));
            });
        }

        if (quote.paymentMethod && quote.paymentMethod.subscribe) {
            quote.paymentMethod.subscribe(function (method) {
                if (!method) {
                    return;
                }
                window.senisGa4('add_payment_info', payload({
                    payment_type: String(method.title || method.method || '')
                }));
            });
        }
    };
});
