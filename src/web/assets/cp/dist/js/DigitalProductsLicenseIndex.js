/** global: Craft */

(function($){

if (typeof Craft.DigitalProducts === 'undefined') {
    Craft.DigitalProducts = {};
}

var elementTypeClass = 'craft\\commerce\\digitalProducts\\elements\\License';

/**
 * Product index class
 */
Craft.DigitalProducts.LicenseIndex = Craft.BaseElementIndex.extend({

    afterInit: function() {
        var href = 'href="'+Craft.getUrl('commerce-digitalproducts/licenses/new')+'"',
            label = Craft.t('commerce-digitalProducts', 'New license');

        this.$newProductBtnGroup = $('<div class="btngroup submit"/>');
        this.$newProductBtn = $('<a class="btn submit add icon" '+href+'>'+label+'</a>').appendTo(this.$newProductBtnGroup);

        this.addButton(this.$newProductBtnGroup);

        this.base();
    }
});

// Register it!
try {
    Craft.registerElementIndexClass(elementTypeClass, Craft.DigitalProducts.LicenseIndex);
}
catch(e) {
    // Already registered
}

})(jQuery);
