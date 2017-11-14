<?php
namespace craft\commerce\digitalProducts\elements;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\digitalProducts\models\ProductType;
use craft\commerce\digitalProducts\Plugin as DigitalProducts;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;

/**
 * Class Commerce_ProductElementType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.elementtypes
 * @since     1.0
 */
class Product extends Purchasable
{
    // Constants
    // =========================================================================

    const STATUS_LIVE = 'live';
    const STATUS_PENDING = 'pending';
    const STATUS_EXPIRED = 'expired';

    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int Product type id
     */
    public $typeId;

    /**
     * @var int Tax category id
     */
    public $taxCategoryId;

    /**
     * @var \DateTime Post date
     */
    public $postDate;

    /**
     * @var \DateTime Expiry date
     */
    public $expiryDate;

    /**
     * @var bool Promotable
     */
    public $promotable;

    /**
     * @var string SKU
     */
    public $sku;

    /**
     * @var int $price
     */
    public $price;

    /**
     * @var ProductType
     */
    private $_productType;

    /**
     * @var bool
     */
    private $_isLicensed;

    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->title;
    }


    /**
     * @return null|string
     */
    public function getName()
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function defineSources(string $context = null): array
    {
        if ($context === 'index') {
            $productTypes = DigitalProducts::getInstance()->getProductTypes()->getEditableProductTypes();
            $editable = true;
        } else {
            $productTypes = DigitalProducts::getInstance()->getProductTypes()->getAllProductTypes();
            $editable = false;
        }

        $productTypeIds = [];

        foreach ($productTypes as $productType) {
            $productTypeIds[] = $productType->id;
        }


        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('commerce-digitalproducts', 'All products'),
                'criteria' => [
                    'typeId' => $productTypeIds,
                    'editable' => $editable
                ],
                'defaultSort' => ['postDate', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('commerce-digitalproducts', 'Product Types')];

        foreach ($productTypes as $productType) {
            $key = 'productType:'.$productType->id;
            $canEditProducts = Craft::$app->getUser()->checkPermission('digitalProducts-manageProductType:'.$productType->id);

            $sources[$key] = [
                'key' => 'producttype:'.$productType->id,
                'label' => $productType->name,
                'data' => [
                    'handle' => $productType->handle,
                    'editable' => $canEditProducts
                ],
                'criteria' => ['typeId' => $productType->id, 'editable' => $editable]
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    public function getStatuses()
    {
        return [
            self::STATUS_LIVE => Craft::t('commerce-digitalproducts', 'Live'),
            self::STATUS_PENDING => Craft::t('commerce-digitalproducts', 'Pending'),
            self::STATUS_EXPIRED => Craft::t('commerce-digitalproducts', 'Expired'),
            self::STATUS_DISABLED => Craft::t('commerce-digitalproducts', 'Disabled')
        ];
    }


    /**
     * @inheritdoc
     */
    public function getEditorHtml(): string
    {
        $viewService = Craft::$app->getView();
        $html = $viewService->renderTemplateMacro('digitalProducts/products/_fields', 'titleField', [$this]);
        $html .= parent::getEditorHtml();
        $html .= $viewService->renderTemplateMacro('digitalProducts/products/_fields', 'generalFields', [$this]);
        $html .= $viewService->renderTemplateMacro('digitalProducts/products/_fields', 'pricingFields', [$this]);
        $html .= $viewService->renderTemplateMacro('digitalProducts/products/_fields', 'behavioralMetaFields', [$this]);
        $html .= $viewService->renderTemplateMacro('digitalProducts/products/_fields', 'generalMetaFields', [$this]);

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements)
    {
        if ($handle === 'isLicensed') {
            $this->_isLicensed = isset($elements[0]) ? true : false;
            return;
        }

        parent::setEagerLoadedElements($handle, $elements);
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle)
    {
        if ($handle === 'isLicensed') {
            $userId = Craft::$app->getUser()->getId();

            if ($userId)
            {
                $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

                $map = (new Query())
                    ->select('productId as source, id as target')
                    ->from('{{%digitalproducts_licenses}}')
                    ->where(['in', 'productId', $sourceElementIds])
                    ->andWhere(['userId' => $userId])
                    ->all();

                return array(
                    'elementType' => License::class,
                    'map' => $map
                );
            }
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    public function getStatus()
    {
        $status = parent::getStatus();

        if ($status === static::STATUS_ENABLED && $this->postDate) {
            $currentTime = DateTimeHelper::currentTimeStamp();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = $this->expiryDate ? $this->expiryDate->getTimestamp() : null;

            if ($postDate <= $currentTime && (!$expiryDate || $expiryDate > $currentTime)) {
                return static::STATUS_LIVE;
            }

            if ($postDate > $currentTime) {
                return static::STATUS_PENDING;
            }

            return static::STATUS_EXPIRED;
        }

        return $status;
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $names = parent::datetimeAttributes();
        $names[] = 'postDate';
        $names[] = 'expiryDate';

        return $names;
    }

    /**
     * @inheritdoc
     */
    public function getIsEditable(): bool
    {
        if ($this->getType()) {
            $id = $this->getType()->id;

            return Craft::$app->getUser()->checkPermission('digitalProducts-manageProductType:'.$id);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl()
    {
        $productType = $this->getType();

        if ($productType) {
            return UrlHelper::cpUrl('digitalproducts/products/'.$productType->handle.'/'.$this->id);
        } else {
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        $productType = $this->getType();

        if ($productType) {
            return $productType->getProductFieldLayout();
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getUriFormat()
    {
        $productType = $this->getType();

        $siteId = $this->siteId ?: Craft::$app->getSites()->currentSite->id;

        if (isset($productType->siteSettings[$siteId]) && $productType->siteSettings[$siteId]->hasUrls) {
            $productTypeSites = $productType->getSiteSettings();

            if (isset($productTypeSites[$this->siteId])) {
                return $productTypeSites[$this->siteId]->uriFormat;
            }
        }

        return null;
    }

    /**
     * Returns the product's product type model.
     *
     * @return ProductType
     */
    public function getType()
    {
        if ($this->_productType) {
            return $this->_productType;
        }

        return $this->_productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeById($this->typeId);
    }

    /**
     * Return true if the current user has a license for this product.
     *
     * @return bool
     */
    public function getIsLicensed()
    {
        if ($this->_isLicensed === null) {
            $this->_isLicensed = false;
            $userId = Craft::$app->getUser()->getId();

            if ($userId) {

                $license = License::find()->ownerId($userId)->one();

                if ($license) {
                    $this->_isLicensed = true;
                }
            }
        }

        return $this->_isLicensed;
    }

    // Implement Purchasable
    // =========================================================================
    /**
     * @inheritdoc
     */
    public function getPurchasableId(): int
    {
        return $this->id;
    }
    /**
     * @inheritdoc
     */
    public function getSnapshot(): array
    {
        return $this->getAttributes();
    }

    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     */
    public function getTaxCategoryId(): int
    {
        return $this->taxCategoryId;
    }

    /**
     * @inheritdoc
     */
    public function hasFreeShipping(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getIsPromotable(): bool
    {
        return $this->promotable;
    }

    // Protected methods
    // =========================================================================
    /**
     * @inheritdoc
     */
    protected function route()
    {
        // Make sure the product type is set to have URLs for this site
        $siteId = Craft::$app->getSites()->currentSite->id;
        $productTypeSiteSettings = $this->getType()->getSiteSettings();

        if (!isset($productTypeSiteSettings[$siteId]) || !$productTypeSiteSettings[$siteId]->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $productTypeSiteSettings[$siteId]->template,
                'variables' => [
                    'product' => $this,
                ]
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'title' => ['label' => Craft::t('commerce-digitalproducts', 'Title')],
            'type' => ['label' => Craft::t('commerce-digitalproducts', 'Type')],
            'slug' => ['label' => Craft::t('commerce-digitalproducts', 'Slug')],
            'sku' => ['label' => Craft::t('commerce-digitalproducts', 'SKU')],
            'price' => ['label' => Craft::t('commerce-digitalproducts', 'Price')],
            'postDate' => ['label' => Craft::t('commerce-digitalproducts', 'Post Date')],
            'expiryDate' => ['label' => Craft::t('commerce-digitalproducts', 'Expiry Date')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        if ($source === '*') {
            $attributes[] = 'type';
        }

        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['title'];
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        /* @var $productType ProductType */
        $productType = $this->getType();

        switch ($attribute) {
            case 'type':
                return ($productType ? Craft::t('site', $productType->name) : '');

            case 'taxCategory':
                $taxCategory = $this->getTaxCategory();

                return ($taxCategory ? Craft::t('site', $taxCategory->name) : '');

            case 'defaultPrice':
                $code = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

                return Craft::$app->getLocale()->getFormatter()->asCurrency($this->$attribute, strtoupper($code));

            case 'promotable':
                return ($this->$attribute ? '<span data-icon="check" title="'.Craft::t('Yes').'"></span>' : '');

            default:
                return parent::tableAttributeHtml($attribute);
        }
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('commerce-digitalproducts', 'Title'),
            'postDate' => Craft::t('commerce-digitalproducts', 'Post Date'),
            'expiryDate' => Craft::t('commerce-digitalproducts', 'Expiry Date'),
            'price' => Craft::t('commerce-digitalproducts', 'Price'),
        ];
    }
}
