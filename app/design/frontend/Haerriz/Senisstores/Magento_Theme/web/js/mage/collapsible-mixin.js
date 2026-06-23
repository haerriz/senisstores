/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */
define(['jquery'], function ($) {
    'use strict';

    return function (collapsibleWidget) {
        $.widget('mage.collapsible', collapsibleWidget, {
            /**
             * @return {boolean}
             * @private
             */
            _isNavSection: function () {
                return this.element.closest('.nav-sections-items').length > 0;
            },

            /**
             * @private
             */
            _stripNavTabAria: function () {
                this.element.closest('.nav-sections-items').removeAttr('role');
                this.element.removeAttr('aria-selected');
                this.header.removeAttr('aria-selected');
            },

            /**
             * @param {boolean} expanded
             * @private
             */
            _applyNavButtonAria: function (expanded) {
                var label = this.header.find('.nav-sections-item-switch').text().trim();

                this.header.attr({
                    role: 'button',
                    'aria-expanded': expanded
                });
                this.header.removeAttr('aria-controls');
                this.element.removeAttr('aria-controls');

                if (label) {
                    this.header.attr('aria-label', label);
                }

                this.content.removeAttr('role');
            },

            /**
             * @private
             */
            _processPanels: function () {
                this._superApply(arguments);

                if (!this._isNavSection()) {
                    return;
                }

                this._stripNavTabAria();
                this._applyNavButtonAria(this.options.active);
                this.content.attr('aria-hidden', !this.options.active);
            },

            /**
             * _refresh re-applies aria-selected which is invalid on role=button.
             *
             * @private
             */
            _refresh: function () {
                this._superApply(arguments);

                if (!this._isNavSection()) {
                    return;
                }

                this._stripNavTabAria();
                this._applyNavButtonAria(this.options.active);
            },

            /**
             * @private
             */
            _open: function () {
                this._superApply(arguments);

                if (!this._isNavSection()) {
                    return;
                }

                this._stripNavTabAria();
                this._applyNavButtonAria(true);
            },

            /**
             * @private
             */
            _close: function () {
                this._superApply(arguments);

                if (!this._isNavSection()) {
                    return;
                }

                this._stripNavTabAria();
                this._applyNavButtonAria(false);
            }
        });

        return $.mage.collapsible;
    };
});
