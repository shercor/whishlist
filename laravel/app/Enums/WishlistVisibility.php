<?php

namespace App\Enums;

enum WishlistVisibility
{
    case PUBLIC;
    case LINK;
    case PRIVATE;

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'publica',
            self::LINK => 'por_enlace',
            self::PRIVATE => 'privada',
        };
    }

    /**
     * Descripción para mostrarle al dueño al momento de elegir.
     */
    public function description(): string
    {
        return match ($this) {
            self::PUBLIC => 'Cualquiera puede encontrarla y verla.',
            self::LINK => 'Solo quien tenga el enlace puede verla, no aparece en búsquedas.',
            self::PRIVATE => 'Solo tú. Los demás deben pedirte acceso.',
        };
    }

    /**
     * Indica si la wishlist necesita un token de enlace para compartirse.
     */
    public function needsShareToken(): bool
    {
        return $this === self::LINK;
    }

    /**
     * Indica si se puede llegar a la wishlist sin permiso explícito del dueño.
     */
    public function isReachableWithoutApproval(): bool
    {
        return $this !== self::PRIVATE;
    }

    /**
     * Todas las etiquetas válidas, para reglas de validación.
     */
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

        throw new \ValueError("Visibilidad no válida: {$label}");
    }
}
