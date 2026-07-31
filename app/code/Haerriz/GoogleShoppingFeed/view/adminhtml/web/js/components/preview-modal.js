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
            previewContent: ko.observable('')
        },

        initialize: function () {
            this._super();
            this.injectButton();
            return this;
        },

        injectButton: function () {
            var self = this;
            // Wait for Magento's button bar to render, then prepend our button
            var checkExist = setInterval(function() {
                var $actions = $('.page-actions-buttons');
                if ($actions.length) {
                    clearInterval(checkExist);
                    var $btn = $('<button>', {
                        id: 'btn-preview-feed',
                        title: 'Preview Feed',
                        type: 'button',
                        class: 'action-secondary',
                        text: 'Preview Feed (5 Items)'
                    }).on('click', function() {
                        self.openPreview();
                    });
                    $actions.prepend($btn);
                }
            }, 500);
        },

        openPreview: function () {
            var self = this;
            
            // Serialize the current form data
            var $form = $('#edit_form');
            if (!$form.length) return;
            var formData = $form.serialize();

            // Initialize modal if not done yet
            var $modalElement = $('#feed-preview-modal');
            if (!$modalElement.hasClass('ui-dialog-content')) {
                modal({
                    type: 'slide',
                    title: 'Live Feed Preview',
                    buttons: [{
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

            $.ajax({
                url: this.previewUrl,
                type: 'POST',
                data: formData,
                success: function (res) {
                    self.isLoading(false);
                    if (res.success) {
                        self.previewContent(res.content || 'No products found or exported.');
                    } else {
                        self.hasError(true);
                        self.errorMessage(res.message || 'Unknown error occurred.');
                    }
                },
                error: function (xhr) {
                    self.isLoading(false);
                    self.hasError(true);
                    
                    var msg = 'Server error during preview generation.';
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.message) msg = response.message;
                    } catch(e) {}
                    
                    self.errorMessage(msg);
                }
            });
        }
    });
});
