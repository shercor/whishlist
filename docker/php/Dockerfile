FROM php:8.4-fpm

# UID/GID del usuario del host, para que los archivos creados dentro del
# contenedor (composer install, artisan make:*) no queden como root.
ARG UID=1000
ARG GID=1000

WORKDIR /var/www

# Dependencias del sistema
RUN apt-get update && apt-get install -y \
  build-essential \
  libpng-dev \
  libzip-dev \
  libjpeg62-turbo-dev \
  libfreetype6-dev \
  libwebp-dev \
  libicu-dev \
  locales \
  zip \
  unzip \
  git \
  curl \
  vim \
  gnupg \
  ca-certificates \
  mariadb-client \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

# Extensiones PHP
# --with-webp no es opcional: los formularios aceptan webp, y sin esto GD no
# tiene imagecreatefromwebp(), así que achicar una foto webp reventaba.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath zip exif pcntl gd intl opcache

RUN pecl install redis && docker-php-ext-enable redis

# Xdebug se instala pero NO se habilita aquí: el zend_extension lo declara
# 90-xdebug.ini. Llamar a docker-php-ext-enable además de eso lo cargaría dos
# veces ("Cannot load Xdebug - it was already loaded" en cada comando php).
RUN pecl install xdebug

# Node.js (para vite / assets)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
  && apt-get install -y nodejs \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Usuario de la aplicación alineado con el UID/GID del host.
# Se reutiliza el grupo/usuario si el GID/UID ya existe en la imagen base.
RUN groupadd -g "${GID}" www 2>/dev/null || groupmod -n www "$(getent group ${GID} | cut -d: -f1)" \
  && useradd -u "${UID}" -g "${GID}" -ms /bin/bash www 2>/dev/null \
  || usermod -l www -d /home/www -m -g "${GID}" "$(getent passwd ${UID} | cut -d: -f1)"

# Los .ini van al final a propósito: cambiarlos no invalida la caché de las
# capas caras de arriba (extensiones, Node).
COPY php.ini "${PHP_INI_DIR}/conf.d/99-app.ini"
COPY 90-xdebug.ini "${PHP_INI_DIR}/conf.d/90-xdebug.ini"

# El código se monta como volumen desde ./laravel, no se copia a la imagen:
# así los cambios en el host se ven al instante sin rebuild.
USER www

EXPOSE 9000

CMD ["php-fpm"]
