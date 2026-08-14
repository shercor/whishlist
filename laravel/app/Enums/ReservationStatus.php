<?php

namespace App\Enums;

enum ReservationStatus
{
    case ACTIVE;
    case FULFILLED;
    case CANCELLED;
    case EXPIRED;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'activa',
            self::FULFILLED => 'cumplida',
            self::CANCELLED => 'cancelada',
            self::EXPIRED => 'expirada',
        };
    }

    /**
     * Solo una reserva viva bloquea el regalo para los demás. El resto son
     * historial: se conservan para poder decirle a alguien "esto lo reservaste
     * tú y lo soltaste", sin que sigan ocupando el ítem.
     */
    public function blocksItem(): bool
    {
        return $this === self::ACTIVE;
    }

    public function colors(): string
    {
        return match ($this) {
            self::ACTIVE => '#0d6efd',
            self::FULFILLED => '#198754',
            self::CANCELLED => '#6c757d',
            self::EXPIRED => '#fd7e14',
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

        throw new \ValueError("Estado de reserva no válido: {$label}");
    }
}
