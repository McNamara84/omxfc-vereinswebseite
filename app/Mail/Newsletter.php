<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Newsletter extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public string $subjectLine, public array $topics) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.newsletter.postapoc');
    }
}
