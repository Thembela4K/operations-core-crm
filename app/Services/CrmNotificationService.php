<?php

namespace App\Services;

use App\Models\CrmNotification;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CrmNotificationService
{
    public function notifyUser(User $user, string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $data = []): CrmNotification
    {
        return $user->crmNotifications()->create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'data' => $data ?: null,
        ]);
    }

    public function notifyDepartment(?Department $department, string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $data = []): int
    {
        if (! $department) {
            return 0;
        }

        return $this->notifyUsers(
            User::query()
                ->where('department_id', $department->id)
                ->where('is_active', true)
                ->get(),
            $type,
            $title,
            $body,
            $actionUrl,
            $data,
        );
    }

    /**
     * @param  EloquentCollection<int, User>  $users
     */
    public function notifyUsers(EloquentCollection $users, string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $data = []): int
    {
        $count = 0;

        foreach ($users as $user) {
            $this->notifyUser($user, $type, $title, $body, $actionUrl, $data);
            $count++;
        }

        return $count;
    }

    public function notifyApprovers(string $type, string $title, ?string $body = null, ?string $actionUrl = null, array $data = []): int
    {
        return $this->notifyUsers(
            User::query()
                ->where('is_active', true)
                ->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_DIRECTOR])
                ->get(),
            $type,
            $title,
            $body,
            $actionUrl,
            $data,
        );
    }
}
