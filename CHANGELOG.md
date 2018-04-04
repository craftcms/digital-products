Changelog
=========

## 2.0.0

### Changed
- This plugin now requires Craft 3.
- Digital products now fires `beforeSaveProductType`, `afterSaveProductType` and `beforeGenerateLicenseKey` events. For all element-related actions, you should look into [Craft 3 changes for Element hooks](https://github.com/craftcms/docs/blob/master/en/updating-plugins.md#element-hooks).
- Instead of `$product->getProductType()` you must now use `$product->getType()`
- Instead of eager-loading a boolean flag `isLicensed` for products, you must now eager-load the `existingLicenses` property, which is an array of existing licenses for that product for the current user.

### Fixed
- Fixed a wrong foreign key constraint ([#1](https://github.com/craftcms/commerce-digital-products/issues/1))

## 1.0.5
- Fixed a bug where digital product prices would sometimes not be saved correctly.

## 1.0.4
- Fixed a bug where digital product prices did not display correctly.

## 1.0.3
- Added support for a plugin release feed.

## 1.0.2
- Fixed bugs.

## 1.0.1
- Fixed bugs.

## 1.0.0
- Initial release