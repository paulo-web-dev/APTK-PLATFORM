<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Casca" do e-mail de customização solicitada (Custom Simples).
 *
 * ⚠️ O envio está DESATIVADO no CustomController até o SMTP ser
 * configurado no .env (MAIL_MAILER, MAIL_HOST, etc). Enquanto isso,
 * o pedido chega como Lead (tipo "custom") no painel administrativo.
 */
class CustomOrderRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nova customização solicitada — '.$this->lead->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.custom-order',
        );
    }
}
