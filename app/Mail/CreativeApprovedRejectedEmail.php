<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CreativeApprovedRejectedEmail extends Mailable
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
