/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */
define(['jquery'], function ($) {
    'use strict';

    return function (captchaWidget) {
        $.widget('mage.captcha', captchaWidget, {
            /**
             * @private
             */
            refresh: function () {
                this.element.addClass(this.options.refreshClass);

                $.ajax({
                    url: this.options.url,
                    type: 'post',
                    async: true,
                    dataType: 'json',
                    context: this,
                    data: {
                        'formId': this.options.type
                    },
                    success: function (response) {
                        if (response.imgSrc) {
                            this.element.find(this.options.imageSelector).attr('src', response.imgSrc);
                        }
                    },
                    complete: function () {
                        this.element.removeClass(this.options.refreshClass);
                    }
                });
            }
        });

        return $.mage.captcha;
    };
});
