<?php

namespace craft\digitalproducts\elements\db;

use craft\base\Element;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\models\ProductType;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\elements\User;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use DateTime;
use yii\db\Connection;

/**
 * LicenseQuery represents a SELECT SQL statement for products in a way that is independent of DBMS.
 *
 * @method License[]|array all($db = null)
 * @method License|array|false one($db = null)
 * @method License|array|false nth(int $n, Connection $db = null)
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class LicenseQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    /**
     * @var string Either owner or user email on the license
     */
    public $email;

    /**
     * @var string Email of the license owner
     */
    public $ownerEmail;

    /**
     * @var string Email of the user that owns the license
     */
    public $userEmail;

   /**
     * @var int|int[] The user id for the user that the license belongs to.
     */
    public $ownerId;

   /**
     * @var int The product id for the product that is licensed
     */
    public $productId;

    /**
     * @var int|int[] The product type id for the product that is licensed
     */
    public $typeId;

    /**
     * @var int The license date on the license
     */
    public $licenseDate;

    /**
     * @var int The id of the order that the license must be a part of.
     */
    public $orderId;

    /**
     * @var string The license key.
     */
    public $licenseKey;


    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = Element::STATUS_ENABLED;
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'owner':
                $this->owner($value);
                break;
            case 'product':
                $this->product($value);
                break;
            case 'type':
                $this->type($value);
                break;
            case 'before':
                $this->before($value);
                break;
            case 'after':
                $this->after($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Sets the [[email]] property.
     *
     * @param string $value The property value
     *
     * @return static self reference
     */
    public function email($value)
    {
        $this->email = $value;

        return $this;
    }

    /**
     * Sets the [[ownerEmail]] property.
     *
     * @param string $value The property value
     *
     * @return static self reference
     */
    public function ownerEmail($value)
    {
        $this->ownerEmail = $value;

        return $this;
    }

    /**
     * Sets the [[userEmail]] property.
     *
     * @param string $value The property value
     *
     * @return static self reference
     */
    public function userEmail($value)
    {
        $this->userEmail = $value;

        return $this;
    }

    /**
     * Sets the [[productId]] property based on a given product or the sku.
     *
     * @param User $value
     *
     * @return static self reference
     */
    public function owner($value) {
        if ($value instanceof User) {
            $this->ownerId = $value->id;
        } else if ($value !== null) {
            $this->ownerId = (new Query())
                ->select(['id'])
                ->from(['{{%users}}'])
                ->where(Db::parseParam('username', $value))
                ->column();
        } else {
            $this->ownerId = null;
        }

        return $this;
    }

    /**
     * Sets the [[productId]] property based on a given product or the sku.
     *
     * @param Product $value
     *
     * @return static self reference
     */
    public function product($value) {
        if ($value instanceof Product) {
            $this->productId = $value->id;
        } else if ($value !== null) {
            $this->productId = (new Query())
                ->select(['id'])
                ->from(['{{%digitalproducts_products}}'])
                ->where(Db::parseParam('sku', $value))
                ->column();
        } else {
            $this->productId = null;
        }

        return $this;
    }

    /**
     * Sets the [[typeId]] property based on a given product types(s)’s handle(s).
     *
     * @param string|string[]|ProductType $value The property value
     *
     * @return static self reference
     */
    public function type($value)
    {
        if ($value instanceof ProductType) {
            $this->typeId = $value->id;
        } else if ($value !== null) {
            $this->typeId = (new Query())
                ->select(['id'])
                ->from(['{{%digitalproducts_producttypes}}'])
                ->where(Db::parseParam('handle', $value))
                ->column();
        } else {
            $this->typeId = null;
        }

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow products whose Post Date is before the given value.
     *
     * @param DateTime|string $value The property value
     *
     * @return static self reference
     */
    public function before($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->dateCreated = ArrayHelper::toArray($this->dateCreated);
        $this->dateCreated[] = '<'.$value;

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow products whose Post Date is after the given value.
     *
     * @param DateTime|string $value The property value
     *
     * @return static self reference
     */
    public function after($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->dateCreated = ArrayHelper::toArray($this->dateCreated);
        $this->dateCreated[] = '>='.$value;

        return $this;
    }

    /**
     * Sets the [[typeId]] property.
     *
     * @param int|int[] $value The property value
     *
     * @return static self reference
     */
    public function typeId($value)
    {
        $this->typeId = $value;

        return $this;
    }

    /**
     * Sets the [[ownerId]] property.
     *
     * @param int|int[] $value The property value
     *
     * @return static self reference
     */
    public function ownerId($value)
    {
        $this->ownerId = $value;

        return $this;
    }

    /**
     * Sets the [[productId]] property.
     *
     * @param int|int[] $value The property value
     *
     * @return static self reference
     */
    public function productId($value)
    {
        $this->productId = $value;

        return $this;
    }

    /**
     * Sets the [[orderId]] property.
     *
     * @param int|int[] $value The property value
     *
     * @return static self reference
     */
    public function orderId($value)
    {
        $this->orderId = $value;

        return $this;
    }

    /**
     * Sets the [[licenseKey]] property.
     *
     * @param string|string[] $value The property value
     *
     * @return static self reference
     */
    public function licenseKey($value)
    {
        $this->licenseKey = $value;

        return $this;
    }

    /**
     * Sets the [[licenseDate]] property.
     *
     * @param DateTime|string $value The property value
     *
     * @return static self reference
     */
    public function licenseDate($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->licenseDate = $value;

        return $this;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        // See if 'type' were set to invalid handles
        if ($this->typeId === []) {
            return false;
        }

        $this->joinElementTable('digitalproducts_licenses');
        $this->subQuery->innerJoin('{{%digitalproducts_products}} digitalproducts_products', '[[digitalproducts_licenses.productId]] = [[digitalproducts_products.id]]');
        $this->subQuery->leftJoin('{{%users}} users', '[[digitalproducts_licenses.userId]] = [[users.id]]');

        $this->query->select([
            'digitalproducts_licenses.id',
            'digitalproducts_licenses.productId',
            'digitalproducts_licenses.licenseKey',
            'digitalproducts_licenses.ownerName',
            'digitalproducts_licenses.ownerEmail',
            'digitalproducts_licenses.userId',
            'digitalproducts_licenses.orderId',
        ]);

        if ($this->email) {
            $this->subQuery->andWhere(['or', ['digitalproducts_licenses.ownerEmail' => $this->email], ['users.email' => $this->email]]);
        }

        if ($this->ownerEmail) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_licenses.ownerEmail', $this->ownerEmail));
        }

        if ($this->userEmail) {
            $this->subQuery->andWhere(Db::parseParam('users.email', $this->userEmail));
        }

        if ($this->ownerId) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_licenses.userId', $this->ownerId));
        }

        if ($this->productId) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_products.id', $this->productId));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_products.typeId', $this->typeId));
        }

        if ($this->licenseDate) {
            $this->subQuery->andWhere(Db::parseDateParam('digitalproducts_products.dateCreated', $this->licenseDate));
        }

        if ($this->orderId) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_licenses.orderId', $this->orderId));
        }

        if ($this->licenseKey) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_licenses.licenseKey', $this->licenseKey));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
        switch ($status) {
            case License::STATUS_ENABLED:
                return [
                    'elements.enabled' => '1'
                ];
            case License::STATUS_DISABLED:
                return [
                    'elements.disabled' => '1'
                ];
            default:
                return parent::statusCondition($status);
        }
    }
}
