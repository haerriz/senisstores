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
            guidanceRows: ko.observableArray([]),
            completenessScore: ko.observable(0),
            dryRunChanged: ko.observable(false),
            qaReportUrl: ''
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
                    if (!$('#btn-qa-report').length) {
                        $('<button>', {
                            id: 'btn-qa-report',
                            title: 'Download QA Report',
                            type: 'button',
                            class: 'action-secondary',
                            text: 'Download QA Report'
                        }).on('click', function () {
                            self.openQaReport();
                        }).insertAfter('#btn-preview-feed');
                    }
                } else if (attempts > 40) {
                    clearInterval(checkExist);
                }
            }, 250);
        },

        openQaReport: function () {
            var id = $('[name="profile_id"], [name="data[profile_id]"]').val()
                || $('[name="entity_id"], [name="data[entity_id]"]').val();
            if (!id) {
                this.hasError(true);
                this.errorMessage('Save the profile before downloading a QA report.');
                return;
            }
            window.location.href = this.qaReportUrl.replace('__PROFILE_ID__', encodeURIComponent(id));
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
            self.guidanceRows([]);
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
                        self.guidanceRows(self.buildGuidanceRows(completeness));
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
        },

        buildGuidanceRows: function (completeness) {
            var counts = completeness.field_missing_counts || {};
            var guidance = completeness.guidance || {};

            return Object.keys(counts).filter(function (field) {
                return counts[field] > 0;
            }).map(function (field) {
                return {
                    field: field,
                    missing: counts[field],
                    guidance: guidance[field] || ''
                };
            });
        }
    });
});
