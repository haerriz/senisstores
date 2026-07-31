define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Ui/js/modal/alert'
], function (Component, ko, $, alert) {
    'use strict';

    return Component.extend({
        defaults: {
            isLoading: ko.observable(true),
            magentoCategories: ko.observableArray([]),
            googleCategories: ko.observableArray([]),
            mappings: ko.observable({}), // magento_id -> google_id
            
            selectedMagentoId: ko.observable(null),
            searchQuery: ko.observable(''),
        },

        initialize: function () {
            this._super();
            this.loadData();
            return this;
        },

        loadData: function () {
            var self = this;
            $.ajax({
                url: this.treeUrl,
                type: 'GET',
                success: function (res) {
                    if (res.success) {
                        self.magentoCategories(res.magento_categories);
                        self.googleCategories(res.google_categories);
                        self.mappings(res.mappings || {});
                    }
                    self.isLoading(false);
                }
            });
        },

        filteredGoogleCategories: ko.computed(function () {
            // Can't use 'this' directly in computed if context is wrong without passing it, but uiComponent binds it.
            // Wait, standard KO computed in extend needs to be a function that references self if initialized later.
            // Since we declare it in defaults or extend, we should define it in initialize.
            return []; 
        }),

        initObservable: function () {
            this._super();
            var self = this;
            
            this.filteredGoogleCategories = ko.computed(function () {
                var q = self.searchQuery().toLowerCase();
                if (!q) return self.googleCategories().slice(0, 200); // Limit initial render for speed
                
                return ko.utils.arrayFilter(self.googleCategories(), function (cat) {
                    return cat.name.toLowerCase().indexOf(q) >= 0 || cat.id.toString().indexOf(q) >= 0;
                }).slice(0, 200);
            });
            
            return this;
        },

        selectMagentoCategory: function (category) {
            this.selectedMagentoId(category.id);
            this.searchQuery(''); // Reset search on select
        },

        getMappingLabel: function (magentoId) {
            var mappings = this.mappings();
            var googleId = mappings[magentoId];
            if (!googleId) return null;
            
            var match = ko.utils.arrayFirst(this.googleCategories(), function(cat) {
                return cat.id == googleId;
            });
            return match ? match.name : googleId;
        },

        isMapped: function (googleId) {
            var magentoId = this.selectedMagentoId();
            if (!magentoId) return false;
            return this.mappings()[magentoId] == googleId;
        },

        mapCategory: function (googleCategory) {
            var magentoId = this.selectedMagentoId();
            if (!magentoId) return;

            var self = this;
            var currentMappings = this.mappings();
            currentMappings[magentoId] = googleCategory.id;
            this.mappings(currentMappings);
            this.mappings.valueHasMutated(); // Force UI update

            $.ajax({
                url: this.saveUrl,
                type: 'POST',
                data: {
                    form_key: window.FORM_KEY,
                    magento_category_id: magentoId,
                    google_category_id: googleCategory.id
                },
                success: function (res) {
                    if (!res.success) {
                        alert({title: 'Error', content: res.message});
                    }
                }
            });
        },

        removeMapping: function () {
            var magentoId = this.selectedMagentoId();
            if (!magentoId) return;

            var self = this;
            var currentMappings = this.mappings();
            delete currentMappings[magentoId];
            this.mappings(currentMappings);
            this.mappings.valueHasMutated();

            $.ajax({
                url: this.saveUrl,
                type: 'POST',
                data: {
                    form_key: window.FORM_KEY,
                    magento_category_id: magentoId,
                    google_category_id: 0 // 0 triggers delete
                }
            });
        }
    });
});
