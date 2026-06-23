/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */
define(['jquery'], function ($) {
    'use strict';

    return function (tabsWidget) {
        $.widget('mage.tabs', tabsWidget, {
            /**
             * @return {boolean}
             * @private
             */
            _isNavSections: function () {
                return this.element.hasClass('nav-sections-items');
            },

            /**
             * Remove tablist ARIA from mobile nav container only.
             * Collapsible headers keep role="button" for aria-expanded.
             *
             * @private
             */
            _stripNavTabAria: function () {
                this.element.removeAttr('role');
            },

            /**
             * Skip invalid tablist ARIA on mobile nav; panels are siblings, not tablist-only children.
             *
             * @private
             */
            _processPanels: function () {
                if (this._isNavSections()) {
                    this.contents = this.element
                        .find(this.options.content)
                        .filter(this._isNotNested.bind(this));

                    this.collapsibles = this.element
                        .find(this.options.collapsibleElement)
                        .filter(this._isNotNested.bind(this));

                    this.headers = this.element
                        .find(this.options.header)
                        .filter(this._isNotNested.bind(this));

                    if (this.headers.length === 0) {
                        this.headers = this.collapsibles;
                    }

                    this.triggers = this.element
                        .find(this.options.trigger)
                        .filter(this._isNotNested.bind(this));

                    if (this.triggers.length === 0) {
                        this.triggers = this.headers;
                    }

                    this._callCollapsible();
                    this._stripNavTabAria();

                    return;
                }

                return this._superApply(arguments);
            },

            /**
             * Re-apply button ARIA after tabs finishes initializing collapsibles.
             *
             * @private
             */
            _callCollapsible: function () {
                this._superApply(arguments);

                if (!this._isNavSections()) {
                    return;
                }

                this._stripNavTabAria();
                this.collapsibles.each(function () {
                    var $header = $(this);
                    var expanded = $header.hasClass('active');

                    $header.attr({
                        role: 'button',
                        'aria-expanded': expanded
                    });
                    $header.removeAttr('aria-controls aria-selected');
                });
                this.contents.removeAttr('role');
            }
        });

        return $.mage.tabs;
    };
});
