define(['jquery'], function ($) {
    'use strict';
    window.senisGa4 = window.senisGa4 || function (eventName, params) {
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('event', eventName, params || {});
    };

    $(document).on('ajaxComplete', function (_event, xhr, settings) {
        if (!settings || !settings.url || !/checkout\/cart\/add/i.test(settings.url) || xhr.status < 200 || xhr.status >= 300) {
            return;
        }
        var form = $('#product_addtocart_form'), data = form.data('senisGa4Item');
        if (data) {
            window.senisGa4('add_to_cart', data);
        }
    });

    return function () {};
});
