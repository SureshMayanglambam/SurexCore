<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

use App\Model\EntryModel;
use App\Model\SettingModel;

if (! function_exists('blade')) {
    /**
     * Render a Blade view from View/, e.g. blade('frontend.index') or blade('admin.dashboard', $data).
     */
    function blade(string $view, array $data = []): string
    {
        return service('blade')->run($view, $data);
    }
}

if (! function_exists('setting')) {
    /**
     * Read a site setting stored in the settings table (similar to WordPress get_option()).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return model(SettingModel::class)->get($key, $default);
    }
}

if (! function_exists('current_user')) {
    /**
     * The logged-in admin user, or null.
     */
    function current_user(): ?object
    {
        static $user = false;

        if ($user === false) {
            $id   = session('user_id');
            $user = $id ? model(App\Model\UserModel::class)->findActive((int) $id) : null;
        }

        return $user;
    }
}

if (! function_exists('log_activity')) {
    /**
     * Add a line to the admin activity log, e.g. log_activity('entry.created', 'Created News "Hello"').
     */
    function log_activity(string $action, string $description, ?string $subjectType = null, ?int $subjectId = null, ?object $user = null): void
    {
        service('activity')->log($action, $description, $subjectType, $subjectId, $user);
    }
}

if (! function_exists('entries')) {
    /**
     * Published entries of a content type, newest first — a query on the type's own table.
     * Chain any query builder method; custom fields are real columns:
     *   entries('news')->paginate(10)
     *   entries('store')->where('price <', 1000)->orderBy('price')->findAll(20)
     */
    function entries(string $type): EntryModel
    {
        return EntryModel::for($type)->published()->latestPublished();
    }
}

if (! function_exists('entry')) {
    /**
     * One published entry by slug, or null: entry('news', $slug).
     */
    function entry(string $type, string $slug): ?App\Entities\Entry
    {
        return EntryModel::for($type)->findPublished($slug);
    }
}

if (! function_exists('content_type_exists')) {
    /**
     * Whether a content type with this slug has been created in the admin panel.
     */
    function content_type_exists(string $slug): bool
    {
        return model(App\Model\ContentTypeModel::class)->findBySlug($slug) !== null;
    }
}

if (! function_exists('pagination')) {
    /**
     * Page links of the last paginate() call, in a view: {!! pagination() !!}
     */
    function pagination(string $group = 'default'): string
    {
        return service('pager')->links($group);
    }
}

if (! function_exists('format_date')) {
    /**
     * Format a date with the site's date format (Settings → General).
     */
    function format_date(?string $datetime, ?string $format = null): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }

        return date($format ?? (string) setting('date_format', 'Y.m.d'), strtotime($datetime));
    }
}

if (! function_exists('media_url')) {
    /**
     * Full URL of an uploaded image/file field value ("uploads/2026/09/abc.jpg"), or '' when empty.
     */
    function media_url(?string $path): string
    {
        return $path ? base_url($path) : '';
    }
}

// ---------------------------------------------------------------------------
// Laravel-style helpers, so Blade templates copied from Laravel projects work.
// (config() and request() are CodeIgniter's own and differ from Laravel:
//  use $site->name / setting('site_name') and url_is('/') instead.)
// ---------------------------------------------------------------------------

if (! function_exists('asset')) {
    /**
     * URL of a file in public/, like Laravel: asset('assets/frontend/css/style.css').
     */
    function asset(string $path = ''): string
    {
        return base_url(ltrim($path, '/'));
    }
}

if (! function_exists('url')) {
    /**
     * url('/about') → full URL; url()->current(), url()->previous() like Laravel.
     */
    function url(?string $path = null): string|object
    {
        if ($path !== null) {
            return site_url($path);
        }

        return new class () {
            public function current(): string
            {
                return current_url();
            }

            public function previous(): string
            {
                return previous_url();
            }

            public function to(string $path): string
            {
                return site_url($path);
            }
        };
    }
}

if (! function_exists('send_mail')) {
    /**
     * Send an HTML email with the SMTP settings from .env (email.*).
     *   send_mail('user@example.com', 'Subject', '<p>Hello</p>');
     *   send_mail(config('Cms')->adminEmail, 'New contact', $html, ['replyTo' => $visitorEmail]);
     *   send_mail($to, 'Subject', "Plain text body", ['text' => true]);
     * Options: text, from, fromName, replyTo, cc, bcc (default sender: email.fromEmail / email.fromName in .env).
     * For mails written as Blade files, see send_mail_template().
     * Returns false on failure (details in writable/logs).
     */
    function send_mail(string|array $to, string $subject, string $html, array $options = []): bool
    {
        $config = config('Email');
        $cms    = config('Cms');
        $email  = service('email', null, false);

        // Plain text: no HTML, and no automatic line wrapping (it can break Japanese lines).
        if (! empty($options['text'])) {
            $email->initialize(['mailType' => 'text', 'wordWrap' => false]);
        }

        $email->setFrom(
            $options['from'] ?? ($config->fromEmail ?: $cms->adminEmail),
            $options['fromName'] ?? ($config->fromName ?: $cms->appName),
        );
        $email->setTo($to);
        if (! empty($options['replyTo'])) {
            $email->setReplyTo($options['replyTo']);
        }
        if (! empty($options['cc'])) {
            $email->setCC($options['cc']);
        }
        if (! empty($options['bcc'])) {
            $email->setBCC($options['bcc']);
        }
        $email->setSubject($subject);
        $email->setMessage($html);
        // Files: 'attachments' => [[$path, $fileNameInTheMail], ...]
        foreach ($options['attachments'] ?? [] as [$path, $name]) {
            $email->attach($path, 'attachment', $name);
        }

        if (! $email->send(false)) {
            log_message('error', 'Email to {to} failed: {debug}', [
                'to'    => is_array($to) ? implode(', ', $to) : $to,
                'debug' => strip_tags($email->printDebugger(['headers'])),
            ]);

            return false;
        }

        return true;
    }
}

if (! function_exists('send_mail_template')) {
    /**
     * Send a plain-text email written as a Blade file that starts with its headers:
     *
     *   From: xxxx株式会社 <{{ $adminEmail }}>
     *   Subject: 【{{ $siteName }}】お問い合わせありがとうございます
     *
     *   {{ $data['name'] }} 様
     *   ...
     *
     * Headers (one per line, until the first empty line): Subject (required), From, Reply-To, Cc, Bcc.
     * Variables in every template: $fromEmail, $fromName (.env email.*), $adminEmail (.env cms.adminEmail), $siteName.
     * "To" is given in code: send_mail_template($to, 'frontend.contact.mail.reply', $data).
     * {{ }} is fine in these files: HTML escaping is undone because the mail is plain text.
     * Returns false on failure (details in writable/logs).
     */
    function send_mail_template(string|array $to, string $view, array $data = [], array $options = []): bool
    {
        try {
            [$subject, $body, $options] = parse_mail_template($view, $data, $options);
        } catch (RuntimeException $e) {
            // A mistake in the template: show it while developing, log it on a live site.
            if (ENVIRONMENT !== 'production') {
                throw $e;
            }
            log_message('critical', $e->getMessage());

            return false;
        }

        return send_mail($to, $subject, $body, ['text' => true] + $options);
    }
}

if (! function_exists('parse_mail_template')) {
    /**
     * Render a mail template and split it into [subject, body, send_mail() options].
     *
     * @throws RuntimeException when a header line is invalid
     */
    function parse_mail_template(string $view, array $data = [], array $options = []): array
    {
        // Available in every mail template (a controller can override them).
        $data += [
            'fromEmail'  => config('Email')->fromEmail,   // .env email.fromEmail
            'fromName'   => config('Email')->fromName,    // .env email.fromName
            'adminEmail' => config('Cms')->adminEmail,    // .env cms.adminEmail
            'siteName'   => setting('site_name', config('Cms')->appName),
        ];

        $text = html_entity_decode(blade($view, $data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", ltrim($text));

        [$head, $body] = array_pad(preg_split("/\n[ \t]*\n/", $text, 2), 2, '');

        $headers = [];
        foreach (explode("\n", $head) as $line) {
            if (! preg_match('/^(Subject|From|Reply-To|Cc|Bcc)[ \t]*:[ \t]*(.*)$/i', trim($line), $m)) {
                throw new RuntimeException("Mail template {$view}: \"{$line}\" is not a supported header line (Subject, From, Reply-To, Cc, Bcc), or the empty line after the headers is missing.");
            }
            $headers[strtolower($m[1])] = trim($m[2]);
        }

        if (($headers['subject'] ?? '') === '') {
            throw new RuntimeException("Mail template {$view} must start with a \"Subject: ...\" line.");
        }

        if (($headers['from'] ?? '') !== '') {
            // "Name <address>" or just "address"
            preg_match('/^(?:(.*?)\s*<([^<>]+)>|([^<>\s]+))$/u', $headers['from'], $from);
            $fromEmail = trim(($from[2] ?? '') ?: ($from[3] ?? ''));

            if (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException("Mail template {$view}: the From address \"{$headers['from']}\" is not valid.");
            }
            $options['from'] = $fromEmail;
            if (trim($from[1] ?? '') !== '') {
                $options['fromName'] = trim($from[1], " \t\"");
            }
        }
        // Reply-To / Cc / Bcc: empty = not used. Several addresses: "a@example.com, b@example.com".
        foreach (['reply-to' => 'replyTo', 'cc' => 'cc', 'bcc' => 'bcc'] as $header => $option) {
            if (($headers[$header] ?? '') === '') {
                continue;
            }
            foreach (array_map('trim', explode(',', $headers[$header])) as $address) {
                if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException("Mail template {$view}: \"{$address}\" in the {$header} line is not a valid email address.");
                }
            }
            $options[$option] = $headers[$header];
        }

        $body = preg_replace("/[ \t]+\n/", "\n", $body);   // trailing spaces left by Blade directives
        $body = preg_replace("/\n{3,}/", "\n\n", $body);   // at most one empty line in a row

        return [$headers['subject'], trim($body, "\n") . "\n", $options];
    }
}

// ---------------------------------------------------------------------------
// Japanese date helpers (the admin panel is Japanese)
// ---------------------------------------------------------------------------

if (! function_exists('time_ago')) {
    /**
     * Relative time in Japanese: たった今 / 5分前 / 3時間前 / 2日前, older dates as 2026/09/28.
     */
    function time_ago(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }

        $seconds = time() - strtotime($datetime);

        return match (true) {
            $seconds < 0       => date('Y/m/d H:i', strtotime($datetime)),
            $seconds < 60      => 'たった今',
            $seconds < 3600    => intdiv($seconds, 60) . '分前',
            $seconds < 86400   => intdiv($seconds, 3600) . '時間前',
            $seconds < 86400 * 7 => intdiv($seconds, 86400) . '日前',
            default            => date('Y/m/d', strtotime($datetime)),
        };
    }
}

if (! function_exists('ja_date')) {
    /**
     * Japanese date: 2026年9月28日（月）
     */
    function ja_date(?string $datetime = null, bool $weekday = true): string
    {
        $ts   = $datetime ? strtotime($datetime) : time();
        $week = ['日', '月', '火', '水', '木', '金', '土'][(int) date('w', $ts)];

        return date('Y年n月j日', $ts) . ($weekday ? "（{$week}）" : '');
    }
}

if (! function_exists('admin_path')) {
    /**
     * Admin URL path from .env cms.adminPath: admin_path() → "wd-manage", admin_path('settings*') → "wd-manage/settings*".
     * For links use route names instead: url_to('admin.dashboard').
     */
    function admin_path(string $path = ''): string
    {
        $base = config('Cms')->adminPath;

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}
