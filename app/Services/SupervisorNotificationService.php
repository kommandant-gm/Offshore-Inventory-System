<?php

namespace App\Services;

use App\Notifications\SupervisorWorkflowNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SupervisorNotificationService
{
    public function send(SupervisorWorkflowNotification $notification, string $logMessage = 'Unable to send supervisor notification.', array $additionalRecipients = []): void
    {
        try {
            foreach (array_filter(array_unique(array_merge($this->recipients(), $additionalRecipients))) as $address) {
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

    public function technicianRecipients(): array
    {
        return User::query()->where('role', 'technician')->where('directory_active', true)->whereNotNull('email')->pluck('email')->all()
            ?: ['muhd.isa@desb.net'];
    }
}
