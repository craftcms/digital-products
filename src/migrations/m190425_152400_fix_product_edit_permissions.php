<?php

namespace craft\digitalproducts\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Db;

/**
 * m190425_152400_fix_product_edit_permissions migration.
 */
class m190425_152400_fix_product_edit_permissions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $productTypes = $projectConfig->get('digital-products.productTypes');

        if (is_array($productTypes)) {
            $groups = $projectConfig->get('users.groups');

            // Create all the new permissions in the table and store ids by name
            $productTypeUids = array_keys($productTypes);

            $permissionIdsByName = [];

            foreach ($productTypeUids as $productTypeUid) {
                $permissionName = 'digitalproducts-manageproducttype:' . $productTypeUid;

                if (empty($permissionIdsByName[$permissionName])) {
                    $this->insert(Table::USERPERMISSIONS, ['name' => $permissionName]);

                    $permissionIdsByName[$permissionName] = (new Query())
                        ->select(['id'])
                        ->from([Table::USERPERMISSIONS])
                        ->where(['name' => $permissionName])
                        ->scalar();
                }
            }

            // Migrate all the group permissions
            if (is_array($groups)) {
                $groupUids = array_keys($groups);

                // Collect eligible permission combos for groups
                $newPermissions = [];
                foreach ($groupUids as $groupUid) {
                    $permissions = $projectConfig->get('users.groups.' . $groupUid . '.permissions');
                    if(is_array($permissions)) {
                        foreach ($productTypeUids as $productTypeUid) {
                            if (in_array('digitalproducts-manageproducts', $permissions, true)) {
                                $permissionName = 'digitalproducts-manageproducttype:' . $productTypeUid;
                                $newPermissions[$groupUid][] = $permissionName;
                            }
                        }
                    }
                }

                $projectConfig->muteEvents = true;
                // Add new permissions
                foreach ($newPermissions as $groupUid => $productTypePermissions) {
                    $schemaVersion = $projectConfig->get('plugins.digital-products.schemaVersion', true);

                    // If schema permits, update the Project config
                    if (version_compare($schemaVersion, '2.1.0', '<=')) {
                        $permissions = array_merge($projectConfig->get('users.groups.' . $groupUid . '.permissions'), $productTypePermissions);
                        $projectConfig->set('users.groups.' . $groupUid . '.permissions', $permissions);
                    }

                    // Update the DB
                    foreach ($productTypePermissions as $permission) {
                        $this->insert(Table::USERPERMISSIONS_USERGROUPS, [
                            'permissionId' => $permissionIdsByName[$permission],
                            'groupId' => Db::idByUid(Table::USERGROUPS, $groupUid)
                        ]);
                    }
                }
                $projectConfig->muteEvents = false;

                // Migrate the users
                foreach ($productTypeUids as $productTypeUid) {
                    // Get all the eligible users
                    $userIds = (new Query())
                        ->select(['users.id'])
                        ->from([Table::USERS . ' AS users'])
                        ->innerJoin(Table::USERPERMISSIONS_USERS . ' AS editProductPermissions', '[[editProductPermissions.userId]] = [[users.id]]')
                        ->innerJoin(Table::USERPERMISSIONS . ' AS editProduct', [
                            'and',
                            '[[editProduct.id]] = [[editProductPermissions.permissionId]]',
                            ['editProduct.name' => 'digitalproducts-manageproducts']
                        ])
                        ->column();

                    $permissionRows = [];

                    foreach ($userIds as $userId) {
                        $permissionRows[] = [$userId, $permissionIdsByName['digitalproducts-manageproducttype:' . $productTypeUid]];
                    }

                    if (!empty($permissionRows)) {
                        $this->batchInsert(Table::USERPERMISSIONS_USERS, ['userId', 'permissionId'], $permissionRows);
                    }
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190425_152400_fix_product_edit_permissions cannot be reverted.\n";
        return false;
    }
}
