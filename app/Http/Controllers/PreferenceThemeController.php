<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreferenceThemeRequest;
use Illuminate\Http\JsonResponse;

/**
 * Enregistre le thème choisi (clair / sombre / auto) par l'utilisateur connecté.
 */
class PreferenceThemeController extends Controller
{
    public function __invoke(PreferenceThemeRequest $requete): JsonResponse
    {
        $requete->user()->update(['preference_theme' => $requete->validated('preference_theme')]);

        return response()->json(['preference_theme' => $requete->user()->preference_theme]);
    }
}
