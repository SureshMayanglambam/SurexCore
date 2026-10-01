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

        return redirect()->route('admin.users')->with('success', "ユーザー「{$data['name']}」を作成しました。");
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

        return redirect()->route('admin.users')->with('success', "ユーザー「{$data['name']}」を更新しました。");
    }

    public function delete(int $id): RedirectResponse
    {
        $item  = $this->findOrFail($id);
        $users = model(UserModel::class);

        if ((int) $item->id === (int) current_user()->id) {
            return redirect()->route('admin.users')->with('error', '自分のアカウントは削除できません。');
        }
        if ($item->role === 'admin' && $item->status === 'active' && $users->countActiveAdmins() <= 1) {
            return redirect()->route('admin.users')->with('error', '有効な管理者が1人しかいないため、削除できません。');
        }

        // Their entries stay; the author is cleared by the foreign key (ON DELETE SET NULL).
        $users->delete($item->id);
        log_activity('user.deleted', "ユーザー「{$item->name}」を削除しました", 'user', (int) $item->id);

        return redirect()->route('admin.users')->with('success', "ユーザー「{$item->name}」を削除しました。");
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
            'name'             => ['label' => '名前', 'rules' => 'required|max_length[100]'],
            'username'         => ['label' => 'ログインID', 'rules' => "permit_empty|min_length[3]|max_length[60]|regex_match[/^[a-zA-Z0-9._-]+$/]|is_unique[users.username,id,{$id}]"],
            'email'            => ['label' => 'メールアドレス', 'rules' => "permit_empty|valid_email|max_length[191]|is_unique[users.email,id,{$id}]"],
            'role'             => ['label' => '権限', 'rules' => "required|in_list[{$roles}]"],
            'status'           => ['label' => 'ステータス', 'rules' => 'required|in_list[active,disabled]'],
            'password'         => ['label' => 'パスワード', 'rules' => ($existing ? 'permit_empty' : 'required') . '|min_length[10]|max_length[72]'],
            'password_confirm' => ['label' => 'パスワード（確認）', 'rules' => 'matches[password]'],
        ];
        $messages = [
            'username' => [
                'regex_match' => 'ログインIDには半角英数字・ドット・ハイフン・アンダースコアのみ使用できます。',
                'is_unique'   => 'このログインIDはすでに使用されています。',
            ],
            'email'            => ['is_unique' => 'このメールアドレスは他のユーザーが使用しています。'],
            'password_confirm' => ['matches' => 'パスワードが一致しません。'],
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
            $errors['email'] = '管理者にはメールアドレスが必須です。';
        }
        if ($input['role'] === 'webadmin' && $input['email'] === '' && $input['username'] === '') {
            $errors['username'] = 'Web管理者にはログインIDまたはメールアドレスが必要です。';
        }

        // Don't let an Admin lock themselves (or everyone) out.
        if ($existing && $existing->role === 'admin' && $existing->status === 'active'
            && ($input['role'] !== 'admin' || $input['status'] !== 'active')) {
            if ((int) $existing->id === (int) current_user()->id) {
                $errors['role'] = '自分の管理者権限を外したり、自分のアカウントを無効にしたりすることはできません。';
            } elseif (model(UserModel::class)->countActiveAdmins() <= 1) {
                $errors['role'] = '有効な管理者が少なくとも1人必要です。';
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
