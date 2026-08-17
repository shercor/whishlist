<?php

namespace App\Enums;

use App\Models\WishlistItem;

/**
 * En qué estado ve un regalo quien está mirando la lista de otro.
 *
 * No se guarda en ninguna columna y no debe guardarse: es el cruce entre el
 * ítem y *quién* pregunta. El mismo regalo es RESERVED_BY_ME para quien lo
 * tomó y RESERVED para el resto, y no existe en absoluto para el dueño de la
 * lista. Guardarlo obligaría a recalcularlo por espectador, que es justo la
 * clase de dato que termina filtrándose.
 *
 * Antes estos cuatro estados vivían sueltos como texto dentro de la vista.
 */
enum GiftState
{
    case AVAILABLE;
    case RESERVED_BY_ME;
    case RESERVED;
    case RECEIVED;

    /**
     * Cómo se le anuncia al que mira. Es lo único que se muestra en pantalla.
     */
    public function title(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Disponible',
            self::RESERVED_BY_ME => 'Lo reservaste tú',
            // Deliberadamente impersonal: que esté tomado se dice, por quién
            // no se dice nunca.
            self::RESERVED => 'Ya lo reservaron',
            self::RECEIVED => 'Ya lo tiene',
        };
    }

    /**
     * Clase de la etiqueta con que se pinta.
     */
    public function badge(): string
    {
        return match ($this) {
            self::AVAILABLE => 'etiqueta',
            self::RESERVED_BY_ME => 'etiqueta etiqueta-ok',
            self::RESERVED => 'etiqueta etiqueta-espera',
            self::RECEIVED => 'etiqueta',
        };
    }

    /**
     * Si todavía se puede ofrecer a quien mira.
     */
    public function isOfferable(): bool
    {
        return $this === self::AVAILABLE;
    }

    /**
     * Deduce el estado a partir de los contadores que carga GiftController.
     * El orden de las preguntas importa: recibido gana sobre reservado, porque
     * si ya lo tiene da igual quién lo hubiera tomado.
     */
    public static function forViewer(WishlistItem $item): self
    {
        if ($item->isReceived()) {
            return self::RECEIVED;
        }

        if (($item->mine_count ?? 0) > 0) {
            return self::RESERVED_BY_ME;
        }

        if (($item->reserved_count ?? 0) > 0) {
            return self::RESERVED;
        }

        return self::AVAILABLE;
    }
}
