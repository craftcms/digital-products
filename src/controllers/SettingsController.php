<?php

namespace craft\commerce\digitalProducts\controllers;

use Craft;
use craft\commerce\digitalProducts\models\Settings as SettingsModel;
use craft\commerce\digitalProducts\Plugin as DigitalProducts;
use craft\web\Controller as BaseController;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class SettingsController extends BaseController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionSaveSettings(): Response
    {
        $this->requirePostRequest();
        $postData = Craft::$app->getRequest()->getParam('settings');
        $settings = new SettingsModel($postData);

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('commerce-digitalproducts', 'Couldn’t save settings.'));

            return $this->renderTemplate('commerce-digitalproducts/settings', ['settings' => $settings]);
        }

        Craft::$app->getPlugins()->savePluginSettings(DigitalProducts::getInstance(), $settings->toArray());

        Craft::$app->getSession()->setNotice(Craft::t('commerce-digitalproducts', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
