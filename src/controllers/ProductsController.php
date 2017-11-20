<?php
namespace craft\commerce\digitalProducts\controllers;

use Craft;
use craft\commerce\digitalProducts\elements\Product;
use craft\commerce\digitalProducts\Plugin as DigitalProducts;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\web\Controller as BaseController;
use yii\base\Exception;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

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
        return $this->renderTemplate('commerce-digitalproducts/products/index');
    }

    /**
     * Edit a product
     *
     * @param string       $productTypeHandle the product type handle
     * @param int|null     $productId         the product id
     * @param string|null  $siteHandle        the site handle
     * @param Product|null $product           the product
     *
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

        $this->requirePermission('digitalProducts-manageProducts:'.$productType->id);

        if ($siteHandle !== null) {
            $variables['site'] = Craft::$app->getSites()->getSiteByHandle($siteHandle);

            if (!$variables['site']) {
                throw new Exception('Invalid site handle: '.$siteHandle);
            }
        }

        $this->_prepareVariableArray($variables);

        if (!empty($variables['product']->id)) {
            $variables['title'] = $variables['product']->title;
        } else {
            $variables['title'] = Craft::t('commerce-digitalproducts', 'Create a new product');
        }

        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'commerce-digitalproducts/products/'.$variables['productTypeHandle'].'/{id}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'].
            (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/'.$variables['site']->handle : '');

        $this->_maybeEnableLivePreview($variables);

        return $this->renderTemplate('commerce-digitalproducts/products/_edit', $variables);
    }

    /**
     * Delete a product.
     *
     * @return Response
     * @throws Exception if no product found
     */
    public function actionDeleteProduct()
    {
        $this->requirePostRequest();

        $productId = Craft::$app->getRequest()->getRequiredParam('productId');
        $product = DigitalProducts::getInstance()->getProducts()->getProductById($productId);

        if (!$product) {
            throw new Exception(Craft::t('commerce-digitalproducts', 'No product exists with the ID “{id}”.',['id' => $productId]));
        }

        $this->requirePermission('digitalProducts-manageProducts:'.$product->typeId);

        if (!DigitalProducts::getInstance()->getProducts()->deleteProduct($product)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => false]);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce-digitalproducts', 'Couldn’t delete product.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'product' => $product
            ]);

            return null;
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $this->asJson(['success' => true]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce-digitalproducts', 'Product deleted.'));

        return $this->redirectToPostedUrl($product);
    }

    /**
     * Save a new or an existing product.
     *
     * @return Response
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $product = $this->_buildProductFromPost();

        $this->requirePermission('digitalProducts-manageProducts:'.$product->typeId);

        $existingProduct = (bool)$product->id;

        if (!DigitalProducts::getInstance()->getProducts()->saveProduct($product)) {
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
        $this->requirePermission('digitalProducts-manageProducts:'.$product->typeId);

        return $this->_showProduct($product);
    }

    /**
     * Redirects the client to a URL for viewing a disabled product on the front end.
     *
     * @param int      $productId the product id
     * @param int|null $siteId    the site id
     *
     * @return Response
     * @throws Exception if there's no valid product template
     */
    public function actionShareProduct(int $productId, int $siteId = null): Response
    {
        /**
         * @var $product Product
         */
        $product = DigitalProducts::getInstance()->getProducts()->getProductById($productId, $siteId);

        if (!$product || DigitalProducts::getInstance()->getProductTypes()->isProductTypeTemplateValid($product->getProductType())) {
            throw new Exception();
        }

        $this->requirePermission('digitalProducts-manageProducts:'.$product->typeId);

        // Create the token and redirect to the product URL with the token in place
        $token = Craft::$app->getTokens()->createToken([
            'action' => 'commerce-digitalproducts/products/viewSharedProduct',
            'params' => ['productId' => $productId, 'locale' => $product->site]
        ]);

        $url = UrlHelper::urlWithToken($product->getUrl(), $token);

        return $this->redirect($url);
    }

    /**
     * Shows an product/draft/version based on a token.
     *
     * @param int      $productId
     * @param int|null $siteId
     *
     * @throws Exception if product not found
     * @return Response
     */
    public function actionViewSharedProduct($productId, $siteId = null): Response
    {
        $this->requireToken();

        $product = DigitalProducts::getInstance()->getProducts()->getProductById($productId, $siteId);

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
     * @throws Exception if product type is not found
     * @return Response
     */
    private function _showProduct(Product $product): Response
    {
        $productType = $product->getProductType();

        if (!$productType) {
            throw new Exception('Product type not found.');
        }

        Craft::$app->language = $product->site;

        // Have this product override any freshly queried products with the same ID/site
        Craft::$app->getElements()->setPlaceholderElement($product);

        $this->getView()->getTwig()->disableStrictVariables();

        return $this->renderTemplate($productType->template, [
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
            $variables['site'] = Craft::$app->getSites()->currentSite;

            if (!in_array($variables['site']->id, $variables['siteIds'], false)) {
                $variables['site'] = Craft::$app->getSites()->getSiteById($variables['siteIds'][0]);
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
        if (empty($variables['product'])) {
            if (!empty($variables['productId'])) {
                $variables['product'] = DigitalProducts::getInstance()->getProducts()->getProductById($variables['productId'], $variables['localeId']);

                if (!$variables['product']) {
                    throw new Exception('Missing product data.');
                }
            } else {
                $variables['product'] = new Product();
                $variables['product']->typeId = $variables['productType']->id;

                if (!empty($variables['siteId'])) {
                    $variables['product']->site = $variables['siteId'];
                }
            }
        }

        // Enable locales
        if ($variables['product']->id) {
            $variables['enabledSiteIds'] = Craft::$app->getElements()->getEnabledSiteIdsForElement($variables['product']->id);
        } else {
            $variables['enabledSiteIds'] = [];

            foreach (Craft::$app->getSites()->getEditableSiteIds() as $site) {
                $variables['enabledSiteIds'][] = $site;
            }
        }
    }

    /**
     * Enable live preview for products with valid templates on desktop browsers.
     *
     * @param array $variables
     */
    private function _maybeEnableLivePreview(array &$variables)
    {
        if (!Craft::$app->getRequest()->isMobileBrowser(true) && DigitalProducts::getInstance()->getProductTypes()->isProductTypeTemplateValid($variables['productType'])) {
            $this->getView()->registerJs('Craft.LivePreview.init('.Json::encode([
                    'fields' => '#title-field, #fields > div > div > .field, #sku-field, #price-field',
                    'extraFields' => '#meta-pane .field',
                    'previewUrl' => $variables['product']->getUrl(),
                    'previewAction' => 'commerce-digitalproducts/products/previewProduct',
                    'previewParams' => [
                        'typeId' => $variables['productType']->id,
                        'productId' => $variables['product']->id,
                        'locale' => $variables['product']->locale,
                    ]
                ]).');');

            $variables['showPreviewBtn'] = true;

            // Should we show the Share button too?
            if ($variables['product']->id) {
                // If the product is enabled, use its main URL as its share URL.
                if ($variables['product']->getStatus() === Product::STATUS_LIVE) {
                    $variables['shareUrl'] = $variables['product']->getUrl();
                } else {
                    $variables['shareUrl'] = UrlHelper::actionUrl('digital-roducts/products/share-roduct', [
                        'productId' => $variables['product']->id,
                        'locale' => $variables['product']->locale
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
        $site = $request->getParam('site');

        if ($productId) {
            $product = DigitalProducts::getInstance()->getProducts()->getProductById($productId, $site);

            if (!$product) {
                throw new Exception('No product found with that id.');
            }
        } else {
            $product = new Product();
        }

        if (isset($data['typeId'])) {
            $product->typeId = $request->getBodyParam('typeId');
        }

        if (isset($data['enabled'])) {
            $product->enabled = $request->getBodyParam('enabled');
        }

        $product->price = Localization::normalizeNumber($request->getBodyParam('price'));
        $product->sku = $request->getBodyParam('sku');

        $product->postDate = (($date = $request->getParam('postDate')) !== false ? (DateTimeHelper::toDateTime($date) ?: null) : $product->postDate);
        $product->expiryDate = (($date = $request->getParam('expiryDate')) !== false ? (DateTimeHelper::toDateTime($date) ?: null) : $product->expiryDate);

        $product->promotable = $request->getBodyParam('promotable');
        $product->taxCategoryId = $request->getBodyParam('taxCategoryId');
        $product->slug = $request->getBodyParam('slug');

        $product->enabledForSite = (bool)$request->getBodyParam('enabledForSite', $product->enabledForSite);
        $product->title = $request->getBodyParam('title', $product->title);
        $product->setFieldValuesFromRequest('fields');

        return $product;
    }
}
