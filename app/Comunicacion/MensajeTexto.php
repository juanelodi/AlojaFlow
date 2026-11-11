<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Comunicacion;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Un mensaje de texto simple, con el asunto y el cuerpo ya generados.
 */
class MensajeTexto extends Mailable
{
    public function __construct(
        public readonly string $asunto,
        public readonly string $cuerpo,
        public readonly string $correoRemitente,
        public readonly string $nombreRemitente,
        public readonly ?string $responderA = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->correoRemitente, $this->nombreRemitente),
            replyTo: $this->responderA === null ? [] : [new Address($this->responderA)],
            subject: $this->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'correo.mensaje-texto', with: ['cuerpo' => $this->cuerpo]);
    }
}
