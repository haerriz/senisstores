define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';
    return function (config) {
        var cart = customerData.get('cart')() || {}, items = (cart.items || []).map(function (item) {
            return {item_id: String(item.product_sku || item.product_id), item_name: item.product_name, price: Number(item.product_price_value || 0), quantity: Number(item.qty || 1)};
        });
        window.senisGa4(config.event, {currency: 'INR', value: Number(cart.subtotalAmount || 0), items: items});
    };
});
