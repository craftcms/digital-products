Changelog
=========

## Unreleased

### Changed
- Digital products now fires `beforeSaveProductType`, `afterSaveProductType` and `beforeGenerateLicenseKey` events. For all element-related actions, you should look into [Craft 3 changes for Element hooks](https://github.com/craftcms/docs/blob/master/en/updating-plugins.md#element-hooks).
- Instead of `$product->getProductType()` you must now use `$product->getType()`
- Instead of eager-loading a boolean flag `isLicensed` for products, you must now eager-load the `existingLicenses` property, which is an array of existing licenses for that product for the current user.

