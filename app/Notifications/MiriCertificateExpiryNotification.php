<?php

namespace App\Notifications;

use App\Models\MajorEquipmentCertificate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MiriCertificateExpiryNotification extends Notification
{
    public function __construct(private readonly MajorEquipmentCertificate $certificate) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $equipment = $this->certificate->equipment;

        return (new MailMessage)
            ->subject("Miri certificate expiring in 60 days: {$this->certificate->certificate_type}")
            ->greeting('Miri Inventory Certificate Expiry Reminder')
            ->line('A certificate recorded in Miri Inventory will expire in 60 days.')
            ->line('Certificate type: '.($this->certificate->certificate_type ?: 'Not specified'))
            ->line('Certificate number: '.($this->certificate->certificate_no ?: 'Not recorded'))
            ->line('Expiry date: '.$this->certificate->expiry_date?->format('d M Y'))
            ->line('Location: '.($equipment?->current_location ?: 'Not recorded'))
            ->line('Equipment tag: '.($equipment?->tag_no ?: 'Not recorded'))
            ->line('Equipment: '.($equipment?->description ?: 'Not recorded'));
    }
}
