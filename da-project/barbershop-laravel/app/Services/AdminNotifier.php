<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AdminActivityNotification;

class AdminNotifier
{
    public function send(string $kind, string $title, string $message, string $url): void
    {
        User::query()
            ->where('is_admin', true)
            ->eachById(function (User $admin) use ($kind, $title, $message, $url): void {
                $admin->notify(new AdminActivityNotification($kind, $title, $message, $url));
            });
    }
}
