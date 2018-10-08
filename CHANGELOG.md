Changelog
=========

## Unreleased

### Changed
- Fixed a bug where querying for products sometimes would be broken.

### Fixed
- Fixed a bug where only fields in the first layout tab were visible when editing a product. ([#8](https://github.com/craftcms/commerce-digital-products/issues/8))
- Fixed a bug where product element slugs would not work. ([#9](https://github.com/craftcms/commerce-digital-products/issues/9))
- Fixed a bug where querying for licenses would not work in some cases. ([#11](https://github.com/craftcms/commerce-digital-products/issues/11))

### Added
- Added `craft\digitalproducts\models\ProductTypeSite::getSite()`

## 2.0.2 - 2018-05-14

### Fixed
- Fixed a bug where licenses were not being generated after completing an Commerce order.

## 2.0.1 - 2018-05-09

### Fixed
- Fixed a bug where tax categories were not being populated when editing a product ([#3](https://github.com/craftcms/commerce-digital-products/issues/3))
- Fixed broken links in composer.json file ([#4](https://github.com/craftcms/commerce-digital-products/issues/4))
- Fixed a bug where License index was not working ([#7](https://github.com/craftcms/commerce-digital-products/issues/7))
- Fixed a bug where the "New product" button was not working on the Product index screen ([#6](https://github.com/craftcms/commerce-digital-products/issues/6))

## 2.0.0 - 2018-04-04

### Changed
- This plugin now requires Craft 3.
- Digital products now fires `beforeSaveProductType`, `afterSaveProductType` and `beforeGenerateLicenseKey` events. For all element-related actions, you should look into [Craft 3 changes for Element hooks](https://github.com/craftcms/docs/blob/master/en/updating-plugins.md#element-hooks).
- Instead of `$product->getProductType()` you must now use `$product->getType()`
- Instead of eager-loading a boolean flag `isLicensed` for products, you must now eager-load the `existingLicenses` property, which is an array of existing licenses for that product for the current user.

### Fixed
- Fixed a wrong foreign key constraint ([#1](https://github.com/craftcms/commerce-digital-products/issues/1))

## 1.0.4 - 2017-03-11
- Fixed a bug where digital product prices did not display correctly.
- Fixed a bug where digital product prices would sometimes not be saved correctly.

## 1.0.3 - 2016-11-02
- Added support for a plugin release feed.

## 1.0.2 - 2016-10-12
- Fixed bugs.

## 1.0.1 - 2016-10-03
- Fixed bugs.

## 1.0.0 - 2016-06-21
- Initial release