define([
    'uiComponent',
    'ko',
    'jquery'
], function (Component, ko, $) {
    'use strict';

    return Component.extend({
        defaults: {
            status: ko.observable('initializing'),
            totalProducts: ko.observable(0),
            processedProducts: ko.observable(0),
            exportedCount: ko.observable(0),
            logs: ko.observableArray([]),
            lastLogId: 0,
            pollingInterval: null
        },

        initialize: function () {
            this._super();
            this.startGeneration();
            return this;
        },

        progressPercent: function () {
            var total = parseInt(this.totalProducts(), 10) || 0;
            var processed = parseInt(this.processedProducts(), 10) || 0;
            if (total === 0) return 0;
            return Math.min(100, Math.round((processed / total) * 100));
        },

        statusText: function () {
            var s = this.status();
            if (s === 'initializing') return 'Connecting...';
            if (s === 'running') return 'Generating Feed...';
            if (s === 'done') return 'Completed Successfully!';
            if (s === 'error') return 'Failed with Errors';
            return s;
        },

        statusColor: function () {
            var s = this.status();
            if (s === 'done') return 'green';
            if (s === 'error') return 'red';
            return '#007bdb';
        },

        startGeneration: function () {
            var self = this;
            
            // Start polling immediately so we catch the initialization
            self.startPolling();

            $.ajax({
                url: this.triggerUrl,
                type: 'POST',
                data: { form_key: window.FORM_KEY },
                success: function (res) {
                    if (!res.success) {
                        self.status('error');
                        self.logs.push({level: 'error', message: 'Failed to start generation: ' + res.message, created_at: new Date().toISOString()});
                    }
                },
                error: function (xhr) {
                    self.status('error');
                    self.logs.push({level: 'error', message: 'Server error triggering generation.', created_at: new Date().toISOString()});
                }
            });
        },

        startPolling: function () {
            var self = this;
            this.pollingInterval = setInterval(function () {
                self.pollProgress();
            }, 2000);
        },

        pollProgress: function () {
            var self = this;
            if (this.status() === 'done' || this.status() === 'error') {
                clearInterval(this.pollingInterval);
                return;
            }

            $.ajax({
                url: this.progressUrl,
                type: 'GET',
                data: { last_log_id: self.lastLogId },
                success: function (res) {
                    if (res.error) return;
                    
                    if (res.status && res.status !== 'waiting') {
                        self.status(res.status);
                    }
                    self.totalProducts(res.total_products || 0);
                    self.processedProducts(res.processed_products || 0);
                    self.exportedCount(res.exported_count || 0);

                    if (res.logs && res.logs.length > 0) {
                        res.logs.forEach(function (log) {
                            self.logs.push(log);
                            self.lastLogId = Math.max(self.lastLogId, parseInt(log.log_id, 10));
                        });
                        
                        // Auto-scroll to bottom of logs
                        var logDiv = document.getElementById('console-logs');
                        if (logDiv) {
                            logDiv.scrollTop = logDiv.scrollHeight;
                        }
                    }

                    if (res.status === 'done' || res.status === 'error') {
                        clearInterval(self.pollingInterval);
                        if (res.status === 'error' && res.failure_message) {
                            self.logs.push({level: 'error', message: res.failure_message, created_at: new Date().toISOString()});
                        }
                    }
                }
            });
        }
    });
});
