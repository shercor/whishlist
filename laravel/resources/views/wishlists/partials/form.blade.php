@php
    /** @var \App\Models\Wishlist|null $wishlist */
    $actual = old('visibility', $wishlist?->visibility ?? \App\Enums\WishlistVisibility::PRIVATE->label());
@endphp

<div class="campo">
    <label for="name">Nombre</label>
    <input id="name" type="text" name="name" maxlength="150" required
           value="{{ old('name', $wishlist?->name) }}" placeholder="Mi cumpleaños 2026">
</div>

<div class="campo">
    <label for="description">Descripción <span class="pista">(opcional)</span></label>
    <textarea id="description" name="description" maxlength="500">{{ old('description', $wishlist?->description) }}</textarea>
</div>

<div class="campo">
    <label for="event_date">Fecha del evento <span class="pista">(opcional)</span></label>
    <input id="event_date" type="date" name="event_date"
           value="{{ old('event_date', $wishlist?->event_date?->format('Y-m-d')) }}">
</div>

<div class="campo">
    <label>Quién puede verla</label>
    <div class="opciones">
        @foreach (\App\Enums\WishlistVisibility::cases() as $visibilidad)
            <label class="opcion">
                <input type="radio" name="visibility" value="{{ $visibilidad->label() }}"
                       @checked($actual === $visibilidad->label())>
                <span>
                    <strong>{{ ucfirst($visibilidad->label()) }}</strong>
                    <span>{{ $visibilidad->description() }}</span>
                </span>
            </label>
        @endforeach
    </div>
</div>
