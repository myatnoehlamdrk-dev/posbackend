<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The heads-up mail sent to a shop's owner address at registration.
 *
 * Deliberately not a verification mail: there is no code, no link and no
 * action to take. Registration already mails a one-time code to the
 * registrant's own address; this one goes to `shops.owner_email`, which is a
 * separate field the registrant types in and which may belong to somebody
 * else entirely. If that somebody did not ask to be listed as owner, this is
 * the only message that tells them, and the "contact us" line is the only
 * exit they get.
 *
 * Wording is fixed rather than configurable on purpose: a notice whose text
 * can be changed per call is a notice that can be made to say nothing.
 */
class ShopOwnerNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $shopName,
        private readonly ?string $ownerName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A shop was created with your email as owner',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    /**
     * The address to report an unwanted listing to.
     *
     * Read from the mailer's own from-address rather than hardcoded, so the
     * contact line cannot drift away from the mailbox that actually exists.
     */
    private function contactAddress(): string
    {
        return (string) config('mail.from.address', 'hello@example.com');
    }

    private function buildHtml(): string
    {
        // Both values come straight from the registration payload, so they are
        // escaped before interpolation -- a shop name is attacker-controlled
        // text landing in someone else's inbox.
        $shopName = e($this->shopName);
        $contact = e($this->contactAddress());
        $greeting = $this->ownerName !== null && $this->ownerName !== ''
            ? 'Hello '.e($this->ownerName).','
            : 'Hello,';

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
                                    <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:600;">Shop created with your email</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:40px;">
                                    <p style="color:#374151;font-size:16px;margin:0 0 16px;">{$greeting}</p>
                                    <p style="color:#374151;font-size:16px;margin:0 0 16px;">A shop was just created with <strong>{$shopName}</strong> listed with your email address as its owner.</p>
                                    <div style="background-color:#f9fafb;border:2px dashed #7B2CBF;border-radius:8px;padding:24px;text-align:center;margin:24px 0;">
                                        <span style="font-size:14px;color:#6b7280;letter-spacing:1px;text-transform:uppercase;">{$shopName}</span>
                                    </div>
                                    <p style="color:#6b7280;font-size:14px;margin:0 0 8px;">This is a notice only &mdash; there is nothing to verify and nothing to confirm.</p>
                                    <p style="color:#6b7280;font-size:14px;margin:0;">If you did not create this shop and do not know who did, please contact us at <strong>{$contact}</strong> as soon as possible.</p>
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
