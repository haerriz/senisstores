define([
    'jquery',
    'mage/utils/wrapper'
], function ($, wrapper) {
    'use strict';

    return function (payloadExtender) {
        return wrapper.wrap(payloadExtender, function (originalAction, payload) {
            payload = originalAction(payload);

            var selectedDT = $('#selected_delivery_timestamp_' +
                payload['addressInformation']['shipping_method_code'] + '_' +
                payload['addressInformation']['shipping_carrier_code']
            );

            if (selectedDT.length) {
                payload.addressInformation['extension_attributes'] = {
                    'selected_delivery_timestamp': selectedDT.val()
                };
            }

            return payload;
        });
    };
});

