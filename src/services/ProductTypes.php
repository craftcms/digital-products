<?php

namespace craft\commerce\digitalProducts\services;

use Craft;
use craft\commerce\digitalProducts\elements\Product;
use craft\commerce\digitalProducts\models\ProductType;
use craft\commerce\digitalProducts\models\ProductTypeSite;
use craft\commerce\digitalProducts\records\ProductType as ProductTypeRecord;
use craft\commerce\digitalProducts\records\ProductTypeSite as ProductTypeSiteRecord;
use craft\commerce\events\ProductTypeEvent;
use craft\db\Query;
use craft\events\SiteEvent;
use craft\helpers\App;
use yii\base\Component;
use yii\base\Exception;

/**
 * Product Type.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 */
class ProductTypes extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event ProductTypeEvent The event that is triggered before a category group is saved.
     */
    const EVENT_BEFORE_SAVE_PRODUCTTYPE = 'beforeSaveProductType';

    /**
     * @event ProductTypeEvent The event that is triggered after a product type is saved.
     */
    const EVENT_AFTER_SAVE_PRODUCTTYPE = 'afterSaveProductType';

    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllProductTypes = false;

    /**
     * @var ProductType[]
     */
    private $_productTypesById;

    /**
     * @var ProductType[]
     */
    private $_productTypesByHandle;

    /**
     * @var int[]
     */
    private $_allProductTypeIds;

    /**
     * @var int[]
     */
    private $_editableProductTypeIds;

    /**
     * @var ProductTypeSite[][]
     */
    private $_siteSettingsByProductId = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns all editable product types.
     *
     * @return ProductType[] An array of all the editable product types.
     */
    public function getEditableProductTypes(): array
    {
        $editableProductTypeIds = $this->getEditableProductTypeIds();
        $editableProductTypes = [];

        foreach ($this->getAllProductTypes() as $productTypes) {
            if (in_array($productTypes->id, $editableProductTypeIds, false)) {
                $editableProductTypes[] = $productTypes;
            }
        }

        return $editableProductTypes;
    }

    /**
     * Returns all of the product type IDs that are editable by the current user.
     *
     * @return array An array of all the editable product types’ IDs.
     */
    public function getEditableProductTypeIds(): array
    {
        if (null === $this->_editableProductTypeIds) {
            $this->_editableProductTypeIds = [];
            $allProductTypeIds = $this->getAllProductTypeIds();

            foreach ($allProductTypeIds as $productTypeId) {
                if (Craft::$app->getUser()->checkPermission('digitalProducts-manageProductType:'.$productTypeId)) {
                    $this->_editableProductTypeIds[] = $productTypeId;
                }
            }
        }

        return $this->_editableProductTypeIds;
    }

    /**
     * Returns all of the product type IDs.
     *
     * @return array An array of all the product types’ IDs.
     */
    public function getAllProductTypeIds(): array
    {
        if (null === $this->_allProductTypeIds) {
            $this->_allProductTypeIds = [];
            $productTypes = $this->getAllProductTypes();

            foreach ($productTypes as $productType) {
                $this->_allProductTypeIds[] = $productType->id;
            }
        }

        return $this->_allProductTypeIds;
    }

    /**
     * Returns all product types.
     *
     * @return ProductType[] An array of all product types.
     */
    public function getAllProductTypes(): array
    {
        if (!$this->_fetchedAllProductTypes) {
            $results = $this->_createProductTypeQuery()->all();

            foreach ($results as $result) {
                $this->_memoizeProductType(new ProductType($result));
            }

            $this->_fetchedAllProductTypes = true;
        }

        return $this->_productTypesById ?: [];
    }

    /**
     * Get a product type by it's handle.
     *
     * @param string $handle The product type's handle.
     *
     * @return ProductType|null The product type or `null`.
     */
    public function getProductTypeByHandle($handle)
    {
        if (isset($this->_productTypesByHandle[$handle])) {
            return $this->_productTypesByHandle[$handle];
        }

        if ($this->_fetchedAllProductTypes) {
            return null;
        }

        $result = $this->_createProductTypeQuery()
            ->where(['handle' => $handle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeProductType(new ProductType($result));

        return $this->_productTypesByHandle[$handle];
    }

    /**
     * Get an array of product type site settings for a product type by it's id.
     *
     * @param int $productTypeId The product type id.
     *
     * @return array The product type settings.
     */
    public function getProductTypeSites($productTypeId): array
    {
        if (!isset($this->_siteSettingsByProductId[$productTypeId])) {
            $rows = (new Query())
                ->select([
                    'id',
                    'productTypeId',
                    'siteId',
                    'uriFormat',
                    'template'
                ])
                ->from('{{%digitalproducts_producttypes_sites}}')
                ->where(['productTypeId' => $productTypeId])
                ->all();

            $this->_siteSettingsByProductId[$productTypeId] = [];

            foreach ($rows as $row) {
                $this->_siteSettingsByProductId[$productTypeId][] = new ProductTypeSite($row);
            }
        }

        return $this->_siteSettingsByProductId[$productTypeId];
    }

    /**
     * Save a product type.
     *
     * @param ProductType $productType   The product type model.
     * @param bool        $runValidation If validation should be ran.
     *
     * @return bool Whether the product type was saved successfully.
     * @throws \Throwable if reasons
     */
    public function saveProductType(ProductType $productType, bool $runValidation = true): bool
    {
        if ($runValidation && !$productType->validate()) {
            Craft::info('Product type not saved due to validation error.', __METHOD__);

            return false;
        }

        $isNewProductType = !$productType->id;

        // Fire a 'beforeSaveProductType' event
        $this->trigger(self::EVENT_BEFORE_SAVE_PRODUCTTYPE, new ProductTypeEvent([
            'productType' => $productType,
            'isNew' => $isNewProductType,
        ]));

        if (!$isNewProductType) {
            $productTypeRecord = ProductTypeRecord::findOne($productType->id);

            if (!$productTypeRecord) {
                throw new Exception("No product type exists with the ID '{$productType->id}'");
            }

            $oldProductTypeRow = $this->_createProductTypeQuery()
                ->where(['id' => $productType->id])
                ->one();
            $oldProductType = new ProductType($oldProductTypeRow);
        } else {
            $productTypeRecord = new ProductTypeRecord();
        }


        $productTypeRecord->name = $productType->name;
        $productTypeRecord->handle = $productType->handle;
        $productTypeRecord->skuFormat = $productType->skuFormat;

        // Get the site settings
        $allSiteSettings = $productType->getSiteSettings();

        // Make sure they're all there
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            if (!isset($allSiteSettings[$siteId])) {
                throw new Exception('Tried to save a product type that is missing site settings');
            }
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Product Field Layout
            $fieldLayout = $productType->getProductFieldLayout();
            Craft::$app->getFields()->saveLayout($fieldLayout);
            $productType->fieldLayoutId = $fieldLayout->id;
            $productTypeRecord->fieldLayoutId = $fieldLayout->id;

            // Save the product type
            $productTypeRecord->save(false);

            // Now that we have a product type ID, save it on the model
            if (!$productType->id) {
                $productType->id = $productTypeRecord->id;
            }

            // Might as well update our cache of the product type while we have it.
            $this->_productTypesById[$productType->id] = $productType;

            // Update the site settings
            // -----------------------------------------------------------------

            $sitesNowWithoutUrls = [];
            $sitesWithNewUriFormats = [];

            if (!$isNewProductType) {
                // Get the old product type site settings
                $allOldSiteSettingsRecords = ProductTypeSiteRecord::find()
                    ->where(['productTypeId' => $productType->id])
                    ->indexBy('siteId')
                    ->all();
            }

            /** @var ProductTypeSiteRecord $siteSettings */
            foreach ($allSiteSettings as $siteId => $siteSettings) {
                // Was this already selected?
                if (!$isNewProductType && isset($allOldSiteSettingsRecords[$siteId])) {
                    $siteSettingsRecord = $allOldSiteSettingsRecords[$siteId];
                } else {
                    $siteSettingsRecord = new ProductTypeSiteRecord();
                    $siteSettingsRecord->productTypeId = $productType->id;
                    $siteSettingsRecord->siteId = $siteId;
                }

                $siteSettingsRecord->hasUrls = $siteSettings->hasUrls;
                $siteSettingsRecord->uriFormat = $siteSettings->uriFormat;
                $siteSettingsRecord->template = $siteSettings->template;

                if (!$siteSettingsRecord->getIsNewRecord()) {
                    // Did it used to have URLs, but not anymore?
                    if ($siteSettingsRecord->isAttributeChanged('hasUrls', false) && !$siteSettings->hasUrls) {
                        $sitesNowWithoutUrls[] = $siteId;
                    }

                    // Does it have URLs, and has its URI format changed?
                    if ($siteSettings->hasUrls && $siteSettingsRecord->isAttributeChanged('uriFormat', false)) {
                        $sitesWithNewUriFormats[] = $siteId;
                    }
                }

                $siteSettingsRecord->save(false);

                // Set the ID on the model
                $siteSettings->id = $siteSettingsRecord->id;
            }

            if (!$isNewProductType) {
                // Drop any site settings that are no longer being used, as well as the associated product/element
                // site rows
                $siteIds = array_keys($allSiteSettings);

                /** @noinspection PhpUndefinedVariableInspection */
                foreach ($allOldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
                    if (!in_array($siteId, $siteIds, false)) {
                        $siteSettingsRecord->delete();
                    }
                }
            }

            // Finally, deal with the existing products...
            // -----------------------------------------------------------------

            if (!$isNewProductType) {
                // Get all of the product IDs in this group
                $productTypeIds = Product::find()
                    ->typeId($productType->id)
                    ->status(null)
                    ->limit(null)
                    ->ids();

                // Are there any sites left?
                if (!empty($allSiteSettings)) {
                    // Drop the old product URIs for any site settings that don't have URLs
                    if (!empty($sitesNowWithoutUrls)) {
                        $db->createCommand()
                            ->update(
                                '{{%elements_sites}}',
                                ['uri' => null],
                                [
                                    'elementId' => $productTypeIds,
                                    'siteId' => $sitesNowWithoutUrls,
                                ])
                            ->execute();
                    } else if (!empty($sitesWithNewUriFormats)) {
                        foreach ($productTypeIds as $productTypeId) {
                            App::maxPowerCaptain();

                            // Loop through each of the changed sites and update all of the products’ slugs and
                            // URIs
                            foreach ($sitesWithNewUriFormats as $siteId) {
                                $product = Product::find()
                                    ->id($productTypeId)
                                    ->siteId($siteId)
                                    ->status(null)
                                    ->one();

                                if ($product) {
                                    Craft::$app->getElements()->updateElementSlugAndUri($product, false, false);
                                }
                            }
                        }
                    }
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        // Fire an 'afterSaveGroup' event
        $this->trigger(self::EVENT_AFTER_SAVE_PRODUCTTYPE, new ProductTypeEvent([
            'productType' => $productType,
            'isNew' => $isNewProductType,
        ]));

        return true;
    }

    /**
     * Delete a product type by it's id.
     *
     * @param int $id The product type's id.
     *
     * @return bool Whether the product type was deleted successfully.
     * @throws \Throwable if reasons
     */
    public function deleteProductTypeById(int $id): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $productType = $this->getProductTypeById($id);

            $criteria = Product::find();
            $criteria->typeId = $productType->id;
            $criteria->status = null;
            $criteria->limit = null;
            $products = $criteria->all();

            foreach ($products as $product) {
                Craft::$app->getElements()->deleteElement($product);
            }

            $fieldLayoutId = $productType->getProductFieldLayout()->id;
            Craft::$app->getFields()->deleteLayoutById($fieldLayoutId);
            if ($productType->hasVariants) {
                Craft::$app->getFields()->deleteLayoutById($productType->getVariantFieldLayout()->id);
            }

            $productTypeRecord = ProductTypeRecord::findOne($productType->id);
            $affectedRows = $productTypeRecord->delete();

            if ($affectedRows) {
                $transaction->commit();
            }

            return (bool)$affectedRows;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    /**
     * Get a product's type by id.
     *
     * @param int $productTypeId The product type's id.
     *
     * @return ProductType|null Either the product type or `null`.
     */
    public function getProductTypeById(int $productTypeId)
    {
        if (isset($this->_productTypesById[$productTypeId])) {
            return $this->_productTypesById[$productTypeId];
        }

        if ($this->_fetchedAllProductTypes) {
            return null;
        }

        $result = $this->_createProductTypeQuery()
            ->where(['id' => $productTypeId])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeProductType(new ProductType($result));

        return $this->_productTypesById[$productTypeId];
    }

    /**
     * Returns whether a product type’s products have URLs, and if the template path is valid.
     *
     * @param ProductType $productType The product for which to validate the template.
     *
     * @return bool Whether the template is valid.
     */
    public function isProductTypeTemplateValid(ProductType $productType): bool
    {
        if ($productType->hasUrls) {
            // Set Craft to the site template mode
            $templatesService = Craft::$app->getView();
            $oldTemplateMode = $templatesService->getTemplateMode();
            $templatesService->setTemplateMode($templatesService::TEMPLATE_MODE_SITE);

            // Does the template exist?
            $templateExists = $templatesService->doesTemplateExist($productType->template);

            // Restore the original template mode
            $templatesService->setTemplateMode($oldTemplateMode);

            if ($templateExists) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add new product type setting rows when a Site is added to Craft.
     *
     * @param SiteEvent $event The event that triggered this.
     *
     * @return void
     */
    public function addSiteHandler(SiteEvent $event)
    {
        if ($event->isNew) {
            $allSiteSettings = (new Query())
                ->select(['productTypeId', 'uriFormat', 'template', 'hasUrls'])
                ->from(['{{%digitalproducts_producttypes_sites}}'])
                ->where(['siteId' => Craft::$app->getSites()->getPrimarySite()->id])
                ->all();

            if (!empty($allSiteSettings)) {
                $newSiteSettings = [];

                foreach ($allSiteSettings as $siteSettings) {
                    $newSiteSettings[] = [
                        $siteSettings['productTypeId'],
                        $event->site->id,
                        $siteSettings['uriFormat'],
                        $siteSettings['template'],
                        $siteSettings['hasUrls']
                    ];
                }

                Craft::$app->getDb()->createCommand()
                    ->batchInsert(
                        '{{%digitalproducts_producttypes_sites}}',
                        ['productTypeId', 'siteId', 'uriFormat', 'template', 'hasUrls'],
                        $newSiteSettings)
                    ->execute();
            }
        }
    }

    // Private methods
    // =========================================================================

    /**
     * Memoize a product type
     *
     * @param ProductType $productType The product type to memoize.
     */
    private function _memoizeProductType(ProductType $productType)
    {
        $this->_productTypesById[$productType->id] = $productType;
        $this->_productTypesByHandle[$productType->handle] = $productType;
    }

    /**
     * Returns a Query object prepped for retrieving purchasables.
     *
     * @return Query The query object.
     */
    private function _createProductTypeQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'fieldLayoutId',
                'name',
                'handle',
                'skuFormat'
            ])
            ->from(['{{%digitalproducts_producttypes}}']);
    }
}
