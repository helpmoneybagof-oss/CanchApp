<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DailySummaryAdmin extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection  $reservations  Reservas del día
     * @param  string      $date          Fecha formateada (ej: "26 de febrero de 2026")
     */
    public function __construct(
        public Collection $reservations,
        public string $date,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resumen del día — ' . $this->date,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.daily-summary',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
