<?php

namespace App\Models\Concerns;

use App\Services\JournalService;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Journalisation automatique des créations, modifications, suppressions et restaurations d'un modèle
 * dans journal_activites (anciennes et nouvelles valeurs, utilisateur, IP).
 *
 * Action : « {nom morph}.{événement} » au participe passé, accordé si le modèle définit
 * JOURNAL_FEMININ = true (ex. « produit.cree », « categorie.supprimee »). Un changement portant
 * seulement sur « actif » devient « .desactive » / « .reactive ».
 *
 * Réglages facultatifs du modèle :
 * - JOURNAL_IGNORES : attributs jamais tracés (ex. stock_actuel, modifié par MouvementStockService) ;
 * - JOURNAL_EVENEMENTS : événements suivis (défaut : tous) — les documents ne journalisent que leur création,
 *   leurs annulations étant tracées par leur service avec le motif ;
 * - libelleJournal() : nom lisible de l'objet, conservé dans le journal même après suppression.
 * Les attributs cachés ($hidden : mot de passe…) apparaissent comme « (modifié) », jamais en clair.
 */
trait Journalisable
{
    public static function bootJournalisable(): void
    {
        static::created(fn (self $modele) => $modele->journaliser('cree', [], $modele->valeursJournal($modele->getAttributes())));

        static::updated(function (self $modele) {
            $changements = array_diff_key($modele->getChanges(), array_flip($modele->attributsNonJournalises()));
            if ($changements === []) {
                return;
            }
            $anciennes = $modele->valeursJournal(array_intersect_key($modele->getRawOriginal(), $changements));
            $evenement = array_keys($changements) === ['actif'] ? ($modele->actif ? 'reactive' : 'desactive') : 'modifie';

            $modele->journaliser($evenement, $anciennes, $modele->valeursJournal($changements));
        });

        // Suppression : instantané des valeurs, pour l'audit
        static::deleted(fn (self $modele) => $modele->journaliser('supprime', $modele->valeursJournal($modele->getAttributes())));

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(fn (self $modele) => $modele->journaliser('restaure'));
        }
    }

    /** Nom lisible de l'objet (nom, numéro, libellé ou clé selon le modèle). */
    public function libelleJournal(): string
    {
        foreach (['numero', 'nom', 'libelle', 'cle'] as $attribut) {
            if (filled($this->getAttribute($attribut))) {
                return (string) $this->getAttribute($attribut);
            }
        }

        return '#'.$this->getKey();
    }

    private function journaliser(string $evenement, array $anciennes = [], array $nouvelles = []): void
    {
        $suivis = defined(static::class.'::JOURNAL_EVENEMENTS') ? static::JOURNAL_EVENEMENTS : null;
        if ($suivis !== null && ! in_array($evenement, $suivis, true)) {
            return;
        }

        $feminin = defined(static::class.'::JOURNAL_FEMININ') && static::JOURNAL_FEMININ;

        app(JournalService::class)->enregistrer(
            $this->getMorphClass().'.'.$evenement.($feminin ? 'e' : ''),
            $this,
            array_filter([
                'objet' => $this->libelleJournal(),
                'anciennes' => $anciennes ?: null,
                'nouvelles' => $nouvelles ?: null,
            ]),
        );
    }

    /** @return list<string> */
    private function attributsNonJournalises(): array
    {
        $ignores = defined(static::class.'::JOURNAL_IGNORES') ? static::JOURNAL_IGNORES : [];

        return [...$ignores, 'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'];
    }

    /** Valeurs lisibles et sûres : attributs ignorés retirés, cachés masqués, dates et énumérations converties. */
    private function valeursJournal(array $valeurs): array
    {
        $resultat = [];
        foreach (array_diff_key($valeurs, array_flip($this->attributsNonJournalises())) as $cle => $valeur) {
            $resultat[$cle] = match (true) {
                in_array($cle, $this->getHidden(), true) => '(modifié)',
                $valeur instanceof BackedEnum => $valeur->value,
                $valeur instanceof DateTimeInterface => $valeur->format('Y-m-d H:i:s'),
                is_string($valeur) && mb_strlen($valeur) > 500 => mb_substr($valeur, 0, 500).'…',
                default => $valeur,
            };
        }

        return $resultat;
    }
}
