<?php

namespace App\Controller;

use App\Model\SettingModel;
use App\Model\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * First-run setup: database connection, tables, first administrator.
 * Only reachable while the CMS is not installed (App\Filters\InstallCheck).
 */
class Install extends BaseController
{
    public function index(): string
    {
        return $this->form();
    }

    public function store(): RedirectResponse|string
    {
        $rules = [
            'site_name'        => ['label' => 'サイト名', 'rules' => 'required|max_length[100]'],
            'admin_name'       => ['label' => '管理者の名前', 'rules' => 'required|max_length[100]'],
            'admin_email'      => ['label' => '管理者のメールアドレス', 'rules' => 'required|valid_email|max_length[191]'],
            'admin_password'   => ['label' => 'パスワード', 'rules' => 'required|min_length[10]|max_length[72]'],
            'password_confirm' => ['label' => 'パスワード（確認）', 'rules' => 'required|matches[admin_password]'],
            'db_host'          => ['label' => 'ホスト', 'rules' => 'required|max_length[255]'],
            'db_port'          => ['label' => 'ポート', 'rules' => 'required|is_natural_no_zero|less_than[65536]'],
            'db_name'          => ['label' => 'データベース名', 'rules' => 'required|max_length[64]'],
            'db_user'          => ['label' => 'ユーザー名', 'rules' => 'required|max_length[64]'],
            'db_pass'          => ['label' => 'パスワード', 'rules' => 'permit_empty|max_length[255]'],
            'db_prefix'        => ['label' => 'テーブル接頭辞', 'rules' => 'permit_empty|max_length[10]|regex_match[/^[a-z0-9_]+$/]'],
        ];

        if (! $this->validate($rules)) {
            return $this->form($this->validator->getErrors());
        }

        $installer = service('installer');
        $input     = $this->request->getPost(array_keys($rules));
        $db        = [
            'hostname' => trim($input['db_host']),
            'port'     => (int) $input['db_port'],
            'database' => trim($input['db_name']),
            'username' => trim($input['db_user']),
            'password' => (string) $input['db_pass'],
            'prefix'   => (string) $input['db_prefix'],
        ];

        if (! $installer->canWriteEnv()) {
            return $this->form(['env' => 'CMSフォルダ内の .env ファイルに書き込めません。Webサーバーに書き込み権限を付与してから、もう一度お試しください。']);
        }

        try {
            $connection = $installer->connect($db);
        } catch (\RuntimeException $e) {
            log_message('error', $e->getMessage());

            return $this->form(['db' => 'データベースに接続できませんでした。ホスト、データベース名、ユーザー名、パスワードを確認してください。']);
        }

        $users = new UserModel($connection);

        if ($connection->tableExists('users') && $users->countAllResults() > 0) {
            return $this->form(['db' => 'このデータベースには SurexCore がすでにインストールされています。空のデータベースを使用するか、別のテーブル接頭辞を指定してください。']);
        }

        try {
            $installer->migrate($connection);

            $settings = new SettingModel($connection);
            $settings->put('site_name', $input['site_name']);
            $settings->put('site_tagline', '');
            $settings->put('posts_per_page', 10);
            $settings->put('date_format', 'Y.m.d');
            $settings->put('activity_retention_days', 90);

            $users->insert([
                'name'     => $input['admin_name'],
                'email'    => $input['admin_email'],
                'password' => $input['admin_password'],
                'role'     => 'admin',
                'status'   => 'active',
            ]);

            $installer->finish($installer->envContents($db, config('App')->baseURL, $input['site_name'], $input['admin_email']));
        } catch (\Throwable $e) {
            log_message('critical', 'Installation failed: {message}', ['message' => $e->getMessage()]);

            return $this->form(['install' => 'インストールに失敗しました: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.login')
            ->with('success', 'SurexCore のインストールが完了しました。作成した管理者アカウントでログインしてください。');
    }

    private function form(array $errors = []): string
    {
        $old = $this->request->getPost() ?? [];
        unset($old['admin_password'], $old['password_confirm'], $old['db_pass']);

        return $this->render('admin.install.index', [
            'errors'   => $errors,
            'old'      => $old + [
                'db_host'   => 'localhost',
                'db_port'   => 3306,
                'db_prefix' => 'sc_',
            ],
            'writable' => [
                '.env'      => service('installer')->canWriteEnv(),
                'writable/' => is_writable(WRITEPATH),
            ],
            'php' => PHP_VERSION,
        ]);
    }
}
