<?php
namespace craft\commerce\digitalProducts\variables;

use craft\commerce\digitalProducts\elements\db\LicenseQuery;
use craft\commerce\digitalProducts\elements\db\ProductQuery;
use craft\commerce\digitalProducts\elements\License;
use craft\commerce\digitalProducts\elements\Product;
use craft\commerce\digitalProducts\models\ProductType;
use craft\commerce\digitalProducts\Plugin as DigitalProductsPlugin;

/**
 * Variable class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 */
class DigitalProducts
{
    /**
     * Return the product types service.
     *
     * @return ProductType[]
     */
    public function getProductTypes(): array
    {
        return DigitalProductsPlugin::getInstance()->getProductTypes()->getAllProductTypes();
    }

    /**
     * Get licenses service.
     *
     * @return LicenseQuery
     */
    public function licenses(): LicenseQuery
    {
        return License::find();
    }

    /**
     * Get Digital Products.
     *
     * @return ProductQuery
     */
    public function products(): ProductQuery
    {
        return Product::find();
    }
}
