<?php

namespace craft\commerce\digitalProducts\plugin;

use craft\commerce\digitalProducts\services\Licenses;
use craft\commerce\digitalProducts\services\Products;
use craft\commerce\digitalProducts\services\ProductTypes;

/**
 * Service trait
 *
 */
trait Services
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the license service.
     *
     * @return Licenses The license service
     */
    public function getLicenses(): Licenses
    {
        return $this->get('licenses');
    }

    /**
     * Returns the product type service.
     *
     * @return ProductTypes The product type service
     */
    public function getProductTypes(): ProductTypes
    {
        return $this->get('productTypes');
    }

    // Private Methods
    // =========================================================================

    /**
     * Set the components of the commerce plugin
     */
    private function _setPluginComponents()
    {
        $this->setComponents([
            'licenses' => Licenses::class,
            'products' => Products::class,
            'productTypes' => ProductTypes::class,
        ]);
    }
}
