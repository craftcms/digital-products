<?php

namespace craft\digitalproducts\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\ProcessPaymentEvent;
use craft\commerce\models\LineItem;
use craft\digitalproducts\elements\License;
use craft\digitalproducts\elements\Product;
use craft\digitalproducts\Plugin as DigitalProducts;
use craft\events\UserEvent;
use yii\base\Component;
use yii\base\Event;

/**
 * Licenses service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2016, Pixel & Tonic, Inc.
 */
class Licenses extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns true if a given license key is unique.
     *
     * @param string $licenseKey the license key
     *
     * @return bool
     */
    public function isLicenseKeyUnique(string $licenseKey): bool
    {
        return !License::find()
            ->licenseKey($licenseKey)
            ->anyStatus()
            ->exists();
    }

    /**
     * Sort trough the ordered items and generate Licenses for Digital Products.
     *
     * @param Event $event
     *
     * @return void
     */
    public static function handleCompletedOrder(Event $event)
    {
        /** @var Order $order */
        $order = $event->sender;
        $lineItems = $order->getLineItems();

        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem) {
            $itemId = $lineItem->purchasableId;
            $element = Craft::$app->getElements()->getElementById($itemId);
            $quantity = $lineItem->qty;

            if ($element instanceof Product) {
                /** @var Product $element */
                for ($i = 0; $i < $quantity; $i++) {
                    DigitalProducts::getInstance()->getLicenses()->licenseProductByOrder($element, $order);
                }
            }
        }
    }

    /**
     * Prevent paying for orders with digital products in it if user not logged in when required by config.
     *
     * @param ProcessPaymentEvent $event
     *
     * @return void
     */
    public static function maybePreventPayment(ProcessPaymentEvent $event)
    {
        if (!DigitalProducts::getInstance()->getSettings()->requireLoggedInUser || !Craft::$app->getUser()->getIsGuest()) {
            return;
        }

        $order = $event->order;

        if (!$order) {
            return;
        }

        $lineItems = $order->getLineItems();

        foreach ($lineItems as $lineItem) {
            $itemId = $lineItem->purchasableId;
            $element = Craft::$app->getElements()->getElementById($itemId);

            if ($element instanceof Product) {
                $event->isValid = false;

                return;
            }
        }
    }

    /**
     * Assign licenses to a just-activated user, if emails match and config allows it.
     *
     * @param UserEvent $event
     *
     * @return void
     */
    public static function handleUserActivation(UserEvent $event)
    {
        if (!DigitalProducts::getInstance()->getSettings()->autoAssignLicensesOnUserRegistration) {
            return;
        }


        $licenses = License::find()->ownerEmail($event->user->email)->all();

        /** @var License $license */
        foreach ($licenses as $license) {
            // Only licenses with unassigned users
            if (!$license->userId) {
                $license->userId = $event->user->id;
                Craft::$app->getElements()->saveElement($license);
            }
        }
    }

    /**
     * Generate a license for a Product per Order.
     *
     * @param Product $product
     * @param Order $order
     *
     * @return bool
     */
    public function licenseProductByOrder(Product $product, Order $order): bool
    {
        $license = new License();
        $license->productId = $product->id;
        $customer = $order->getCustomer();

        if ($customer && $user = $customer->getUser()) {
            $license->ownerEmail = $user->email;
            $license->ownerName = $user->getName();
            $license->userId = $user->id;
        } else {
            $license->ownerEmail = $order->email;
        }

        $license->orderId = $order->id;

        return Craft::$app->getElements()->saveElement($license);
    }

    /**
     * Generate a license key.
     *
     * @return string
     */
    public function generateLicenseKey(): string
    {
        $codeAlphabet = DigitalProducts::getInstance()->getSettings()->licenseKeyCharacters;
        $keyLength = DigitalProducts::getInstance()->getSettings()->licenseKeyLength;

        $licenseKey = '';

        for ($i = 0; $i < $keyLength; $i++) {
            $licenseKey .= $codeAlphabet[random_int(0, strlen($codeAlphabet) - 1)];
        }

        return $licenseKey;
    }
}
