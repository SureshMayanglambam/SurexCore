<?php

namespace App\Admin;

use App\Model\ActivityLogModel;
use App\Model\UserModel;

/**
 * Settings → Activity Log (Admin only).
 */
class ActivityLog extends AdminController
{
    public function index(): string
    {
        $model  = model(ActivityLogModel::class);
        $userId = (int) $this->request->getGet('user');
        $action = (string) $this->request->getGet('action');

        if ($userId > 0) {
            $model->where('user_id', $userId);
        }
        if ($action !== '') {
            // Filter by group, e.g. "auth" matches auth.login, auth.logout, auth.failed
            $model->like('action', $action . '.', 'after');
        }

        return $this->render('admin.activity.index', [
            'items'     => $model->orderBy('id', 'DESC')->paginate(50),
            'pager'     => $model->pager,
            'users'     => model(UserModel::class)->select('id, name')->orderBy('name')->findAll(),
            'userId'    => $userId,
            'action'    => $action,
            'actions'   => [
                'auth'     => 'ログイン／ログアウト',
                'entry'    => 'コンテンツ',
                'media'    => 'メディア',
                'type'     => 'コンテンツタイプ',
                'user'     => 'ユーザー',
                'settings' => '設定',
                'system'   => 'システム',
            ],
            'retention' => (int) setting('activity_retention_days', 90),
        ]);
    }
}
