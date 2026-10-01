<?php

namespace App\Model;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table          = 'users';
    protected $returnType     = 'object';
    protected $useTimestamps  = true;
    protected $allowedFields  = ['name', 'username', 'email', 'password', 'role', 'status', 'last_login_at'];
    protected $beforeInsert   = ['prepareData'];
    protected $beforeUpdate   = ['prepareData'];

    public function findActive(int $id): ?object
    {
        return $this->where('status', 'active')->find($id);
    }

    /**
     * Return the user when the login (login ID or email) and password are valid, otherwise null.
     */
    public function attempt(string $login, string $password): ?object
    {
        $login = strtolower(trim($login));
        $user  = $this->groupStart()->where('email', $login)->orWhere('username', $login)->groupEnd()
            ->where('status', 'active')
            ->first();

        if ($user === null) {
            // Spend the same time as a real check so response timing does not reveal valid emails.
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');

            return null;
        }

        if (! password_verify($password, $user->password)) {
            return null;
        }

        if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
            $this->update($user->id, ['password' => $password]);
        }

        return $user;
    }

    public function countActiveAdmins(): int
    {
        return $this->where('role', 'admin')->where('status', 'active')->countAllResults();
    }

    /**
     * Passwords are always stored hashed; pass plain text in 'password'.
     * Login IDs and emails are stored lowercase; empty ones become NULL (both are unique).
     */
    protected function prepareData(array $data): array
    {
        if (! empty($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['data']['password']);
        }

        foreach (['email', 'username'] as $field) {
            if (array_key_exists($field, $data['data'])) {
                $value                = strtolower(trim((string) $data['data'][$field]));
                $data['data'][$field] = $value === '' ? null : $value;
            }
        }

        return $data;
    }
}
