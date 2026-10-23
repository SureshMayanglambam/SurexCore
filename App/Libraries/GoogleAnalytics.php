<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Google Analytics 4 numbers for the admin dashboard, read with the GA4 Data API.
 *
 * Signs in as a Google Cloud *service account* (JSON key uploaded in Settings → Analytics): a JWT
 * signed with openssl is exchanged for an access token — plain HTTPS, no Google library, so it runs
 * on shared hosting. Reports are cached for an hour; "active now" for two minutes.
 *
 * The key file lives in writable/analytics/ (never served to the web, not in backups or deploy
 * packages); the property ID is the setting "ga4_property_id".
 */
class GoogleAnalytics
{
    public const KEY_FILE = WRITEPATH . 'analytics' . DIRECTORY_SEPARATOR . 'service-account.json';

    private const SCOPE     = 'https://www.googleapis.com/auth/analytics.readonly';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API       = 'https://analyticsdata.googleapis.com/v1beta/properties/';

    /** Ranges offered on the dashboard (days). */
    public const RANGES = [7, 28, 90];

    public function isConfigured(): bool
    {
        return $this->isSample() || ($this->propertyId() !== '' && is_file(self::KEY_FILE));
    }

    /**
     * "Show sample data" (Settings → Analytics): generated numbers to check the dashboard without Google.
     */
    public function isSample(): bool
    {
        return setting('ga4_sample', '0') === '1';
    }

    public function propertyId(): string
    {
        return (string) setting('ga4_property_id', '');
    }

    /**
     * The service account e-mail (to add as a Viewer in GA4), or null when no key is uploaded.
     */
    public function clientEmail(): ?string
    {
        return $this->key()['client_email'] ?? null;
    }

    /**
     * Check an uploaded key file. Returns the decoded key, or throws with a readable reason.
     */
    public static function validateKey(string $json): array
    {
        $key = json_decode($json, true);

        if (! is_array($key) || ($key['type'] ?? '') !== 'service_account' || empty($key['client_email']) || empty($key['private_key'])) {
            throw new RuntimeException('not_service_account');
        }
        if (openssl_pkey_get_private($key['private_key']) === false) {
            throw new RuntimeException('bad_private_key');
        }

        return $key;
    }

    /**
     * Dashboard report for the last $days days (today included), compared with the period before.
     */
    public function report(int $days, bool $fresh = false): array
    {
        $days = in_array($days, self::RANGES, true) ? $days : 28;
        if ($this->isSample()) {
            return $this->sampleReport($days);
        }
        $cacheKey = 'ga4_report_' . md5($this->propertyId() . '|' . $days);

        if (! $fresh && is_array($cached = cache($cacheKey))) {
            return $cached;
        }

        $current  = ['startDate' => ($days - 1) . 'daysAgo', 'endDate' => 'today'];
        $previous = ['startDate' => (2 * $days - 1) . 'daysAgo', 'endDate' => $days . 'daysAgo'];
        $totals   = ['activeUsers', 'sessions', 'screenPageViews', 'averageSessionDuration', 'engagementRate'];

        $reports = $this->call('batchRunReports', ['requests' => [
            ['dateRanges' => [$current + ['name' => 'current'], $previous + ['name' => 'previous']], 'metrics' => $this->metrics($totals)],
            ['dateRanges' => [$current], 'dimensions' => [['name' => 'date']], 'metrics' => $this->metrics(['activeUsers', 'screenPageViews']),
                'orderBys' => [['dimension' => ['dimensionName' => 'date']]]],
            ['dateRanges' => [$current], 'dimensions' => [['name' => 'deviceCategory']], 'metrics' => $this->metrics(['activeUsers']),
                'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]]],
            ['dateRanges' => [$current], 'dimensions' => [['name' => 'pagePath'], ['name' => 'pageTitle']], 'metrics' => $this->metrics(['screenPageViews']),
                'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]], 'limit' => 6],
            ['dateRanges' => [$current], 'dimensions' => [['name' => 'sessionDefaultChannelGroup']], 'metrics' => $this->metrics(['sessions']),
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 6],
        ]])['reports'] ?? [];

        $byRange = [];
        foreach ($reports[0]['rows'] ?? [] as $row) {
            $byRange[$row['dimensionValues'][0]['value'] ?? 'current'] = array_combine($totals, array_map(fn ($m) => (float) $m['value'], $row['metricValues']));
        }
        $empty = array_fill_keys($totals, 0.0);

        // Every day of the range, also days without visits
        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $daily[date('Ymd', strtotime("-{$i} days"))] = ['date' => date('Y-m-d', strtotime("-{$i} days")), 'users' => 0, 'views' => 0];
        }
        foreach ($reports[1]['rows'] ?? [] as $row) {
            $date = $row['dimensionValues'][0]['value'];
            if (isset($daily[$date])) {
                $daily[$date]['users'] = (int) $row['metricValues'][0]['value'];
                $daily[$date]['views'] = (int) $row['metricValues'][1]['value'];
            }
        }

        $rows = static fn (array $report, callable $map) => array_map($map, $report['rows'] ?? []);

        $data = [
            'days'     => $days,
            'current'  => $byRange['current'] ?? $empty,
            'previous' => $byRange['previous'] ?? $empty,
            'daily'    => array_values($daily),
            'devices'  => $rows($reports[2] ?? [], fn ($r) => ['name' => $r['dimensionValues'][0]['value'], 'users' => (int) $r['metricValues'][0]['value']]),
            'pages'    => $rows($reports[3] ?? [], fn ($r) => ['path' => $r['dimensionValues'][0]['value'], 'title' => $r['dimensionValues'][1]['value'], 'views' => (int) $r['metricValues'][0]['value']]),
            'sources'  => $rows($reports[4] ?? [], fn ($r) => ['name' => $r['dimensionValues'][0]['value'], 'sessions' => (int) $r['metricValues'][0]['value']]),
            'fetched'  => date(DATE_ATOM),
        ];

        cache()->save($cacheKey, $data, HOUR);

        return $data;
    }

    /**
     * Visitors in the last 30 minutes (Realtime API), cached for 2 minutes.
     */
    public function activeNow(): int
    {
        if ($this->isSample()) {
            return 3 + (int) (time() / 120) % 9;   // changes every 2 minutes, like the real one
        }
        $cacheKey = 'ga4_realtime_' . md5($this->propertyId());
        $cached   = cache($cacheKey);
        if (is_int($cached)) {
            return $cached;
        }

        $result = $this->call('runRealtimeReport', ['metrics' => $this->metrics(['activeUsers'])]);
        $count  = (int) ($result['rows'][0]['metricValues'][0]['value'] ?? 0);
        cache()->save($cacheKey, $count, 120);

        return $count;
    }

    /**
     * Realistic generated report: weekday/weekend rhythm and gentle growth. Same numbers all day
     * (seeded by the date), different for each range.
     */
    private function sampleReport(int $days): array
    {
        mt_srand(crc32(date('Y-m-d') . '|' . $days));

        $daily = [];
        $users = $views = 0;
        for ($i = $days - 1; $i >= 0; $i--) {
            $t  = strtotime("-{$i} days");
            $u  = (int) round((date('N', $t) >= 6 ? 95 : 160) + 35 * sin($i / 4) + mt_rand(-18, 18) + ($days - $i) * (60 / $days));
            $v  = (int) round($u * (2.0 + mt_rand(0, 50) / 100));
            $daily[] = ['date' => date('Y-m-d', $t), 'users' => $u, 'views' => $v];
            $users += $u;
            $views += $v;
        }

        $unique  = $users * .78;
        $current = ['activeUsers' => $unique, 'sessions' => $users * 1.2, 'screenPageViews' => $views, 'averageSessionDuration' => 70 + mt_rand(0, 60), 'engagementRate' => .55 + mt_rand(0, 15) / 100];
        $share   = static fn (float $total, array $parts) => array_map(static fn ($p) => (int) round($total * $p), $parts);

        [$mobile, $desktop, $tablet] = $share($unique, [.63, .33, .04]);
        $pages = $share($views, [.31, .15, .09, .08, .05, .04]);
        $src   = $share($users * 1.2, [.48, .26, .12, .07, .04, .01]);

        return [
            'days'     => $days,
            'sample'   => true,
            'current'  => $current,
            // Each figure changes by its own amount against the previous period (−8 % … +18 %)
            'previous' => array_map(static fn ($v) => $v / (1 + mt_rand(-8, 18) / 100), $current),
            'daily'    => $daily,
            'devices'  => [['name' => 'mobile', 'users' => $mobile], ['name' => 'desktop', 'users' => $desktop], ['name' => 'tablet', 'users' => $tablet]],
            'pages'    => [
                ['path' => '/', 'title' => 'Home', 'views' => $pages[0]],
                ['path' => '/news', 'title' => 'News', 'views' => $pages[1]],
                ['path' => '/about', 'title' => 'About us', 'views' => $pages[2]],
                ['path' => '/services', 'title' => 'Services', 'views' => $pages[3]],
                ['path' => '/contact', 'title' => 'Contact', 'views' => $pages[4]],
                ['path' => '/news/sample-post', 'title' => 'Sample news post', 'views' => $pages[5]],
            ],
            'sources'  => array_map(static fn ($name, $n) => ['name' => $name, 'sessions' => $n],
                ['Organic Search', 'Direct', 'Organic Social', 'Referral', 'Email', 'Unassigned'], $src),
            'fetched'  => date(DATE_ATOM),
        ];
    }

    public function clearCache(): void
    {
        cache()->deleteMatching('ga4_*');
    }

    // ------------------------------------------------------------------ HTTP

    private function metrics(array $names): array
    {
        return array_map(static fn ($n) => ['name' => $n], $names);
    }

    private function call(string $method, array $body): array
    {
        $property = preg_replace('/\D/', '', $this->propertyId());
        if ($property === '' || ! is_file(self::KEY_FILE)) {
            throw new RuntimeException('not_configured');
        }

        return $this->post(self::API . $property . ':' . $method, json_encode($body), [
            'Authorization: Bearer ' . $this->token(),
            'Content-Type: application/json',
        ]);
    }

    /**
     * OAuth access token for the service account (JWT bearer grant), cached until shortly before it expires.
     */
    private function token(): string
    {
        $key      = $this->key();
        $cacheKey = 'ga4_token_' . md5((string) ($key['client_email'] ?? ''));
        if (is_string($token = cache($cacheKey)) && $token !== '') {
            return $token;
        }

        $now     = time();
        $encode  = static fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $header  = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims  = $encode(json_encode([
            'iss' => $key['client_email'], 'scope' => self::SCOPE, 'aud' => self::TOKEN_URL,
            'iat' => $now, 'exp' => $now + 3600,
        ]));
        if (! openssl_sign($header . '.' . $claims, $signature, (string) $key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('bad_private_key');
        }

        $result = $this->post(self::TOKEN_URL, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $header . '.' . $claims . '.' . $encode($signature),
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        $token = (string) ($result['access_token'] ?? '');
        cache()->save($cacheKey, $token, max(60, (int) ($result['expires_in'] ?? 3600) - 120));

        return $token;
    }

    private function key(): array
    {
        static $key = null;

        return $key ??= (is_file(self::KEY_FILE) ? (json_decode((string) file_get_contents(self::KEY_FILE), true) ?: []) : []);
    }

    /**
     * POST and decode JSON; Google errors become RuntimeException with a short code
     * (no_access, api_disabled, bad_key, property_not_found, google_error: …, network).
     */
    private function post(string $url, string $body, array $headers): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $response = curl_exec($curl);
        $status   = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $network  = curl_error($curl);

        if ($response === false) {
            throw new RuntimeException('network: ' . $network);
        }

        $json = json_decode((string) $response, true) ?: [];
        if ($status >= 200 && $status < 300) {
            return $json;
        }

        // API errors: {"error": {"message", "status"}}; token errors: {"error": "invalid_grant", "error_description"}
        $error   = $json['error'] ?? null;
        $message = is_array($error) ? (string) ($error['message'] ?? '') : (string) ($json['error_description'] ?? $error ?? '');
        $message = $message !== '' ? $message : 'HTTP ' . $status;
        $status_ = is_array($error) ? (string) ($error['status'] ?? '') : '';

        throw new RuntimeException(match (true) {
            ($json['error'] ?? null) === 'invalid_grant' || str_contains($message, 'invalid_grant') => 'bad_key',
            str_contains($message, 'has not been used') || str_contains($message, 'is disabled') || $status_ === 'SERVICE_DISABLED' => 'api_disabled',
            $status_ === 'PERMISSION_DENIED' || $status === 403 => 'no_access',
            $status_ === 'NOT_FOUND' || $status === 404 || str_contains($message, 'property') && $status === 400 => 'property_not_found',
            default => 'google_error: ' . mb_substr($message, 0, 200),
        });
    }
}
