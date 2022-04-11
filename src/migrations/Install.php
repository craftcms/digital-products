<?php

namespace craft\digitalproducts\migrations;

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * Installation Migration
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();

        $this->delete('{{%elementindexsettings}}', ['type' => [Order::class, Product::class]]);

        return true;
    }

    /**
     * Creates the tables for Digital Products
     */
    protected function createTables()
    {
        $this->createTable('{{%digitalproducts_licenses}}', [
            'id' => $this->primaryKey(),
            'productId' => $this->integer()->notNull(),
            'orderId' => $this->integer()->null(),
            'licenseKey' => $this->string()->notNull(),
            'ownerName' => $this->string(),
            'ownerEmail' => $this->string()->notNull(),
            'userId' => $this->integer()->null(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%digitalproducts_products}}', [
            'id' => $this->primaryKey(),
            'typeId' => $this->integer()->notNull(),
            'taxCategoryId' => $this->integer()->notNull(),
            'postDate' => $this->dateTime(),
            'expiryDate' => $this->dateTime(),
            'promotable' => $this->boolean()->notNull(),
            'sku' => $this->string()->notNull(),
            'price' => $this->decimal(14, 4)->notNull()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%digitalproducts_producttypes}}', [
            'id' => $this->primaryKey(),
            'fieldLayoutId' => $this->integer(),
            'name' => $this->string()->notNull(),
            'handle' => $this->string()->notNull(),
            'skuFormat' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createTable('{{%digitalproducts_producttypes_sites}}', [
            'id' => $this->primaryKey(),
            'productTypeId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'uriFormat' => $this->text(),
            'template' => $this->string(500),
            'hasUrls' => $this->boolean(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    /**
     * Drop the tables
     */
    protected function dropTables()
    {
        $this->dropTable('{{%digitalproducts_licenses}}');
        $this->dropTable('{{%digitalproducts_products}}');
        $this->dropTable('{{%digitalproducts_producttypes}}');
        $this->dropTable('{{%digitalproducts_producttypes_sites}}');
    }

    /**
     * Creates the indexes.
     */
    protected function createIndexes()
    {
        $this->createIndex(null, '{{%digitalproducts_licenses}}', 'licenseKey', true);
        $this->createIndex(null, '{{%digitalproducts_licenses}}', 'orderId', false);
        $this->createIndex(null, '{{%digitalproducts_licenses}}', 'productId', false);

        $this->createIndex(null, '{{%digitalproducts_products}}', 'sku', true);
        $this->createIndex(null, '{{%digitalproducts_products}}', 'typeId', false);
        $this->createIndex(null, '{{%digitalproducts_products}}', 'taxCategoryId', false);

        $this->createIndex(null, '{{%digitalproducts_producttypes}}', 'handle', true);
        $this->createIndex(null, '{{%digitalproducts_producttypes}}', 'fieldLayoutId', false);

        $this->createIndex(null, '{{%digitalproducts_producttypes_sites}}', ['productTypeId', 'siteId'], true);
        $this->createIndex(null, '{{%digitalproducts_producttypes_sites}}', 'siteId', false);
    }

    /**
     * Adds the foreign keys.
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(null, '{{%digitalproducts_licenses}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%digitalproducts_licenses}}', 'orderId', '{{%commerce_orders}}', 'id', 'SET NULL', null);
        $this->addForeignKey(null, '{{%digitalproducts_licenses}}', 'productId', '{{%digitalproducts_products}}', 'id', 'RESTRICT', null);
        $this->addForeignKey(null, '{{%digitalproducts_licenses}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);

        $this->addForeignKey(null, '{{%digitalproducts_products}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey(null, '{{%digitalproducts_products}}', 'taxCategoryId', '{{%commerce_taxcategories}}', 'id', 'RESTRICT', null);
        $this->addForeignKey(null, '{{%digitalproducts_products}}', 'typeId', '{{%digitalproducts_producttypes}}', 'id', 'CASCADE', null);

        $this->addForeignKey(null, '{{%digitalproducts_producttypes}}', 'fieldLayoutId', '{{%fieldlayouts}}', 'id', 'SET NULL', null);

        $this->addForeignKey(null, '{{%digitalproducts_producttypes_sites}}', 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, '{{%digitalproducts_producttypes_sites}}', 'productTypeId', '{{%digitalproducts_producttypes}}', 'id', 'CASCADE', null);
    }

    /**
     * Adds the foreign keys.
     */
    protected function dropForeignKeys()
    {
        MigrationHelper::dropAllForeignKeysOnTable('{{%digitalproducts_licenses}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%digitalproducts_products}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%digitalproducts_producttypes}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%digitalproducts_producttypes_sites}}', $this);
    }
}
