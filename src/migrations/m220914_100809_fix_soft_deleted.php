<?php

namespace craft\digitalproducts\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\digitalproducts\db\Table as DigitalProductsTable;
use craft\digitalproducts\elements\Product;

/**
 * m220914_100809_fix_soft_deleted migration.
 */
class m220914_100809_fix_soft_deleted extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // For each product that has been soft-deleted with licenses, restore it but disable it.
        $productIds = (new Query())
            ->select(['productId'])
            ->from(DigitalProductsTable::LICENSES)
            ->groupBy('productId')
            ->column();

        $products = Product::find()->trashed(true)->id($productIds)->limit(null)->ids();
        foreach ($products as $productId) {
            $this->update(Table::ELEMENTS, ['enabled' => false, 'dateDeleted' => null], ['id' => $productId]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220914_100809_fix_soft_deleted cannot be reverted.\n";
        return false;
    }
}
