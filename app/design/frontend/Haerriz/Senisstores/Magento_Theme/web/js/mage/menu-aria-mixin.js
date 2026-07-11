/**
 * Magento ARIA Navigation Fix Mixin
 * Fixes accessibility issues with jQuery UI menu widget on Magento 2.4.7:
 *
 * 1. role="menu" on <ul> with role="menuitem" on <a> inside <li> breaks ARIA ownership.
 *    Fix: add role="none" to <li> elements — makes them ARIA-transparent so
 *         <a role="menuitem"> is effectively a direct child of <ul role="menu">.
 *
 * 2. Top-level navigation <ul> should use role="menubar" not role="menu".
 *
 * Per WCAG 2.1 / ARIA 1.2 / Adobe Commerce 2.4.7 a11y standards.
 */
define(['jquery'], function ($) {
    'use strict';

    return function (widget) {
        $.widget('mage.menu', widget, {

            /**
             * Override _create to fix ARIA roles after jQuery UI initialises.
             */
            _create: function () {
                this._super();
                this._fixAriaRoles();
            },

            /**
             * Override refresh to re-apply ARIA fixes after dynamic updates.
             */
            refresh: function () {
                this._super();
                this._fixAriaRoles();
            },

            /**
             * Fix ARIA roles so the navigation tree is compliant:
             *
             *  <nav role="navigation">
             *    <ul role="menubar">           <- top level gets menubar
             *      <li role="none">            <- li is transparent to ARIA
             *        <a role="menuitem">       <- correct ownership chain
             *          <ul role="menu">        <- sub-menus keep role="menu"
             *            <li role="none">
             *              <a role="menuitem">
             */
            _fixAriaRoles: function () {
                var $root = this.element;

                // Top-level <ul> (direct child of <nav data-action="navigation">) gets role="menubar"
                // Sub-level <ul> keep role="menu" (already set by jQuery UI)
                if ($root.parent('[data-action="navigation"]').length ||
                    $root.parent('.navigation').length) {
                    $root.attr('role', 'menubar');
                }

                // All <li> elements: set role="none" to remove them from
                // the ARIA ownership chain (they are purely structural wrappers)
                $root.find('li').each(function () {
                    $(this).attr('role', 'none');
                });

                // Sub-menu <ul> elements must keep role="menu"
                $root.find('ul').each(function () {
                    $(this).attr('role', 'menu');
                });

                // Anchors that are direct children of <li> must have role="menuitem"
                // jQuery UI sets this; re-affirm to ensure nothing is missed
                $root.find('li > a').each(function () {
                    if (!$(this).attr('role')) {
                        $(this).attr('role', 'menuitem');
                    }
                });
            }
        });

        return $.mage.menu;
    };
});
