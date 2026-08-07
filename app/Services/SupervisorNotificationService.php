<?php

namespace App\Services;

use App\Notifications\SupervisorWorkflowNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SupervisorNotificationService
{
    public function send(SupervisorWorkflowNotification $notification, string $logMessage = 'Unable to send supervisor notification.'): void
    {
        try {
            foreach ($this->recipients() as $address) {
                Notification::route('mail', $address)->notify($notification);
            }
        } catch (\Throwable $exception) {
            Log::error($logMessage, ['exception' => $exception]);
        }
    }

    public function recipients(): array
    {
        $roleRecipients = User::query()->where('role', 'supervisor')->where('directory_active', true)->whereNotNull('email')->pluck('email')->all();
        return $roleRecipients ?: config('mail.supervisor_addresses', []);
    }
}
