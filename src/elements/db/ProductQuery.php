<?php

namespace craft\digitalproducts\elements\db;

use Craft;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\models\ProductType;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use DateTime;
use yii\db\Connection;

/**
 * ProductQuery represents a SELECT SQL statement for products in a way that is independent of DBMS.
 *
 * @method Product[]|array all($db = null)
 * @method Product|array|false one($db = null)
 * @method Product|array|false nth(int $n, Connection $db = null)
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class ProductQuery extends ElementQuery
{
    // Properties
    // =========================================================================

    /**
     * @var bool Whether to only return products that the user has permission to edit.
     */
    public $editable = false;

    /**
     * @var mixed The Post Date that the resulting products must have.
     */
    public $expiryDate;

    /**
     * @var mixed The Post Date that the resulting products must have.
     */
    public $postDate;

    /**
     * @var mixed The sku the resulting products must have.
     */
    public $sku;

    /**
     * @var int|int[]|null The product type ID(s) that the resulting products must have.
     */
    public $typeId;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function __construct(string $elementType, array $config = [])
    {
        // Default status
        if (!isset($config['status'])) {
            $config['status'] = Product::STATUS_LIVE;
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
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
     * Sets the [[postDate]] property to only allow products whose Post Date is before the given value.
     *
     * @param DateTime|string $value The property value
     * @return static self reference
     */
    public function before($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '<' . $value;

        return $this;
    }

    /**
     * Sets the [[postDate]] property to only allow products whose Post Date is after the given value.
     *
     * @param DateTime|string $value The property value
     * @return static self reference
     */
    public function after($value)
    {
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::W3C);
        }

        $this->postDate = ArrayHelper::toArray($this->postDate);
        $this->postDate[] = '>=' . $value;

        return $this;
    }

    /**
     * Sets the [[editable]] property.
     *
     * @param bool $value The property value (defaults to true)
     * @return static self reference
     */
    public function editable(bool $value = true)
    {
        $this->editable = $value;
        return $this;
    }

    /**
     * Sets the [[expiryDate]] property.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function expiryDate($value)
    {
        $this->expiryDate = $value;
        return $this;
    }

    /**
     * Sets the [[postDate]] property.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function postDate($value)
    {
        $this->postDate = $value;
        return $this;
    }

    /**
     * Sets the [[sku]] property.
     *
     * @param mixed $value The property value
     * @return static self reference
     */
    public function sku($value)
    {
        $this->sku = $value;
        return $this;
    }

    /**
     * Sets the [[typeId]] property based on a given product types(s)’s handle(s).
     *
     * @param string|string[]|ProductType|null $value The property value
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
     * Sets the [[typeId]] property.
     *
     * @param int|int[]|null $value The property value
     * @return static self reference
     */
    public function typeId($value)
    {
        $this->typeId = $value;
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

        $this->joinElementTable('digitalproducts_products');

        $this->query->select([
            'digitalproducts_products.expiryDate',
            'digitalproducts_products.id',
            'digitalproducts_products.postDate',
            'digitalproducts_products.price',
            'digitalproducts_products.promotable',
            'digitalproducts_products.sku',
            'digitalproducts_products.taxCategoryId',
            'digitalproducts_products.typeId'
        ]);

        if ($this->expiryDate) {
            $this->subQuery->andWhere(Db::parseDateParam('digitalproducts_products.expiryDate', $this->expiryDate));
        }

        if ($this->postDate) {
            $this->subQuery->andWhere(Db::parseDateParam('digitalproducts_products.postDate', $this->postDate));
        }

        if ($this->sku) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_products.sku', $this->sku));
        }

        if ($this->typeId) {
            $this->subQuery->andWhere(Db::parseParam('digitalproducts_products.typeId', $this->typeId));
        }

        $this->_applyEditableParam();

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function statusCondition(string $status)
    {
        $currentTimeDb = Db::prepareDateForDb(new \DateTime());

        switch ($status) {
            case Product::STATUS_LIVE:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_sites.enabled' => '1'
                    ],
                    ['<=', 'digitalproducts_products.postDate', $currentTimeDb],
                    [
                        'or',
                        ['digitalproducts_products.expiryDate' => null],
                        ['>', 'digitalproducts_products.expiryDate', $currentTimeDb]
                    ]
                ];
            case Product::STATUS_PENDING:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_sites.enabled' => '1',
                    ],
                    ['>', 'digitalproducts_products.postDate', $currentTimeDb]
                ];
            case Product::STATUS_EXPIRED:
                return [
                    'and',
                    [
                        'elements.enabled' => '1',
                        'elements_sites.enabled' => '1'
                    ],
                    ['not', ['digitalproducts_products.expiryDate' => null]],
                    ['<=', 'digitalproducts_products.expiryDate', $currentTimeDb]
                ];
            default:
                return parent::statusCondition($status);
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * Applies the 'editable' param to the query being prepared.
     *
     * @return void
     * @throws QueryAbortedException
     */
    private function _applyEditableParam()
    {
        if (!$this->editable) {
            return;
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user) {
            throw new QueryAbortedException();
        }

        // Limit the query to only the sections the user has permission to edit
        $this->subQuery->andWhere([
            'digitalproducts_products.typeId' => DigitalProducts::getInstance()->getProductTypes()->getEditableProductTypeIds()
        ]);
    }
}
