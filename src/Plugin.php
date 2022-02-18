<?php

namespace craft\digitalproducts;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\commerce\elements\Order;
use craft\commerce\services\Payments as PaymentService;
use craft\commerce\services\Purchasables;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\fields\Products;
use craft\digitalproducts\gql\interfaces\elements\Product as GqlProductInterface;
use craft\digitalproducts\gql\queries\Product as GqlProductQueries;
use craft\digitalproducts\helpers\ProjectConfigData;
use craft\digitalproducts\models\Settings;
use craft\digitalproducts\plugin\Routes;
use craft\digitalproducts\plugin\Services;
use craft\digitalproducts\services\ProductTypes;
use craft\digitalproducts\variables\DigitalProducts;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlPermissionsEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\DefineConsoleActionsEvent;
use craft\console\Application as ConsoleApplication;
use craft\console\Controller as ConsoleController;
use craft\console\controllers\ResaveController;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Gql;
use craft\services\ProjectConfig;
use craft\services\Sites;
use craft\services\UserPermissions;
use craft\services\Users as UsersService;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;


/**
 * Digital Products Plugin for Craft Commerce.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class Plugin extends BasePlugin
{
    /**
     * @inheritDoc
     */
    public $hasCpSection = true;

    /**
     * @inheritDoc
     */
    public $hasCpSettings = true;

    /**
     * @inheritDoc
     */
    public $schemaVersion = '2.1.0';

    use Services;
    use Routes;

    /**
     * Initialize the plugin.
     */
    public function init()
    {
        parent::init();

        $this->_setPluginComponents();
        $this->_registerFieldTypes();
        $this->_registerPurchasableTypes();
        $this->_registerVariable();
        $this->_registerEventHandlers();
        $this->_registerCpRoutes();
        $this->_registerPermissions();
        $this->_registerElementTypes();
        $this->_registerGqlInterfaces();
        $this->_registerGqlQueries();
        $this->_registerGqlPermissions();
        $this->_defineResaveCommand();
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): array
    {
        $navItems = parent::getCpNavItem();

        if (Craft::$app->getUser()->checkPermission('digitalProducts-manageProducts')) {
            $navItems['subnav']['products'] = [
                'label' => Craft::t('digital-products', 'Products'),
                'url' => 'digital-products/products'
            ];
        }

        if (Craft::$app->getUser()->checkPermission('digitalProducts-manageProductTypes')) {
            $navItems['subnav']['productTypes'] = [
                'label' => Craft::t('digital-products', 'Product Types'),
                'url' => 'digital-products/producttypes'
            ];
        }

        if (Craft::$app->getUser()->checkPermission('digitalProducts-manageLicenses')) {
            $navItems['subnav']['licenses'] = [
                'label' => Craft::t('digital-products', 'Licenses'),
                'url' => 'digital-products/licenses'
            ];
        }

        if (Craft::$app->getUser()->getIsAdmin()) {
            $navItems['subnav']['settings'] = [
                'label' => Craft::t('digital-products', 'Settings'),
                'url' => 'digital-products/settings'
            ];
        }

        return $navItems;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse()
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('digital-products/settings'));
    }



    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    /**
     * Defines the `resave/digital-products` command.
     */
    private function _defineResaveCommand()
    {
        if (
            !Craft::$app instanceof ConsoleApplication ||
            version_compare(Craft::$app->version, '3.2.0-beta.3', '<')
        ) {
            return;
        }

        Event::on(ResaveController::class, ConsoleController::EVENT_DEFINE_ACTIONS, function (DefineConsoleActionsEvent $e) {
            $e->actions['digital-products'] = [
                'action' => function (): int {
                    /** @var ResaveController $controller */
                    $controller = Craft::$app->controller;
                    $query = Product::find();
                    if ($controller->type !== null) {
                        $query->type(explode(',', $controller->type));
                    }
                    return $controller->saveElements($query);
                },
                'options' => ['type'],
                'helpSummary' => 'Re-saves Commerce digital products.',
                'optionsHelp' => [
                    'type' => 'The product type handle(s) of the digital products to resave.',
                ],
            ];
        });
    }

    /**
     * Register the event handlers.
     */
    private function _registerEventHandlers()
    {
        Event::on(
            UsersService::class,
            UsersService::EVENT_AFTER_ACTIVATE_USER,
            [$this->getLicenses(), 'handleUserActivation']
        );

        Event::on(
            PaymentService::class,
            PaymentService::EVENT_BEFORE_PROCESS_PAYMENT,
            [$this->getLicenses(), 'maybePreventPayment']
        );

        if ($this->getSettings()->generateLicenseOnOrderPaid) {
            Event::on(
                Order::class,
                Order::EVENT_AFTER_ORDER_PAID,
                [$this->getLicenses(), 'handleCompletedOrder']
            );
        } else {
            Event::on(
                Order::class,
                Order::EVENT_AFTER_COMPLETE_ORDER,
                [$this->getLicenses(), 'handleCompletedOrder']
            );
        }

        Event::on(
            Sites::class,
            Sites::EVENT_AFTER_SAVE_SITE,
            [$this->getProductTypes(), 'afterSaveSiteHandler']
        );

        Event::on(
            Sites::class,
            Sites::EVENT_AFTER_SAVE_SITE,
            [$this->getProducts(), 'afterSaveSiteHandler']
        );

        $projectConfigService = Craft::$app->getProjectConfig();
        $productTypeService = $this->getProductTypes();
        $projectConfigService->onAdd(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', [$productTypeService, 'handleChangedProductType'])
            ->onUpdate(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', [$productTypeService, 'handleChangedProductType'])
            ->onRemove(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.{uid}', [$productTypeService, 'handleDeletedProductType']);

        Event::on(
            Sites::class,
            Sites::EVENT_AFTER_DELETE_SITE,
            [$productTypeService, 'pruneDeletedSite']
        );

        Event::on(
            ProjectConfig::class,
            ProjectConfig::EVENT_REBUILD,
            function (RebuildConfigEvent $event) {
                $event->config['digital-products'] = ProjectConfigData::rebuildProjectConfig();
            }
        );
    }

    /**
     * Register Commerce’s fields
     */
    private function _registerFieldTypes()
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Products::class;
            }
        );
    }

    /**
     * Register Commerce’s purchasable
     */
    private function _registerPurchasableTypes()
    {
        Event::on(
            Purchasables::class,
            Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = Product::class;
            }
        );
    }

    /**
     * Register Digital Product permissions
     */
    private function _registerPermissions()
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $productTypes = $this->getProductTypes()->getAllProductTypes();

                $productTypePermissions = [];

                foreach ($productTypes as $productType) {
                    $suffix = ':' . $productType->uid;
                    $productTypePermissions['digitalProducts-manageProductType' . $suffix] = ['label' => Craft::t('digital-products', 'Manage “{type}” products', ['type' => $productType->name])];
                }

                $event->permissions[Craft::t('digital-products', 'Digital Products')] = [
                    'digitalProducts-manageProductTypes' => ['label' => Craft::t('digital-products', 'Manage product types')],
                    'digitalProducts-manageProducts' => ['label' => Craft::t('digital-products', 'Manage products'), 'nested' => $productTypePermissions],
                    'digitalProducts-manageLicenses' => ['label' => Craft::t('digital-products', 'Manage licenses')],
                ];
            }
        );
    }

    /**
     * Register Digital Product template variable
     */
    private function _registerVariable()
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('digitalProducts', DigitalProducts::class);
            }
        );
    }

    /**
     * Register the element types supplied by Digital Products
     */
    private function _registerElementTypes()
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $e) {
                $e->types[] = Product::class;
                $e->types[] = License::class;
            }
        );
    }

    /**
     * Register the Gql interfaces
     */
    private function _registerGqlInterfaces()
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            function (RegisterGqlTypesEvent $event) {
                // Add my GraphQL types
                $types = $event->types;
                $types[] = GqlProductInterface::class;
                $event->types = $types;
            }
        );
    }

    /**
     * Register the Gql things
     */
    private function _registerGqlQueries()
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_QUERIES,
            function (RegisterGqlQueriesEvent $event) {
                // Add my GraphQL queries
                $event->queries = array_merge($event->queries, GqlProductQueries::getQueries());
            }
        );
    }

    /**
     * Register the Gql things
     */
    private function _registerGqlPermissions()
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_PERMISSIONS,
            function (RegisterGqlPermissionsEvent $event) {
                $permissions = [];

                $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

                if (!empty($productTypes)) {
                    $label = Craft::t('digital-products', 'Digital Products');
                    $productPermissions = [];

                    foreach ($productTypes as $productType) {
                        $suffix = 'digitalProductTypes.' . $productType->uid;
                        $productPermissions[$suffix . ':read'] = ['label' => Craft::t('digital-products', 'View digital product type - {productType}', ['productType' => Craft::t('site', $productType->name)])];
                    }

                    $permissions[$label] = $productPermissions;
                }

                $event->permissions = array_merge($event->permissions, $permissions);
            }
        );
    }
}
