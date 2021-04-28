<?php

namespace craft\digitalproducts\controllers;

use Craft;
use craft\base\Field;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\models\ProductType;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\digitalproducts\web\assets\cp\Bundle;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\Controller as BaseController;
use DateTime;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class DigitalProducts_ProductsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class ProductsController extends BaseController
{

    /**
     * @inheritdoc
     */
    protected $allowAnonymous = ['actionViewSharedProduct'];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->requirePermission('digitalProducts-manageProducts');
        parent::init();
    }

    /**
     * Index of digital products
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $editableProductTypes = \craft\digitalproducts\Plugin::getInstance()->getProductTypes()->getEditableProductTypes();
        $this->getView()->registerAssetBundle(Bundle::class);
        return $this->renderTemplate('digital-products/products/index', compact('editableProductTypes'));
    }

    /**
     * Edit a product
     *
     * @param string $productTypeHandle the product type handle
     * @param int|null $productId the product id
     * @param string|null $siteHandle the site handle
     * @param Product|null $product the product
     * @return Response
     * @throws Exception in case of a missing product type or an incorrect site handle.
     */
    public function actionEdit(string $productTypeHandle, int $productId = null, string $siteHandle = null, Product $product = null): Response
    {
        $productType = null;

        $variables = [
            'productTypeHandle' => $productTypeHandle,
            'productId' => $productId,
            'product' => $product
        ];

        // Make sure a correct product type handle was passed so we can check permissions
        if ($productTypeHandle) {
            $productType = DigitalProducts::getInstance()->getProductTypes()->getProductTypeByHandle($productTypeHandle);
        }

        if (!$productType) {
            throw new Exception('The product type was not found.');
        }

        $this->requirePermission('digitalProducts-manageProductType:' . $productType->uid);
        $variables['productType'] = $productType;

        if ($siteHandle !== null) {
            $variables['site'] = Craft::$app->getSites()->getSiteByHandle($siteHandle);

            if (!$variables['site']) {
                throw new Exception('Invalid site handle: ' . $siteHandle);
            }
        }

        $this->_prepareVariableArray($variables);

        if (!empty($variables['product']->id)) {
            $variables['title'] = $variables['product']->title;
        } else {
            $variables['title'] = Craft::t('digital-products', 'Create a new product');
        }

        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'digital-products/products/' . $variables['productTypeHandle'] . '/{id}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'] .
            (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/' . $variables['site']->handle : '');

        $this->_maybeEnableLivePreview($variables);

        //$variables['promotions']['sales'] = Commerce::getInstance()->getSales()->getSalesForProduct($variables['product']);

        return $this->renderTemplate('digital-products/products/_edit', $variables);
    }

    /**
     * Delete a product.
     *
     * @return Response|null
     * @throws Exception if no product found
     */
    public function actionDeleteProduct()
    {
        $this->requirePostRequest();

        $productId = Craft::$app->getRequest()->getRequiredParam('productId');
        $product = Product::find()
            ->id($productId)
            ->anyStatus()
            ->one();

        if (!$product) {
            throw new Exception(Craft::t('digital-products', 'No product exists with the ID “{id}”.', ['id' => $productId]));
        }

        $this->requirePermission('digitalProducts-manageProductType:' . $product->getType()->uid);

        if (!Craft::$app->getElements()->deleteElement($product)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => false]);
            }

            Craft::$app->getSession()->setError(Craft::t('digital-products', 'Couldn’t delete product.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'product' => $product
            ]);

            return null;
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('digital-products', 'Product deleted.'));

        return $this->redirectToPostedUrl($product);
    }

    /**
     * Save a new or an existing product.
     *
     * @return Response|null
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $product = $this->_buildProductFromPost();

        $this->requirePermission('digitalProducts-manageProductType:' . $product->getType()->uid);

        $existingProduct = (bool)$product->id;

        if (!Craft::$app->getElements()->saveElement($product)) {
            if (!$existingProduct) {
                $product->id = null;
            }

            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save product.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'product' => $product
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Product saved.'));

        return $this->redirectToPostedUrl($product);
    }

    /**
     * Previews a product.
     *
     * @return Response
     */
    public function actionPreviewProduct(): Response
    {
        $this->requirePostRequest();

        $product = $this->_buildProductFromPost();
        $this->requirePermission('digitalProducts-manageProductType:' . $product->getType()->uid);

        return $this->_showProduct($product);
    }

    /**
     * Redirects the client to a URL for viewing a disabled product on the front end.
     *
     * @param int $productId the product id
     * @param int|null $siteId the site id
     *
     * @return Response
     * @throws Exception if there's no valid product template
     */
    public function actionShareProduct(int $productId, int $siteId = null): Response
    {
        /** @var Product $product */
        $product = Craft::$app->getElements()->getElementById($productId, Product::class, $siteId);

        if (!$product) {
            throw new BadRequestHttpException('Invalid product ID: ' . $productId);
        }

        if (!DigitalProducts::getInstance()->getProductTypes()->isProductTypeTemplateValid($product->getType(), $product->siteId)) {
            throw new ServerErrorHttpException('Product type has an invalid template path');
        }

        $this->requirePermission('digitalProducts-manageProductType:' . $product->getType()->uid);

        // Create the token and redirect to the product URL with the token in place
        $token = Craft::$app->getTokens()->createToken([
            'action' => 'digital-products/products/viewSharedProduct',
            'params' => ['productId' => $productId, 'site' => $product->getSite()]
        ]);

        $url = UrlHelper::urlWithToken($product->getUrl(), $token);

        return $this->redirect($url);
    }

    /**
     * Shows an product/draft/version based on a token.
     *
     * @param int $productId
     * @param int|null $siteId
     *
     * @return Response
     * @throws Exception if product not found
     */
    public function actionViewSharedProduct($productId, $siteId = null): Response
    {
        $this->requireToken();

        /** @var Product $product */
        $product = Craft::$app->getElements()->getElementById($productId, Product::class, $siteId);

        if (!$product) {
            throw new Exception('Product not found.');
        }

        return $this->_showProduct($product);
    }

    // Private Methods
    // =========================================================================

    /**
     * Displays a product.
     *
     * @param Product $product
     *
     * @return Response
     * @throws Exception if product type is not found
     */
    private function _showProduct(Product $product): Response
    {

        $productType = $product->getType();

        if (!$productType) {
            throw new ServerErrorHttpException('Product type not found.');
        }

        $siteSettings = $productType->getSiteSettings();

        if (!isset($siteSettings[$product->siteId]) || !$siteSettings[$product->siteId]->hasUrls) {
            throw new ServerErrorHttpException('The product ' . $product->id . ' doesn\'t have a URL for the site ' . $product->siteId . '.');
        }

        $site = Craft::$app->getSites()->getSiteById($product->siteId);

        if (!$site) {
            throw new ServerErrorHttpException('Invalid site ID: ' . $product->siteId);
        }

        Craft::$app->language = $site->language;

        // Have this product override any freshly queried products with the same ID/site
        if ($product->id) {
            Craft::$app->getElements()->setPlaceholderElement($product);
        }

        $this->getView()->getTwig()->disableStrictVariables();

        return $this->renderTemplate($siteSettings[$product->siteId]->template, [
            'product' => $product
        ]);
    }

    /**
     * Prepare $variable array for editing a Product
     *
     * @param array $variables by reference
     *
     * @throws ForbiddenHttpException if missing permissions
     * @throws Exception if data ir missing or corrupt
     */
    private function _prepareVariableArray(&$variables)
    {
        $variables['tabs'] = [];

        // Locale related checks
        if (Craft::$app->getIsMultiSite()) {
            // Only use the sites that the user has access to
            $variables['siteIds'] = Craft::$app->getSites()->getEditableSiteIds();
        } else {
            $variables['siteIds'] = [Craft::$app->getSites()->getPrimarySite()->id];
        }

        if (!$variables['siteIds']) {
            throw new ForbiddenHttpException('User not permitted to edit content in any sites supported by this section');
        }

        if (empty($variables['site'])) {
            $site = $variables['site'] = Craft::$app->getSites()->currentSite;

            if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
                $site = $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
            }
        } else {
            // Make sure they were requesting a valid site
            /** @var Site $site */
            $site = $variables['site'];
            if (!in_array($site->id, $variables['siteIds'], false)) {
                throw new ForbiddenHttpException('User not permitted to edit content in this site');
            }
        }

        // Product related checks
        /** @var ProductType $productType */
        $productType = $variables['productType'];

        if (empty($variables['product'])) {
            if (!empty($variables['productId'])) {
                $variables['product'] = Craft::$app->getElements()->getElementById($variables['productId'], Product::class, $site->id);

                if (!$variables['product']) {
                    throw new Exception('Missing product data.');
                }
            } else {
                $variables['product'] = new Product();
                $variables['product']->typeId = $productType->id;

                if (!empty($variables['siteId'])) {
                    $variables['product']->site = $variables['siteId'];
                }
            }
        }

        /** @var Product $product */
        $product = $variables['product'];

        // Enable locales
        if ($variables['product']->id) {
            $variables['enabledSiteIds'] = Craft::$app->getElements()->getEnabledSiteIdsForElement($variables['product']->id);
        } else {
            $variables['enabledSiteIds'] = [];

            foreach (Craft::$app->getSites()->getEditableSiteIds() as $site) {
                $variables['enabledSiteIds'][] = $site;
            }
        }

        foreach ($productType->getProductFieldLayout()->getTabs() as $index => $tab) {
            // Do any of the fields on this tab have errors?
            $hasErrors = false;

            if ($product->hasErrors()) {
                foreach ($tab->getFields() as $field) {
                    /** @var Field $field */
                    if ($hasErrors = $product->hasErrors($field->handle . '.*')) {
                        break;
                    }
                }
            }

            $variables['tabs'][] = [
                'label' => Craft::t('commerce', $tab->name),
                'url' => '#tab' . ($index + 1),
                'class' => $hasErrors ? 'error' : null
            ];
        }
    }

    /**
     * Enable live preview for products with valid templates on desktop browsers.
     *
     * @param array $variables
     */
    private function _maybeEnableLivePreview(array &$variables)
    {
        if (
            !Craft::$app->getRequest()->isMobileBrowser(true) &&
            DigitalProducts::getInstance()->getProductTypes()->isProductTypeTemplateValid($variables['productType'], $variables['product']->siteId)
        ) {
            $this->getView()->registerJs('Craft.LivePreview.init(' . Json::encode([
                    'fields' => '#title-field, #fields > div > div > .field, #sku-field, #price-field',
                    'extraFields' => '#meta-pane .field',
                    'previewUrl' => $variables['product']->getUrl(),
                    'previewAction' => Craft::$app->getSecurity()->hashData('digital-products/products/preview-product'),
                    'previewParams' => [
                        'typeId' => $variables['productType']->id,
                        'productId' => $variables['product']->id,
                        'siteId' => $variables['product']->siteId,
                    ]
                ]) . ');');

            $variables['showPreviewBtn'] = true;

            // Should we show the Share button too?
            if ($variables['product']->id) {
                // If the product is enabled, use its main URL as its share URL.
                if ($variables['product']->getStatus() === Product::STATUS_LIVE) {
                    $variables['shareUrl'] = $variables['product']->getUrl();
                } else {
                    $variables['shareUrl'] = UrlHelper::actionUrl('digital-products/products/share-product', [
                        'productId' => $variables['product']->id,
                        'siteId' => $variables['product']->siteId
                    ]);
                }
            }
        } else {
            $variables['showPreviewBtn'] = false;
        }
    }

    /**
     * Build product from POST data.
     *
     * @return Product
     * @throws Exception if product cannot be found
     */
    private function _buildProductFromPost(): Product
    {
        $request = Craft::$app->getRequest();
        $productId = $request->getParam('productId');
        $site = $request->getParam('siteId');

        if ($productId) {
            $product = Craft::$app->getElements()->getElementById($productId, Product::class, $site);

            if (!$product) {
                throw new Exception('No product found with that id.');
            }
        } else {
            $product = new Product();
        }

        $product->typeId = $request->getBodyParam('typeId');
        $product->enabled = $request->getBodyParam('enabled');

        $product->price = Localization::normalizeNumber($request->getBodyParam('price'));
        $product->sku = $request->getBodyParam('sku');

        $product->postDate = (($date = $request->getParam('postDate')) !== false ? (DateTimeHelper::toDateTime($date) ?: null) : $product->postDate);
        $product->expiryDate = (($date = $request->getParam('expiryDate')) !== false ? (DateTimeHelper::toDateTime($date) ?: null) : $product->expiryDate);

        $product->promotable = (bool)$request->getBodyParam('promotable');
        $product->taxCategoryId = $request->getBodyParam('taxCategoryId');
        $product->slug = $request->getBodyParam('slug');

        $product->enabledForSite = (bool)$request->getBodyParam('enabledForSite', $product->enabledForSite);
        $product->title = $request->getBodyParam('title', $product->title);
        $product->setFieldValuesFromRequest('fields');

        // Last checks
        if (empty($product->sku)) {
            $productType = $product->getType();
            $product->sku = Craft::$app->getView()->renderObjectTemplate($productType->skuFormat, $product);
        }

        if (!$product->postDate) {
            $product->postDate = new DateTime();
        }

        return $product;
    }
}
