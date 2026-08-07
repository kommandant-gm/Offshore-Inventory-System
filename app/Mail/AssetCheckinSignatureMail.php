<?php

namespace App\Mail;

use App\Models\AssetAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssetCheckinSignatureMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AssetAssignment $assignment, public string $signUrl) {}

    public function build(): self
    {
        return $this->subject("Asset check-in acknowledgement required: {$this->assignment->asset->asset_tag_no}")
            ->view('emails.it-assets.checkin-signature');
    }
}
