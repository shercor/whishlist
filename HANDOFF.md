# Continuar en otro equipo

Estado del proyecto al **17 de agosto de 2026** y qué hacer para retomarlo en
otra máquina o en una sesión nueva.

`whishlist` es una aplicación Laravel 13 dockerizada. Los usuarios arman listas
de deseos; otros pueden reservar los regalos sin que el dueño se entere, para
no arruinar la sorpresa. Ese detalle es el que manda sobre todo el diseño.

---

## 1. Puesta en marcha en el equipo nuevo

```bash
git clone https://github.com/shercor/whishlist.git
cd whishlist
```

### Antes del primer commit: la identidad git

**Esto es lo más fácil de olvidar.** La identidad está configurada *local al
repositorio*, y esa configuración **no viaja con el clon**. En una máquina
nueva heredarás la global, que es la de trabajo, y firmarás commits de un repo
personal con el correo de ideauno.

```bash
git config user.name  "Sebastian Herrera"
git config user.email "sebastian.herreracor@gmail.com"
```

Sin `--global`: la global debe seguir siendo `sebastian.herrera@ideauno.cl`
para los repos de Bitbucket.

Verifica antes de commitear:

```bash
git config user.email    # debe decir sebastian.herreracor@gmail.com
```

### Autenticación con GitHub

El remoto es HTTPS. En el equipo nuevo no existe `~/.git-credentials`, así que
el primer `push` pedirá usuario y token:

- usuario: `shercor`
- contraseña: un **Personal Access Token** de GitHub (no la contraseña de la
  cuenta). Necesita scope `repo`.

Para que quede guardado: `git config --global credential.helper store`.

`gh` CLI no estaba instalado en el equipo anterior; si lo instalas, `gh auth
login` resuelve todo esto de una.

### Levantar el entorno

```bash
make setup
```

Eso solo. Hace, en este orden: `.env` de docker → levanta contenedores →
`composer install` dentro del contenedor php → `.env` de Laravel →
`key:generate` → `storage:link` → `migrate` → `seed`. Al terminar imprime la
URL y las credenciales.

Si los puertos por defecto chocan con otro proyecto, primero
`cp .env.example .env`, ajústalos, y recién ahí `make setup`.

### Comprobar que quedó bien

```bash
curl -o /dev/null -w '%{http_code}\n' http://localhost:8080   # 302 → /login
make test                                                     # 64 passed
```

Entra a http://localhost:8080: te lleva al login. Puedes crear una cuenta nueva
en `/register` —pide nombre, arroba, correo y contraseña— o entrar con un
usuario de demo, cuyo arroba es la parte del correo antes de la @ (`@ana`,
`@bruno`…): `ana@whishlist.test`,
`bruno@whishlist.test`, `camila@whishlist.test`, `diego@whishlist.test`, todos
con contraseña `password`. Administrador: `admin@whishlist.test` / `admin1234`.

### Qué NO viaja con el repositorio

Esto sorprende al llegar a la máquina nueva, así que conviene saberlo antes:

| No viaja | Consecuencia en el equipo nuevo |
|----------|----------------------------------|
| Los dos `.env` | Los crea `make setup` desde los `.env.example`. La `APP_KEY` será distinta. |
| La base de datos | Empiezas con lo que siembren los seeders: 4 usuarios de demo, no más. |
| `storage/app/public/` | **Todas las fotos subidas** —de perfil y de productos— se quedan acá. Las fichas mostrarán su placeholder. |
| `public/storage` | Es un symlink por entorno. Lo crea `make storage`, que ya corre dentro de `make setup`. |
| `usuarios-de-prueba.md` | Está en `.gitignore`. |

**Los ~100 usuarios de prueba tampoco viajan.** Se generaron con un script
suelto de una sesión, no con un seeder del repositorio, porque son datos de una
máquina y no parte del proyecto. En el equipo nuevo tendrás los cuatro de demo.
Si los quieres allá, hay que convertir ese generador en un seeder y commitearlo
—es una decisión pendiente, no un olvido—.

---

## 2. Estado actual

Commits en `main`:

| Commit    | Qué trae                                                  |
|-----------|-----------------------------------------------------------|
| `5474705` | Entorno dockerizado: PHP 8.4-FPM, nginx, MariaDB 11.4, Redis, Makefile, README |
| `a1b918e` | `db` y `redis` publicados solo en `127.0.0.1`             |
| `0a60c3d` | Modelo de datos: 4 enums, 8 migraciones, 7 modelos, 5 seeders |
| `830d138` | Factories de los 7 modelos y 49 tests del dominio          |
| `476cc54` | Capa de aplicación: policies, controladores, rutas, vistas |
| `b21ec12` | Servicio `scheduler`, sin el que nada programado corría    |
| `dbc046f` | Modo oscuro, estilos de celular, imagen y «me gusta» en productos |
| `d6af2cd` | `@username`, búsqueda de personas, copiar enlace, foto en caja cuadrada |
| `27872c0` | Seguidores, perfil privado y reparto de acceso lista por lista |
| `873d2c5` | «Por enlace» se fusiona en «privada»: una sola lista privada que se invita y se comparte |
| `b51c639` | Modal de detalle del regalo, copiar con ícono dentro del campo y avisos flotantes |
| `a59d261` | Foto de perfil en todas las vistas y buscador de personas con sugerencias |
| `d13d9a2` | Las solicitudes de seguimiento aparecen en «Solicitudes», con contador |
| `9663d7f` | El perfil muestra las listas privadas a las que ya tienes acceso |
| (el último) | Pone al día el HANDOFF y el README para el traslado de equipo |

**Hecho y verificado corriendo:** el entorno completo, el esquema de base de
datos, los seeders de catálogo y de demo, y los invariantes del dominio
(reserva única, sorpresa protegida, producto privado invisible, búsqueda
fulltext). Todo eso está automatizado en la suite, no solo probado a mano:
`make test` → **64 passed**.

**La aplicación ya se usa de punta a punta.** Probado por HTTP contra el
entorno real: registrarse, crear una lista, agregar un regalo escrito a mano,
entrar con otra cuenta, reservarlo, y verlo en «mis reservas» — mientras el
dueño de la lista no ve ni rastro de esa reserva. Registrarse pide nombre,
arroba, correo y contraseña (mínimo 8, repetida); **no hay verificación de
correo**, y al enviar el formulario quedas dentro.

Lo que trae la capa de aplicación:

| Pieza                          | Qué resuelve                                       |
|--------------------------------|----------------------------------------------------|
| `WishlistPolicy`               | los cuatro caminos para abrir una lista (ver «Quién ve la lista de quién») |
| `Follow` + `FollowController`  | seguir gente; el perfil privado aprueba, el público acepta solo |
| `AccessSource`                 | por dónde entró cada quien, que es lo que decide cuánto dura su acceso |
| `ReservationPolicy`            | la regla que la base no puede: el dueño jamás reserva en su propia lista |
| `WishlistItemPolicy`           | solo el dueño agrega, edita o marca recibido       |
| `ReservationService`           | traduce el choque de dos reservas simultáneas a un mensaje |
| `ReleaseExpiredReservations`   | comando programado cada hora en `routes/console.php` |
| `Product::scopeSearchPrefix()` | buscador del catálogo: «pelu» encuentra «Peluche»  |
| `Product::scopeBestFirst()`    | entre fichas repetidas del mismo producto, primero la más votada |
| `Wishlist::unlockByLink()`     | el enlace secreto se anota en la sesión, para no arrastrar el token por cada URL |
| `GiftState`                    | los cuatro estados con que un tercero ve un regalo |
| `ProductPolicy`                | solo se vota el catálogo público                   |

### El catálogo se cura solo

Un producto puede tener foto subida por quien lo creó (`image_path`, disco
`public`) además de la del sitio de la tienda (`image_url`); manda la subida.
Y cualquiera puede darle «me gusta» a una ficha del catálogo público, que es
lo que decide el orden en que se muestran: `bestFirst()` ordena por votos y,
entre las que empatan en cero —el estado inicial de todo—, adelanta a las que
al menos tienen foto. Así, de tres fichas del mismo producto, la bien hecha es
la que la gente ve.

Las fotos van al disco `public` con nombre aleatorio de 40 caracteres. Eso las
deja servidas por nginx sin pasar por PHP, y su privacidad descansa en que la
URL es inadivinable: el mismo trato que ya se le da al enlace secreto de una
lista.

### Cómo se nombra a una persona

Cada cuenta tiene un `username` único —el arroba— que es **lo único por lo que
se puede buscar**. `User::scopeSearchByUsername()` mira esa columna y ninguna
otra: si buscara por `name`, la opción de ocultar el nombre no serviría de
nada, y si mirara el correo bastaría con probar direcciones para saber quién
está registrado. Sin término de búsqueda no devuelve a nadie, a propósito: el
directorio completo de la plataforma no debe poder recorrerse.

Cada cuenta puede tener **foto de perfil** (`avatar_path`, disco `public`,
opcional siempre). Cuando no hay, no se deja el hueco ni un icono genérico: se
dibujan sus iniciales sobre un color sacado de su arroba, así el mismo usuario
tiene siempre el mismo círculo y se reconoce de una lista a otra. Todo eso vive
en el componente `<x-avatar>`, con tres tamaños fijos —`chico`, `normal`,
`grande`—. **Los tamaños son fijos a propósito:** en una lista donde unos
tienen foto y otros no, cualquier variación desalinea todas las filas.

Ojo con las iniciales: salen de `publicName()`, no de `name`. Si la persona
oculta su nombre, sus iniciales reales tampoco deben asomar; en ese caso salen
del arroba.

`AvatarService` es el único lugar donde se guarda y se borra una foto. Existe
para que el borrado de la anterior no se olvide: repartido entre el registro y
el perfil, es justo lo que se escapa y el disco se llena de fotos que ya nadie
muestra.

El nombre real es privado por defecto (`show_name` nace en `false`) y cada
persona decide en `/perfil` si quiere mostrarlo. La regla operativa:

> **Nunca `$user->name` en una vista. Siempre `$user->publicName()`.**

Ese método es el único punto por donde un nombre sale hacia la interfaz, y es
lo que respeta la decisión de cada persona. `handle()` devuelve el arroba
siempre, para cuando se quiere el identificador y no el nombre.

El perfil vive en `/u/{username}` y muestra **exactamente lo que esa persona
puede abrir**: las públicas si el perfil es alcanzable, más las privadas a las
que ya tiene acceso porque la invitaron o porque entró con el enlace. Lo que no
puede abrir no se cuenta ni se insinúa: decir «tiene 2 listas más que no puedes
ver» ya es contar algo de alguien que eligió no contarlo.

`visibleWishlistsFor()` filtra con la **propia policy** en vez de repetir sus
reglas en un `where`. Son cuatro caminos con matices —el acceso por enlace no
exige seguir al dueño, el invitado sí— y tenerlos escritos en dos lugares es
como se desincronizan: o se enseña algo que da 403 al pinchar, o se esconde
algo que sí se puede abrir. `FollowAccessTest::test_what_the_profile_lists_is_exactly_what_can_be_opened`
compara ambas listas y falla si se separan.

Hay una lista de `USERNAMES_RESERVADOS` en el modelo, porque el usuario aparece
en una URL y porque nadie debe poder hacerse pasar por el sistema. La migración
que rellena los usuarios de las cuentas viejas repite esa lista a mano en vez
de leer la constante: una migración ya ejecutada no puede cambiar de
comportamiento porque alguien edite el modelo después.

### Quién ve la lista de quién

Es la regla central del proyecto y tiene tres piezas que se combinan.

**0. La lista es pública o privada, y no hay tercer caso.** Existió un nivel
intermedio `por_enlace` y se eliminó: desde que la privada también lleva
enlace, «por enlace» era una privada a la que su dueño nunca invitaba a nadie
—dos nombres para lo mismo—. Hoy **una lista privada se reparte de las dos
formas a la vez**: se invita a gente elegida *y* se pasa el enlace, sin tener
que elegir entre ambas. La migración `merge_link_visibility_into_private`
convirtió las que había; los enlaces que ya circulaban siguen funcionando
porque `openByLink` busca por token sin mirar la visibilidad.

**1. El perfil** es privado o público, y nace privado. Uno privado se encuentra
por su arroba y hasta ahí: no muestra **ninguna** lista, ni siquiera las
marcadas como públicas, hasta que su dueño acepte que lo sigan. Si mostrara las
públicas igual, «perfil privado» no querría decir nada. Sus listas tampoco
salen en `/discover`.

**2. Los seguidores.** Un perfil público acepta al instante; uno privado deja
la solicitud pendiente. Seguir existe para acotar a quién se le puede dar una
lista privada: sin esto, el dueño tendría que elegir entre *todos* los usuarios
de la plataforma.

**3. El acceso a cada lista privada, una por una.** Los permisos son por lista,
no por persona: que alguien te siga no le abre tus tres listas privadas.

Los cuatro caminos para abrir una lista, y ninguno más:

| Camino | Requisito | Cuánto dura |
|--------|-----------|-------------|
| Ser el dueño | — | siempre |
| El enlace secreto | conocer el enlace | hasta que el dueño lo quite a mano |
| Invitación o solicitud aprobada | seguir al dueño **ahora** | se cae al dejar de seguir |
| Lista pública | perfil del dueño alcanzable | mientras siga siéndolo |

**Lo que hay que entender del tercero:** el acceso no se guarda como «tiene
permiso para siempre». La policy vuelve a preguntar por el seguimiento cada vez
que se abre la lista, así que dejar de seguir corta el acceso en ese instante y
**no hay ninguna tarea que limpie nada**. Está verificado por mutación: si se
quita esa segunda pregunta y se confía solo en la fila aprobada, cae
`FollowAccessTest::test_unfollowing_closes_a_private_wishlist_immediately` y no
cae ningún otro.

`AccessSource` es lo que distingue cuánto dura cada acceso: `invitacion` y
`solicitud` exigen seguir; `enlace` no, porque se sostiene en el enlace. Por eso
el enlace es la puerta de quien no va a seguir a nadie —la tía que no usa la
app— sin obligar al dueño a abrirle todo.

**El enlace ya no vive solo en la sesión**: al entrar queda anotada una fila con
`source = enlace`, que es lo que le permite al dueño ver quién entró y echarlo
desde «Quién la ve». Un acceso que solo existiera en la sesión del visitante
sería invisible e irrevocable.

**No vuelvas a agregar un nivel «por enlace».** Ya existió y se quitó por
redundante; `EnumTest::test_a_wishlist_is_public_or_private_and_nothing_else`
está puesto justamente para que el intento falle y obligue a leer esto.

### Las fotos van en caja cuadrada

La caja es siempre cuadrada (`aspect-ratio: 1`) y la foto entra entera dentro
(`object-fit: contain`), con fondo neutro rellenando lo que sobra. Es lo que
permite no exigirle nada a quien sube: casi ninguna foto de celular o de tienda
es cuadrada, y rechazarlas sería quedarse sin fotos.

**No usar `cover`**: llenaría el cuadrado recortando, y a la foto vertical de
un polerón le cortaría media prenda, que es justo lo que hay que ver. Se probó
con una imagen de 200×400 y una de 300×300: las dos tarjetas miden lo mismo y
ninguna imagen se recorta.

La previsualización al subir usa la misma clase `.marco-foto`, para que lo que
se ve antes de guardar sea exactamente lo que va a quedar.

**El modal sí suelta el cuadrado** (`.marco-foto.grande`): en las tarjetas la
caja es cuadrada porque van varias una debajo de otra y deben medir lo mismo,
pero en el detalle hay una sola foto y nada que alinear, así que forzarlo solo
agregaría franjas vacías a una imagen apaisada.

### El buscador de personas sugiere mientras escribes

`/usuarios` trae un menú que carga coincidencias desde tres letras, con
`fetch` contra `users.suggest`. Detalles que importan:

- **El formulario sigue funcionando sin javascript.** El menú se monta encima y
  solo adelanta el resultado; enviar sigue pintando la página en el servidor.
- El endpoint aplica **las mismas reglas** que la búsqueda de siempre —solo
  `username`, nunca `name` ni el correo—: no es una puerta de atrás.
- Devuelve iniciales y tono además de la foto, para que quien no tiene avatar
  se dibuje igual que en el resto de la aplicación sin que el javascript tenga
  que recalcular nada.
- Cancela la petición anterior con `AbortController`: escribiendo rápido salen
  varias en camino y **la última en llegar no es siempre la de lo último que se
  escribió**.
- Espera 250 ms desde la última tecla; sin eso sale una petición por pulsación.

### El detalle de un regalo

El título de cualquier regalo abre un `<dialog>` con la foto en grande y los
datos ordenados, y desde ahí se puede reservar o soltar sin volver a la lista.
Está en todas las pantallas donde aparece un producto: la lista propia, la de
otro, el catálogo y «voy a regalar».

Es un `<dialog>` nativo y no un div con clases: trae gratis el foco atrapado
dentro, cerrar con Escape, el fondo inerte y el backdrop. Reimplementar eso a
mano es de donde salen los modales que no se pueden cerrar con teclado.

El componente es `<x-producto-modal>` y las acciones llegan por su slot, porque
cada pantalla ofrece cosas distintas sobre el mismo producto.

### Los avisos flotantes

`avisar(mensaje)` deja un aviso abajo a la derecha que se va solo a los 2,6
segundos. Flota y no ocupa espacio real a propósito: un aviso en el flujo
empuja el contenido y hace saltar lo que el usuario está mirando justo cuando
acaba de hacer algo.

**Su animación no toca la opacidad, y no es un descuido.** Animarla desde 0
deja el aviso invisible si la animación no llega a correr —pasó de hecho al
renderizar con tiempo virtual— y un aviso invisible es peor que uno sin
animación. Empieza visible y solo se desliza hasta su sitio.

### Modo claro y oscuro

Todo el color vive en variables CSS y el tema solo cambia sus valores, así que
un componente nuevo queda oscuro con solo usar los tokens. Hay **tres** estados,
no dos: claro, oscuro y «lo que diga el sistema», que es el de fábrica. La
elección se guarda en `localStorage` y se aplica en un script del `<head>` —a
propósito bloqueante, para que no se vea el destello del tema contrario.

Los estilos de celular cortan en 640px. Están comprobados a 390px de ancho
real, sin desbordes horizontales.

Los tests que importan y qué vigilan:

| Archivo                            | Qué protege                                        |
|------------------------------------|----------------------------------------------------|
| `ReservationInvariantTest`         | que la base rechace una segunda reserva activa     |
| `SurpriseProtectionTest`           | que el dueño nunca reciba datos de reserva         |
| `WishlistItemTest`                 | disponible/recibido, orden por prioridad, unidades |
| `ProductVisibilityTest`            | catálogo público vs. producto privado ajeno        |
| `ProductSearchTest`                | búsqueda fulltext acotada a lo visible             |
| `WishlistVisibilityTest`           | las dos visibilidades, token de enlace, acceso aprobado |
| `EnumTest`                         | ida y vuelta de `label()` ↔ caso del enum          |
| `FollowAccessTest`                 | **quién ve la lista de quién**: perfil privado, seguidores, invitación, enlace, que dejar de seguir corte el acceso, y que el perfil liste exactamente lo que se puede abrir |

Los dos invariantes garantizados por la base se verificaron por mutación:
quitando el índice único, el test falla. No son verdes por casualidad.

**El agujero que queda, ya más chico:** `FollowAccessTest` cubre las catorce
reglas de quién ve la lista de quién, que era lo más delicado. Pero el resto de
la capa de aplicación —controladores, formularios, subida de fotos, «me
gusta», el buscador con sugerencias— sigue sin un solo test y se verificó a
mano, por HTTP y con capturas, una vez. La lista concreta está en la sección 5.

**No hecho todavía:** API (todo es Blade con formularios), notificaciones,
achicar las fotos al subirlas, y los tests de todo lo que no sea el acceso.

---

## 3. Decisiones de diseño que conviene no deshacer

Están tomadas con criterio y verificadas. Si algo se va a cambiar, que sea a
propósito y no por desconocer el porqué.

### Las reservas viven en su propia tabla

`reservations` está separada de `wishlist_items`. Si el dato de quién reservó
estuviera en la fila del ítem, el dueño lo traería sin querer en cualquier
consulta de su propia lista y se acabaría la sorpresa. Separado, llegar a esa
información obliga a un join explícito, que nadie hace por accidente.

**El modelo original tenía `reserved_by_user` dentro del ítem. Se corrigió a
propósito.**

### Una sola reserva activa, garantizada por la base

`reservations.active_flag` vale `1` mientras la reserva está viva y `NULL`
cuando termina. MariaDB no considera iguales dos `NULL` en un índice único, así
que `reservations_one_active_per_item` sobre `(wishlist_item_id, active_flag)`
permite todo el historial de un ítem con máximo una reserva activa.

Está probado: el segundo intento de reserva activa lo rechaza la base con
`SQLSTATE 23000`. No depende de que la aplicación valide, ni de que dos
personas no hagan clic a la vez.

Para soltar una reserva, `Reservation::release()` pone `active_flag` en `NULL`.

### Un ítem equivale a una unidad

Por eso `wishlist_items` **no** tiene índice único sobre
`(wishlist_id, product_id)`: repetir el mismo producto es la forma de pedir
tres tazas. No agregues ese índice.

### Catálogo con productos privados

Todo producto vive en `products`. `is_public = true` es el catálogo curado y
buscable; `is_public = false` es lo que un usuario escribió a mano, visible
solo para él. Un solo modelo, sin campos duplicados. Scope: `Product::visibleTo($user)`.

### Los tests corren contra MariaDB, no SQLite

El esquema usa `FULLTEXT` y `FIELD()`, que SQLite no soporta. `phpunit.xml`
apunta a la base `whishlist_db_test`, que se crea sola al inicializar el
volumen (`docker/mysql/init/`). Si el volumen ya existía: `make test-db`.

**No cambies los tests a SQLite.** Probar contra un motor distinto del que usas
en producción da seguridad falsa.

### `db` y `redis` solo en localhost

En `docker-compose.yml` llevan `127.0.0.1:` delante del puerto. Sin ese
prefijo Docker publica en `0.0.0.0` y quedan alcanzables desde toda la red
local — y **ufw no lo impide**, porque el tráfico de contenedores pasa por
`FORWARD` y no por `INPUT`. El `webserver` sí queda abierto, a propósito, para
poder probar desde el celular en la misma wifi.

---

## 4. Convenciones del proyecto

Se siguen las de `ideauno-evaluacion360-backend-laravel`, que es el proyecto de
referencia.

**Commits:** mensaje de **una sola línea**, conciso, en español. **Sin trailer
`Co-Authored-By`.** Ejemplo: `Agrega el modelo de datos de wishlists con
migraciones y seeders`.

**Enums:** enum puro (no backed), con dos métodos de texto que **no** hay que
confundir:

- `label()` es el valor que se guarda en la base: minúscula, sin tildes,
  snake_case. Cambiarlo invalida las filas ya guardadas.
- `title()` es lo que se muestra en pantalla: con mayúscula inicial y tildes.

Vinieron separados después de que la interfaz mostrara «alta» y «por_enlace» en
las etiquetas: se estaba usando el valor de la base como texto de la interfaz.
En las vistas nunca va `label()` salvo dentro de un `value=""`.

```php
enum ReservationStatus
{
    case ACTIVE;
    public function label(): string { return match($this) { self::ACTIVE => 'activa' }; }
    public function title(): string { return match($this) { self::ACTIVE => 'Activa' }; }
}
```

Hay además un enum que no se guarda en ninguna columna, `GiftState`, y que no
debe guardarse: describe cómo ve un regalo **quien pregunta**, y el mismo ítem
es `RESERVED_BY_ME` para uno y `RESERVED` para el resto. Se deduce con
`GiftState::forViewer($item)`.

**Migraciones:** clase anónima, `foreignId()->constrained()->cascadeOnDelete()`,
defaults tomados del enum (`->default(ItemPriority::MEDIUM->label())`), índices
compuestos declarados explícitamente. Comentarios en español explicando el
*porqué* cuando la decisión no sea obvia.

**Vistas compartidas:** componente anónimo (`resources/views/components/`)
cuando hace falta **slot** o props con valor por defecto —hoy `<x-avatar>` y
`<x-producto-modal>`—; `@include` cuando es un trozo de marcado que solo lee las
variables que ya hay alrededor. No hay más criterio que ese.

**Modelos:** `protected $fillable` (no los atributos `#[Fillable]` de Laravel
13, salvo en `User` que viene así del esqueleto). Relaciones con tipo de
retorno. Scopes reutilizables en vez de repetir `where` por todos lados.

**Estructura de `app/`:** `Enums/`, `Models/`, `Models/Scopes/`, `Services/`,
`Traits/`, `Helpers/`, `Http/Requests/`, `Http/Resources/`. Así está en
evaluacion360.

**Seeders:** separados por naturaleza. Datos de referencia (categorías, tags,
catálogo, admin) corren en cualquier entorno; los de demo solo en `local` y
`testing`, controlado en `DatabaseSeeder`. Todos idempotentes con
`updateOrCreate`.

**Idioma:** nombres de tablas, columnas y código en inglés; comentarios,
`label()` de enums y mensajes al usuario en español.

---

## 5. Lo que sigue

La aplicación funciona. Lo que sigue, en orden de importancia:

1. **Los tests que faltan.** `FollowAccessTest` ya cubre lo más delicado —quién
   ve la lista de quién, el enlace, la invitación, que dejar de seguir corte el
   acceso, y que buscar el nombre real no encuentre a nadie—. Lo que sigue
   **sin red** y yo escribiría primero:
   - Que al reemplazar una foto de perfil **se borre la anterior del disco**.
     Falla en silencio y solo se nota cuando el disco está lleno.
   - Que el dueño no pueda reservar en su propia lista (hoy solo lo impide la
     policy).
   - Que no se pueda votar un producto privado.
   - Que la segunda reserva simultánea muestre el mensaje en vez de reventar.
   - Que `users.suggest` no devuelva a nadie buscando un nombre real.
2. **Achicar las fotos al subirlas.** Hoy se guarda el archivo tal cual, hasta
   4 MB, y se muestra en una miniatura de 72px. Una lista con veinte regalos
   son veinte fotos de celular completas viajando por la red. `intervention/
   image` y un `Job` en la cola resuelven esto; la cola ya está en redis, lo
   que falta es el worker.
3. **Borrar `resources/views/welcome.blade.php`.** Quedó huérfana: `/` ahora
   redirige a `/login` o a `/wishlists` y nadie la renderiza.
4. **Notificaciones.** Avisar al dueño que le pidieron acceso, y a quien reservó
   que su plazo de 14 días está por vencer. Sin esto, el job que libera reservas
   vencidas sorprende al que iba a comprar. Necesita worker de cola: no hay
   servicio que corra `queue:work`, igual que pasaba con el scheduler.
5. **API.** Hoy todo es Blade con formularios. Si va a haber app móvil, aquí
   entran los `Resource`: exponer `is_reserved` como booleano para quien mira,
   **nunca** el `user_id` de la reserva, y para el dueño ni siquiera el booleano.

---

## 6. Decisiones pendientes

**Resuelto:** cómo se encuentra a alguien y cómo se le da una lista privada.
Se busca por arroba, se le sigue, y el dueño reparte lista por lista desde
«Quién la ve». Lo que queda de ese hilo:

**Sigue sin haber forma de pedir una lista privada que no sabes que existe.**
El dueño puede *dártela* sin que la pidas, y ese es el camino previsto. Pero si
sospechas que @ana tiene una lista de cumpleaños y quieres pedírsela, no
puedes: la solicitud se guarda por lista y para crearla hace falta un
`wishlist_id` que no conoces. Mostrarlo en el perfil revelaría que la tiene, y
se decidió no hacerlo.

**Reservas huérfanas.** Si reservas un regalo y después el dueño te quita de
sus seguidores, pierdes la lista de vista pero la reserva sigue viva y el
regalo bloqueado para los demás. No se puede avisar al dueño —le arruinaría la
sorpresa— así que el ítem queda tomado hasta que el job de reservas vencidas lo
suelte, a los 14 días. Es tolerable, pero está sin decidir si debería soltarse
en el acto.

**Visibilidad del catálogo.** Hoy cualquier producto `is_public = true` es
visible para todos, y los seeders son la única forma de crear uno. Falta
decidir si los usuarios pueden proponer productos al catálogo público y si eso
requiere moderación.

---

## 7. Comandos

`make help` los lista todos.

| Comando           | Qué hace                                              |
|-------------------|-------------------------------------------------------|
| `make setup`      | instalación completa desde cero                       |
| `make up` / `down`| levantar / bajar contenedores                         |
| `make sh`         | shell dentro del contenedor php                       |
| `make artisan …`  | `make artisan migrate:status`                         |
| `make composer …` | `make composer require laravel/sanctum`               |
| `make fresh`      | borrar tablas, migrar y sembrar de nuevo              |
| `make storage`    | enlaza `public/storage` (las fotos que suben)         |
| `make test`       | tests contra MariaDB                                  |
| `make db`         | cliente mysql contra la base                          |
| `make xdebug-on`  | activar Xdebug (se apaga al reconstruir la imagen)    |
| `make destroy`    | borrar contenedores **y la base** (pregunta antes)    |

Con flags, entrecomilla: `make artisan "make:model Gift -m"`.

---

## 8. Cosas con las que tropezar

**Tu UID no es 1000.** El Makefile exporta `id -u` / `id -g` solo, así que
`make` funciona igual. Pero si levantas con `docker compose` a mano, usa
`UID=$(id -u) GID=$(id -g) docker compose up -d --build`, o los archivos que
cree el contenedor quedarán de otro dueño.

**`UID` no va en el `.env`.** Bash la trata como variable de solo lectura y
sourcear el archivo tira un warning. El Makefile la exporta.

**Puertos ocupados.** Ajusta `HTTP_PORT`, `DB_PORT`, `REDIS_PORT`, `VITE_PORT`
en `.env` y `make up`. Por defecto: 8080, 3309, 6380, 5174.

**Xdebug se apaga al reconstruir la imagen.** `make xdebug-on` modifica el
`.ini` dentro del contenedor, no en la imagen. Tras un `make rebuild` hay que
volver a activarlo.

**El test de búsqueda no puede correr dentro de una transacción.** InnoDB
actualiza el índice `FULLTEXT` recién al confirmar, así que con
`RefreshDatabase` —que envuelve cada test en una transacción— `MATCH ... AGAINST`
no encuentra nada de lo que el propio test acaba de insertar. Por eso
`ProductSearchTest` usa `DatabaseTruncation`. Está comprobado: con
`RefreshDatabase` sus tres tests fallan.

**`make test` falla con `TLS/SSL error: certificate is not yet valid`.** No es
el código: es el reloj. MariaDB genera un certificado efímero al arrancar, y si
después el reloj del equipo se atrasa —suspender la máquina, una corrección de
NTP— ese certificado queda fechado en el futuro y el cliente lo rechaza.
Se arregla con `docker compose restart db`. Pista para reconocerlo:
`docker compose ps` muestra todos los contenedores «Up Less than a second»
aunque lleven días arriba.

**Las fotos no se ven y el enlace `public/storage` parece roto.** Desde el host
lo está: apunta a `/var/www/storage/app/public`, que es la ruta *dentro* del
contenedor. Es lo correcto —nginx sirve desde ahí— y por eso `public/storage`
está en `.gitignore` y lo crea `make storage`, que ya corre dentro de
`make setup`. Si las fotos dan 404 en un clon nuevo, es que faltó ese paso.

**Lo que se programe en el scheduler necesita su contenedor.** El servicio
`scheduler` corre `schedule:work`; sin él, `routes/console.php` queda escrito
pero no se ejecuta nunca. Ya pasó una vez con las reservas vencidas. Lo mismo
va a pasar con la cola: `QUEUE_CONNECTION=redis` pero **no hay** servicio que
corra `queue:work`.

**Hay dos `.env`.** El de la raíz lo lee docker compose; el de `laravel/` lo
lee Laravel. `make env` sincroniza las credenciales de BD del primero al
segundo, así que edita solo el de la raíz.

**El `.env` real nunca se sube.** Lo que está en GitHub son los `.env.example`
con credenciales de juguete.

**Una foto que existía deja de verse tras clonar.** No está rota la aplicación:
`storage/app/public/` no viaja en git, así que la fila apunta a un archivo que
en esa máquina no existe. Los productos caen en su placeholder y los perfiles
en sus iniciales. Es lo esperable; ver «Qué NO viaja con el repositorio».

**Chrome headless miente por debajo de 500px de ancho.** Si vas a verificar
diseño de celular con capturas: `--window-size=390,900` no rinde a 390, deja el
viewport en 500 y **recorta** la imagen, que se ve igual que un desbordamiento
horizontal. Ya llevó una vez a «arreglar» un bug inexistente. La forma correcta
es meter la página en un `<iframe>` del ancho real dentro de una ventana más
grande, y comparar `clientWidth` con `scrollWidth`.
