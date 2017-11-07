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
     * @return mixed
     */
    public function _registerCpRoutes()
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['digitalproducts/producttypes/new'] = 'commerce-digitalproducts/product-types/edit';
            $event->rules['digitalproducts/producttypes/<productTypeId:\d+>)'] = 'digital-products/product-types/edit';

            $event->rules['digitalproducts/products/<productTypeHandle:{handle}>'] = 'commerce-digitalproducts/products/index';
            $event->rules['digitalproducts/products/<productTypeHandle:{handle}>/new'] = 'commerce-digitalproducts/products/edit';
            $event->rules['digitalproducts/products/<productTypeHandle:{handle}>/new/<siteHandle:{handle}>'] = 'commerce-digitalproducts/products/edit';
            $event->rules['digitalproducts/products/<productTypeHandle:{handle}>/<productId:\d+>'] = 'commerce-digitalproducts/products/edit';
            $event->rules['digitalproducts/products/<productTypeHandle:{handle}>/<productId:\d+>/<siteHandle:{handle}>'] = 'commerce-digitalproducts/products/edit';

            $event->rules['digitalproducts/licenses/new'] = 'commerce-digitalproducts/licenses/edit';
            $event->rules['digitalproducts/licenses/<licenseId:\d+>'] = 'commerce-digitalproducts/licenses/edit';
        });

    }
}

