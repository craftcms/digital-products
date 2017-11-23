<?php

namespace craft\commerce\digitalProducts\models;

use craft\commerce\base\Model;

/**
 * Settings model.
 *
 * @property bool   $autoAssignUserOnPurchase
 * @property bool   $autoAssignLicensesOnUserRegistration
 * @property string $licenseKeyCharacters
 * @property int    $licenseKeyLength
 * @property bool   $requireLoggedInUser
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Settings extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var bool Whether license should automatically be assigned to existing users if the emails match.
     */
    public $autoAssignUserOnPurchase = false;

    /**
     * @var bool Whether licenses should be automatically assigned to newly-registered users if the emails match.
     */
    public $autoAssignLicensesOnUserRegistration = true;

    /**
     * @var string The available characters that can be used in license key generation.
     */
    public $licenseKeyCharacters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * @var int The length of generated license keys.
     */
    public $licenseKeyLength = 24;

    /**
     * @var bool Whether a user must be logged in when completing an order with at least one digital product in the cart.
     */
    public $requireLoggedInUser = true;

}
