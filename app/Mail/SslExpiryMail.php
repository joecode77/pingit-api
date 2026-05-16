<?php

// app/Mail/SslExpiryMail.php

namespace App\Mail;

use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SslExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public readonly Monitor $monitor)
    {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $label         = $this->monitor->name ?? $this->monitor->url;
        $daysRemaining = $this->monitor->ssl_days_remaining;

        return new Envelope(
            subject: "⚠️ SSL Certificate Expiring Soon: {$label} ({$daysRemaining} days left)",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ssl-expiry',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}