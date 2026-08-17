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
`key:generate` → `migrate` → `seed`. Al terminar imprime la URL y las
credenciales.

Si los puertos por defecto chocan con otro proyecto, primero
`cp .env.example .env`, ajústalos, y recién ahí `make setup`.

### Comprobar que quedó bien

```bash
curl -o /dev/null -w '%{http_code}\n' http://localhost:8080   # 302 → /login
make test                                                     # 49 passed
```

Entra a http://localhost:8080: te lleva al login. Puedes crear una cuenta nueva
en `/register` —pide nombre, correo y contraseña, nada más— o entrar con un
usuario de demo: `ana@whishlist.test`,
`bruno@whishlist.test`, `camila@whishlist.test`, `diego@whishlist.test`, todos
con contraseña `password`. Administrador: `admin@whishlist.test` / `admin1234`.

---

## 2. Estado actual

Commits en `main`:

| Commit    | Qué trae                                                  |
|-----------|-----------------------------------------------------------|
| `5474705` | Entorno dockerizado: PHP 8.4-FPM, nginx, MariaDB 11.4, Redis, Makefile, README |
| `a1b918e` | `db` y `redis` publicados solo en `127.0.0.1`             |
| `0a60c3d` | Modelo de datos: 4 enums, 8 migraciones, 7 modelos, 5 seeders |
| `830d138` | Factories de los 7 modelos y 49 tests del dominio          |
| (el último) | Capa de aplicación: policies, controladores, rutas, vistas |

**Hecho y verificado corriendo:** el entorno completo, el esquema de base de
datos, los seeders de catálogo y de demo, y los invariantes del dominio
(reserva única, sorpresa protegida, producto privado invisible, búsqueda
fulltext). Todo eso está automatizado en la suite, no solo probado a mano:
`make test` → **49 passed**.

**La aplicación ya se usa de punta a punta.** Probado por HTTP contra el
entorno real: registrarse, crear una lista, agregar un regalo escrito a mano,
entrar con otra cuenta, reservarlo, y verlo en «mis reservas» — mientras el
dueño de la lista no ve ni rastro de esa reserva. Registrarse pide solo nombre,
correo y contraseña (mínimo 8, repetida); **no hay verificación de correo**,
y al enviar el formulario quedas dentro.

Lo que trae la capa de aplicación:

| Pieza                          | Qué resuelve                                       |
|--------------------------------|----------------------------------------------------|
| `WishlistPolicy`               | los cuatro caminos para abrir una lista: dueño, pública, acceso aprobado, enlace secreto |
| `ReservationPolicy`            | la regla que la base no puede: el dueño jamás reserva en su propia lista |
| `WishlistItemPolicy`           | solo el dueño agrega, edita o marca recibido       |
| `ReservationService`           | traduce el choque de dos reservas simultáneas a un mensaje |
| `ReleaseExpiredReservations`   | comando programado cada hora en `routes/console.php` |
| `Product::scopeSearchPrefix()` | buscador del catálogo: «pelu» encuentra «Peluche»  |
| `Wishlist::unlockByLink()`     | el enlace secreto se anota en la sesión, para no arrastrar el token por cada URL |

Los tests que importan y qué vigilan:

| Archivo                            | Qué protege                                        |
|------------------------------------|----------------------------------------------------|
| `ReservationInvariantTest`         | que la base rechace una segunda reserva activa     |
| `SurpriseProtectionTest`           | que el dueño nunca reciba datos de reserva         |
| `WishlistItemTest`                 | disponible/recibido, orden por prioridad, unidades |
| `ProductVisibilityTest`            | catálogo público vs. producto privado ajeno        |
| `ProductSearchTest`                | búsqueda fulltext acotada a lo visible             |
| `WishlistVisibilityTest`           | visibilidades, token de enlace, acceso aprobado    |
| `EnumTest`                         | ida y vuelta de `label()` ↔ caso del enum          |

Los dos invariantes garantizados por la base se verificaron por mutación:
quitando el índice único, el test falla. No son verdes por casualidad.

**El agujero que queda:** los 49 tests cubren el dominio —modelos, scopes,
invariantes, enums— y **ni uno solo toca la capa de aplicación**. Policies,
controladores y vistas se verificaron a mano, por HTTP, una vez. Nada impide
que el próximo cambio rompa una policy en silencio. Ese es el trabajo número
uno de la próxima sesión.

**No hecho todavía:** API (todo es Blade con formularios), notificaciones,
y los tests recién mencionados.

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

**Enums:** enum puro (no backed), con `label()` que devuelve un string en
español en snake_case. Ese label es lo que se guarda en la base, como
`varchar`, no como enum nativo de MySQL.

```php
enum ReservationStatus
{
    case ACTIVE;
    public function label(): string { return match($this) { self::ACTIVE => 'activa' }; }
}
```

**Migraciones:** clase anónima, `foreignId()->constrained()->cascadeOnDelete()`,
defaults tomados del enum (`->default(ItemPriority::MEDIUM->label())`), índices
compuestos declarados explícitamente. Comentarios en español explicando el
*porqué* cuando la decisión no sea obvia.

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

1. **Tests de la capa de aplicación.** Lo único urgente. Como mínimo: que el
   dueño no pueda reservar en su propia lista, que un extraño reciba 403 al
   abrir una lista privada, que el enlace secreto sí la abra, que la vista del
   dueño no traiga jamás el `user_id` de una reserva, y que la segunda reserva
   simultánea muestre el mensaje en vez de reventar. Todo eso hoy lo sostiene
   código sin red.
2. **Borrar `resources/views/welcome.blade.php`.** Quedó huérfana: `/` ahora
   redirige a `/login` o a `/wishlists` y nadie la renderiza.
3. **Notificaciones.** Avisar al dueño que le pidieron acceso, y a quien reservó
   que su plazo de 14 días está por vencer. Sin esto, el job que libera reservas
   vencidas sorprende al que iba a comprar.
4. **API.** Hoy todo es Blade con formularios. Si va a haber app móvil, aquí
   entran los `Resource`: exponer `is_reserved` como booleano para quien mira,
   **nunca** el `user_id` de la reserva, y para el dueño ni siquiera el booleano.

---

## 6. Decisiones pendientes

**Cómo un usuario encuentra a otro** para pedirle acceso a su lista privada.
Sigue sin resolverse. Hoy hay dos puertas y ninguna sirve para *buscar a una
persona*: `/discover` lista las públicas, y a una privada se llega por su URL
—que alguien te tuvo que pasar—, donde el 403 ofrece el botón de pedir acceso.
`users` solo tiene `name` y `email`. Buscar por email es incómodo y expone
datos. Alternativas: agregar un `username` público, o un enlace de invitación
que cree la solicitud ya aprobada.

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

**Hay dos `.env`.** El de la raíz lo lee docker compose; el de `laravel/` lo
lee Laravel. `make env` sincroniza las credenciales de BD del primero al
segundo, así que edita solo el de la raíz.

**El `.env` real nunca se sube.** Lo que está en GitHub son los `.env.example`
con credenciales de juguete.
