<?php

namespace craft\digitalproducts\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\fields\Products;
use craft\helpers\Json;
use craft\helpers\MigrationHelper;
use yii\base\InvalidArgumentException;

/**
 * m171129_154500_craft3_upgrade migration.
 */
class m171129_154500_craft3_upgrade extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Element changes
        // =====================================================================

        $this->update('{{%elements}}', [
            'type' => Product::class
        ], ['type' => 'DigitalProducts_Product']);

        $this->update('{{%elements}}', [
            'type' => License::class
        ], ['type' => 'DigitalProducts_License']);

        $this->update('{{%fields}}', [
            'type' => Products::class
        ], ['type' => 'DigitalProducts_Products']);

        // Update field settings
        $fields = (new Query())
            ->select(['id', 'type', 'translationMethod', 'settings'])
            ->from(['{{%fields}}'])
            ->where([
                'type' => [
                    Products::class
                ]
            ])
            ->all($this->db);

        foreach ($fields as $field) {
            try {
                $settings = Json::decode($field['settings']);
            } catch (InvalidArgumentException $e) {
                echo 'Field ' . $field['id'] . ' (' . $field['type'] . ') settings were invalid JSON: ' . $field['settings'] . "\n";

                return false;
            }

            $settings['localizeRelations'] = ($field['translationMethod'] === 'site');

            unset($settings['targetLocale']);

            $this->update(
                '{{%fields}}',
                [
                    'translationMethod' => 'none',
                    'settings' => Json::encode($settings),
                ],
                ['id' => $field['id']],
                [],
                false);
        }

        // Per-site setting changes
        // =====================================================================

        // Add the new columns
        $this->addColumn('{{%digitalproducts_producttypes_i18n}}', 'template', $this->string(500));
        $this->addColumn('{{%digitalproducts_producttypes_i18n}}', 'hasUrls', $this->boolean());

        // Migrate hasUrls to be site specific
        $productTypes = (new Query())
            ->select('id, hasUrls, template')
            ->from('{{%digitalproducts_producttypes}}')
            ->all();

        foreach ($productTypes as $productType) {
            $productTypeSites = (new Query())
                ->select('*')
                ->from('{{%digitalproducts_producttypes_i18n}}')
                ->all();

            foreach ($productTypeSites as $productTypeSite) {
                $productTypeSite['template'] = $productType['template'];
                $productTypeSite['hasUrls'] = $productType['hasUrls'];
                $this->update('{{%digitalproducts_producttypes_i18n}}', $productTypeSite, ['id' => $productTypeSite['id']]);
            }
        }

        // Drop the obsolete columns
        $this->dropColumn('{{%digitalproducts_producttypes}}', 'template');
        $this->dropColumn('{{%digitalproducts_producttypes}}', 'hasUrls');

        // Drop the never-used column.
        $this->dropColumn('{{%digitalproducts_producttypes}}', 'urlFormat');

        // And urlFormat is uriFormat from now on.
        MigrationHelper::renameColumn('{{%digitalproducts_producttypes_i18n}}', 'urlFormat', 'uriFormat', $this);

        // Locales are now Sites
        // =====================================================================

        // Before messing with columns, it's much safer to drop all the FKs and indexes
        MigrationHelper::dropAllForeignKeysOnTable('{{%digitalproducts_producttypes_i18n}}');
        MigrationHelper::dropAllIndexesOnTable('{{%digitalproducts_producttypes_i18n}}');

        // Drop the old locale FK column and rename the new siteId FK column
        $this->dropColumn('{{%digitalproducts_producttypes_i18n}}', 'locale');
        MigrationHelper::renameColumn('{{%digitalproducts_producttypes_i18n}}', 'locale__siteId', 'siteId', $this);

        // And then just recreate them.
        $this->createIndex($this->db->getIndexName('{{%digitalproducts_producttypes_i18n}}', 'productTypeId,siteId', true), '{{%digitalproducts_producttypes_i18n}}', 'productTypeId,siteId', true);
        $this->createIndex($this->db->getIndexName('{{%digitalproducts_producttypes_i18n}}', 'siteId', false), '{{%digitalproducts_producttypes_i18n}}', 'siteId', false);
        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_producttypes_i18n}}', 'siteId'), '{{%digitalproducts_producttypes_i18n}}', 'siteId', '{{%sites}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_producttypes_i18n}}', 'productTypeId'), '{{%digitalproducts_producttypes_i18n}}', 'productTypeId', '{{%digitalproducts_producttypes}}', 'id', 'CASCADE', null);

        // Rename the locale table and some columns
        MigrationHelper::renameTable('{{%digitalproducts_producttypes_i18n}}', '{{%digitalproducts_producttypes_sites}}', $this);

        // Add some new foreign keys
        // =====================================================================
        $this->addForeignKey($this->db->getForeignKeyName('{{%digitalproducts_licenses}}', 'userId'), '{{%digitalproducts_licenses}}', 'userId', '{{%users}}', 'id', 'SET NULL', 'NO ACTION');

        // Now populate the email field for all user licenses
        // =====================================================================
        $userIds = (new Query())
            ->select('userId')
            ->from('{{%digitalproducts_licenses}}')
            ->column();

        $userRows = (new Query())
            ->select('id, email')
            ->from('{{%users}}')
            ->where(['id' => array_unique($userIds)])
            ->all();

        foreach ($userRows as $row) {
            if ($row['id']) {
                $this->update('{{%digitalproducts_licenses}}', ['ownerEmail' => $row['email']], ['userId' => $row['id']]);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m171129_154500_craft3_upgrade cannot be reverted.\n";
        return false;
    }
}
