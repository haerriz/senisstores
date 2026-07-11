/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */
var config = {
    deps: [
        'Magento_Theme/js/jquery-evalUrl-patch'
    ],
    config: {
        mixins: {
            'mage/tabs': {
                'Magento_Theme/js/mage/tabs-mixin': true
            },
            'mage/collapsible': {
                'Magento_Theme/js/mage/collapsible-mixin': true
            },
            'Magento_Captcha/js/captcha': {
                'Magento_Theme/js/mage/captcha-mixin': true
            }
        }
    }
};
