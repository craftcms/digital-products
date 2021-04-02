<?php

namespace craft\digitalproducts\elements;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\digitalproducts\elements\actions\DeleteProduct;
use craft\digitalproducts\elements\db\ProductQuery;
use craft\digitalproducts\models\ProductType;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\digitalproducts\records\Product as ProductRecord;
use craft\elements\actions\SetStatus;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use DateTime;
use yii\base\Exception;

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
     * @var DateTime Post date
     */
    public $postDate;

    /**
     * @var DateTime Expiry date
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
     * @var float $price
     */
    public $price;

    /**
     * @var ProductType
     */
    private $_productType;

    /**
     * @var License[]
     */
    private $_existingLicenses;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('digital-products', 'Digital Product');
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->title;
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
                'label' => Craft::t('digital-products', 'All products'),
                'criteria' => [
                    'typeId' => $productTypeIds,
                    'editable' => $editable
                ],
                'defaultSort' => ['postDate', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('digital-products', 'Product Types')];

        foreach ($productTypes as $productType) {
            $key = 'productType:' . $productType->uid;
            $canEditProducts = Craft::$app->getUser()->checkPermission('digitalProducts-manageProductType:' . $productType->uid);

            $sources[$key] = [
                'key' => $key,
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
    public function getStatuses(): array
    {
        return [
            self::STATUS_LIVE => Craft::t('digital-products', 'Live'),
            self::STATUS_PENDING => Craft::t('digital-products', 'Pending'),
            self::STATUS_EXPIRED => Craft::t('digital-products', 'Expired'),
            self::STATUS_DISABLED => Craft::t('digital-products', 'Disabled')
        ];
    }


    /**
     * @inheritdoc
     */
    public function getEditorHtml(): string
    {
        $viewService = Craft::$app->getView();
        $html = $viewService->renderTemplateMacro('digital-products/products/_fields', 'titleField', [$this]);
        $html .= parent::getEditorHtml();
        $html .= $viewService->renderTemplateMacro('digital-products/products/_fields', 'generalFields', [$this]);
        $html .= $viewService->renderTemplateMacro('digital-products/products/_fields', 'pricingFields', [$this]);
        $html .= $viewService->renderTemplateMacro('digital-products/products/_fields', 'behavioralMetaFields', [$this]);
        $html .= $viewService->renderTemplateMacro('digital-products/products/_fields', 'generalMetaFields', [$this]);

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements)
    {
        if ($handle === 'existingLicenses') {
            $this->_existingLicenses = $elements;

            return;
        }

        parent::setEagerLoadedElements($handle, $elements);
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle)
    {
        if ($handle === 'existingLicenses') {
            $userId = Craft::$app->getUser()->getId();

            if ($userId) {
                $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

                $map = (new Query())
                    ->select('productId as source, id as target')
                    ->from('{{%digitalproducts_licenses}}')
                    ->where(['in', 'productId', $sourceElementIds])
                    ->andWhere(['userId' => $userId])
                    ->all();

                return [
                    'elementType' => License::class,
                    'map' => $map
                ];
            }
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    public function getIsAvailable(): bool
    {
        return $this->getStatus() === static::STATUS_LIVE;
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
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['typeId', 'sku', 'price'], 'required'];
        $rules[] = [['sku'], 'string'];

        return $rules;
    }


    /**
     * @inheritdoc
     *
     * @return ProductQuery The newly created [[ProductQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new ProductQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getIsEditable(): bool
    {
        if ($this->getType()) {
            $uid = $this->getType()->uid;

            return Craft::$app->getUser()->checkPermission('digitalProducts-manageProductType:' . $uid);
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
            return UrlHelper::cpUrl('digital-products/products/' . $productType->handle . '/' . $this->id);
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        $productType = $this->getType();

        return $productType ? $productType->getProductFieldLayout() : null;
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
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return true;
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

        return $this->typeId ? $this->_productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeById($this->typeId) : null;
    }

    /**
     * Gets the tax category
     *
     * @return TaxCategory|null
     */
    public function getTaxCategory()
    {
        if ($this->taxCategoryId) {
            return Commerce::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
        }

        return null;
    }

    /**
     * Return all the licenses for this product for the current user.
     *
     * @return License[]
     */
    public function getExistingLicenses(): array
    {
        if ($this->_existingLicenses === null) {
            $this->_existingLicenses = [];
            $userId = Craft::$app->getUser()->getId();

            if ($userId) {
                $this->_existingLicenses = License::find()->ownerId($userId)->all();
            }
        }

        return $this->_existingLicenses;
    }

    /**
     * @inheritdoc
     * @since 2.4
     */
    public function getGqlTypeName(): string
    {
        return static::gqlTypeNameByContext($this->getType());
    }

    /**
     * @inheritdoc
     * @since 2.4
     */
    public static function gqlTypeNameByContext($context): string
    {
        /** @var ProductType $context */
        return $context->handle . '_Product';
    }

    /**
     * @inheritdoc
     * @since 2.4
     */
    public static function gqlScopesByContext($context): array
    {
        /** @var ProductType $context */
        return ['digitalProductTypes.' . $context->uid];
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $productRecord = ProductRecord::findOne($this->id);

            if (!$productRecord) {
                throw new Exception('Invalid product id: ' . $this->id);
            }
        } else {
            $productRecord = new ProductRecord();
            $productRecord->id = $this->id;
        }

        $productRecord->postDate = $this->postDate;
        $productRecord->expiryDate = $this->expiryDate;
        $productRecord->typeId = $this->typeId;
        $productRecord->promotable = (bool)$this->promotable;
        $productRecord->taxCategoryId = $this->taxCategoryId;
        $productRecord->price = $this->price;

        // Generate SKU if empty
        if (empty($this->sku)) {
            try {
                $productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeById($this->typeId);
                $this->sku = Craft::$app->getView()->renderObjectTemplate($productType->skuFormat, $this);
            } catch (\Exception $e) {
                $this->sku = '';
            }
        }

        $productRecord->sku = $this->sku;

        $productRecord->save(false);

        return parent::afterSave($isNew);
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
    public function getPrice(): float
    {
        return (float)$this->price;
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
     * @return bool
     */
    public function getIsShippable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getIsPromotable(): bool
    {
        return (bool)$this->promotable;
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
                'template' => (string)$productTypeSiteSettings[$siteId]->template,
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
            'title' => ['label' => Craft::t('digital-products', 'Title')],
            'type' => ['label' => Craft::t('digital-products', 'Type')],
            'slug' => ['label' => Craft::t('digital-products', 'Slug')],
            'sku' => ['label' => Craft::t('digital-products', 'SKU')],
            'price' => ['label' => Craft::t('digital-products', 'Price')],
            'postDate' => ['label' => Craft::t('digital-products', 'Post Date')],
            'expiryDate' => ['label' => Craft::t('digital-products', 'Expiry Date')],
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
                return ($this->$attribute ? '<span data-icon="check" title="' . Craft::t('digital-products', 'Yes') . '"></span>' : '');

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
            'title' => Craft::t('digital-products', 'Title'),
            [
                'label' => Craft::t('digital-products', 'Post Date'),
                'orderBy' => 'postDate',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('digital-products', 'Expiry Date'),
                'orderBy' => 'expiryDate',
                'defaultDir' => 'desc',
            ],
            'price' => Craft::t('digital-products', 'Price'),
        ];
    }

    /**
     * @inheritdoc
     * @since 2.4
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = parent::defineActions($source);

        // Get the section(s) we need to check permissions on
        if ($source == '*') {
            $productTypes = DigitalProducts::getInstance()->getProductTypes()->getEditableProductTypes();
        } else if (preg_match('/^productType:(\d+)$/', $source, $matches)) {
            $productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeById($matches[1]);

            if ($productType) {
                $productTypes = [$productType];
            }
        } else if (preg_match('/^productType:(.+)$/', $source, $matches)) {
            $productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeByUid($matches[1]);

            if ($productType) {
                $productTypes = [$productType];
            }
        }

        if (!empty($productTypes)) {
            $userSession = Craft::$app->getUser();
            $canManage = false;

            foreach ($productTypes as $productType) {
                $canManage = $userSession->checkPermission('digitalProducts-manageProductType:' . $productType->uid);
            }

            if ($canManage) {
                // Allow deletion
                $deleteAction = Craft::$app->getElements()->createAction([
                    'type' => DeleteProduct::class,
                    'confirmationMessage' => Craft::t('digital-products', 'Are you sure you want to delete the selected products?'),
                    'successMessage' => Craft::t('digital-products', 'Products deleted.'),
                ]);
                $actions[] = $deleteAction;
                $actions[] = SetStatus::class;
            }
        }

        return $actions;
    }
}
