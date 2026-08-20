#!/usr/bin/env python3
"""
Comprueba que docs/openapi.yaml sigue describiendo la API que existe.

La documentación de una API se pudre en silencio: alguien agrega una ruta, no
toca el spec, y el archivo sigue pareciendo correcto. Esto lo pilla comparando
el spec con lo que Laravel dice que hay de verdad.

Uso:
    make api-docs-check
    # o a mano:
    docker compose exec -T app php artisan route:list --json | python3 docs/check-openapi.py
"""

import json
import pathlib
import sys

SPEC = pathlib.Path(__file__).parent / 'openapi.yaml'
VERBOS = ('get', 'post', 'put', 'patch', 'delete')


def rutas_reales(datos):
    """Las operaciones que Laravel expone bajo api/v1, normalizadas."""
    encontradas = set()
    for ruta in datos:
        if not ruta['uri'].startswith('api/v1'):
            continue
        # El spec usa {user}; Laravel escribe {user:username} porque resuelve
        # por esa columna. Es la misma ruta.
        uri = '/' + ruta['uri'].removeprefix('api/v1/').replace('{user:username}', '{user}')
        for verbo in ruta['method'].split('|'):
            if verbo.lower() in VERBOS:
                encontradas.add((verbo.lower(), uri))
    return encontradas


def rutas_documentadas(spec):
    return {
        (verbo, camino)
        for camino, item in spec['paths'].items()
        for verbo in item
        if verbo in VERBOS
    }


def main():
    try:
        import yaml
    except ImportError:
        sys.exit("Falta PyYAML: pip install pyyaml (o usa el venv del proyecto).")

    if sys.stdin.isatty():
        sys.exit(__doc__)

    spec = yaml.safe_load(SPEC.read_text())
    reales = rutas_reales(json.loads(sys.stdin.read()))
    documentadas = rutas_documentadas(spec)

    sin_documentar = sorted(reales - documentadas)
    inventadas = sorted(documentadas - reales)

    for verbo, uri in sin_documentar:
        print(f"  SIN DOCUMENTAR  {verbo.upper():7} {uri}")
    for verbo, uri in inventadas:
        print(f"  YA NO EXISTE    {verbo.upper():7} {uri}")

    if sin_documentar or inventadas:
        print(f"\n  {len(sin_documentar)} sin documentar, {len(inventadas)} de más.")
        print("  Actualiza docs/openapi.yaml.")
        return 1

    print(f"  ✓ {len(reales)} operaciones, todas documentadas y ninguna de más.")
    return 0


if __name__ == '__main__':
    sys.exit(main())
