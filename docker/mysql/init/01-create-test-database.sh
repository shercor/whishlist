#!/bin/bash
# Crea la base de datos de tests junto a la de desarrollo.
#
# Los tests corren contra MariaDB y no contra SQLite a propósito: el esquema usa
# cosas que SQLite no tiene (índice FULLTEXT, FIELD() para ordenar por
# prioridad). Probar contra otro motor daría una falsa sensación de seguridad.
#
# Va en /docker-entrypoint-initdb.d/ y solo se ejecuta la primera vez que se
# crea el volumen. Si el volumen ya existe, hay que crearla a mano con:
#   make test-db
set -e

mariadb -u root -p"${MARIADB_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`${MARIADB_DATABASE}_test\`
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE}_test\`.* TO '${MARIADB_USER}'@'%';
    FLUSH PRIVILEGES;
EOSQL

echo "Base de datos de tests lista: ${MARIADB_DATABASE}_test"
