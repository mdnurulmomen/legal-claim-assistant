<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

class CreativeApprovedRejectedEmail extends Mailable  implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $mail_data;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($mail_data)
    {
        $this->mail_data = $mail_data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mail_data['subject'] . ' - ' . env('APP_NAME'),
            from: new Address('info@legalclaimassistant.com', 'Legal Claim Assistant'),
            replyTo: [
                new Address('info@legalclaimassistant.com', 'Legal Claim Assistant')
            ]
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->mail_data['template'],
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

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'), env('APP_NAME'))
            ->subject($this->mail_data['subject'] . ' - ' . env('APP_NAME'))
            ->markdown($this->mail_data['template'])
            ->with($this->mail_data['message']);
            // ->attach($this->mail_data['file'], isset($this->mail_data['mimes']) ? ['mime' => $this->mail_data['mimes']] : []);
    }
}
