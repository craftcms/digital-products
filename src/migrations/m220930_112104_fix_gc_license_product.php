<?php

namespace craft\digitalproducts\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\digitalproducts\db\Table;

/**
 * m220930_112104_fix_gc_license_product migration.
 */
class m220930_112104_fix_gc_license_product extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropForeignKeyIfExists(Table::LICENSES, 'productId');
        $this->addForeignKey(null, Table::LICENSES, 'productId', Table::PRODUCTS, 'id', 'CASCADE', null);
//
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m220930_112104_fix_gc_license_product cannot be reverted.\n";
        return false;
    }
}
