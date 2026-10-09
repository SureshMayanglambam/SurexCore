<?php

namespace App\Admin;

use App\Model\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Settings → Users (Admin only).
 *
 * Admin:    email is required (login ID optional).
 * WebAdmin: needs a login ID or an email (either is enough to log in).
 */
class Users extends AdminController
{
    public function index(): string
    {
        return $this->render('admin.users.index', [
            'items' => model(UserModel::class)->orderBy('role')->orderBy('name')->findAll(),
        ]);
    }

    public function create(): string
    {
        return $this->render('admin.users.form', ['item' => null]);
    }

    public function store(): RedirectResponse
    {
        $data = $this->validated();

        if (! is_array($data)) {
            return $data;
        }

        $id = (int) model(UserModel::class)->insert($data);
        log_activity('user.created', "{$this->roleLabel($data['role'])}「{$data['name']}」を作成しました", 'user', $id);

        return redirect()->route('admin.users')->with('success', lang('Admin.flash.user_created', [$data['name']]));
    }

    public function edit(int $id): string
    {
        return $this->render('admin.users.form', ['item' => $this->findOrFail($id)]);
    }

    public function update(int $id): RedirectResponse
    {
        $item = $this->findOrFail($id);
        $data = $this->validated($item);

        if (! is_array($data)) {
            return $data;
        }

        model(UserModel::class)->update($item->id, $data);
        log_activity(
            'user.updated',
            "ユーザー「{$data['name']}」を更新しました" . (isset($data['password']) ? '（パスワードを変更）' : ''),
            'user',
            (int) $item->id,
        );

        return redirect()->route('admin.users')->with('success', lang('Admin.flash.user_updated', [$data['name']]));
    }

    public function delete(int $id): RedirectResponse
    {
        $item  = $this->findOrFail($id);
        $users = model(UserModel::class);

        if ((int) $item->id === (int) current_user()->id) {
            return redirect()->route('admin.users')->with('error', lang('Admin.flash.cant_delete_self'));
        }
        if ($item->role === 'admin' && $item->status === 'active' && $users->countActiveAdmins() <= 1) {
            return redirect()->route('admin.users')->with('error', lang('Admin.flash.need_one_admin_del'));
        }

        // Their entries stay; the author is cleared by the foreign key (ON DELETE SET NULL).
        $users->delete($item->id);
        log_activity('user.deleted', "ユーザー「{$item->name}」を削除しました", 'user', (int) $item->id);

        return redirect()->route('admin.users')->with('success', lang('Admin.flash.user_deleted', [$item->name]));
    }

    private function findOrFail(int $id): object
    {
        return model(UserModel::class)->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }

    private function roleLabel(string $role): string
    {
        return config('Cms')->roles[$role] ?? $role;
    }

    /**
     * Validated data, or a redirect back to the form with errors.
     */
    private function validated(?object $existing = null): array|RedirectResponse
    {
        $id    = (int) ($existing->id ?? 0);
        $roles = implode(',', array_keys(config('Cms')->roles));
        $rules = [
            'name'             => ['label' => lang('Admin.val.name'), 'rules' => 'required|max_length[100]'],
            'username'         => ['label' => lang('Admin.val.login_id'), 'rules' => "permit_empty|min_length[3]|max_length[60]|regex_match[/^[a-zA-Z0-9._-]+$/]|is_unique[users.username,id,{$id}]"],
            'email'            => ['label' => lang('Admin.val.email'), 'rules' => "permit_empty|valid_email|max_length[191]|is_unique[users.email,id,{$id}]"],
            'role'             => ['label' => lang('Admin.val.role'), 'rules' => "required|in_list[{$roles}]"],
            'status'           => ['label' => lang('Admin.val.status'), 'rules' => 'required|in_list[active,disabled]'],
            'password'         => ['label' => lang('Admin.val.password'), 'rules' => ($existing ? 'permit_empty' : 'required') . '|min_length[10]|max_length[72]'],
            'password_confirm' => ['label' => lang('Admin.val.password_c'), 'rules' => 'matches[password]'],
        ];
        $messages = [
            'username' => [
                'regex_match' => lang('Admin.val.login_id_chars'),
                'is_unique'   => lang('Admin.val.login_id_taken'),
            ],
            'email'            => ['is_unique' => lang('Admin.val.email_taken')],
            'password_confirm' => ['matches' => lang('Admin.val.pw_mismatch')],
        ];

        $input = $this->request->getPost(array_keys($rules));

        // Compare case-insensitively: both are stored lowercase.
        $input['username'] = strtolower(trim((string) $input['username']));
        $input['email']    = strtolower(trim((string) $input['email']));

        if (! $this->validateData($input, $rules, $messages)) {
            return $this->backWithErrors($this->validator->getErrors());
        }

        $errors = [];

        if ($input['role'] === 'admin' && $input['email'] === '') {
            $errors['email'] = lang('Admin.val.admin_needs_email');
        }
        if ($input['role'] === 'webadmin' && $input['email'] === '' && $input['username'] === '') {
            $errors['username'] = lang('Admin.val.webadmin_needs_id');
        }

        // Don't let an Admin lock themselves (or everyone) out.
        if ($existing && $existing->role === 'admin' && $existing->status === 'active'
            && ($input['role'] !== 'admin' || $input['status'] !== 'active')) {
            if ((int) $existing->id === (int) current_user()->id) {
                $errors['role'] = lang('Admin.flash.cant_demote_self');
            } elseif (model(UserModel::class)->countActiveAdmins() <= 1) {
                $errors['role'] = lang('Admin.flash.need_one_admin');
            }
        }

        if ($errors !== []) {
            return $this->backWithErrors($errors);
        }

        $data = [
            'name'     => trim($input['name']),
            'username' => $input['username'],
            'email'    => $input['email'],
            'role'     => $input['role'],
            'status'   => $input['status'],
        ];

        if ($input['password'] !== '' && $input['password'] !== null) {
            $data['password'] = $input['password'];
        }

        return $data;
    }
}
