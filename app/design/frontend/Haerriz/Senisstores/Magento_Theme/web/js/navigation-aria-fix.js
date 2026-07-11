/**
 * Haerriz/Senisstores — Navigation ARIA Accessibility Fix
 *
 * Fixes WCAG 2.1 / ARIA 1.2 compliance violations introduced by jQuery UI's
 * menu widget which adds role="menu" to <ul> and role="menuitem" to <a> tags,
 * leaving <li> elements with no ARIA role (breaking ownership chain).
 *
 * Fix strategy:
 *  - <ul> (top-level nav)  → role="menubar"
 *  - <li> (all levels)     → role="none"   (ARIA-transparent wrapper)
 *  - <ul> (sub-menus)      → role="menu"
 *  - <a>  (menu links)     → role="menuitem" (already set by jQuery UI)
 *
 * We use a MutationObserver so the fix re-applies whenever jQuery UI
 * refreshes or re-renders the navigation (e.g., responsive breakpoints).
 *
 * Adobe Commerce 2.4.7 / Per WCAG 2.1 SC 4.1.2 & ARIA 1.2 spec.
 */
define(['jquery'], function ($) {
    'use strict';

    var NAV_SELECTOR = '[data-action="navigation"] > ul, .navigation > ul';
    var fixQueued = false;

    /**
     * Apply correct ARIA roles to the navigation tree.
     */
    function applyAriaRoles() {
        var $topUl = $(NAV_SELECTOR).first();

        if ($topUl.length === 0) {
            return;
        }

        // Top-level <ul>: change from role="menu" (jQuery UI default) to role="menubar"
        $topUl.attr('role', 'menubar');

        // All <li> elements: role="none" removes them from the ARIA ownership chain
        // making <a role="menuitem"> a direct virtual child of <ul role="menubar/menu">
        $topUl.find('li').attr('role', 'none');

        // Sub-menu <ul> elements must stay as role="menu"
        $topUl.find('ul').attr('role', 'menu');
    }

    /**
     * Debounced wrapper so rapid DOM changes don't spam applyAriaRoles.
     */
    function queueFix() {
        if (!fixQueued) {
            fixQueued = true;
            setTimeout(function () {
                fixQueued = false;
                applyAriaRoles();
            }, 50);
        }
    }

    /**
     * Wait for jQuery UI to initialise the menu widget (it adds ui-menu class),
     * then apply our fix and watch for future changes.
     */
    function waitForMenuInit() {
        var $nav = $(NAV_SELECTOR).first();

        if ($nav.hasClass('ui-menu')) {
            // Already initialised — fix immediately
            applyAriaRoles();
            startObserver($nav[0]);
        } else {
            // Poll until jQuery UI has processed the element
            var attempts = 0;
            var poller = setInterval(function () {
                $nav = $(NAV_SELECTOR).first();
                attempts++;

                if ($nav.hasClass('ui-menu') || attempts > 40) {
                    clearInterval(poller);
                    applyAriaRoles();
                    startObserver($nav[0]);
                }
            }, 100);
        }
    }

    /**
     * Watch the navigation element for role/class attribute changes
     * (jQuery UI refresh, responsive handlers, etc.) and re-apply fixes.
     *
     * @param {Element} navEl
     */
    function startObserver(navEl) {
        if (!navEl || !window.MutationObserver) {
            return;
        }

        var observer = new MutationObserver(function (mutations) {
            var needsFix = false;

            mutations.forEach(function (mutation) {
                if (mutation.attributeName === 'role') {
                    var newRole = mutation.target.getAttribute('role');
                    // If something reset a role back to menu/null on the root ul, fix it
                    if (mutation.target === navEl && newRole !== 'menubar') {
                        needsFix = true;
                    }
                    // If a <li> got a role other than "none", fix it
                    if (mutation.target.tagName === 'LI' && newRole !== 'none') {
                        needsFix = true;
                    }
                }
            });

            if (needsFix) {
                queueFix();
            }
        });

        observer.observe(navEl.parentElement || navEl, {
            subtree: true,
            attributes: true,
            attributeFilter: ['role', 'class']
        });
    }

    // Kick off after DOM is ready
    $(document).ready(function () {
        waitForMenuInit();
    });
});
