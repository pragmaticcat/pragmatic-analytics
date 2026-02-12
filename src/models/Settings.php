<?php

namespace pragmatic\analytics\models;

use craft\base\Model;

class Settings extends Model
{
    public bool $enableTracking = true;
    public bool $requireConsent = false;
    public bool $excludeLoggedInUsers = true;
    public bool $excludeBots = true;
    public string $excludeEnvironments = 'dev,development,local,staging';

    public bool $injectGaScript = false;
    public string $gaMeasurementId = '';

    public function rules(): array
    {
        return [
            [['enableTracking', 'requireConsent', 'excludeLoggedInUsers', 'excludeBots', 'injectGaScript'], 'boolean'],
            [['excludeEnvironments', 'gaMeasurementId'], 'string'],
            ['gaMeasurementId', 'trim'],
            ['gaMeasurementId', 'match', 'pattern' => '/^$|^G-[A-Z0-9]+$/i'],
        ];
    }
}
