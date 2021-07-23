<?php

namespace craft\digitalproducts\plugin;

use craft\digitalproducts\services\Licenses;
use craft\digitalproducts\services\Products;
use craft\digitalproducts\services\ProductTypes;

/**
 * Service trait
 *
 */
trait Services
{
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
     * Returns the product  service.
     *
     * @return Products The product type service
     */
    public function getProducts(): Products
    {
        return $this->get('products');
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

    /**
     * Set the components of the commerce plugin
     */
    private function _setPluginComponents()
    {
        $this->setComponents([
            'licenses' => Licenses::class,
            'productTypes' => ProductTypes::class,
            'products' => Products::class,
        ]);
    }
}
