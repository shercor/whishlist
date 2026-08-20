<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La foto de perfil propia, como sub-recurso de `me`.
 *
 * Va aparte del `PATCH me` por dos razones, y ninguna es técnica:
 *
 * 1. El resto de la API habla json y un binario no cabe en un documento json.
 *    Metiéndola en `me` ese endpoint tendría que aceptar dos codificaciones de
 *    cuerpo distintas según lo que traiga, y un cliente no sabría cuál usar sin
 *    leer la documentación de cada campo.
 * 2. Quitar la foto hay que poder decirlo. En un PATCH, «no mando el campo» y
 *    «mándalo a null» se confunden: todos los campos son `sometimes`, que es lo
 *    que hace que un PATCH deje en paz lo que no toca. La web lo resuelve con
 *    una casilla `quitar_avatar`, lenguaje de formulario que ProfileController
 *    ya descartó a propósito. Un DELETE lo dice sin inventar campos.
 *
 * PUT y no POST porque reemplazar la foto es idempotente —subirla dos veces
 * deja una foto, no dos—, igual que `like` y `receipt`.
 *
 * **Esto necesita PHP 8.4.** Multipart en un verbo que no sea POST solo se
 * desarma desde que existe `request_parse_body()`, que es lo que llama
 * `Request::createFromGlobals()` de Symfony; antes, `$_FILES` llegaba vacío en
 * PUT y PATCH. Y la suite no lo protege: el cliente de pruebas arma la petición
 * por dentro sin pasar por ese parser, así que en un PHP viejo estos tests
 * seguirían verdes mientras el endpoint devuelve 422 «la foto de perfil es
 * obligatoria» contra un cliente de verdad. Comprobado por HTTP, no solo en la
 * suite.
 */
class AvatarController extends Controller
{
    /**
     * Las dos respuestas devuelven el perfil entero y no 204 porque el nombre
     * del archivo lo inventa el servidor —40 caracteres al azar—: sin el
     * cuerpo, el cliente no tiene forma de saber qué URL pintar y necesitaría
     * un GET más para enterarse.
     */
    public function store(Request $request, AvatarService $avatares): JsonResponse
    {
        $request->validate([
            // Las mismas reglas que el formulario web. 'image' mira el
            // contenido, no la extensión: un ejecutable renombrado a .png no
            // pasa de acá.
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
        ], [], ['avatar' => 'foto de perfil']);

        $usuario = $request->user();

        // El servicio es el mismo de la web: borra la foto anterior del disco
        // y encola el achicado. Escribir el guardado a mano acá es cómo se
        // olvida una de las dos cosas.
        $avatares->update($usuario, $request->file('avatar'));

        return response()->json([
            'data' => UserResource::make($usuario->fresh())->resolve($request),
        ]);
    }

    public function destroy(Request $request, AvatarService $avatares): JsonResponse
    {
        $usuario = $request->user();

        $avatares->remove($usuario);

        return response()->json([
            'data' => UserResource::make($usuario->fresh())->resolve($request),
        ]);
    }
}
