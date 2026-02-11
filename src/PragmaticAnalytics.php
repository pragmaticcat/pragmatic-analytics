<?php

namespace pragmatic\analytics;

use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

class PragmaticAnalytics extends Plugin
{
    public bool $hasCpSection = true;
    public string $templateRoot = 'src/templates';

    public function init(): void
    {
        parent::init();

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['pragmatic-analytics'] = 'pragmatic-analytics/default/index';
                $event->rules['pragmatic-analytics/general'] = 'pragmatic-analytics/default/general';
                $event->rules['pragmatic-analytics/options'] = 'pragmatic-analytics/default/options';
            }
        );
    }

    public function getCpNavItem(): array
    {
        $item = parent::getCpNavItem();
        $item['label'] = 'Pragmatic';
        $item['subnav'] = [
            'analytics' => [
                'label' => 'Analytics',
                'url' => 'pragmatic-analytics/general',
            ],
        ];

        return $item;
    }
}
