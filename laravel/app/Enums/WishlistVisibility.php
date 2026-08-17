<?php

namespace App\Enums;

enum WishlistVisibility
{
    case PUBLIC;
    case LINK;
    case PRIVATE;

    /**
     * Valor que se guarda en la base: sin tildes y en snake_case a propósito.
     * Lo que se muestra en pantalla es title().
     */
    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'publica',
            self::LINK => 'por_enlace',
            self::PRIVATE => 'privada',
        };
    }

    /**
     * Cómo se escribe de cara al usuario, con tilde y todo.
     */
    public function title(): string
    {
        return match ($this) {
            self::PUBLIC => 'Pública',
            self::LINK => 'Por enlace',
            self::PRIVATE => 'Privada',
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
     *
     * Toda lista que no sea pública lleva enlace, la privada incluida. Es la
     * puerta para quien no está en el círculo —la tía que no usa la app y no
     * va a seguir a nadie— sin obligar al dueño a abrir la lista entera.
     *
     * La diferencia que queda entre LINK y PRIVATE es qué más admiten: la
     * privada además se reparte a dedo entre seguidores; la de enlace vive
     * solo de su enlace.
     */
    public function needsShareToken(): bool
    {
        return $this !== self::PUBLIC;
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
