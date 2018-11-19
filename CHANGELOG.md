Changelog
=========

## 2.0.3 - 2018-10-19

### Added
- Added `craft\digitalproducts\models\ProductTypeSite::getSite()`.

### Changed
- Licenses are now generated after the order is paid, instead of when it’s completed. ([#21](https://github.com/craftcms/commerce-digital-products/issues/21))

### Fixed
- Fixed a bug where Digital Products fields were named “Products”. ([#13](https://github.com/craftcms/commerce-digital-products/issues/13))
- Fixed a bug where querying for products didn’t always return the correct results.
- Fixed a bug where only fields in the first field layout tab were visible when editing a product. ([#8](https://github.com/craftcms/commerce-digital-products/issues/8))
- Fixed a bug where products coludn’t hvae URLs. ([#9](https://github.com/craftcms/commerce-digital-products/issues/9))
- Fixed a bug where querying for licenses would not work in some cases. ([#11](https://github.com/craftcms/commerce-digital-products/issues/11))
- Fixed a bug where it wasn’t possible to delete a product. ([#12](https://github.com/craftcms/commerce-digital-products/issues/12))
- Fixed a bug where product template paths and URI formats weren’t being automatically generated correctly. ([#16](https://github.com/craftcms/commerce-digital-products/issues/16))
- Fixed deprecation errors. ([#19](https://github.com/craftcms/commerce-digital-products/issues/19))

## 2.0.2 - 2018-05-14

### Fixed
- Fixed a bug where licenses were not being generated after completing an order.

## 2.0.1 - 2018-05-09

### Fixed
- Fixed a bug where tax categories were not being populated when editing a product. ([#3](https://github.com/craftcms/commerce-digital-products/issues/3))
- Fixed broken links in `composer.json`. ([#4](https://github.com/craftcms/commerce-digital-products/issues/4))
- Fixed an error that occurred on the Licenses index page. ([#7](https://github.com/craftcms/commerce-digital-products/issues/7))
- Fixed a bug where the “New product” button wasn’t working on the Products index page. ([#6](https://github.com/craftcms/commerce-digital-products/issues/6))

## 2.0.0 - 2018-04-04

### Added
- Added Craft 3 compatibility.

### Changed
- Digital products now fires `beforeSaveProductType`, `afterSaveProductType` and `beforeGenerateLicenseKey` events.
- Product types are now accessible via `$product->getProductType()` instead of `$product->getType()`.
- Instead of eager-loading a boolean flag `isLicensed` for products, you must now eager-load the `existingLicenses` property, which is an array of existing licenses for that product for the current user.

### Fixed
- Fixed a broken foreign key constraint. ([#1](https://github.com/craftcms/commerce-digital-products/issues/1))

## 1.0.4 - 2017-03-11

### Fixed
- Fixed a bug where digital product prices weren’t displaying correctly.
- Fixed a bug where digital product prices could be saved incorrectly.

## 1.0.3 - 2016-11-02

### Changed
- Added support for a plugin release feed.

## 1.0.2 - 2016-10-12

### Fixed
- Fixed bugs.

## 1.0.1 - 2016-10-03

### Fixed
- Fixed bugs.

## 1.0.0 - 2016-06-21

- Initial release
