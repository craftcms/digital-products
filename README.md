# Digital Products plugin for Craft Commerce.

This plugin makes it possible to sell licenses for digital products with [Craft Commerce](http://craftcommerce.com).

## Requirements

Digital Products requires Craft CMS 3.0 or later and Craft Commerce 2.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require craftcms/digital-products

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Digital Products.

## Events

### The `beforeSaveProductType` and `afterSaveProductType` events

Plugins can be notified right before or right after a product type is saved in case your plugin needs to do something at that point:

```php
use craft\digitalproducts\events\ProductTypeEvent;
use craft\digitalproducts\services\ProductTypes;
use yii\base\Event;

// ...

Event::on(ProductTypes::class, ProductTypes::EVENT_BEFORE_SAVE_PRODUCTTYPE, function(ProductTypeEvent $e) {
    // Some custom code to be executed when a product type is saved
});
```

### The `beforeGenerateLicenseKey` event

Plugins get a chance to provide a license key instead of relying on Digital Products to generate one.

```php
use craft\digitalproducts\elements\License;
use craft\digitalproducts\events\GenerateKeyEvent;
use craft\digitalproducts\Plugin as DigitalProducts;
use yii\base\Event;

// ...

Event::on(License::class, License::EVENT_GENERATE_LICENSE_KEY, function(GenerateKeyEvent $e) {
    do {
        $licenseKey = // custom key generation logic...
    } while (!DigitalProducts::getInstance()->getLicenses()->isLicenseKeyUnique($licenseKey));

    $e->licenseKey = $licenseKey;
});
```

## Eager loading

Both licenses and products have several eager-loadable properties

### Licenses

* `product` - Allows you to eager-load the product associated with the license.
* `order` - Allows you to eager-load the order associated with the license, if any.
* `owner` - Allows you to eager-load the Craft user that owns the license, if any.

### Products
* `existingLicenses` - Eager-loads all the existing licenses for the currently logged in Craft User.

## Examples

### Displaying the licensed product for the currently logged in Craft User.

```
    {% if currentUser %}
        {% set licenses = craft.digitalProducts.licenses.owner(currentUser).with(['products', 'order']) %}

        <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title">Licenses</h3></div>
        {% if licenses %}
            <table class="table">
                <thead>
                    <tr>
                        <th>Licensed product</th>
                        <th>License date</th>
                        <th>Order</th>
                    </tr>
                </thead>
                <tbody>
                {% for license in licenses %}
                    <tr>
                        <td><a href="{{ license.product.getUrl() }}">{{ license.product.title }}</a></td>
                        <td>{{ license.dateCreated|date('Y-m-d H:i:s') }}</td>
                        <td>
                            {% if license.orderId %}
                                <a href="/store/order?number={{ license.order.number }}">Order no. {{ license.orderId }}</a>
                            {% endif %}
                        </td>
                    </tr>
                {% endfor %}
                </tbody>
            </table>
        {% endif %}
    {% else %}
        <p>Please log in first</p>
    {% endif %}
```

### Checking if currently logged in user is licensed to access a product.

```
    {% set products = craft.digitalProducts.products.type('onlineCourses').with(['existingLicenses']) %}
    {% if products|length %}
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>License status</th>
                </tr>
            </thead>
            <tbody>
                {% for product in products %}
                    <tr>
                        <td>{{ product.title }}</td>
                        <td>
                            {% if product.existingLicenses|length %}
                                You already own this product.
                            {% else %}
                                <a href="{{ product.getUrl() }}">Get it now!</a>
                            {% endif %}
                        </td>
                    </tr>
                {% endfor %}
            </tbody>
        </table>
    {% endif %}
```

## Changelog

### 2.0

* Digital products now fires `beforeSaveProductType`, `afterSaveProductType` and `beforeGenerateLicenseKey` events. For all element-related actions, you should look into [Craft 3 changes for Element hooks](https://github.com/craftcms/docs/blob/master/en/updating-plugins.md#element-hooks).
* Instead of `$product->getProductType()` you must now use `$product->getType()`
* Instead of eager-loading a boolean flag `isLicensed` for products, you must now eager-load the `existingLicenses` property, which is an array of existing licenses for that product for the current user.

### 1.0.5

* Fixed a bug where digital product prices would sometimes not be saved correctly.

### 1.0.4

* Fixed a bug where digital product prices did not display correctly.

### 1.0.3

* Added support for a plugin release feed.

### 1.0.2

* Fixed bugs.

### 1.0.1

* Fixed bugs.

### 1.0.0

* Initial release