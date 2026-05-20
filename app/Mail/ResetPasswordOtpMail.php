<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\HtmlString; 
class ResetPasswordOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    /**
     * Create a new message instance.
     */
    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'كود إعادة تعيين كلمة المرور',
        );
    }

    /**
     * Get the message content definition.
     */
   public function content(): Content
{
    $text = "كود التحقق الخاص بك لإعادة تعيين كلمة المرور هو: " . $this->otp;
    
    return new Content(
        htmlString: new \Illuminate\Support\HtmlString($text)
    );
}
}