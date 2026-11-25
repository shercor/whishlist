---
name: diseno-wishlist
description: >-
  Sistema visual de la app wishlist (Angular + Ionic + Tailwind en app/): el ámbar de los regalos
  como color de marca y de "lo encendido", vidrio translúcido en modo oscuro sobre el fondo de
  regalos, la barrita encendida para lo elegido, línea ámbar tenue en los bordes, resplandor
  ámbar, tarjetas de regalo con foto, botones, chips, menús, hojas, avatares y logo. Usar SIEMPRE
  que se cree o se cambie cualquier cosa visible en app/ (una pantalla, un componente, un modal,
  un menú, un botón, colores, estilos, CSS o clases de Tailwind), aunque el pedido no hable de
  diseño ("agrega un botón para...", "haz una pantalla de...", "muestra X en la lista"), y cuando
  se pregunte por qué algo se ve como se ve.
---

# Diseño de wishlist

La app tiene identidad propia: **el ámbar de los regalos** (`#f59e0b`, el del
patrón `patron-regalos.svg` y la estrella del logo) es la marca y, además, la
señal de **lo encendido**: lo que está elegido, lo que se va a tocar y la acción
principal. Todo lo demás es neutro. En modo oscuro las superficies son de
**vidrio** (translúcidas y con desenfoque) para que el fondo de regalos se
adivine detrás. En modo claro, blancas y limpias.

Partió del diseño de tracker-gastos (fondo gris, tarjetas, controles de 40 px)
y se separó de él: aquí no hay azul, el principal es ámbar y hay vidrio. Si
algo de este archivo choca con lo de tracker-gastos, en este proyecto manda
este archivo.

**Vista de referencia:** el detalle de una lista,
`app/src/app/features/wishlists/wishlist.page.html`. Junta casi todo: barra de
vidrio, botones, tarjetas con foto, chips, paginación y hojas. Ante la duda,
copiar cómo lo resuelve esa vista.

## Si también está ui-ux-pro-max

`ui-ux-pro-max` es una guía genérica de UI/UX instalada en el proyecto. Se
activa con las mismas tareas que esta, así que muchas veces se cargan las dos.
El usuario confía en ella para **la estructura y las ideas**, no para el
**estilo**:

- **De ui-ux-pro-max se toma:** cómo ordenar la pantalla y su HTML (bloques,
  resúmenes, grillas, jerarquía, qué va primero), los estados (vacío, carga,
  error), la accesibilidad, los tamaños de toque y las ideas de producto
  (por ejemplo, avisar cuándo se suelta una reserva). En eso puede cambiar lo
  que hay.
- **De este archivo se toma, siempre:** el estilo, es decir los colores (el
  ámbar y los neutros, sin la paleta que proponga la skill), los efectos
  visuales (vidrio, línea ámbar tenue, resplandor, barrita), las fuentes de §7
  y las clases de componente de `tailwind.css`. Una propuesta de
  ui-ux-pro-max se arma con las piezas de aquí (`.card`, `.gift-grid`,
  `.gift-card`, chips, `.section-title`...), no con CSS propio de la pantalla.
- **Al informar el cambio**, se dice en una línea qué recomendación de estilo
  de ui-ux-pro-max no se siguió y por qué.
- **El usuario puede elegir.** Si pide usar ui-ux-pro-max entera (estilo
  incluido) para una tarea, se usa esa, avisándole antes qué regla de aquí se
  rompe.
- **No crear un segundo sistema de diseño.** Nada de
  `search.py --design-system --persist` (que crea `design-system/`) en este
  repo, salvo que el usuario lo pida.

## Dónde vive cada cosa

| Archivo | Contenido |
|---|---|
| `app/src/tailwind.css` | Color de marca y variables, y todas las clases de componente (`.card`, `.control`, `.gift-card`, `.photo-frame`, menús, barra...). Cada bloque trae un comentario con su porqué |
| `app/src/animations.css` | Curvas, keyframes y clases de animación |
| `app/src/patron-regalos.svg` | El patrón de regalos del fondo |
| `app/public/logo-wishlist-sobre-*.png` | El logo en sus dos versiones (texto claro u oscuro) |
| `app/src/app/shared/` | Piezas compartidas: `avatar.ts`, `user-menu.ts` (foto y menú de la cuenta), `sign-out.ts` |
| `app/src/app/core/theme/theme.service.ts` | Modo claro u oscuro, por clase (`.ion-palette-dark` en `<html>`) y elegible |

Antes de escribir una clase nueva, buscar si ya existe en `tailwind.css`. Una
clase nueva va ahí, con un comentario en español que diga para qué sirve,
dónde se usa y **por qué** es así: el resto del archivo está escrito de esa
forma y es lo que permite tocarlo después sin romper nada.

## Herramientas

- **Ionic** (componentes desde `@ionic/angular`, íconos `*-outline` de
  ionicons) para la estructura, y **Tailwind 4 sin preflight y con utilidades
  sin layer** para espaciado, color y tipografía. Nunca `@import 'tailwindcss'`
  completo: el reset pisa a Ionic.
- El modo oscuro es **por clase**: en el HTML se escribe `dark:` (la variante
  está definida sobre `.ion-palette-dark`) y en `tailwind.css` las reglas
  oscuras van con `:where(.ion-palette-dark) ...`, que no suma especificidad.
- Sin librerías de UI. Las fuentes van dentro de la app (§7), nunca desde
  Google Fonts ni otro CDN.

## 1. El ámbar: marca y "lo encendido"

`--ion-color-primary` es el ámbar, así que lo toman los botones sólidos, las
casillas y el foco de Ionic. Úsalo con significado:

- **Sí:** la acción principal de una zona (`ion-button` sólido), lo elegido o la
  pantalla actual, lo que se está por tocar (hover o foco), los contadores, la
  prioridad "Alta" y las marcas de la marca (logo, regalito del título).
- **No:** decorar. Si todo brilla, nada se lee como activo.

Contraste, porque el ámbar puro sobre blanco se lee mal (2:1):

- **Texto, enlaces, íconos sueltos y contornos de foco:** `var(--accent-text)`
  (ámbar 700 en claro y 400 en oscuro). Nunca `text-[var(--ion-color-primary)]`
  para un texto.
- **Texto sobre un relleno ámbar:** oscuro, `var(--ion-color-primary-contrast)`.
  Nunca blanco.
- **Fondos suaves de ámbar:** `bg-amber-50 dark:bg-amber-500/15`, o
  `color-mix(in srgb, var(--gift-amber) N%, ...)` en CSS.

El resto de los colores, solo con significado:

| Color | Significa | Ejemplos |
|---|---|---|
| Ámbar | Marca, principal, elegido, pendiente | Agregar regalo, sección actual, "★ Alta", solicitud pendiente |
| Esmeralda | Es tuyo o ya está hecho | "Lo reservaste tú", "Recibido", "Aprobada" |
| Neutro | Estados sin acción y todo lo demás | "Media", "Baja", "Ya lo reservaron", "Ya lo tiene" |
| Rojo | Errores y salidas destructivas | `role="alert"`, `.link-danger`, `color="danger"`, Cerrar sesión |

Azul no se usa. `.notice-info` es neutro, de vidrio. `.notice-warning` es ámbar
y solo sirve para algo que de verdad pide atención.

## 2. Vidrio en modo oscuro, sobre el fondo de regalos

- Toda página lleva `<ion-content class="gift-background" [fullscreen]="true">`.
  El fondo de regalos solo se ve en modo oscuro. `fullscreen` hace que el
  contenido pase por debajo de la barra de vidrio.
- **Barra superior:** de vidrio en los dos modos (blanco 72 % o `neutral-950`
  62 %, con `blur(14px)`), sin sombra y con una **línea ámbar tenue** abajo.
- **Tarjetas** (`.card`): blancas en claro. En oscuro, `neutral-900` al 62 %
  con `blur(16px) saturate(140%)`. Los campos dentro de ellas también son
  translúcidos.
- **Menús y popovers:** vidrio más opaco (80–88 %, `blur(18px)`), porque flotan
  sobre contenido con texto.
- Cuanto más texto hay detrás, más opaco el vidrio. Si al mirarlo se lee lo de
  atrás, está demasiado transparente o el desenfoque no está actuando (ver §10).

## 3. La línea ámbar tenue, el resplandor y la barrita

Son tres recursos que se repiten y amarran la identidad:

- **Línea ámbar tenue** (`color-mix(in srgb, var(--gift-amber) 26–35%, transparent)`,
  1 px): en los bordes de la estructura (bajo la barra superior, entre el menú
  lateral y el contenido, y el contorno del menú de la cuenta). Dentro de una
  superficie, los separadores son neutros (`black/6` o `white/7`).
- **Resplandor ámbar:** un `radial-gradient` que nace arriba a la izquierda,
  detrás de la marca o de tu identidad, y se apaga hacia abajo (10–14 % de
  ámbar). Está en el menú lateral, detrás del logo, y en el menú de la cuenta,
  detrás de tu foto. Uno por superficie, nunca de relleno.
- **Barrita encendida:** marca la fila elegida en una lista vertical de
  destinos u opciones. Son 3 px de ámbar pegados al borde izquierdo, con un
  brillo suave y la animación `menu-bar-in`. La fila va a todo el ancho y sin
  fondo teñido, con texto e ícono en `--accent-text`.
  - En el menú lateral marca la sección actual.
  - En menús desplegables también aparece al pasar el mouse o llegar con el
    teclado (`.ion-focused`).
  - En las **filas que abren otra pantalla dentro de una tarjeta** (Mis listas,
    Descubrir, personas, avisos...) aparece al pasar el mouse o con el
    teclado. Ya está global: basta con que la fila sea `.tappable-row` e hija
    directa de `.card`. Así "esto se abre" se dice igual en toda la app.
  - En una opción destructiva, la barrita es roja (`--bar-color`).
  - Hay que darle `z-index: 1` al `::before` sobre `ion-item`, o el fondo de la
    fila la tapa. Copiar los bloques de `ion-menu.app-menu` o de
    `ion-popover.user-menu` en `tailwind.css`.

## 4. Botones y controles

- **Una acción principal por zona:** `ion-button` sólido (ámbar con texto
  oscuro). Las secundarias van con `fill="outline"` sin `color`, que quedan
  neutras con borde tenue y vidrio en oscuro. Las destructivas usan
  `color="danger"` o `.link-button.link-danger`.
- Sin mayúsculas ni sombra de Material, con radio `lg` y 40 px de alto
  (`--control-height`). Ya está global: no ponerle utilidades de alto ni de
  radio a un `ion-button`.
- **En el teléfono, las secundarias solo con ícono**, para que la principal
  llene la fila: `class="compact-mobile"`, el texto en
  `<span class="button-label">` y `aria-label` en el botón.
- Campos, selects, casillas con texto y botones con forma de opción usan
  `.control`: mismo alto, radio y fuente, y 16 px de letra en el teléfono
  (evita el zoom de iOS). Lo elegido (`:checked`, `aria-pressed`) se tiñe de
  ámbar.
- Paginación: `button.control.page-button`, con la actual en ámbar sólido
  (`aria-current="page"`), flechas y la línea "11–20 de 26".
- Áreas táctiles de 40 px como mínimo.

## 5. Tarjetas de regalo y fotos

- **Grilla:** `.gift-grid`. Las columnas dependen del ancho de la lista, no de
  la pantalla, así que el `<main>` lleva `@container`. Son 2 en el teléfono y,
  desde ahí, tantas como quepan hasta un **máximo de 5**; con más espacio, las
  tarjetas crecen en vez de sumar columnas.
- **Tarjeta** (`.card.gift-card`): foto **cuadrada** arriba, con la prioridad
  encima en un chip sólido (`.gift-card-priority`, "★ Alta" en ámbar). Abajo,
  la categoría pequeña, el nombre en hasta 2 líneas, las notas en hasta 2
  líneas y el precio al pie (`mt-auto`), con los chips de estado a su lado. Al
  pasar el mouse sube 2 px y su borde se enciende de ámbar.
- **Fotos de producto: siempre completas, nunca recortadas.** Se usa
  `.photo-frame` con dos `<img>` de la misma URL: la primera (`aria-hidden`) es
  el relleno ampliado y borroso, y la segunda la foto en `object-fit: contain`.
  La foto toca los bordes por su lado largo y el resto se llena con ella misma
  borrosa sobre crema. Vale para tarjetas y para miniaturas en hojas.
- **Sin foto:** el regalo de la marca (`.gift-mark`) sobre crema
  (`bg-amber-50 dark:bg-amber-500/10`, sobre blanco). Nunca una inicial gris.
  En una fila, una lista lleva el mismo regalo en un recuadro redondeado
  (`rounded-xl bg-amber-50 dark:bg-amber-500/15`). Las iniciales quedan solo
  para las personas (`app-avatar`).
- Lo que ya no está disponible (recibido, o reservado por otro) va con
  `opacity-60`.
- **En una lista ajena**, cada regalo disponible lleva al pie el botón
  "Lo regalo yo" (`ion-button.gift-card-action`, con contorno: una grilla de
  botones sólidos no deja ver nada). Reserva sin abrir la hoja; el resto de la
  tarjeta abre la hoja con la ficha. Arriba, la foto y el nombre de la dueña
  (`.owner-link`) y la fecha del evento con cuenta regresiva (`eventCountdown`).

## 6. Menús, hojas y jerarquía de la pantalla

- **Lo principal primero.** Si una pantalla tiene un contenido central (los
  regalos), no lo empujes hacia abajo con cajas secundarias.
  - Las acciones secundarias con mucho contenido van a una **hoja**: por
    ejemplo, Compartir abre `share-sheet.ts` con el enlace y "Quién la ve".
  - Las aclaraciones permanentes van en **una línea con ícono** sobre el
    contenido (la de "No verás si alguien reservó tus regalos..."), no en una
    franja.
- **Listas agrupadas** (Voy a regalar: reservas por persona): lo que agrupa
  es un **encabezado de sección fuera de las tarjetas**, sobre el fondo, con
  foto, `h2` grande y, debajo, un enlace secundario gris (`a.list-link` con
  `chevron-forward`) y el chip de la fecha. Debajo, el contenido del grupo: en
  una tarjeta con filas o, si son regalos, en `.gift-grid` como en una lista.
  Nunca el encabezado como una fila más de la tarjeta: se lee al mismo nivel
  que lo que agrupa. Entre grupos, bastante más espacio que dentro de uno
  (`gap-9`).
- **Resumen arriba, si ayuda a decidir.** Voy a regalar abre con una tarjeta
  de tres cifras (regalos, personas, total aprox.): número en `font-display`
  y su nombre chico debajo, en un `<dl>`. Una cifra que no se puede dar
  completa (faltan páginas, monedas distintas) no se muestra: mejor nada que
  un número equivocado.
- **Poco texto explicativo.** Bajo el título de una pantalla, una frase corta
  como mucho. Lo que vale para toda una sección se dice una vez bajo su título
  (como "Pueden recibir tus listas privadas." en Te siguen), no en cada fila.
- **Las acciones poco frecuentes no compiten con el contenido.** Quitar a un
  seguidor o quitarle el acceso a alguien va en un ícono discreto
  (`fill="clear" color="medium"`, con `aria-label` y confirmación), no en un
  enlace rojo en cada fila. El rojo queda para lo que borra algo de verdad.
- **Ajustes que se prenden o se apagan:** `ion-toggle`, con su explicación en
  una línea a la izquierda (Mi perfil, Privacidad).
- **Hojas** (`ion-modal.auto-height` con `.modal-body`): en el teléfono suben
  desde abajo (`breakpoints [0, 1]`) y en escritorio son una tarjeta de 28rem.
  Arriba llevan un `h2` y el botón de cerrar (`fill="clear" color="medium"`).
- **Las secciones son cinco**, definidas en `shared/navigation.ts` (`SECTIONS`):
  Mis listas, Descubrir, Voy a regalar, Gente y Notificaciones, esta última
  siempre al final. Cinco es lo que cabe cómodo en la barra inferior. Antes de
  sumar una sección nueva, busca dentro de cuál cabe: así Personas vive dentro
  de Gente, como buscador, y Avisos y Solicitudes dentro de Notificaciones.
- **En el teléfono, barra inferior** (`shared/tab-bar.ts`), como la de Tenpo.
  - Es de vidrio, con la línea ámbar tenue arriba.
  - La sección actual lleva la barrita encendida en el borde de arriba, y su
    ícono y texto en ámbar, sin negrita: en negrita el texto salta y no cabe.
  - El globito suma los contadores de la sección.
  - Se ve en todas las pantallas con sesión. En las de más adentro (una lista,
    editar) ninguna sección queda marcada y la barrita se apaga.
  - La barrita es una sola y se desliza hasta la sección elegida.
  - El menú lateral se apaga en el teléfono, así que el ☰ desaparece solo.
- **Desde `md`, menú lateral:** solo para navegar. Lleva el logo y las mismas
  secciones, con un ancho fijo de 15rem. Los ajustes no van ahí.
- **Pestañas dentro de una sección** (`.segmented`, como Avisos y Solicitudes):
  son controles parejos con la actual teñida de ámbar y su contador. Cada
  pestaña tiene su dirección, pero al cambiar no hay animación ni se suma al
  historial (`navigateRoot` con `animated: false`). Si solo cambian lo que se
  ve, sin dirección propia (Agregar regalo: Del catálogo y Escribirlo yo), son
  `button.control.segmented-item` con `aria-pressed`. Nunca `ion-segment`: sus
  mayúsculas y su raya son de Android y no combinan con el resto.
- **Tu cuenta:** tu foto arriba a la derecha de cada barra
  (`<ion-buttons slot="end"><app-user-menu /></ion-buttons>`). Al tocarla se
  abre el menú con Mi perfil, el modo claro u oscuro y, al final y en rojo,
  Cerrar sesión.
- **Títulos de sección:** `.section-title`, con la cuenta tras un punto medio
  ("Regalos · 26", "Te siguen · 0"), nunca entre paréntesis.
  Aclaraciones: `.hint`. Texto secundario:
  `text-sm text-neutral-500 dark:text-neutral-400`.

## 7. Marca: logo, regalito y avatares

- **Logo** en el menú lateral, al entrar y al crear cuenta. Hay dos archivos,
  uno con el texto claro y otro con el texto oscuro, y se elige con
  `theme.isDark()`:
  `[src]="theme.isDark() ? 'logo-wishlist-sobre-oscuro.png' : 'logo-wishlist-sobre-claro.png'"`.
  El texto alternativo es `alt="wishlist"`.
  - El nombre de la app es **wishlist**. "whishlist", con h, es un error
    heredado en el código: no lo propagues a textos nuevos.
- **Regalito en cada barra, sin el nombre de la pantalla a la vista** (ya está
  en el `h1` del contenido). El nombre queda solo para lectores de pantalla:
  `<ion-title><span class="title-gift" aria-hidden="true"></span><span class="sr-only">Título</span></ion-title>`.
- **Fuentes, redondeadas como el logo** (definidas en `tailwind.css`, junto
  al color de marca):
  - **Nunito** para todo el texto: es la `--ion-font-family` y `font-sans`.
  - **Fredoka** (`font-display`) para los títulos: el `h1` de cada pantalla
    y los `h2` de hojas, estados vacíos y encabezados de grupo ya la toman
    solos. También para un número que hace de título, como los del resumen de
    Voy a regalar. No para `.section-title` ni para el texto de las tarjetas.
  - Vienen de `@fontsource-variable/*`, cargadas en `angular.json`: funcionan
    sin conexión y no avisan a Google de quién abre la app. Para cambiar una,
    se cambia el paquete y el nombre en `@theme`.
- **Avatares** (`app-avatar`): foto o iniciales. Cuando el avatar es un botón,
  o es "tú", lleva un **aro con separación**: 2 px de espacio entre la foto y el
  aro, que se enciende de ámbar al pasar el mouse o con lo que abre activo. Ver
  `.user-menu-trigger` y `.user-menu-avatar`. El espacio hace que el aro se lea
  como marco con cualquier foto.

## 8. Movimiento

Solo `transform` y `opacity`, con las clases de `animations.css` y las curvas
`--ease-out` o `--ease-spring`:

| Clase | Cuándo |
|---|---|
| `.enter-up` | Bloques y filas que entran. En listas con `[style.--i]`, limitado a 10, para el escalonado |
| `.enter-down` | Avisos que aparecen arriba |
| `.pressable` | Todo lo que se toca: se hunde un poco |
| `.icon-float` | El ícono de un estado vacío |
| `menu-bar-in` | La barrita encendida al aparecer |

- El hover que mueve cosas va dentro de `@media (hover: hover)`, porque en el
  teléfono quedaría pegado después de tocar.
- `animations.css` ya respeta `prefers-reduced-motion`.
- Al cambiar de pantalla, la animación por defecto es el **fundido**
  (`core/transitions/`); el menú de administrador permite probar otras.
  Cambiar de sección también anima, desde el menú lateral y desde la barra
  inferior. Las pestañas dentro de una sección (`.segmented`) no.
- Si hay datos y se recarga una parte (por ejemplo, al cambiar de página), esa
  parte baja a `opacity-60` con `aria-busy` en vez de volver al esqueleto.

## 9. Estados de carga y vacíos

- **Carga:** `ion-skeleton-text animated` con la forma real, sea la grilla de
  tarjetas cuadradas o las filas.
- **Vacío:** tarjeta centrada con un ícono en círculo ámbar suave
  (`bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300`,
  `.icon-float`), un `h2`, una frase de qué hacer y el botón con la acción.

## 10. Trampas de Ionic ya resueltas

- **El vidrio de un popover o modal no desenfoca.** Ionic deja "llena" su
  animación de opacidad al abrir, y mientras exista Chrome corta lo que ve
  `backdrop-filter`. Al abrirse (`didPresent`), fijar el estado final y soltar
  la animación: ver `settle()` en `shared/user-menu.ts`.
- **Las variables de Ionic no cambian.** Ionic las define con clases, que le
  ganan a una regla de solo elemento. Prefijar con `:root` (como en
  `:root ion-split-pane`) o con la clase del modo (`ion-header.header-md`).
- `ion-button` mueve su `aria-label` al botón interno. Para encontrarlo desde
  un script, buscar por el ícono o por el texto.
- Los estilos de afuera le ganan a `::slotted`, así que
  `ion-button.algo ion-icon { margin: 0 }` funciona.

## 11. Accesibilidad y textos

- Foco visible en todo lo que se toca, con un contorno de 2 px en
  `--accent-text`.
- `aria-label` en español en todo botón sin texto visible, y
  `aria-hidden="true"` en íconos y decoración. En menús: `role="menu"` y
  `menuitem`, `aria-haspopup` y `aria-expanded` en el disparador, y
  `aria-current="page"` en lo actual.
- Los textos de la interfaz van en español de Chile, tuteando, cortos y
  concretos.

## Revisar antes de terminar

- [ ] ¿Se ve bien en **modo claro y oscuro**, en el **teléfono (~420 px)** y en
      **escritorio (~1280 px)**?
- [ ] ¿El ámbar marca solo lo principal, lo elegido o lo encendido? ¿Los textos
      ámbar usan `--accent-text` y los rellenos ámbar llevan texto oscuro?
- [ ] ¿En modo oscuro las superficies nuevas son de vidrio, y se lee bien lo que
      tienen encima?
- [ ] ¿La página lleva `gift-background` y `[fullscreen]`, y su título el
      regalito y la foto de la cuenta?
- [ ] ¿Hay una sola acción principal por zona? ¿Las secundarias se compactan en
      el teléfono?
- [ ] ¿Las fotos se ven completas (`.photo-frame`) y lo que no tiene foto lleva
      el regalo sobre crema?
- [ ] ¿Hay esqueleto con la forma real, estado vacío con acción, `.pressable` y
      entradas de `animations.css`?

Para comprobarlo con capturas reales, sigue la sección siguiente.

## Comprobar con capturas

`scripts/capturas.mjs` abre Chrome sin ventana, entra con un token y saca una
captura por cada combinación de ruta, ancho y modo. Puede, además, pasar el
mouse sobre algo (para ver la barrita o un borde encendido) y tocar algo (abrir
un menú, una hoja...), y saca una captura más por cada cosa. Los pasos:

```bash
# 1. Un token para un usuario de prueba de los seeders (ver HANDOFF.md):
TOKEN=$(docker compose exec -T app php artisan tinker --execute \
  'echo App\Models\User::where("email","ana@whishlist.test")->first()->createToken("capturas")->plainTextToken;')

# 2. Las capturas (carpeta de salida en el scratchpad de la sesión):
node .claude/skills/diseno-wishlist/scripts/capturas.mjs --token="$TOKEN" \
  --out=<carpeta> --paths=/listas/1,/perfil --widths=420,1280 --themes=oscuro,claro \
  [--hover='.card > .tappable-row'] [--click=.user-menu-trigger] [--scroll=300]

# 3. Revocar el token al terminar:
curl -s -X DELETE localhost:8080/api/v1/tokens/current \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

Mira las capturas antes de dar algo por terminado. Ahí se notan cosas que el
código no muestra: un vidrio que no desenfoca, un texto ámbar ilegible en modo
claro, una barra que tapa el contenido.

Ningún producto de prueba trae foto. Para revisar tarjetas con imágenes, sube
una foto a una ficha propia con `PUT /api/v1/products/{id}/image` y quítala al
terminar.
