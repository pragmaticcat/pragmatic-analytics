<?php

namespace pragmatic\analytics\controllers;

use craft\web\Controller;
use yii\web\Response;

class DefaultController extends Controller
{
    protected int|bool|array $allowAnonymous = false;

    public function actionIndex(): Response
    {
        return $this->redirect('pragmatic-analytics/general');
    }

    public function actionGeneral(): Response
    {
        return $this->renderTemplate('pragmatic-analytics/general');
    }

    public function actionOptions(): Response
    {
        return $this->renderTemplate('pragmatic-analytics/options');
    }
}
