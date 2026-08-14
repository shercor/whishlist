# whishlist

API / aplicación Laravel 13 dockerizada desde el primer commit.

## Estructura

```
.
├── docker/              contenedores
│   ├── php/             Dockerfile + php.ini + config de Xdebug
│   ├── nginx/           default.conf
│   └── mysql/           my.cnf
├── laravel/             el proyecto Laravel (los fuentes)
├── docker-compose.yml
├── Makefile             atajos: make help
├── .env.example         config de DOCKER (puertos, credenciales)
└── laravel/.env.example config de LARAVEL
```

Cuatro contenedores: **app** (PHP 8.4-FPM), **webserver** (nginx),
**db** (MariaDB 11.4) y **redis**.

## Los dos `.env` (importante)

Es la parte que más confunde. Son dos archivos con dos dueños distintos:

| Archivo         | Lo lee         | Contiene                                    |
|-----------------|----------------|---------------------------------------------|
| `.env`          | docker compose | puertos del host, nombre de contenedores, credenciales de la BD |
| `laravel/.env`  | Laravel        | `APP_KEY`, conexión a la BD, redis, mail, ...|

Las credenciales tienen que coincidir entre los dos. Para no editarlas dos
veces, **`make env` copia los valores de BD del `.env` de la raíz al de
Laravel**. Tú solo tocas el de la raíz.

## Puesta en marcha

```bash
git clone <repo> whishlist
cd whishlist
make setup
```

`make setup` deja todo andando y te imprime la URL y las credenciales.
Si los puertos por defecto (8080, 3309, 6380, 5174) chocan con otro proyecto,
haz `cp .env.example .env`, edítalos, y recién entonces `make setup`.

### El mismo proceso a mano

Por si algo falla y necesitas ver dónde. Este es el orden correcto y el porqué:

```bash
# 1. Clonar
git clone <repo> whishlist && cd whishlist

# 2. .env de la raíz. VA ANTES de levantar: docker compose lo lee para
#    saber los puertos, el nombre de los contenedores y la password de la BD.
cp .env.example .env
$EDITOR .env

# 3. Levantar los contenedores. Con --build la primera vez.
#    UID/GID hacen que los archivos que cree el contenedor sean tuyos.
UID=$(id -u) GID=$(id -g) docker compose up -d --build

# 4. Dependencias. Sí, VA DESPUÉS y VA DENTRO del contenedor php:
#    composer no está instalado en tu máquina, está en la imagen.
docker compose exec app composer install

# 5. .env de Laravel. Después del install porque el paso 6 necesita vendor/.
#    Las credenciales de BD deben coincidir con las del paso 2.
cp laravel/.env.example laravel/.env
$EDITOR laravel/.env

# 6. Llave de encriptación
docker compose exec app php artisan key:generate

# 7. Migraciones + seeders (el seeder crea el usuario administrador)
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

Y listo: <http://localhost:8080> (o el `HTTP_PORT` que hayas puesto).

**Resumen del orden que preguntabas:** las credenciales se ajustan **dos
veces, en dos archivos distintos** — nunca la misma dos veces. El `.env` de
docker va *antes* de levantar; el de Laravel va *después* de `composer
install`. Y `composer install` va dentro del contenedor, después de
levantarlo. `make env` colapsa esos dos ajustes en uno.

## Modelo de datos

```
categories ──< products >── product_tag ──> tags
                  │
wishlists ──< wishlist_items ──< reservations ──> users
    │                                  │
    └──< wishlist_accesses ──> users   └─ (quién reserva: oculto al dueño)
```

| Tabla                | Para qué                                                     |
|----------------------|--------------------------------------------------------------|
| `categories`         | Juguetes, comida, libros. Cerradas y pocas.                  |
| `tags`               | Pokémon, pikachu, café. Abiertos, crecen con el uso.         |
| `products`           | Catálogo. `is_public` separa lo curado de lo que escribe un usuario a mano. |
| `product_tag`        | Pivote. Clave primaria compuesta, sin duplicados posibles.    |
| `wishlists`          | Las listas. `visibility` + `share_token` para el enlace.      |
| `wishlist_items`     | Un producto dentro de una lista. `received_at` = "ya me llegó". |
| `wishlist_accesses`  | Solicitudes de acceso a listas privadas.                      |
| `reservations`       | Quién reservó qué. **Tabla aparte a propósito.**              |

### Las tres decisiones que sostienen el dominio

**1. Las reservas viven en su propia tabla.** Si el dato de quién reservó
estuviera en `wishlist_items`, el dueño lo traería sin querer en cualquier
consulta de su propia lista y se acabaría la sorpresa. Separado, llegar a esa
información obliga a un join explícito, que nadie hace por accidente.

**2. Un solo regalo reservado a la vez, garantizado por la base.** La columna
`active_flag` vale `1` mientras la reserva está viva y `NULL` cuando termina.
Como MariaDB no considera iguales dos `NULL` en un índice único, el índice
`reservations_one_active_per_item` deja convivir todo el historial de un ítem
con, como máximo, una reserva activa. No depende de que la aplicación se
acuerde de validar, ni de que dos personas no hagan clic a la vez.

**3. Un ítem equivale a una unidad.** Por eso `wishlist_items` no tiene índice
único sobre `(wishlist_id, product_id)`: repetir el mismo producto es cómo se
piden tres tazas.

### Datos que siembra `make seed`

Referencia (todos los entornos): 12 categorías, 36 tags, 25 productos de
catálogo y el usuario administrador.

Demo (solo en `local` y `testing`): cuatro usuarios, cinco listas cubriendo las
tres visibilidades, ítems disponibles, reservados y ya recibidos, solicitudes
de acceso en cada estado, un producto privado y una reserva vencida. La
contraseña de todos es `password`.

## Credenciales por defecto

Todas viven en `.env` / `laravel/.env` y conviene cambiarlas.

- **Aplicación** — `admin@whishlist.test` / `admin1234`
  (`ADMIN_EMAIL` y `ADMIN_PASSWORD` en `laravel/.env`).
  Si cambias la contraseña o la olvidas: `make admin`.
- **Base de datos** — host `localhost`, puerto `3309`, usuario `whishlist`,
  password `secret`, base `whishlist_db`. Root: `root_secret`.

## Comandos

`make help` los lista todos. Los que más vas a usar:

| Comando          | Qué hace                                              |
|------------------|-------------------------------------------------------|
| `make setup`     | instalación completa desde cero                       |
| `make up`        | levantar contenedores                                 |
| `make down`      | bajar contenedores (la BD se conserva)                |
| `make sh`        | shell dentro del contenedor php                       |
| `make artisan …` | `make artisan migrate:status`                         |
| `make composer …`| `make composer require laravel/sanctum`               |
| `make migrate`   | migraciones pendientes                                |
| `make fresh`     | borrar todo, migrar y sembrar de nuevo                |
| `make admin`     | recrear / resetear el usuario administrador           |
| `make db`        | cliente mysql contra la base                          |
| `make test`      | correr los tests                                      |
| `make optimize`  | limpiar todas las cachés de Laravel                   |
| `make destroy`   | borrar contenedores **y la base de datos** (pregunta) |

Para comandos con flags, entrecomilla el argumento para que make no se los
quede: `make artisan "make:model Wish -m"`. O usa `make sh` y trabaja dentro.

## Puertos y exposición

| Servicio    | Puerto | Alcance                              |
|-------------|--------|--------------------------------------|
| `webserver` | 8080   | toda la red (para probar desde el celular) |
| `app` vite  | 5174   | toda la red                          |
| `db`        | 3309   | **solo esta máquina**                |
| `redis`     | 6380   | **solo esta máquina**                |

La base y Redis llevan `127.0.0.1:` delante del puerto en
`docker-compose.yml`. Sin ese prefijo Docker publica en `0.0.0.0`, es decir en
todas las interfaces, y quedarían alcanzables desde cualquier equipo de la red
local con las credenciales del `.env`.

Ojo con esto: **ufw no lo impide**. Docker mete sus propias reglas de iptables
en la cadena `DOCKER`, colgada de `nat/PREROUTING` y `FORWARD`; las reglas de
ufw viven en `INPUT`, que ese tráfico nunca toca. Un `ufw deny 3309` no serviría
de nada — el prefijo `127.0.0.1:` sí.

No afecta a Laravel: la app llega a `db` y `redis` por la red interna de
Docker, resolviendo por nombre de servicio, sin pasar por los puertos
publicados. Y desde el host te sigues conectando con `localhost:3309`.

## Xdebug

Viene instalado pero apagado, para no penalizar el rendimiento a diario.

```bash
make xdebug-on     # activar
make xdebug-off    # desactivar
```

Se apaga al reconstruir la imagen. En VS Code, `.vscode/launch.json`:

```json
{
  "name": "Listen for Xdebug",
  "type": "php",
  "request": "launch",
  "port": 9003,
  "pathMappings": { "/var/www": "${workspaceFolder}/laravel" }
}
```

## Problemas comunes

**Puerto ocupado** — cambia `HTTP_PORT` / `DB_PORT` / `REDIS_PORT` /
`VITE_PORT` en `.env` y `make up`.

**Permisos en `storage/`** — no debería pasar, porque la imagen se construye
con tu UID/GID. Si pasa (por ejemplo cambiaste de usuario):

```bash
make sh-root
chown -R www:www /var/www/storage /var/www/bootstrap/cache
```

**"Class not found" tras crear archivos**

```bash
make composer dump-autoload
```

**Empezar la BD de cero** — `make fresh` (vacía las tablas) o `make destroy`
seguido de `make setup` (borra el volumen entero).
