define([
    'Magento_Customer/js/customer-data',
    'Haerriz_Seo/js/ga4-commerce'
], function (customerData) {
    'use strict';
    return function (config) {
        var fired = false, subscription, emit = function (cart) {
            if (fired || !cart || !cart.items || !cart.items.length) {
                return;
            }
            fired = true;
            window.senisGa4(config.event, window.senisCartPayload());
            if (subscription) {
                subscription.dispose();
            }
        }, observable = customerData.get('cart');

        emit(observable());
        if (!fired) {
            subscription = observable.subscribe(emit);
            customerData.reload(['cart'], false);
        }
    };
});
