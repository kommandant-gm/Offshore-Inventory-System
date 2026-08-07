<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Event;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use App\Models\EmailActivityLog;
use App\Notifications\SupervisorWorkflowNotification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->channel !== 'mail' || ! $event->notification instanceof SupervisorWorkflowNotification) return;
            $this->recordEmailActivity($event->notifiable->routeNotificationFor('mail'), $event->notification, 'sent');
        });
        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            if ($event->channel !== 'mail' || ! $event->notification instanceof SupervisorWorkflowNotification) return;
            $this->recordEmailActivity($event->notifiable->routeNotificationFor('mail'), $event->notification, 'failed', $event->data['exception'] ?? null);
        });
    }

    private function recordEmailActivity(string $recipient, SupervisorWorkflowNotification $notification, string $status, mixed $error = null): void
    {
        try {
            $recipientUser = \App\Models\User::query()->where('email', $recipient)->first();
            $greeting = $recipientUser?->name ? "Hello {$recipientUser->name}," : 'Hello Supervisor,';
            $lines = [$greeting, $notification->activityIntro()];
            if ($recipientUser?->job_title) $lines[] = "Your role: {$recipientUser->job_title}";
            foreach ($notification->activityDetails() as $label => $value) $lines[] = "{$label}: {$value}";
            if ($notification->activityUrl()) $lines[] = "{$notification->activityActionLabel()}: {$notification->activityUrl()}";
            if ($notification->activityAttachmentName()) $lines[] = "Attachment: {$notification->activityAttachmentName()}";
            $lines[] = 'This is an acknowledgement notification from the Dayang Inventory Management System.';

            EmailActivityLog::create([
                'recipient' => $recipient,
                'subject' => $notification->activitySubject(),
                'body' => implode("\n\n", $lines),
                'details' => $notification->activityDetails(),
                'action_url' => $notification->activityUrl(),
                'action_label' => $notification->activityActionLabel(),
                'attachment_name' => $notification->activityAttachmentName(),
                'notification_type' => $notification->activityType(),
                'status' => $status,
                'error' => $error instanceof \Throwable ? $error->getMessage() : (is_string($error) ? $error : null),
                'sent_at' => $status === 'sent' ? now() : null,
            ]);
        } catch (\Throwable) {
            // Email logging must never prevent the original notification flow.
        }
    }
}
