<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Newsletter extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;

    public array $topics;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subjectLine, array $topics)
    {
        $this->subjectLine = $subjectLine;
        $this->topics = $topics;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.newsletter.postapoc');
    }
}
