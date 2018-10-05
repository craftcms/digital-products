<?php

namespace craft\digitalproducts;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\commerce\services\Purchasables;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\fields\Products;
use craft\digitalproducts\models\Settings;
use craft\digitalproducts\plugin\Routes;
use craft\digitalproducts\plugin\Services;
use craft\digitalproducts\variables\DigitalProducts;
use craft\commerce\elements\Order;
use craft\commerce\services\Payments as PaymentService;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Elements;
use craft\services\Fields;
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
    // Traits
    // =========================================================================

    use Services;
    use Routes;


    // Public Methods
    // =========================================================================

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

        if (Craft::$app->getUser()->checkPermission('digitalProducts-manageProducts')) {
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
     * Get Settings URL
     */
    public function getSettingsUrl(): bool
    {
        return false;
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
     * Register the event handlers.
     */
    private function _registerEventHandlers()
    {
        Event::on(UsersService::class, UsersService::EVENT_AFTER_ACTIVATE_USER, [$this->getLicenses(), 'handleUserActivation']);
        Event::on(Order::class, Order::EVENT_AFTER_COMPLETE_ORDER, [$this->getLicenses(), 'handleCompletedOrder']);
        Event::on(PaymentService::class, PaymentService::EVENT_BEFORE_PROCESS_PAYMENT_EVENT, [$this->getLicenses(), 'maybePreventPayment']);
    }

    /**
     * Register Commerce’s fields
     */
    private function _registerFieldTypes()
    {
        Event::on(Fields::className(), Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Products::class;
        });
    }

    /**
     * Register Commerce’s purchasable
     */
    private function _registerPurchasableTypes()
    {
        Event::on(Purchasables::className(), Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Product::class;
        });
    }

    /**
     * Register Digital Product permissions
     */
    private function _registerPermissions()
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, function(RegisterUserPermissionsEvent $event) {
            $productTypes = [];//$this->getProductTypes()->getAllProductTypes();

            $productTypePermissions = [];

            foreach ($productTypes as $id => $productType) {
                $suffix = ':'.$id;
                $productTypePermissions['digitalProducts-manageProductType'.$suffix] = ['label' => Craft::t('digital-products', 'Manage “{type}” products', ['type' => $productType->name])];
            }

            $event->permissions[] = [
                'digitalProducts-manageProductTypes' => ['label' => Craft::t('digital-products', 'Manage product types')],
                'digitalProducts-manageProducts' => ['label' => Craft::t('digital-products', 'Manage products'), 'nested' => $productTypePermissions],
                'digitalProducts-manageLicenses' => ['label' => Craft::t('digital-products', 'Manage licenses')],
            ];
        });
    }

    /**
     * Register Digital Product template variable
     */
    private function _registerVariable()
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->set('digitalProducts', DigitalProducts::class);
        });

    }

    /**
     * Register the element types supplied by Digital Products
     */
    private function _registerElementTypes()
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function(RegisterComponentTypesEvent $e) {
            $e->types[] = Product::class;
            $e->types[] = License::class;
        });
    }

}
