define([
    'uiComponent',
    'ko',
    'jquery'
], function (Component, ko, $) {
    'use strict';

    return Component.extend({
        defaults: {
            isLoading: ko.observable(true),
            magentoAttributes: ko.observableArray([]),
            mappings: ko.observableArray([]),
            targetTextareaId: 'attributes_mapping_serialized'
        },

        initialize: function () {
            this._super();
            var self = this;

            // Wait for DOM to be fully loaded
            $(document).ready(function() {
                self.loadAttributes();
                self.hijackTextarea();
            });

            // Subscribe to changes to update the hidden textarea
            this.mappings.subscribe(function() {
                self.syncToTextarea();
            }, this, "change");

            return this;
        },

        loadAttributes: function () {
            var self = this;
            $.ajax({
                url: this.attributesUrl,
                type: 'GET',
                success: function (res) {
                    if (res.success && res.attributes) {
                        self.magentoAttributes(res.attributes);
                    }
                    self.isLoading(false);
                    // Force a re-render of initial mappings so the selects populate correctly
                    var current = self.mappings().slice();
                    self.mappings([]);
                    self.mappings(current);
                },
                error: function () {
                    self.isLoading(false);
                }
            });
        },

        hijackTextarea: function () {
            var self = this;
            
            // Periodically check if Magento's UI components have rendered the textarea
            var checkExist = setInterval(function() {
                var $textarea = $('textarea[name="' + self.targetTextareaId + '"]');
                if ($textarea.length) {
                    clearInterval(checkExist);
                    
                    // Hide the standard Magento field container
                    $textarea.closest('.admin__field').hide();
                    
                    // Show our visual component
                    $('#visual-mapper-container').insertAfter($textarea.closest('.admin__field')).show();

                    // Parse existing JSON to populate visual mapper
                    try {
                        var existingData = JSON.parse($textarea.val() || '{}');
                        var mapped = [];
                        for (var field in existingData) {
                            if (existingData.hasOwnProperty(field)) {
                                var val = existingData[field];
                                var attr = val;
                                var mods = [];
                                
                                // Handle if value is array: [attribute_code, modifier1, modifier2]
                                if (Array.isArray(val)) {
                                    attr = val[0];
                                    mods = val.slice(1);
                                }
                                
                                mapped.push({
                                    field: ko.observable(field),
                                    attribute: ko.observable(attr),
                                    modifiers: ko.observableArray(mods)
                                });
                            }
                        }
                        self.mappings(mapped);
                        
                        // Setup deep subscription
                        ko.utils.arrayForEach(self.mappings(), function (mapping) {
                            mapping.field.subscribe(function() { self.syncToTextarea(); });
                            mapping.attribute.subscribe(function() { self.syncToTextarea(); });
                            mapping.modifiers.subscribe(function() { self.syncToTextarea(); });
                        });
                        
                    } catch (e) {
                        console.error('Failed to parse existing mapping data', e);
                    }
                }
            }, 500);
        },

        addMapping: function () {
            var newMapping = {
                field: ko.observable('g:new_field'),
                attribute: ko.observable('sku'),
                modifiers: ko.observableArray([])
            };
            
            var self = this;
            newMapping.field.subscribe(function() { self.syncToTextarea(); });
            newMapping.attribute.subscribe(function() { self.syncToTextarea(); });
            newMapping.modifiers.subscribe(function() { self.syncToTextarea(); });
            
            this.mappings.push(newMapping);
            this.syncToTextarea();
        },

        removeMapping: function (mapping) {
            this.mappings.remove(mapping);
            this.syncToTextarea();
        },

        promptModifiers: function (mapping) {
            var current = mapping.modifiers().join(',');
            var input = prompt("Enter modifiers separated by comma (e.g. strip_tags,round,truncate):", current);
            if (input !== null) {
                var arr = input.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
                mapping.modifiers(arr);
                this.syncToTextarea();
            }
        },

        syncToTextarea: function () {
            var $textarea = $('textarea[name="' + this.targetTextareaId + '"]');
            if (!$textarea.length) return;

            var finalJson = {};
            ko.utils.arrayForEach(this.mappings(), function (mapping) {
                var field = mapping.field();
                if (!field) return;
                
                if (mapping.modifiers().length > 0) {
                    finalJson[field] = [mapping.attribute()].concat(mapping.modifiers());
                } else {
                    finalJson[field] = mapping.attribute();
                }
            });

            // Update textarea and trigger change so Magento's UI framework knows it's dirty
            $textarea.val(JSON.stringify(finalJson, null, 4)).trigger('change');
        }
    });
});
