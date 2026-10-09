<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware « droit:code » : l'utilisateur connecté doit posséder le droit demandé
 * (ex. Route::middleware('droit:ventes.creer')). L'Administrateur passe toujours (Gate::before).
 */
class VerifierDroit
{
    public function handle(Request $requete, Closure $suivant, string $code): Response
    {
        $utilisateur = $requete->user();

        // Compte désactivé pendant la session : déconnexion immédiate
        if ($utilisateur && ! $utilisateur->actif) {
            Auth::guard('web')->logout();
            $requete->session()->invalidate();
            $requete->session()->regenerateToken();

            return redirect()->route('connexion')
                ->with('erreur', 'Votre compte a été désactivé. Contactez l\'administrateur.');
        }

        abort_unless($utilisateur?->can($code), 403, 'Vous n\'avez pas le droit d\'accéder à cette page.');

        return $suivant($requete);
    }
}
