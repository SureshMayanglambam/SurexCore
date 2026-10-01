<?php

namespace App\Admin;

use App\Model\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * My profile: every user can change their own name, email and password.
 * Role, status and login ID are managed by Admins under Settings → Users.
 */
class Profile extends AdminController
{
    public function edit(): string
    {
        return $this->render('admin.profile');
    }

    public function update(): RedirectResponse
    {
        $user  = current_user();
        $rules = [
            'name'             => ['label' => '名前', 'rules' => 'required|max_length[100]'],
            'email'            => ['label' => 'メールアドレス', 'rules' => "permit_empty|valid_email|max_length[191]|is_unique[users.email,id,{$user->id}]"],
            'current_password' => ['label' => '現在のパスワード', 'rules' => 'required'],
            'password'         => ['label' => '新しいパスワード', 'rules' => 'permit_empty|min_length[10]|max_length[72]'],
            'password_confirm' => ['label' => '新しいパスワード（確認）', 'rules' => 'matches[password]'],
        ];

        $input          = $this->request->getPost(array_keys($rules));
        $input['email'] = strtolower(trim((string) $input['email']));

        if (! $this->validateData($input, $rules, ['email' => ['is_unique' => 'このメールアドレスは他のユーザーが使用しています。']])) {
            return $this->backWithErrors($this->validator->getErrors());
        }

        if (! password_verify((string) $input['current_password'], $user->password)) {
            return $this->backWithErrors(['current_password' => '現在のパスワードが正しくありません。']);
        }
        if ($user->role === 'admin' && $input['email'] === '') {
            return $this->backWithErrors(['email' => '管理者ユーザーにはメールアドレスが必要です。']);
        }
        if ($input['email'] === '' && ($user->username ?? '') === '') {
            return $this->backWithErrors(['email' => 'ログインIDが設定されていないため、メールアドレスが必要です。']);
        }

        $data = ['name' => trim($input['name']), 'email' => $input['email']];

        if (($input['password'] ?? '') !== '') {
            $data['password'] = $input['password'];
        }

        model(UserModel::class)->update($user->id, $data);
        log_activity('user.profile', '自分のプロフィールを更新しました' . (isset($data['password']) ? '（パスワード変更あり）' : ''), 'user', (int) $user->id);

        return redirect()->route('admin.profile')->with('success', 'プロフィールを更新しました。');
    }
}
