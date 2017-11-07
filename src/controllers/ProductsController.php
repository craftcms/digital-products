<?php
namespace craft\commerce\digitalProducts\controllers;

use Craft;
use craft\commerce\digitalProducts\elements\Product;
use craft\commerce\digitalProducts\Plugin as DigitalProducts;
use craft\helpers\Json;
use craft\helpers\Localization;
use craft\helpers\UrlHelper;
use craft\web\Controller as BaseController;
use yii\base\Exception;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
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
     */
    public function actionIndex(): Response
    {
        $this->renderTemplate('digitalproducts/products/index');
    }

    /**
     * Edit a product
     *
     * @param string       $productTypeHandle the product type handle
     * @param int|null     $productId         the product id
     * @param string|null  $siteHandle        the site handle
     * @param Product|null $product           the product
     *
     * @throws Exception in case of lacking permissions or missing/corrupt data
     */
    public function actionEdit(string $productTypeHandle, int $productId = null, string $siteHandle = null, Product $product = null)
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
                throw new NotFoundHttpException('Invalid site handle: '.$siteHandle);
            }
        }

        $this->_prepareVariableArray($variables);

        if (!empty($variables['product']->id)) {
            $variables['title'] = $variables['product']->title;
        } else {
            $variables['title'] = Craft::t('commerce-digitalproducts', 'Create a new product');
        }

        // Can't just use the entry's getCpEditUrl() because that might include the site handle when we don't want it
        $variables['baseCpEditUrl'] = 'digitalproducts/products/'.$variables['productTypeHandle'].'/{id}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'].
            (Craft::$app->getIsMultiSite() && Craft::$app->getSites()->currentSite->id !== $variables['site']->id ? '/'.$variables['site']->handle : '');

        $this->_maybeEnableLivePreview($variables);

        $this->renderTemplate('digitalproducts/products/_edit', $variables);
    }

    /**
     * Deletes a product.
     *
     * @throws HttpException if no product found
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

        if (DigitalProducts::getInstance()->getProducts()->deleteProduct($product)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => true]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Product deleted.'));
            $this->redirectToPostedUrl($product);
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => false]);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t delete product.'));
            Craft::$app->getUrlManager()->setRouteParams([
                'product' => $product
            ]);

            return null;
        }
    }

    /**
     * Save a new or existing product.
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $product = $this->_buildProductFromPost();

        $this->_enforceProductPermissionsForProductType($product->typeId);

        $existingProduct = (bool)$product->id;

        if (DigitalProducts::getInstance()->getProducts()->saveProduct($product)) {
            craft()->userSession->setNotice(Craft::t('Product saved.'));
            $this->redirectToPostedUrl($product);
        }

        if (!$existingProduct) {
            $product->id = null;
        }

        Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save product.'));
        Craft::$app->getUrlManager()->setRouteParams([
            'product' => $product
        ]);

        return null;
    }

    /**
     * Previews a product.
     */
    public function actionPreviewProduct()
    {

        $this->requirePostRequest();

        $product = $this->_buildProductFromPost();
        $this->_enforceProductPermissionsForProductType($product->typeId);

        $this->_showProduct($product);
    }

    /**
     * Redirects the client to a URL for viewing a disabled product on the front end.
     *
     * @param mixed $productId
     * @param mixed $locale
     *
     * @throws HttpException
     */
    public function actionShareProduct($productId, $locale = null)
    {
        /**
         * @var $product DigitalProducts_ProductModel
         */
        $product = craft()->digitalProducts_products->getProductById($productId, $locale);

        if (!$product || !craft()->digitalProducts_productTypes->isProductTypeTemplateValid($product->getProductType())) {
            throw new HttpException(404);
        }

        $this->_enforceProductPermissionsForProductType($product->typeId);

        // Create the token and redirect to the product URL with the token in place
        $token = craft()->tokens->createToken([
            'action' => 'digitalProducts/products/viewSharedProduct',
            'params' => [
                'productId' => $productId,
                'locale' => $product->locale
            ]
        ]);

        $url = UrlHelper::getUrlWithToken($product->getUrl(), $token);
        craft()->request->redirect($url);
    }

    /**
     * Shows an product/draft/version based on a token.
     *
     * @param mixed $productId
     * @param mixed $locale
     *
     * @throws HttpException
     * @return null
     */
    public function actionViewSharedProduct($productId, $locale = null)
    {
        $this->requireToken();

        $product = craft()->digitalProducts_products->getProductById($productId, $locale);

        if (!$product) {
            throw new HttpException(404);
        }

        $this->_showProduct($product);
    }

    // Private Methods
    // =========================================================================

    /**
     * Displays a product.
     *
     * @param DigitalProducts_ProductModel $product
     *
     * @throws HttpException
     * @return null
     */
    private function _showProduct(DigitalProducts_ProductModel $product)
    {
        $productType = $product->getProductType();

        if (!$productType) {
            throw new HttpException(404);
        }

        craft()->setLanguage($product->locale);

        // Have this product override any freshly queried products with the same ID/locale
        craft()->elements->setPlaceholderElement($product);

        craft()->templates->getTwig()->disableStrictVariables();

        $this->renderTemplate($productType->template, [
            'product' => $product
        ]);
    }


    /**
     * Prepare $variable array for editing a Product
     *
     * @param array $variables by reference
     *
     * @throws Exception in case of missing/corrupt data or lacking permissions.
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
                    throw new HttpException(404);
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
                    'previewAction' => 'digitalProducts/products/previewProduct',
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
                if ($variables['product']->getStatus() == Product::STATUS_LIVE) {
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
     * @return Product
     * @throws Exception
     */
    private function _buildProductFromPost(): Product
    {
        $request = Craft::$app->getRequest();
        $productId = $request->getParam('productId');
        $site = $request->getParam('site');

        if ($productId) {
            $product = DigitalProducts::getInstance()->getProducts()->getProductById($productId, $site);

            if (!$product) {
                throw new Exception(Craft::t('commerce-digitalproducts', 'No product with the ID “{id}”',
                    ['id' => $productId]));
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
