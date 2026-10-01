<?php

namespace App\Admin;

use App\Model\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Settings → General (Admin only).
 */
class Settings extends AdminController
{
    /**
     * Date formats offered for format_date().
     */
    public const DATE_FORMATS = ['Y.m.d', 'Y-m-d', 'Y/m/d', 'Y年n月j日', 'M j, Y', 'j F Y'];

    private const DEFAULTS = [
        'site_name'               => 'SurexCore',
        'site_tagline'            => '',
        'date_format'             => 'Y.m.d',
        'posts_per_page'          => 10,
        'activity_retention_days' => 90,
        'maintenance_mode'        => '0',
        'search_noindex'          => '0',
    ];

    public function index(): string
    {
        $settings = model(SettingModel::class);
        $values   = [];

        foreach (self::DEFAULTS as $name => $default) {
            $values[$name] = $settings->get($name, $default);
        }

        $mail = config('Email');

        return $this->render('admin.settings.index', [
            'values'      => $values,
            'dateFormats' => self::DATE_FORMATS,
            // Read-only summary of the SMTP settings in .env
            'mail'        => [
                'host'       => $mail->SMTPHost,
                'port'       => $mail->SMTPPort,
                'encryption' => $mail->SMTPCrypto ?: 'なし',
                'from'       => trim(($mail->fromName ?: config('Cms')->appName) . ' <' . ($mail->fromEmail ?: config('Cms')->adminEmail) . '>'),
                'configured' => $mail->protocol !== 'smtp' || $mail->SMTPHost !== '',
            ],
        ]);
    }

    public function update(): RedirectResponse
    {
        $rules = [
            'site_name'               => ['label' => 'サイト名', 'rules' => 'required|max_length[100]'],
            'site_tagline'            => ['label' => 'キャッチフレーズ', 'rules' => 'permit_empty|max_length[255]'],
            'date_format'             => ['label' => '日付の形式', 'rules' => 'required|max_length[20]'],
            'posts_per_page'          => ['label' => '1ページの表示件数', 'rules' => 'required|is_natural_no_zero|less_than_equal_to[100]'],
            'activity_retention_days' => ['label' => '操作ログの保存期間', 'rules' => 'required|is_natural_no_zero|less_than_equal_to[3650]'],
            'maintenance_mode'        => ['label' => 'メンテナンスモード', 'rules' => 'permit_empty|in_list[0,1]'],
            'search_noindex'          => ['label' => '検索エンジンにインデックスさせない', 'rules' => 'permit_empty|in_list[0,1]'],
        ];

        $input                     = $this->request->getPost(array_keys($rules));
        $input['maintenance_mode'] = $input['maintenance_mode'] === '1' ? '1' : '0';
        $input['search_noindex']   = ($input['search_noindex'] ?? '') === '1' ? '1' : '0';

        if (! $this->validateData($input, $rules)) {
            return $this->backWithErrors($this->validator->getErrors());
        }
        // Not an in_list rule: some formats contain commas.
        if (! in_array($input['date_format'], self::DATE_FORMATS, true)) {
            return $this->backWithErrors(['date_format' => '日付の形式は一覧から選択してください。']);
        }

        $settings = model(SettingModel::class);
        $changed  = [];

        foreach ($this->validator->getValidated() as $name => $value) {
            $value = trim((string) $value);

            if ((string) $settings->get($name, self::DEFAULTS[$name]) !== $value) {
                $settings->put($name, $value);
                $changed[] = $rules[$name]['label'] ?? $name;
            }
        }

        if ($changed !== []) {
            log_activity('settings.updated', '設定を変更しました: ' . implode('、', $changed));
        }

        return redirect()->route('admin.settings')->with('success', '設定を保存しました。');
    }

    /**
     * Send a test email to the logged-in admin to check the SMTP settings in .env.
     */
    public function testEmail(): RedirectResponse
    {
        $user = current_user();

        if (empty($user->email)) {
            return redirect()->route('admin.settings')->with('error', 'アカウントにメールアドレスが登録されていません。マイプロフィールで追加してください。');
        }

        $sent = send_mail(
            $user->email,
            'テストメール（' . setting('site_name', config('Cms')->appName) . '）',
            '<p>これは <strong>' . esc(setting('site_name', '')) . '</strong> の管理画面から送信されたテストメールです。</p>'
            . '<p>このメールが届いていれば、.env のSMTP設定は正常に動作しています。</p>'
            . '<p style="color:#888">送信日時: ' . date('Y-m-d H:i:s') . '（送信サーバー: ' . esc(config('Email')->SMTPHost) . '）</p>',
        );

        log_activity('settings.test_email', "{$user->email} 宛てのテストメールの送信に" . ($sent ? '成功しました' : '失敗しました'));

        return redirect()->route('admin.settings')->with(
            $sent ? 'success' : 'error',
            $sent
                ? "{$user->email} 宛てにテストメールを送信しました。"
                : 'テストメールを送信できませんでした。.env の email.* 設定を確認してください（詳細は writable/logs にあります）。',
        );
    }
}
