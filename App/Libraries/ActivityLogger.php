<?php

namespace App\Libraries;

use App\Model\ActivityLogModel;

/**
 * Records who did what in the admin panel (Settings → Activity Log).
 * Old entries are pruned automatically based on the "activity_retention_days" setting.
 */
class ActivityLogger
{
    public function log(string $action, string $description, ?string $subjectType = null, ?int $subjectId = null, ?object $user = null): void
    {
        $user  ??= current_user();
        $request = service('request');
        $model   = model(ActivityLogModel::class);

        try {
            $model->insert([
                'user_id'      => $user->id ?? null,
                'user_name'    => $user->name ?? null,
                'action'       => $action,
                'description'  => mb_substr($description, 0, 500),
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId,
                'ip_address'   => $request->getIPAddress(),
                'user_agent'   => mb_substr((string) $request->getUserAgent(), 0, 255),
            ]);

            // Prune on roughly 1 in 50 writes instead of needing a cron job.
            if (random_int(1, 50) === 1) {
                $model->prune(max(1, (int) setting('activity_retention_days', 90)));
            }
        } catch (\Throwable $e) {
            // Logging must never break the action being logged.
            log_message('error', 'Activity log failed: {message}', ['message' => $e->getMessage()]);
        }
    }
}
