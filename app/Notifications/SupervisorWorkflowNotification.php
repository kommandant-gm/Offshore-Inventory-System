<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupervisorWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $subject,
        private readonly string $intro,
        private readonly array $details,
        private readonly string $url,
        private readonly string $actionLabel,
    ) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->greeting('Hello Supervisor,')->line($this->intro);
        foreach ($this->details as $label => $value) $mail->line("{$label}: {$value}");
        return $mail->action($this->actionLabel, $this->url)->line('This is an acknowledgement notification from the Dayang Inventory Management System.');
    }
}
