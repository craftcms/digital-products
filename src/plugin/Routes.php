<?php

namespace craft\digitalproducts\plugin;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

trait Routes
{
    /**
     * Control Panel routes.
     */
    public function _registerCpRoutes()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['digital-products/producttypes/new'] = 'digital-products/product-types/edit';
                $event->rules['digital-products/producttypes/<productTypeId:\d+>'] = 'digital-products/product-types/edit';

                $event->rules['digital-products/products'] = 'digital-products/products/index';
                $event->rules['digital-products/products/<productTypeHandle:{handle}>'] = 'digital-products/products/index';
                $event->rules['digital-products/products/<productTypeHandle:{handle}>/new'] = 'digital-products/products/edit';
                $event->rules['digital-products/products/<productTypeHandle:{handle}>/new/<siteHandle:{handle}>'] = 'digital-products/products/edit';
                $event->rules['digital-products/products/<productTypeHandle:{handle}>/<productId:\d+>'] = 'digital-products/products/edit';
                $event->rules['digital-products/products/<productTypeHandle:{handle}>/<productId:\d+>/<siteHandle:{handle}>'] = 'digital-products/products/edit';

                $event->rules['digital-products/licenses/new'] = 'digital-products/licenses/edit';
                $event->rules['digital-products/licenses/<licenseId:\d+>'] = 'digital-products/licenses/edit';
            }
        );
    }
}

