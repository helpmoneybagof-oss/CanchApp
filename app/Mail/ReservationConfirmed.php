<?php

namespace App\Mail;

use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Reserva confirmada! #'.$this->reservation->confirmation_code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-confirmed',
        );
    }

    public function attachments(): array
    {
        $reservation = $this->reservation->load(['court', 'items.product', 'user']);

        $pdf = Pdf::loadView('emails.pdf.reservation-voucher', [
            'reservation' => $reservation,
        ]);

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                'comprobante-reserva-'.$reservation->confirmation_code.'.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
