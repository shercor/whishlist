<?php

namespace App\Enums;

enum FollowStatus
{
    case PENDING;
    case ACCEPTED;

    /**
     * Valor que se guarda en la base. Para mostrar en pantalla, title().
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'pendiente',
            self::ACCEPTED => 'aceptado',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::ACCEPTED => 'Te sigue',
        };
    }

    /**
     * Solo un seguimiento aceptado cuenta como tal.
     *
     * Importa porque ser seguidor es requisito para conservar el acceso a una
     * lista privada: una solicitud pendiente no debe abrir nada.
     */
    public function isActive(): bool
    {
        return $this === self::ACCEPTED;
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

        throw new \ValueError("Estado de seguimiento no válido: {$label}");
    }
}
