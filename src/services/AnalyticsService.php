<?php

namespace pragmatic\analytics\services;

use Craft;
use craft\base\Component;
use pragmatic\analytics\PragmaticAnalytics;
use yii\db\Expression;
use yii\db\IntegrityException;
use yii\web\Cookie;
use yii\web\Request;
use yii\web\Response;

class AnalyticsService extends Component
{
    public const DAILY_STATS_TABLE = '{{%pragmaticanalytics_daily_stats}}';
    public const PAGE_DAILY_STATS_TABLE = '{{%pragmaticanalytics_page_daily_stats}}';
    public const DAILY_UNIQUE_VISITORS_TABLE = '{{%pragmaticanalytics_daily_unique_visitors}}';

    public function trackHit(string $path, Request $request, Response $response): bool
    {
        $settings = PragmaticAnalytics::$plugin->getSettings();
        if (!$settings->enableTracking) {
            return false;
        }

        if ($this->shouldSkipTracking($request)) {
            return false;
        }

        $normalizedPath = $this->normalizePath($path);
        $today = gmdate('Y-m-d');

        $db = Craft::$app->getDb();
        $visitorId = $this->resolveVisitorId($request, $response);
        $visitorHash = hash('sha256', $visitorId);

        $transaction = $db->beginTransaction();
        try {
            $db->createCommand()->upsert(
                self::DAILY_STATS_TABLE,
                ['date' => $today, 'visits' => 1, 'uniqueVisitors' => 0],
                ['visits' => new Expression('[[visits]] + 1')]
            )->execute();

            $db->createCommand()->upsert(
                self::PAGE_DAILY_STATS_TABLE,
                ['date' => $today, 'path' => $normalizedPath, 'visits' => 1],
                ['visits' => new Expression('[[visits]] + 1')]
            )->execute();

            $inserted = $this->registerUniqueVisitor($today, $visitorHash);
            if ($inserted) {
                $db->createCommand()->update(
                    self::DAILY_STATS_TABLE,
                    ['uniqueVisitors' => new Expression('[[uniqueVisitors]] + 1')],
                    ['date' => $today]
                )->execute();
            }

            $transaction->commit();
            return true;
        } catch (\Throwable $exception) {
            $transaction->rollBack();
            Craft::error('Pragmatic Analytics tracking failed: ' . $exception->getMessage(), __METHOD__);
            return false;
        }
    }

    public function getOverview(int $days = 30): array
    {
        $rangeStart = $this->rangeStart($days);
        $row = (new \craft\db\Query())
            ->from(self::DAILY_STATS_TABLE)
            ->where(['>=', 'date', $rangeStart])
            ->select([
                'visits' => new Expression('COALESCE(SUM([[visits]]), 0)'),
                'uniqueVisitors' => new Expression('COALESCE(SUM([[uniqueVisitors]]), 0)'),
            ])
            ->one();

        return [
            'visits' => (int)($row['visits'] ?? 0),
            'uniqueVisitors' => (int)($row['uniqueVisitors'] ?? 0),
        ];
    }

    public function getDailyStats(int $days = 30): array
    {
        $rangeStart = $this->rangeStart($days);
        return (new \craft\db\Query())
            ->from(self::DAILY_STATS_TABLE)
            ->where(['>=', 'date', $rangeStart])
            ->orderBy(['date' => SORT_ASC])
            ->all();
    }

    public function getTopPages(int $days = 30, int $limit = 10): array
    {
        $rangeStart = $this->rangeStart($days);
        return (new \craft\db\Query())
            ->from(self::PAGE_DAILY_STATS_TABLE)
            ->where(['>=', 'date', $rangeStart])
            ->groupBy(['path'])
            ->select([
                'path',
                'visits' => new Expression('SUM([[visits]])'),
            ])
            ->orderBy(['visits' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    private function shouldSkipTracking(Request $request): bool
    {
        $settings = PragmaticAnalytics::$plugin->getSettings();
        $environment = strtolower((string)getenv('CRAFT_ENVIRONMENT'));
        $excludedEnvironments = array_values(array_filter(array_map('trim', explode(',', $settings->excludeEnvironments))));
        $excludedEnvironments = array_map('strtolower', $excludedEnvironments);

        if (!empty($environment) && in_array($environment, $excludedEnvironments, true)) {
            return true;
        }

        if ($settings->excludeLoggedInUsers && !Craft::$app->getUser()->getIsGuest()) {
            return true;
        }

        if ($settings->excludeBots && $this->isLikelyBot($request->getUserAgent() ?? '')) {
            return true;
        }

        return false;
    }

    private function isLikelyBot(string $userAgent): bool
    {
        if ($userAgent === '') {
            return false;
        }

        return (bool)preg_match('/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|preview|headless|pingdom|uptime/i', $userAgent);
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return mb_substr($path, 0, 1024);
    }

    private function resolveVisitorId(Request $request, Response $response): string
    {
        $cookieName = 'pa_vid';
        $existing = $request->getCookies()->getValue($cookieName);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $visitorId = bin2hex(random_bytes(16));
        $response->getCookies()->add(new Cookie([
            'name' => $cookieName,
            'value' => $visitorId,
            'expire' => time() + (400 * 24 * 60 * 60),
            'httpOnly' => true,
            'secure' => $request->getIsSecureConnection(),
            'sameSite' => Cookie::SAME_SITE_LAX,
        ]));

        return $visitorId;
    }

    private function registerUniqueVisitor(string $date, string $visitorHash): bool
    {
        try {
            Craft::$app->getDb()->createCommand()->insert(self::DAILY_UNIQUE_VISITORS_TABLE, [
                'date' => $date,
                'visitorHash' => $visitorHash,
            ])->execute();
            return true;
        } catch (IntegrityException) {
            return false;
        }
    }

    private function rangeStart(int $days): string
    {
        $days = max($days, 1);
        return gmdate('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    }
}
