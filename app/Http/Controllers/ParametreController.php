<?php

namespace App\Http\Controllers;

use App\Enums\CouleurAccent;
use App\Http\Requests\ParametresRequest;
use App\Models\Parametre;
use App\Models\Role;
use App\Services\FactureService;
use App\Services\NumerotationService;
use App\Services\ParametreService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administration > Paramètres (droit parametres.gerer), en onglets :
 * Entreprise (identité et logo des documents), Facturation (numérotation, TVA, format, pied de page),
 * Ventes (remise maximale générale et par rôle, stock négatif), Apparence (thème par défaut, couleur d'accent).
 */
class ParametreController extends Controller
{
    /** Onglets : code => [libellé, icône, description]. */
    public const ONGLETS = [
        'entreprise' => ['Entreprise', 'store', 'Identité affichée sur les factures, reçus et rapports.'],
        'facturation' => ['Facturation', 'file-text', 'Numérotation des documents, TVA et impression des factures.'],
        'ventes' => ['Ventes', 'shopping-cart', 'Remises autorisées à la caisse et règles de stock.'],
        'apparence' => ['Apparence', 'palette', 'Thème et couleur de l\'application et des documents.'],
    ];

    public function __construct(private readonly ParametreService $parametres) {}

    public function index(string $onglet = 'entreprise'): View
    {
        $valeur = fn (string $cle, mixed $defaut = '') => Parametre::valeur($cle, $defaut);

        return view('parametres.index', [
            'onglet' => $onglet,
            'onglets' => self::ONGLETS,
            'valeurs' => [
                ...collect(['nom_entreprise', 'adresse', 'telephone', 'email', 'nif_stat', 'pied_de_facture'])->mapWithKeys(fn ($c) => [$c => $valeur($c)])->all(),
                'taux_tva' => $valeur('taux_tva', '0'),
                'format_facture' => FactureService::formatParDefaut(),
                'remise_max_pourcentage' => $valeur('remise_max_pourcentage', '0'),
                'stock_negatif_autorise' => Parametre::actif('stock_negatif_autorise'),
                'theme_defaut' => $valeur('theme_defaut', 'auto'),
                'couleur_accent' => CouleurAccent::courante()->value,
            ],
            'prefixes' => collect(NumerotationService::PREFIXES)->map(fn (array $p, string $document) => [
                'cle' => $p[0], 'libelle' => $p[2], 'valeur' => app(NumerotationService::class)->prefixe($document), 'defaut' => $p[1],
            ]),
            'logo' => $this->logoExiste() ? route('parametres.logo', ['v' => md5((string) $valeur('logo'))]) : null,
            'roles' => Role::with('droits')->orderBy('nom')->get(),
            'accents' => CouleurAccent::cases(),
        ]);
    }

    public function update(ParametresRequest $requete, string $onglet): RedirectResponse
    {
        $donnees = $requete->validated();

        match ($onglet) {
            'entreprise' => $this->entreprise($requete, $donnees),
            'ventes' => $this->ventes($donnees),
            'facturation' => $this->parametres->enregistrer($donnees),
            'apparence' => $this->parametres->enregistrer($donnees),
        };

        return to_route('parametres.index', $onglet)->with('succes', 'Paramètres « '.self::ONGLETS[$onglet][0].' » enregistrés.');
    }

    /** Logo actuel (disque privé), pour l'aperçu de l'onglet Entreprise. */
    public function logo(): StreamedResponse
    {
        abort_unless($this->logoExiste(), 404);

        return Storage::disk('local')->response((string) Parametre::valeur('logo'), headers: ['Cache-Control' => 'private, max-age=86400']);
    }

    private function entreprise(ParametresRequest $requete, array $donnees): void
    {
        $this->parametres->enregistrer(collect($donnees)->only(['nom_entreprise', 'adresse', 'telephone', 'email', 'nif_stat'])->all());

        if ($requete->hasFile('logo')) {
            $this->parametres->remplacerLogo($requete->file('logo'));
        } elseif ($requete->boolean('retirer_logo')) {
            $this->parametres->retirerLogo();
        }
    }

    private function ventes(array $donnees): void
    {
        $this->parametres->enregistrer([
            'remise_max_pourcentage' => $donnees['remise_max_pourcentage'],
            'stock_negatif_autorise' => (bool) ($donnees['stock_negatif_autorise'] ?? false),
        ]);
        $this->parametres->enregistrerRemises($donnees['remises'] ?? []);
    }

    private function logoExiste(): bool
    {
        $chemin = trim((string) Parametre::valeur('logo', ''));

        return $chemin !== '' && Storage::disk('local')->exists($chemin);
    }
}
