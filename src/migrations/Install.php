<?php

namespace craft\digitalproducts\migrations;

use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;

/**
 * Installation Migration
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Install extends Migration
{
    // Public Methods
    // =========================================================================

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

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables for Craft Commerce
     *
     * @return void
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
     *
     * @return void
     */
    protected function dropTables()
    {
        $this->dropTable('{{%digitalproducts_licenses}}');
        $this->dropTable('{{%digitalproducts_products}}');
        $this->dropTable('{{%digitalproducts_producttypes}}');
        $this->dropTable('{{%digitalproducts_producttypes_sites}}');

        return null;
    }

    /**
     * Creates the indexes.
     *
     * @return void
     */
    protected function createIndexes()
    {
        $this->createIndex($this->db->getIndexName('{{%digitalproducts_licenses}}', 'licenseKey', true), '{{%digitalproducts_licenses}}', 'licenseKey', true);
        $this->createIndex($this->db->getIndexName('{{%digitalproducts_licenses}}', 'orderId', false), '{{%digitalproducts_licenses}}', 'orderId', false);
        $this->createIndex($this->db->getIndexName('{{%digitalproducts_licenses}}', 'productId', false), '{{%digitalproducts_licenses}}', 'productId', false);

        $this->createIndex($this->db->getIndexName('{{%digitalproducts_products}}', 'sku', true), '{{%digitalproducts_products}}', 'sku', true);
        $this->createIndex($this->db->getIndexName('{{%digitalproducts_products}}', 'typeId', false), '{{%digitalproducts_products}}', 'typeId', false);
        $this->createIndex($this->db->getIndexName('{{%digitalproducts_products}}', 'taxCategoryId', false), '{{%digitalproducts_products}}', 'taxCategoryId', false);

        $this->createIndex($this->db->getIndexName('{{%digitalproducts_producttypes}}', 'handle', true), '{{%digitalproducts_producttypes}}', 'handle', true);
        $this->createIndex($this->db->getIndexName('{{%digitalproducts_producttypes}}', 'fieldLayoutId', false), '{{%digitalproducts_producttypes}}', 'fieldLayoutId', false);

        $this->createIndex($this->db->getIndexName('{{%digitalproducts_producttypes_sites}}', ['productTypeId', 'siteId'], true), '{{%digitalproducts_producttypes_sites}}', ['productTypeId', 'siteId'], true);
        $this->createIndex($this->db->getIndexName('{{%digitalproducts_producttypes_sites}}', 'siteId', false), '{{%digitalproducts_producttypes_sites}}', 'siteId', false);
    }

    /**
     * Adds the foreign keys.
     *
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_licenses}}', 'id'), '{{%digitalproducts_licenses}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_licenses}}', 'orderId'), '{{%digitalproducts_licenses}}', 'orderId', '{{%commerce_orders}}', 'id', 'SET NULL', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_licenses}}', 'productId'), '{{%digitalproducts_licenses}}', 'productId', '{{%digitalproducts_products}}', 'id', 'RESTRICT', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_licenses}}', 'userId'), '{{%digitalproducts_licenses}}', 'userId', '{{%users}}', 'id', 'SET NULL', null);

        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_products}}', 'id'), '{{%digitalproducts_products}}', 'id', '{{%elements}}', 'id', 'CASCADE', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_products}}', 'taxCategoryId'), '{{%digitalproducts_products}}', 'taxCategoryId', '{{%commerce_taxcategories}}', 'id', 'RESTRICT', null);
        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_products}}', 'typeId'), '{{%digitalproducts_products}}', 'typeId', '{{%digitalproducts_producttypes}}', 'id', 'CASCADE', null);

        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_producttypes}}', 'fieldLayoutId'), '{{%digitalproducts_producttypes}}', 'fieldLayoutId', '{{%fieldlayouts}}', 'id', 'SET NULL', null);

        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_producttypes_sites}}', 'siteId'), '{{%digitalproducts_producttypes_sites}}', 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_producttypes_sites}}', 'productTypeId'), '{{%digitalproducts_producttypes_sites}}', 'productTypeId', '{{%digitalproducts_producttypes}}', 'id', 'CASCADE', null);
    }

    /**
     * Adds the foreign keys.
     *
     * @return void
     */
    protected function dropForeignKeys()
    {
        MigrationHelper::dropAllForeignKeysOnTable('{{%digitalproducts_licenses}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%digitalproducts_products}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%digitalproducts_producttypes}}', $this);
        MigrationHelper::dropAllForeignKeysOnTable('{{%digitalproducts_producttypes_sites}}', $this);
    }
}
