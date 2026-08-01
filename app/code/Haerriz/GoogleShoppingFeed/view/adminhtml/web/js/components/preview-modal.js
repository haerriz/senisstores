define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Ui/js/modal/modal'
], function (Component, ko, $, modal) {
    'use strict';

    return Component.extend({
        defaults: {
            isLoading: ko.observable(false),
            hasError: ko.observable(false),
            errorMessage: ko.observable(''),
            previewContent: ko.observable(''),
            rowCount: ko.observable(0),
            format: ko.observable(''),
            channel: ko.observable(''),
            fieldErrors: ko.observableArray([]),
            completenessScore: ko.observable(0),
            dryRunChanged: ko.observable(false)
        },

        initialize: function () {
            this._super();
            this.injectButton();
            return this;
        },

        injectButton: function () {
            var self = this;
            var attempts = 0;
            var checkExist = setInterval(function () {
                attempts += 1;
                var $actions = $('.page-actions-buttons');
                if ($actions.length) {
                    clearInterval(checkExist);
                    if (!$('#btn-preview-feed').length) {
                        var $btn = $('<button>', {
                            id: 'btn-preview-feed',
                            title: 'Preview Feed',
                            type: 'button',
                            class: 'action-secondary',
                            text: 'Preview Feed (5 Items)'
                        }).on('click', function () {
                            self.openPreview();
                        });
                        $actions.prepend($btn);
                    }
                } else if (attempts > 40) {
                    clearInterval(checkExist);
                }
            }, 250);
        },

        openPreview: function () {
            var self = this;
            var $form = $('#edit_form');
            if (!$form.length) {
                self.hasError(true);
                self.errorMessage('Feed edit form not found. Save the page and try again.');
                return;
            }

            var formData = $form.serializeArray();
            if (window.FORM_KEY) {
                formData.push({name: 'form_key', value: window.FORM_KEY});
            }
            formData.push({
                name: 'dry_run_changed',
                value: self.dryRunChanged() ? '1' : '0'
            });

            var $modalElement = $('#feed-preview-modal');
            if (!$modalElement.hasClass('ui-dialog-content')) {
                modal({
                    type: 'slide',
                    title: 'Live Feed Preview',
                    buttons: [{
                        text: 'Refresh',
                        class: 'action-primary',
                        click: function () {
                            self.openPreview();
                        }
                    }, {
                        text: 'Close',
                        class: 'action-secondary',
                        click: function () {
                            this.closeModal();
                        }
                    }]
                }, $modalElement);
            }

            $modalElement.modal('openModal');
            self.isLoading(true);
            self.hasError(false);
            self.previewContent('');
            self.fieldErrors([]);
            self.rowCount(0);
            self.format('');
            self.channel('');
            self.completenessScore(0);

            $.ajax({
                url: this.previewUrl,
                type: 'POST',
                dataType: 'json',
                data: $.param(formData),
                success: function (res) {
                    self.isLoading(false);
                    if (res && res.success) {
                        self.previewContent(res.content || 'No products found or exported.');
                        self.rowCount(res.row_count || 0);
                        self.format(res.format || '');
                        self.channel(res.channel || '');
                        self.fieldErrors(Array.isArray(res.field_errors) ? res.field_errors : []);
                        var completeness = res.completeness || {};
                        self.completenessScore(completeness.score != null ? completeness.score : 0);
                    } else {
                        self.hasError(true);
                        self.errorMessage((res && res.message) || 'Unknown error occurred.');
                    }
                },
                error: function (xhr) {
                    self.isLoading(false);
                    self.hasError(true);

                    var msg = 'Server error during preview generation.';
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            msg = response.message;
                        }
                    } catch (e) {
                        if (xhr.responseText) {
                            msg = xhr.responseText.substring(0, 500);
                        }
                    }
                    self.errorMessage(msg);
                }
            });
        }
    });
});
