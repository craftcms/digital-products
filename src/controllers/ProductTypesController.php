<?php
namespace craft\commerce\digitalProducts\controllers;

use Craft;
use craft\commerce\digitalProducts\elements\Product;
use craft\commerce\digitalProducts\models\ProductType;
use craft\commerce\digitalProducts\models\ProductTypeSite;
use craft\commerce\digitalProducts\Plugin as DigitalProducts;
use craft\web\Controller as BaseController;
use yii\base\Exception;
use yii\web\Response;


/**
 * Class DigitalProducts_ProductsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */

class ProductTypesController extends BaseController
{

    // Public Methods
    // =========================================================================


    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->requirePermission('digitalProducts-manageProductTypes');
        parent::init();
    }

    /**
     * Edit a product type.
     *
     * @param int|null         $productTypeId the product type id
     * @param ProductType|null $productType   the product type
     *
     * @return Response
     * @@throws Exception if product type is not found
     */
    public function actionEdit(int $productTypeId = null, ProductType $productType = null): Response
    {
        $variables = [
            'productTypeId' => $productTypeId,
            'productType' => $productType,
        ];

        if (empty($variables['productType'])) {
            $productType = $productTypeId ? DigitalProducts::getInstance()->getProductTypes()->getProductTypeById($productTypeId) : new ProductType();

            if (!$productType) {
                throw new Exception('Product type not found.');
            }

            $variables['productType'] = $productType;
        }
        
        $variables['title'] = $variables['productType']->id ? $variables['productType']->name : Craft::t('commerce-digitalproducts', 'Create a new digital product type');

        return $this->renderTemplate('commerce-digitalproducts/producttypes/_edit', $variables);
    }

    /**
     * Save a Product Type
     *
     * @return Response
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $productType = new ProductType();

        $request = Craft::$app->getRequest();
        
        $productType->id = $request->getBodyParam('productTypeId');
        $productType->name = $request->getBodyParam('name');
        $productType->handle = $request->getBodyParam('handle');
        $productType->hasUrls = $request->getBodyParam('hasUrls');
        $productType->skuFormat = $request->getBodyParam('skuFormat');

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $request->getBodyParam('sites.'.$site->handle);

            $siteSettings = new ProductTypeSite();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = !empty($postedSettings['uriFormat']);

            if ($siteSettings->hasUrls) {
                $siteSettings->uriFormat = $postedSettings['uriFormat'];
                $siteSettings->template = $postedSettings['template'];
            } else {
                $siteSettings->uriFormat = null;
                $siteSettings->template = null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $productType->setSiteSettings($allSiteSettings);

        // Set the product type field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Product::class;
        $productType->getBehavior('productFieldLayout')->setFieldLayout($fieldLayout);

        // Save it
        if (DigitalProducts::getInstance()->getProductTypes()->saveProductType($productType)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce-digitalproducts', 'Product type saved.'));

            return $this->redirectToPostedUrl($productType);
        }

        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save product type.'));

        // Send the productType back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'productType' => $productType
        ]);

        return null;
    }

    /**
     * Delete a Product Type.
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $productTypeId = Craft::$app->getRequest()->getRequiredParam('id');
        DigitalProducts::getInstance()->getProductTypes()->deleteProductTypeById($productTypeId);

        return $this->asJson(['success' => true]);
    }

}
