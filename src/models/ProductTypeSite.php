<?php
namespace craft\digitalproducts\models;

use craft\base\Model;
use craft\digitalproducts\Plugin as DigitalProducts;
use yii\base\InvalidConfigException;

/**
 * Product type locale model class.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */

class ProductTypeSite extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int Product type ID
     */
    public $productTypeId;

    /**
     * @var int Site ID
     */
    public $siteId;

    /**
     * @var bool Has Urls
     */
    public $hasUrls;

    /**
     * @var string URL Format
     */
    public $uriFormat;

    /**
     * @var string Template Path
     */
    public $template;

    /**
     * @var ProductType
     */
    private $_productType;

    /**
     * @var bool
     */
    public $uriFormatIsRequired = true;

    // Public Methods
    // =========================================================================

    /**
     * Returns the Product Type.
     *
     * @return ProductType
     * @throws InvalidConfigException if [[groupId]] is missing or invalid
     */
    public function getProductType(): ProductType
    {
        if ($this->_productType !== null) {
            return $this->_productType;
        }

        if (!$this->productTypeId) {
            throw new InvalidConfigException('Site is missing its product type ID');
        }

        if (($this->_productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeById($this->productTypeId)) === null) {
            throw new InvalidConfigException('Invalid product type ID: '.$this->productTypeId);
        }

        return $this->_productType;
    }

    /**
     * Sets the Product Type.
     *
     * @param ProductType $productType
     *
     * @return void
     */
    public function setProductType(ProductType $productType)
    {
        $this->_productType = $productType;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->uriFormatIsRequired) {
            $rules[] = ['uriFormat', 'required'];
        }

        return $rules;
    }
}
