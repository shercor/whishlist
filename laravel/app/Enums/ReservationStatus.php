<?php

namespace App\Enums;

enum ReservationStatus
{
    case ACTIVE;
    case FULFILLED;
    case CANCELLED;
    case EXPIRED;

    /**
     * La soltó el sistema porque quien la tenía dejó de alcanzar la lista.
     *
     * Es su propio estado y no CANCELLED a propósito: «Cancelada» le dice a la
     * persona que ella la soltó, y aquí no hizo nada —le quitaron el acceso, o
     * la lista se volvió privada—. Verlo como cancelada en «Voy a regalar» la
     * deja pensando que se equivocó de botón.
     */
    case REVOKED;

    /**
     * Valor que se guarda en la base. Para mostrar en pantalla, title().
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'activa',
            self::FULFILLED => 'cumplida',
            self::CANCELLED => 'cancelada',
            self::EXPIRED => 'expirada',
            self::REVOKED => 'revocada',
        };
    }

    /**
     * Cómo se le anuncia a quien reservó.
     */
    public function title(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activa',
            self::FULFILLED => 'Cumplida',
            self::CANCELLED => 'Cancelada',
            self::EXPIRED => 'Vencida',
            self::REVOKED => 'Liberada',
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

        throw new \ValueError("Estado de reserva no válido: {$label}");
    }
}
