<?php

namespace App\Enums;

enum ItemPriority
{
    case LOW;
    case MEDIUM;
    case HIGH;

    /**
     * Valor que se guarda en la base. No se muestra en pantalla: para eso
     * está title(). Cambiar estas cadenas invalida las filas ya guardadas.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => 'baja',
            self::MEDIUM => 'media',
            self::HIGH => 'alta',
        };
    }

    /**
     * Cómo se escribe de cara al usuario.
     */
    public function title(): string
    {
        return match ($this) {
            self::LOW => 'Baja',
            self::MEDIUM => 'Media',
            self::HIGH => 'Alta',
        };
    }

    /**
     * Texto pensado para quien va a regalar, no para el dueño de la lista.
     */
    public function hint(): string
    {
        return match ($this) {
            self::LOW => 'Me gustaría, pero sin apuro.',
            self::MEDIUM => 'Me haría ilusión.',
            self::HIGH => 'Es lo que más quiero.',
        };
    }

    /**
     * Peso para ordenar: primero lo que más quiere el dueño.
     */
    public function weight(): int
    {
        return match ($this) {
            self::HIGH => 3,
            self::MEDIUM => 2,
            self::LOW => 1,
        };
    }

    public function colors(): string
    {
        return match ($this) {
            self::LOW => '#6c757d',
            self::MEDIUM => '#0d6efd',
            self::HIGH => '#dc3545',
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

        throw new \ValueError("Prioridad no válida: {$label}");
    }
}
