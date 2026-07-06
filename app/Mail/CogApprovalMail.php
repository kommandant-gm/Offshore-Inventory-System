<?php

namespace App\Mail;

use App\Models\Cog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CogApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cog $cog,
        public string $approvalUrl,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject("COG {$this->cog->cog_no} approval request")
            ->view('emails.cogs.approval');
    }
}
