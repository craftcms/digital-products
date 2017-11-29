<?php

namespace craft\commerce\digitalProducts\plugin;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

trait Routes
{
    // Private Methods
    // =========================================================================

    /**
     * Control Panel routes.
     *
     * @return void
     */
    public function _registerCpRoutes()
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['commerce-digital-products/producttypes/new'] = 'commerce-digital-products/product-types/edit';
            $event->rules['commerce-digital-products/producttypes/<productTypeId:\d+>'] = 'commerce-digital-products/product-types/edit';

            $event->rules['commerce-digital-products/products/<productTypeHandle:{handle}>'] = 'commerce-digital-products/products/index';
            $event->rules['commerce-digital-products/products/<productTypeHandle:{handle}>/new'] = 'commerce-digital-products/products/edit';
            $event->rules['commerce-digital-products/products/<productTypeHandle:{handle}>/new/<siteHandle:{handle}>'] = 'commerce-digital-products/products/edit';
            $event->rules['commerce-digital-products/products/<productTypeHandle:{handle}>/<productId:\d+>'] = 'commerce-digital-products/products/edit';
            $event->rules['commerce-digital-products/products/<productTypeHandle:{handle}>/<productId:\d+>/<siteHandle:{handle}>'] = 'commerce-digital-products/products/edit';

            $event->rules['commerce-digital-products/licenses/new'] = 'commerce-digital-products/licenses/edit';
            $event->rules['commerce-digital-products/licenses/<licenseId:\d+>'] = 'commerce-digital-products/licenses/edit';
        });
    }
}

