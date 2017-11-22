<?php
namespace craft\commerce\digitalProducts\elements;

use Craft;
use craft\base\Element;
use craft\commerce\digitalProducts\elements\db\LicenseQuery;
use craft\commerce\digitalProducts\events\GenerateKeyEvent;
use craft\commerce\digitalProducts\models\ProductType;
use craft\commerce\digitalProducts\Plugin as DigitalProducts;
use craft\commerce\digitalProducts\records\License as LicenseRecord;
use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\db\Query;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use Exception;

/**
 * Class Commerce_LicenseElementType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @since     1.0
 */
class License extends Element
{

    // Constants
    // =========================================================================

    /**
     * @event GenerateKeyEvent The event that is triggered after a payment request is being built
     */
    const EVENT_GENERATE_LICENSE_KEY = 'beforeGenerateLicenseKey';

    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int|null Product id
     */
    public $productId;

    /**
     * @var int|null Order id
     */
    public $orderId;

    /**
     * @var string the license key
     */
    public $licenseKey;

    /**
     * @var string|null License owner name
     */
    public $ownerName;

    /**
     * @var string|null License owner email
     */
    public $ownerEmail;

    /**
     * @var int|null License owner user id
     */
    public $userId;

    /**
     * @var string
     */
    private $_licensedTo = null;

    /**
     * @var Product
     */
    private $_product = null;

    /**
     * @var User
     */
    private $_user = null;

    /**
     * @var Order
     */
    private $_order = null;

    // Public Methods
    // =========================================================================

    /**
     * @return null|string
     */
    public function __toString()
    {
        return Craft::t('commerce-digitalproducts', 'License for “{product}”', ['product' => $this->getProductName()]);
    }

    /**
     * Return the email tied to the license.
     *
     * @return string
     */
    public function getLicensedTo(): string
    {
        if (null === $this->_licensedTo) {
            $this->_licensedTo = "";

            if (null !== $this->userId && null === $this->_user) {
                $this->_user = Craft::$app->getUsers()->getUserById($this->userId);
            }

            if ($this->_user) {
                $this->_licensedTo = $this->_user->email;
            } else {
                $this->_licensedTo = $this->ownerEmail;
            }
        }

        return $this->_licensedTo;
    }

    /**
     * Return the product tied to the license.
     *
     * @return Product|null
     */
    public function getProduct()
    {
        if ($this->_product) {
            return $this->_product;
        }

        return $this->_product = DigitalProducts::getInstance()->getProducts()->getProductById($this->productId);
    }

    /**
     * Return the order tied to the license.
     *
     * @return null|Order
     */
    public function getOrder()
    {
        if ($this->_order) {
            return $this->_order;
        }

        if ($this->orderId) {
            return $this->_order = Commerce::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return null;
    }

    /**
     * Return the product type for the product tied to the license.
     *
     * @return ProductType|null
     */
    public function getProductType()
    {
        $product = $this->getProduct();

        if ($product) {
            return $product->getProductType();
        }

        return null;
    }

    /**
     * @return string
     */
    public function getProductName(): string
    {
        return (string) $this->getProduct();
    }

    /**
     * @inheritdoc BaseElementModel::getCpEditUrl()
     *
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce-digitalproducts/licenses/'.$this->id);
    }

    /**
     * Get the link for editing the order that purchased this license.
     *
     * @return string
     */
    public function getOrderEditUrl(): string
    {
        if ($this->orderId) {
            return UrlHelper::cpUrl('commerce/orders/'.$this->orderId);
        }

        return '';
    }

    /**
     * @return null|string
     */
    public function getName()
    {

        return Craft::t('commerce-digitalproducts', 'License');
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
    public static function defineSources(string $context = null): array
    {
        $productTypes = DigitalProducts::getInstance()->getProductTypes()->getAllProductTypes();

        $productTypeIds = [];

        foreach ($productTypes as $productType) {
            $productTypeIds[] = $productType->id;
        }

        $sources = [
            '*' => [
                'label' => Craft::t('commerce-digitalproducts', 'All product types'),
                'criteria' => ['typeId' => $productTypeIds],
                'defaultSort' => ['dateCreated', 'desc']
            ]
        ];

        $sources[] = ['heading' => Craft::t('commerce-digitalproducts', 'Product Types')];

        foreach ($productTypes as $productType) {
            $key = 'productType:'.$productType->id;

            $sources[$key] = [
                'key' => $key,
                'label' => $productType->name,
                'data' => [
                    'handle' => $productType->handle
                ],
                'criteria' => ['typeId' => $productType->id]
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle)
    {
        $sourceElementIds = ArrayHelper::getColumn($sourceElements, 'id');

        if ($handle === 'product') {
            $map = (new Query())
                ->select('id as source, productId as target')
                ->from('{{%digitalproducts_licenses}}')
                ->where(['in', 'id', $sourceElementIds])
                ->all();

            return array(
                'elementType' => Product::class,
                'map' => $map
            );
        }

        if ($handle === 'order') {
            $map = (new Query())
                ->select('id as source, orderId as target')
                ->from('{{%digitalproducts_licenses}}')
                ->where(['in', 'id', $sourceElementIds])
                ->all();

            return array(
                'elementType' => Order::class,
                'map' => $map
            );
        }

        if ($handle === 'owner') {
            $map = (new Query())
                ->select('id as source, userId as target')
                ->from('{{%digitalproducts_licenses}}')
                ->where(['in', 'id', $sourceElementIds])
                ->all();

            return array(
                'elementType' => 'User',
                'map' => $map
            );
        }

        return parent::eagerLoadingMap($sourceElements, $handle);
    }

    /**
     * @inheritdoc
     */
    public function setEagerLoadedElements(string $handle, array $elements)
    {
        if ($handle === 'product') {
            $this->_product = $elements[0] ?? null;

            return;
        }

        if ($handle === 'owner') {
            $this->_user = $elements[0] ?? null;

            return;
        }

        if ($handle === 'order') {
            $this->_order = $elements[0] ?? null;

            return;
        }

        parent::setEagerLoadedElements($handle, $elements);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [['productId'], 'required'];
        $rules[] = [
            'userId',
            'required',
            'message' => Craft::t('commerce-digitalproducts', 'A license must have either an email or an owner assigned to it.'),
            'when' => function ($model) {
                return empty($model->ownerEmail);
            }];

        return $rules;
    }

    /**
     * @inheritdoc
     *
     * @return LicenseQuery The newly created [[LicenseQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new LicenseQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $licenseRecord = LicenseRecord::findOne($this->id);

            if (!$licenseRecord) {
                throw new Exception('Invalid license id: '.$this->id);
            }
        } else {
            $licenseRecord = new LicenseRecord();
            $licenseRecord->id = $this->id;
        }

        if ($this->userId) {
            $user = Craft::$app->getUsers()->getUserById($this->userId);
        } else {
            $user = User::find()->email($this->ownerEmail)->one();
        }

        // Assign the license to a user if config allows for it, user id is left null and email matches
        if (DigitalProducts::getInstance()->getSettings()->autoAssignUserOnPurchase
            && $this->userId === null
            && $user
        ) {
            $this->userId = $user->id;
        }

        $licenseRecord->ownerName = $user ? $user->name : $this->ownerName;
        $licenseRecord->ownerEmail = $user ? $user->email : $this->ownerEmail;
        $licenseRecord->userId = $this->userId;

        // Some properties of the license are immutable
        if ($isNew) {
            $licenseRecord->orderId = $this->orderId;
            $licenseRecord->productId = $this->productId;
            $licenseRecord->licenseKey = $this->generateKey();
        }

        $licenseRecord->save(false);

    }

    // Protected Methods
    // =========================================================================

    /**
     * Generate a new license key.
     *
     * @return string
     */
    protected function generateKey(): string
    {
        $generateKeyEvent = new GenerateKeyEvent(['license' => $this]);

        // Raising the 'afterGenerateLicenseKey' event
        if ($this->hasEventHandlers(self::EVENT_GENERATE_LICENSE_KEY)) {
            $this->trigger(self::EVENT_GENERATE_LICENSE_KEY, $generateKeyEvent);
        }

        // If a plugin provided the license key - use that.
        if ($generateKeyEvent->licenseKey !== null) {
            return $generateKeyEvent->licenseKey;
        }

        do {
            $licenseKey = DigitalProducts::getInstance()->getLicenses()->generateLicenseKey();

        } while (!DigitalProducts::getInstance()->getLicenses()->isLicenseKeyUnique($licenseKey));

        return $licenseKey;
    }

    /**
     * @inheritdoc
     */
    protected static  function defineTableAttributes(): array
    {
        return [
            'product' => ['label' => Craft::t('commerce-digitalproducts', 'Licensed Product')],
            'productType' => ['label' => Craft::t('commerce-digitalproducts', 'Product Type')],
            'dateCreated' => ['label' => Craft::t('commerce-digitalproducts', 'License Issue Date')],
            'licensedTo' => ['label' => Craft::t('commerce-digitalproducts', 'Licensed To')],
            'orderLink' => ['label' => Craft::t('commerce-digitalproducts', 'Associated Order')]
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        if ($source === '*') {
            $attributes[] = 'productType';
        }

        $attributes[] = 'product';
        $attributes[] = 'dateCreated';
        $attributes[] = 'licensedTo';
        $attributes[] = 'orderLink';


        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['licensedTo', 'product'];
    }

    /**
     * @inheritdoc
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'productType':
                return $this->getProductType();

            case 'licensedTo':
                return $this->getLicensedTo();

            case 'orderLink':
                $url = $this->getOrderEditUrl();

                return $url ? '<a href="'.$url.'">'.Craft::t('commerce-digitalproducts', 'View order').'</a>' : '';

            default: {
                return parent::tableAttributeHtml($attribute);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            'slug' => Craft::t('commerce-digitalproducts', 'Product name'),
            'licensedTo' => Craft::t('commerce-digitalproducts', 'Owner'),
            'dateCreated' => Craft::t('commerce-digitalproducts', 'License issue date'),
        ];
    }


    // Protected methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected static function prepElementQueryForTableAttribute(ElementQueryInterface $elementQuery, string $attribute)
    {
        if ($attribute === 'product')
        {
            $with = $elementQuery->with ?: [];
            $with[] = 'product';
            $elementQuery->with = $with;
            return;
        }

        if ($attribute === 'licensedTo')
        {
            $with = $elementQuery->with ?: [];
            $with[] = 'owner';
            $elementQuery->with = $with;
            return;
        }

        parent::prepElementQueryForTableAttribute($elementQuery, $attribute);
    }
}
