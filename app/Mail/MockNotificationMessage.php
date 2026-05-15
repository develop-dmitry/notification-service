<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MockNotificationMessage extends Mailable
{
    public function __construct(public string $body) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Notification');
    }

    public function content(): Content
    {
        return new Content(htmlString: e($this->body));
    }
}
