<?php

namespace App\Enums;

/**
 * Una lista es pública o privada, y no hay tercer caso.
 *
 * Existió un `LINK` («por enlace») como nivel intermedio, y se eliminó porque
 * dejó de ser un nivel distinto: desde que la privada también tiene enlace, la
 * de enlace era una privada a la que su dueño nunca invitaba a nadie. Dos
 * nombres para lo mismo obligan a explicar una diferencia que no existe.
 *
 * Lo que era «por enlace» ahora es sencillamente una lista privada que se
 * comparte con su enlace, y la misma lista admite además invitar gente. Ya no
 * hay que elegir entre las dos formas de repartirla.
 */
enum WishlistVisibility
{
    case PUBLIC;
    case PRIVATE;

    /**
     * Valor que se guarda en la base: sin tildes y en snake_case a propósito.
     * Lo que se muestra en pantalla es title().
     */
    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'publica',
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
            self::PRIVATE => 'Privada',
        };
    }

    /**
     * Descripción para mostrarle al dueño al momento de elegir.
     */
    public function description(): string
    {
        return match ($this) {
            self::PUBLIC => 'Cualquiera puede encontrarla, salvo que tu perfil sea privado.',
            self::PRIVATE => 'Solo quien tú invites o quien tenga su enlace.',
        };
    }

    /**
     * Indica si la wishlist necesita un token de enlace para compartirse.
     *
     * La privada lo lleva siempre: el enlace es su segunda puerta, la de quien
     * no va a seguir a nadie —la tía que no usa la app— sin que el dueño tenga
     * que abrirle la lista de otra forma.
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
