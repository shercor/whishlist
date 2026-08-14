<?php

namespace App\Enums;

enum AccessRequestStatus
{
    case PENDING;
    case APPROVED;
    case REJECTED;
    case REVOKED;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'pendiente',
            self::APPROVED => 'aprobado',
            self::REJECTED => 'rechazado',
            self::REVOKED => 'revocado',
        };
    }

    /**
     * Solo APPROVED da acceso. REVOKED es un acceso que el dueño quitó después,
     * y se distingue de REJECTED para saber si alguna vez llegó a ver la lista.
     */
    public function grantsAccess(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Indica si el dueño todavía debe responder esta solicitud.
     */
    public function isAwaitingResponse(): bool
    {
        return $this === self::PENDING;
    }

    public function colors(): string
    {
        return match ($this) {
            self::PENDING => '#ffc107',
            self::APPROVED => '#198754',
            self::REJECTED => '#dc3545',
            self::REVOKED => '#6c757d',
        };
    }

    public static function labels(): array
    {
        return array_map(fn (self $case) => $case->label(), self::cases());
    }

    public static function fromLabel(string $label): self
    {
        foreach (self::cases() as $case) {
            if ($case->label() === $label) {
                return $case;
            }
        }

        throw new \ValueError("Estado de solicitud no válido: {$label}");
    }
}
