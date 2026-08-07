<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class SupervisorWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $subject,
        private readonly string $intro,
        private readonly array $details,
        private readonly ?string $url,
        private readonly string $actionLabel,
        private readonly ?string $attachmentPath = null,
        private readonly ?string $attachmentName = null,
    ) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function activitySubject(): string { return $this->subject; }

    public function activityType(): string { return class_basename(static::class); }

    public function activityIntro(): string { return $this->intro; }

    public function activityDetails(): array { return $this->details; }

    public function activityUrl(): ?string { return $this->url; }

    public function activityActionLabel(): string { return $this->actionLabel; }

    public function activityAttachmentName(): ?string { return $this->attachmentName; }

    public function toMail(object $notifiable): MailMessage
    {
        $recipientEmail = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('mail') : null;
        $recipient = $recipientEmail ? User::query()->where('email', $recipientEmail)->first() : null;
        $greeting = $recipient?->name ? "Hello {$recipient->name}," : 'Hello Supervisor,';
        $mail = (new MailMessage)->subject($this->subject)->greeting($greeting)->line($this->intro);
        if ($recipient?->job_title) {
            $mail->line("Your role: {$recipient->job_title}");
        }
        foreach ($this->details as $label => $value) $mail->line("{$label}: {$value}");
        if ($this->url) {
            $mail->action($this->actionLabel, $this->url);
        }

        if ($this->attachmentPath) {
            $mail->attachFromStorageDisk('local', $this->attachmentPath, $this->attachmentName ?: 'asset-movement.pdf', ['mime' => 'application/pdf']);
        }

        return $mail->line('This is an acknowledgement notification from the Dayang Inventory Management System.');
    }
}
