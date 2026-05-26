<?php

namespace craft\digitalproducts\migrations;

use craft\commerce\db\Table as CommerceTable;
use craft\db\Migration;
use craft\db\Query;
use craft\digitalproducts\db\Table;

/**
 * m260526_000000_fix_purchasable_store_base_price migration.
 */
class m260526_000000_fix_purchasable_store_base_price extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Copy price from digitalproducts_products to commerce_purchasables_stores.basePrice
        // for any digital products where basePrice is null (i.e. saved before the fix).
        $products = (new Query())
            ->select(['id', 'price'])
            ->from(Table::PRODUCTS)
            ->all();

        foreach ($products as $product) {
            $this->update(
                CommerceTable::PURCHASABLES_STORES,
                ['basePrice' => $product['price']],
                ['purchasableId' => $product['id'], 'basePrice' => null],
                [],
                false,
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m260526_000000_fix_purchasable_store_base_price cannot be reverted.\n";
        return false;
    }
}
