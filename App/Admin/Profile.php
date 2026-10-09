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
            'name'             => ['label' => lang('Admin.val.name'), 'rules' => 'required|max_length[100]'],
            'email'            => ['label' => lang('Admin.val.email'), 'rules' => "permit_empty|valid_email|max_length[191]|is_unique[users.email,id,{$user->id}]"],
            'current_password' => ['label' => lang('Admin.val.current_pw'), 'rules' => 'required'],
            'password'         => ['label' => lang('Admin.val.new_pw'), 'rules' => 'permit_empty|min_length[10]|max_length[72]'],
            'password_confirm' => ['label' => lang('Admin.val.new_pw_c'), 'rules' => 'matches[password]'],
        ];

        $input          = $this->request->getPost(array_keys($rules));
        $input['email'] = strtolower(trim((string) $input['email']));

        if (! $this->validateData($input, $rules, ['email' => ['is_unique' => lang('Admin.val.email_taken')]])) {
            return $this->backWithErrors($this->validator->getErrors());
        }

        if (! password_verify((string) $input['current_password'], $user->password)) {
            return $this->backWithErrors(['current_password' => lang('Admin.val.current_pw_wrong')]);
        }
        if ($user->role === 'admin' && $input['email'] === '') {
            return $this->backWithErrors(['email' => lang('Admin.val.admin_needs_email2')]);
        }
        if ($input['email'] === '' && ($user->username ?? '') === '') {
            return $this->backWithErrors(['email' => lang('Admin.val.need_email_no_id')]);
        }

        $data = ['name' => trim($input['name']), 'email' => $input['email']];

        if (($input['password'] ?? '') !== '') {
            $data['password'] = $input['password'];
        }

        model(UserModel::class)->update($user->id, $data);
        log_activity('user.profile', '自分のプロフィールを更新しました' . (isset($data['password']) ? '（パスワード変更あり）' : ''), 'user', (int) $user->id);

        return redirect()->route('admin.profile')->with('success', lang('Admin.flash.profile_updated'));
    }
}
