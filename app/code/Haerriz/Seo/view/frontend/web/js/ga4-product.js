define(['jquery', 'Haerriz_Seo/js/ga4-commerce'], function ($) {
    'use strict';
    return function (config) {
        $('#product_addtocart_form').data('senisGa4Item', config);
    };
});
