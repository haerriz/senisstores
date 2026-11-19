define(['jquery'], function ($) {
    'use strict';
    return function (config) {
        window.senisGa4('view_item', config);
        $('#product_addtocart_form').data('senisGa4Item', config);
    };
});
