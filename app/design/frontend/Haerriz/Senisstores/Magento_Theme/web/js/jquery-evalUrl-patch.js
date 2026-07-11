/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */
define(['jquery'], function ($) {
    'use strict';

    /**
     * jQuery._evalUrl uses sync XHR (deprecated). Load scripts via <script> instead.
     *
     * @param {String} url
     * @returns {jQuery.Promise}
     */
    $._evalUrl = function (url) {
        return $.Deferred(function (deferred) {
            var script = document.createElement('script');

            script.async = true;
            script.src = url;
            script.onload = function () {
                deferred.resolve();
            };
            script.onerror = function () {
                deferred.reject();
            };

            document.head.appendChild(script);
        }).promise();
    };

    return $;
});
