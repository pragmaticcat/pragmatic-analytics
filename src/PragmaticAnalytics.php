<?php

namespace pragmatic\analytics;

use craft\base\Plugin;
use craft\base\Model;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use craft\web\View;
use craft\web\twig\variables\Cp;
use pragmatic\analytics\models\Settings;
use pragmatic\analytics\services\AnalyticsService;
use yii\base\Event;
use yii\helpers\Json;

class PragmaticAnalytics extends Plugin
{
    public static PragmaticAnalytics $plugin;

    public bool $hasCpSection = true;
    public string $schemaVersion = '1.0.0';
    public string $templateRoot = 'src/templates';

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'analytics' => AnalyticsService::class,
        ]);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['pragmatic-analytics'] = 'pragmatic-analytics/default/index';
                $event->rules['pragmatic-analytics/general'] = 'pragmatic-analytics/default/general';
                $event->rules['pragmatic-analytics/options'] = 'pragmatic-analytics/default/options';
                $event->rules['pragmatic-analytics/save-settings'] = 'pragmatic-analytics/default/save-settings';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['pragmatic-analytics/track'] = 'pragmatic-analytics/default/track';
            }
        );

        // Register nav item under shared "Pragmatic" group
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                $groupKey = null;
                foreach ($event->navItems as $key => $item) {
                    if (($item['label'] ?? '') === 'Pragmatic' && isset($item['subnav'])) {
                        $groupKey = $key;
                        break;
                    }
                }

                if ($groupKey === null) {
                    $newItem = [
                        'label' => 'Pragmatic',
                        'url' => 'pragmatic-analytics/general',
                        'icon' => __DIR__ . '/icons/icon.svg',
                        'subnav' => [],
                    ];

                    // Insert after the first matching nav item
                    $afterKey = null;
                    $insertAfter = ['users', 'assets', 'categories', 'entries'];
                    foreach ($insertAfter as $target) {
                        foreach ($event->navItems as $key => $item) {
                            if (($item['url'] ?? '') === $target) {
                                $afterKey = $key;
                                break 2;
                            }
                        }
                    }

                    if ($afterKey !== null) {
                        $pos = array_search($afterKey, array_keys($event->navItems)) + 1;
                        $event->navItems = array_merge(
                            array_slice($event->navItems, 0, $pos, true),
                            ['pragmatic' => $newItem],
                            array_slice($event->navItems, $pos, null, true),
                        );
                        $groupKey = 'pragmatic';
                    } else {
                        $event->navItems['pragmatic'] = $newItem;
                        $groupKey = 'pragmatic';
                    }
                }

                $event->navItems[$groupKey]['subnav']['analytics'] = [
                    'label' => 'Analytics',
                    'url' => 'pragmatic-analytics/general',
                ];
            }
        );

        Event::on(
            View::class,
            View::EVENT_END_BODY,
            function () {
                $request = \Craft::$app->getRequest();
                if (!$request->getIsSiteRequest() || !$request->getAcceptsHtml()) {
                    return;
                }

                $view = \Craft::$app->getView();
                $settings = $this->getSettings();

                if ($settings->enableTracking) {
                    $trackUrl = UrlHelper::siteUrl('pragmatic-analytics/track');
                    $requireConsent = $settings->requireConsent ? 'true' : 'false';
                    $trackScript = <<<JS
(() => {
  if ($requireConsent) {
    const hasConsent = window.PragmaticAnalyticsConsent === true || document.cookie.includes('pa_consent=1');
    if (!hasConsent) {
      return;
    }
  }

  const path = window.location.pathname + window.location.search;
  const url = '$trackUrl?p=' + encodeURIComponent(path);
  fetch(url, {
    method: 'GET',
    credentials: 'same-origin',
    keepalive: true,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  }).catch(() => {});
})();
JS;
                    $view->registerJs($trackScript, View::POS_END);
                }

                if ($settings->injectGaScript && !empty($settings->gaMeasurementId)) {
                    $measurementId = Json::htmlEncode($settings->gaMeasurementId);
                    $gaScript = <<<JS
(() => {
  const measurementId = $measurementId;
  if (!measurementId) {
    return;
  }

  if (!window.dataLayer) {
    window.dataLayer = [];
  }

  const gtag = function(){window.dataLayer.push(arguments);};
  window.gtag = window.gtag || gtag;
  gtag('js', new Date());
  gtag('config', measurementId);
})();
JS;
                    $view->registerJsFile('https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($settings->gaMeasurementId), ['async' => true]);
                    $view->registerJs($gaScript, View::POS_END);
                }
            }
        );
    }

    public function getCpNavItem(): ?array
    {
        return null;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
}
