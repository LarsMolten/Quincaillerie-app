<?php

namespace App\Rapports;

use App\Enums\SensMouvement;
use App\Enums\TypeMouvementStock;
use App\Models\MouvementStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Filtres des mouvements de stock, partagés par les pages Mouvements, Entrées, Sorties et l'export Excel :
 * produit (recherche ou identifiant), type, sens, période (puces ou dates du/au), utilisateur.
 */
class FiltresMouvements
{
    public const PERIODES = ['aujourdhui' => 'Aujourd\'hui', '7j' => '7 jours', '30j' => '30 jours', 'mois' => 'Ce mois', 'tout' => 'Tout'];

    /**
     * @param  array{recherche: string, produit: ?int, type: ?string, sens: ?string, periode: string, du: ?string, au: ?string, utilisateur: ?int}  $valeurs
     */
    private function __construct(public readonly array $valeurs) {}

    /** Lit les filtres de la requête ; $sens impose le sens (pages Entrées et Sorties). */
    public static function depuis(Request $requete, ?SensMouvement $sens = null): self
    {
        $date = fn (string $cle) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $requete->query($cle)) ? (string) $requete->query($cle) : null;
        $types = array_map(fn (TypeMouvementStock $t) => $t->value, TypeMouvementStock::cases());
        $periode = (string) $requete->query('periode', 'tout');

        return new self([
            'recherche' => trim((string) $requete->query('recherche')),
            'produit' => $requete->integer('produit') ?: null,
            'type' => in_array($requete->query('type'), $types, true) ? $requete->query('type') : null,
            'sens' => $sens?->value ?? (in_array($requete->query('sens'), ['entree', 'sortie'], true) ? $requete->query('sens') : null),
            'periode' => array_key_exists($periode, self::PERIODES) ? $periode : 'tout',
            'du' => $date('du'),
            'au' => $date('au'),
            'utilisateur' => $requete->integer('utilisateur') ?: null,
        ]);
    }

    /** Mouvements filtrés, du plus récent au plus ancien, avec produit, utilisateur et document d'origine. */
    public function requete(): Builder
    {
        $f = $this->valeurs;

        return MouvementStock::query()
            ->with(['produit.unite', 'utilisateur', 'reference'])
            ->when($f['produit'], fn (Builder $q, int $id) => $q->where('produit_id', $id))
            ->when($f['recherche'] !== '', fn (Builder $q) => $q->whereHas('produit', fn (Builder $p) => $p->recherche($f['recherche'])))
            ->when($f['type'], fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($f['sens'], fn (Builder $q, string $sens) => $q->where('sens', $sens))
            ->when($this->debut(), fn (Builder $q, Carbon $debut) => $q->where('created_at', '>=', $debut))
            ->when($f['au'], fn (Builder $q, string $au) => $q->where('created_at', '<', Carbon::parse($au)->addDay()->startOfDay()))
            ->when($f['utilisateur'], fn (Builder $q, int $id) => $q->where('utilisateur_id', $id))
            ->latest('created_at')
            ->latest('id');
    }

    /** Vrai si un filtre restreint la liste (au-delà du sens imposé). */
    public function actifs(?SensMouvement $sensImpose = null): bool
    {
        $f = $this->valeurs;

        return $f['recherche'] !== '' || $f['produit'] || $f['type'] || $f['utilisateur'] || $f['du'] || $f['au']
            || $f['periode'] !== 'tout' || ($f['sens'] && $sensImpose === null);
    }

    private function debut(): ?Carbon
    {
        if ($this->valeurs['du']) {
            return Carbon::parse($this->valeurs['du'])->startOfDay();
        }

        return match ($this->valeurs['periode']) {
            'aujourdhui' => today(),
            '7j' => today()->subDays(6),
            '30j' => today()->subDays(29),
            'mois' => today()->startOfMonth(),
            default => null,
        };
    }
}
