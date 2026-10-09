<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\CompteInactifException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConnexionRequest;
use App\Services\JournalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConnexionController extends Controller
{
    public function __construct(private readonly JournalService $journal) {}

    public function create(): View
    {
        return view('auth.connexion');
    }

    public function store(ConnexionRequest $requete): RedirectResponse
    {
        try {
            $utilisateur = $requete->authentifier($this->journal);
        } catch (CompteInactifException $exception) {
            return back()->onlyInput('email')->with('erreur', $exception->getMessage());
        }

        $requete->session()->regenerate();
        $this->journal->enregistrer('connexion', $utilisateur);

        return redirect()->intended(route('accueil'));
    }

    public function destroy(Request $requete): RedirectResponse
    {
        $this->journal->enregistrer('deconnexion', $requete->user());

        Auth::guard('web')->logout();
        $requete->session()->invalidate();
        $requete->session()->regenerateToken();

        return redirect()->route('connexion')->with('info', 'Vous êtes déconnecté.');
    }
}
