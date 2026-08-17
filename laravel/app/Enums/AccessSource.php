<?php

namespace App\Enums;

/**
 * Por dónde entró alguien a una lista privada.
 *
 * No es un detalle de auditoría: decide cuánto dura el acceso. El que entró
 * porque el dueño lo invitó o porque se lo pidió depende de seguir siendo su
 * seguidor, y lo pierde si deja de serlo. El que entró con el enlace se
 * sostiene solo en el enlace, así que no se le exige seguir a nadie y solo se
 * le quita a mano.
 */
enum AccessSource
{
    case INVITATION;
    case REQUEST;
    case LINK;

    /**
     * Valor que se guarda en la base. Para mostrar en pantalla, title().
     */
    public function label(): string
    {
        return match ($this) {
            self::INVITATION => 'invitacion',
            self::REQUEST => 'solicitud',
            self::LINK => 'enlace',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::INVITATION => 'Lo invitaste',
            self::REQUEST => 'Te lo pidió',
            self::LINK => 'Entró con el enlace',
        };
    }

    /**
     * Si este acceso exige que la persona siga al dueño para seguir en pie.
     */
    public function requiresFollow(): bool
    {
        return $this !== self::LINK;
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

        throw new \ValueError("Origen de acceso no válido: {$label}");
    }
}
