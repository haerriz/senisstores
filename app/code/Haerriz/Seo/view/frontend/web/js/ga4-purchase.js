define(['Haerriz_Seo/js/ga4-commerce'], function () {
    'use strict';
    return function (config) {
        var key = 'senis_ga4_purchase_' + String(config.transaction_id || '');
        if (!config.transaction_id || window.localStorage.getItem(key)) {
            return;
        }
        window.senisGa4('purchase', config);
        window.localStorage.setItem(key, '1');
    };
});
