<?php

namespace craft\digitalproducts\migrations;

use craft\db\Migration;
use craft\digitalproducts\db\Table;

class m260526_000001_nullable_license_product extends Migration
{
    public function safeUp(): bool
    {
        $this->alterColumn(Table::LICENSES, 'productId', $this->integer()->null());
        $this->alterColumn(Table::LICENSES, 'ownerEmail', $this->string()->null());

        return true;
    }

    public function safeDown(): bool
    {
        $this->alterColumn(Table::LICENSES, 'ownerEmail', $this->string()->notNull());
        $this->alterColumn(Table::LICENSES, 'productId', $this->integer()->notNull());

        return true;
    }
}
