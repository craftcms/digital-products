<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\digitalproducts\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\ArrayHelper;

/**
 * m181025_111000_product_types_project_config_support migration.
 */
class m181025_111000_product_types_project_config_support extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.digital-products.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.1', '>')) {
            return true;
        }

        $productTypeRows = (new Query())
            ->select(['*'])
            ->from(['{{%digitalproducts_producttypes}}'])
            ->all();

        $layoutIds = ArrayHelper::getColumn($productTypeRows, 'fieldLayoutId');
        $fieldLayouts = $this->_generateFieldLayoutArray($layoutIds);


        $typeSiteRows = (new Query())
            ->select([
                'type_sites.hasUrls',
                'type_sites.uriFormat',
                'type_sites.template',
                'sites.uid AS siteUid',
                'types.uid AS typeUid',
            ])
            ->from(['{{%digitalproducts_producttypes_sites}} type_sites'])
            ->innerJoin('{{%sites}} sites', '[[sites.id]] = [[type_sites.siteId]]')
            ->innerJoin('{{%digitalproducts_producttypes}} types', '[[types.id]] = [[type_sites.productTypeId]]')
            ->all();

        $typeSiteData = [];

        foreach ($typeSiteRows as $typeSiteRow) {
            $typeSiteData[$typeSiteRow['typeUid']][$typeSiteRow['siteUid']] = [
                'hasUrls' => (bool)$typeSiteRow['hasUrls'],
                'uriFormat' => $typeSiteRow['uriFormat'],
                'template' => $typeSiteRow['template'],
            ];
        }

        $configData = [];

        foreach ($productTypeRows as $typeRow) {
            $layout = $fieldLayouts[$typeRow['fieldLayoutId']];
            $layoutUid = $layout['uid'];
            unset($layout['uid']);

            $configData[$typeRow['uid']] = [
                'name' => $typeRow['name'],
                'handle' => $typeRow['handle'],
                'skuFormat' => $typeRow['skuFormat'],
                'siteSettings' => $typeSiteData[$typeRow['uid']],
                'fieldLayouts' => [$layoutUid => $layout],
            ];
        }

        $projectConfig->set('digital-products.productTypes', $configData);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181025_111000_product_types_project_config_support cannot be reverted.\n";
        return false;
    }

    /**
     * Generate field layout config data for a list of array ids
     *
     * @param int[] $layoutIds
     *
     * @return array
     */
    private function _generateFieldLayoutArray(array $layoutIds): array
    {
        // Get all the UIDs
        $fieldLayoutUids = (new Query())
            ->select(['id', 'uid'])
            ->from(['{{%fieldlayouts}}'])
            ->where(['id' => $layoutIds])
            ->pairs();

        $fieldLayouts = [];
        foreach ($fieldLayoutUids as $id => $uid) {
            $fieldLayouts[$id] = [
                'uid' => $uid,
                'tabs' => [],
            ];
        }

        // Get the tabs and fields
        $fieldRows = (new Query())
            ->select([
                'fields.handle',
                'fields.uid AS fieldUid',
                'layoutFields.fieldId',
                'layoutFields.required',
                'layoutFields.sortOrder AS fieldOrder',
                'tabs.id AS tabId',
                'tabs.name as tabName',
                'tabs.sortOrder AS tabOrder',
                'tabs.uid AS tabUid',
                'layouts.id AS layoutId',
            ])
            ->from(['{{%fieldlayoutfields}} AS layoutFields'])
            ->innerJoin('{{%fieldlayouttabs}} AS tabs', '[[layoutFields.tabId]] = [[tabs.id]]')
            ->innerJoin('{{%fieldlayouts}} AS layouts', '[[layoutFields.layoutId]] = [[layouts.id]]')
            ->innerJoin('{{%fields}} AS fields', '[[layoutFields.fieldId]] = [[fields.id]]')
            ->where(['layouts.id' => $layoutIds])
            ->orderBy(['tabs.sortOrder' => SORT_ASC, 'layoutFields.sortOrder' => SORT_ASC])
            ->all();

        foreach ($fieldRows as $fieldRow) {
            $layout = &$fieldLayouts[$fieldRow['layoutId']];

            if (empty($layout['tabs'][$fieldRow['tabUid']])) {
                $layout['tabs'][$fieldRow['tabUid']] =
                    [
                        'name' => $fieldRow['tabName'],
                        'sortOrder' => (int)$fieldRow['tabOrder'],
                    ];
            }

            $tab = &$layout['tabs'][$fieldRow['tabUid']];

            $field['required'] = (bool)$fieldRow['required'];
            $field['sortOrder'] = (int)$fieldRow['fieldOrder'];

            $tab['fields'][$fieldRow['fieldUid']] = $field;
        }

        // Get rid of UIDs
        foreach ($fieldLayouts as &$fieldLayout) {
            $fieldLayout['tabs'] = array_values($fieldLayout['tabs']);
        }

        return $fieldLayouts;
    }
}
