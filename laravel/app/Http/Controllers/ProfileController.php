<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * El perfil propio: el usuario con que te encuentran y si tu nombre real se
 * muestra o no.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['usuario' => $request->user()]);
    }

    public function update(ProfileRequest $request, AvatarService $avatares): RedirectResponse
    {
        $usuario = $request->user();

        $usuario->update($request->safe()->only(['name', 'username', 'show_name', 'is_private']));

        if ($request->boolean('quitar_avatar')) {
            $avatares->remove($usuario);
        } else {
            $avatares->update($usuario, $request->file('avatar'));
        }

        return redirect()->route('profile.edit')
            ->with('status', 'Perfil actualizado.');
    }
}
