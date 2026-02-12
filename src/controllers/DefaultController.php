<?php

namespace pragmatic\analytics\controllers;

use Craft;
use craft\web\Controller;
use pragmatic\analytics\PragmaticAnalytics;
use yii\web\Response;

class DefaultController extends Controller
{
    protected int|bool|array $allowAnonymous = ['track'];

    public function actionIndex(): Response
    {
        return $this->redirect('pragmatic-analytics/general');
    }

    public function actionGeneral(): Response
    {
        $this->requireCpRequest();

        $analytics = PragmaticAnalytics::$plugin->get('analytics');
        return $this->renderTemplate('pragmatic-analytics/general', [
            'overview' => $analytics->getOverview(30),
            'dailyStats' => $analytics->getDailyStats(30),
            'topPages' => $analytics->getTopPages(30, 10),
        ]);
    }

    public function actionOptions(): Response
    {
        $this->requireCpRequest();

        return $this->renderTemplate('pragmatic-analytics/options', [
            'settings' => PragmaticAnalytics::$plugin->getSettings(),
        ]);
    }

    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
        $this->requireAdmin();

        $rawSettings = (array)$this->request->getBodyParam('settings', []);
        $plugin = PragmaticAnalytics::$plugin;
        $settings = $plugin->getSettings();
        $settings->setAttributes($rawSettings, false);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            Craft::$app->getSession()->setError('No se pudieron guardar los ajustes.');
            Craft::$app->getUrlManager()->setRouteParams(['settings' => $settings]);
            return null;
        }

        Craft::$app->getSession()->setNotice('Ajustes guardados.');
        return $this->redirectToPostedUrl();
    }

    public function actionTrack(): Response
    {
        $path = (string)$this->request->getQueryParam('p', '/');
        PragmaticAnalytics::$plugin->get('analytics')->trackHit($path, $this->request, $this->response);
        return $this->asRaw('');
    }
}
