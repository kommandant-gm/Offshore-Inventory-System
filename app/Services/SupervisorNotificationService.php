<?php

namespace App\Services;

use App\Notifications\SupervisorWorkflowNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SupervisorNotificationService
{
    public function send(SupervisorWorkflowNotification $notification, string $logMessage = 'Unable to send supervisor notification.'): void
    {
        try {
            foreach (config('mail.supervisor_addresses', []) as $address) {
                Notification::route('mail', $address)->notify($notification);
            }
        } catch (\Throwable $exception) {
            Log::error($logMessage, ['exception' => $exception]);
        }
    }
}
