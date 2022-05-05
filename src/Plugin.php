<?php

namespace craft\digitalproducts;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\commerce\elements\Order;
use craft\commerce\services\Payments as PaymentService;
use craft\commerce\services\Purchasables;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\fieldlayoutelements\ProductTitleField;
use craft\digitalproducts\fields\Products;
use craft\digitalproducts\gql\interfaces\elements\Product as GqlProductInterface;
use craft\digitalproducts\helpers\ProjectConfigData;
use craft\digitalproducts\models\Settings;
use craft\digitalproducts\plugin\Routes;
use craft\digitalproducts\plugin\Services;
use craft\digitalproducts\services\ProductTypes;
use craft\digitalproducts\variables\DigitalProducts;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlSchemaComponentsEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
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
 *
 * @method Settings getSettings()
 *
 * @property-read Settings $settings
 * @property-read array $cpNavItem
 * @property-read mixed $settingsResponse
 */
class Plugin extends BasePlugin
{
    /**
     * @inheritDoc
     */
    public bool $hasCpSection = true;

    /**
     * @inheritDoc
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritDoc
     */
    public string $schemaVersion = '3.0.0';

    /**
     * @inheritDoc
     */
    public string $minVersionRequired = '2.4.3.2';

    use Services;
    use Routes;

    /**
     * Initialize the plugin.
     */
    public function init(): void
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
        $this->_registerGqlComponents();
        $this->_defineFieldLayoutElements();
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
                'url' => 'digital-products/products',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('digitalProducts-manageProductTypes')) {
            $navItems['subnav']['productTypes'] = [
                'label' => Craft::t('digital-products', 'Product Types'),
                'url' => 'digital-products/producttypes',
            ];
        }

        if (Craft::$app->getUser()->checkPermission('digitalProducts-manageLicenses')) {
            $navItems['subnav']['licenses'] = [
                'label' => Craft::t('digital-products', 'Licenses'),
                'url' => 'digital-products/licenses',
            ];
        }

        if (Craft::$app->getUser()->getIsAdmin()) {
            $navItems['subnav']['settings'] = [
                'label' => Craft::t('digital-products', 'Settings'),
                'url' => 'digital-products/settings',
            ];
        }

        return $navItems;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('digital-products/settings'));
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    private function _defineFieldLayoutElements(): void
    {
        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_NATIVE_FIELDS, static function(DefineFieldLayoutFieldsEvent $e) {
            /** @var FieldLayout $fieldLayout */
            $fieldLayout = $e->sender;

            if ($fieldLayout->type == Product::class) {
                $e->fields[] = ProductTitleField::class;
            }
        });
    }

    /**
     * Register the event handlers.
     */
    private function _registerEventHandlers(): void
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
            static function(RebuildConfigEvent $event) {
                $event->config['digital-products'] = ProjectConfigData::rebuildProjectConfig();
            }
        );
    }

    /**
     * Register Commerce’s fields
     */
    private function _registerFieldTypes(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = Products::class;
            }
        );
    }

    /**
     * Register Commerce’s purchasable
     */
    private function _registerPurchasableTypes(): void
    {
        Event::on(
            Purchasables::class,
            Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = Product::class;
            }
        );
    }

    /**
     * Register Digital Product permissions
     */
    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $productTypes = $this->getProductTypes()->getAllProductTypes();

                $productTypePermissions = [];

                foreach ($productTypes as $productType) {
                    $suffix = ':' . $productType->uid;
                    $productTypePermissions['digitalProducts-manageProductType' . $suffix] = ['label' => Craft::t('digital-products', 'Manage “{type}” products', ['type' => $productType->name])];
                }

                $event->permissions[] = [
                    'heading' => Craft::t('digital-products', 'Digital Products'),
                    'permissions' => [
                        'digitalProducts-manageProductTypes' => ['label' => Craft::t('digital-products', 'Manage product types')],
                        'digitalProducts-manageProducts' => ['label' => Craft::t('digital-products', 'Manage products'), 'nested' => $productTypePermissions],
                        'digitalProducts-manageLicenses' => ['label' => Craft::t('digital-products', 'Manage licenses')],
                    ],
                ];
            }
        );
    }

    /**
     * Register Digital Product template variable
     */
    private function _registerVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('digitalProducts', DigitalProducts::class);
            }
        );
    }

    /**
     * Register the element types supplied by Digital Products
     */
    private function _registerElementTypes(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            static function(RegisterComponentTypesEvent $e) {
                $e->types[] = Product::class;
                $e->types[] = License::class;
            }
        );
    }

    /**
     * Register the Gql interfaces
     */
    private function _registerGqlInterfaces(): void
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_TYPES,
            static function(RegisterGqlTypesEvent $event) {
                // Add my GraphQL types
                $types = $event->types;
                $types[] = GqlProductInterface::class;
                $event->types = $types;
            }
        );
    }

    /**
     * Register the Gql components
     *
     * @return void
     */
    private function _registerGqlComponents(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_SCHEMA_COMPONENTS, static function(RegisterGqlSchemaComponentsEvent $event) {
            $queryComponents = [];

            $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

            if (!empty($productTypes)) {
                $label = Craft::t('digital-products', 'Digital Products');
                $productPermissions = [];

                foreach ($productTypes as $productType) {
                    $suffix = 'digitalProductTypes.' . $productType->uid;
                    $productPermissions[$suffix . ':read'] = ['label' => Craft::t('digital-products', 'View digital product type - {productType}', ['productType' => Craft::t('site', $productType->name)])];
                }

                $queryComponents[$label] = $productPermissions;
            }

            $event->queries = array_merge($event->queries, $queryComponents);
        });
    }
}
