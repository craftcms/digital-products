<?php

namespace craft\commerce\digitalProducts\migrations;

use craft\db\Migration;

/**
 * m171117_120000_craft3_update
 */
class m171117_120000_craft3_update extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m171117_120000_craft3_update cannot be reverted.\n";

        return false;
    }
}
