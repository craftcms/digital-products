<?php

namespace craft\digitalproducts\controllers;

use Craft;
use craft\digitalproducts\models\Settings as SettingsModel;
use craft\digitalproducts\Plugin as DigitalProducts;
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
            Craft::$app->getSession()->setError(Craft::t('digital-products', 'Couldn’t save settings.'));

            return $this->renderTemplate('digital-products/settings', ['settings' => $settings]);
        }

        Craft::$app->getPlugins()->savePluginSettings(DigitalProducts::getInstance(), $settings->toArray());

        Craft::$app->getSession()->setNotice(Craft::t('digital-products', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }
}
