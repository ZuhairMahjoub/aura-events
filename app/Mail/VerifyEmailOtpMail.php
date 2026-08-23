<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public int $otp)
    {
        // تم استقبال الـ OTP بنجاح وجعله public ليسهل قراءته مباشرة داخل الـ htmlString
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تفعيل حسابك - كود OTP',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: "
                <div style='direction: rtl; text-align: center; font-family: Arial, sans-serif; padding: 30px; background-color: #ffffff;'>
                    <h2 style='color: #1e293b; margin-bottom: 10px;'>أهلاً بك في Aura Events</h2>
                    <p style='color: #4b5563; font-size: 16px; margin-bottom: 25px;'>شكراً لتسجيلك معنا. رمز التحقق (OTP) الخاص بك لتفعيل الحساب هو:</p>
                    
                    <div style='font-size: 32px; font-weight: bold; color: #4f46e5; letter-spacing: 5px; margin: 20px auto; padding: 15px 30px; background-color: #f3f4f6; display: inline-block; border-radius: 8px; border: 1px solid #e5e7eb;'>
                        {$this->otp}
                    </div>
                    
                    <p style='color: #9ca3af; font-size: 14px; margin-top: 25px;'>هذا الرمز صالح لمدة 10 دقائق فقط.</p>
                </div>
            "
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