<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private string $otp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Password Reset Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;padding:0;background-color:#f4f4f7;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                            <tr>
                                <td style="background:linear-gradient(135deg,#7B2CBF,#9D4EDD);padding:32px 40px;text-align:center;">
                                    <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:600;">Password Reset</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:40px;">
                                    <p style="color:#374151;font-size:16px;margin:0 0 16px;">You requested a password reset. Use the code below to verify your identity:</p>
                                    <div style="background-color:#f9fafb;border:2px dashed #7B2CBF;border-radius:8px;padding:24px;text-align:center;margin:24px 0;">
                                        <span style="font-size:36px;font-weight:bold;color:#7B2CBF;letter-spacing:8px;">{$this->otp}</span>
                                    </div>
                                    <p style="color:#6b7280;font-size:14px;margin:0 0 8px;">This code will expire in <strong>10 minutes</strong>.</p>
                                    <p style="color:#6b7280;font-size:14px;margin:0;">If you did not request a password reset, please ignore this email.</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="background-color:#f9fafb;padding:24px 40px;border-top:1px solid #e5e7eb;">
                                    <p style="color:#9ca3af;font-size:12px;margin:0;text-align:center;">This is an automated message. Please do not reply.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
