<?php

namespace craft\digitalproducts\migrations;

use craft\db\Query;
use craft\digitalproducts\db\Table;
use craft\digitalproducts\Plugin;
use craft\migrations\BaseContentRefactorMigration;

/**
 * m240620_150738_content_refactor_elements migration.
 */
class m240620_150738_content_refactor_elements extends BaseContentRefactorMigration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Migrate digital products by product type
        foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
            $this->updateElements(
                (new Query())->from(Table::PRODUCTS)->where(['typeId' => $productType->id]),
                $productType->getProductFieldLayout()
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240620_150738_content_refactor_elements cannot be reverted.\n";
        return false;
    }
}
