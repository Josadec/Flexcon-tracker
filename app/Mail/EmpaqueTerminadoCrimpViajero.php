<?php

namespace App\Mail;

use App\Models\Lot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo "Empaque terminado CRIMP Viajero" (M9).
 * Resumen de cierre de empaque para un viajero con CRIMP: cantidades completadas y
 * sobrantes de piezas y de CRIMP. Se dispara manualmente desde la vista de Empaque.
 */
class EmpaqueTerminadoCrimpViajero extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lot $viajero,
        public ?int $labelCount = null,
        public ?string $extraComments = null,
        public ?string $packerName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Empaque terminado CRIMP — Viajero '.$this->viajero->lot_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.empaque-terminado-crimp-viajero',
        );
    }
}
