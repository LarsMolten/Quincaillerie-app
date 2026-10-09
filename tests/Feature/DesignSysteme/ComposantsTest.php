<?php

namespace Tests\Feature\DesignSysteme;

use App\Enums\EtatStock;
use App\Enums\StatutVente;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\ViewException;
use Tests\TestCase;

class ComposantsTest extends TestCase
{
    public function test_icone_decorative_ou_annoncee(): void
    {
        $this->blade('<x-icone nom="package" class="text-lien" />')
            ->assertSee('<svg class="shrink-0 size-5 text-lien" aria-hidden="true" focusable="false"', false);

        $this->blade('<x-icone nom="package" titre="Produits" />')
            ->assertSee('role="img" aria-label="Produits"', false)
            ->assertSee('<title>Produits</title>', false);
    }

    public function test_une_icone_inconnue_est_signalee_en_developpement(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Icône inconnue « licorne »');

        $this->blade('<x-icone nom="licorne" />');
    }

    public function test_bouton_variantes_lien_et_chargement(): void
    {
        $this->blade('<x-bouton icone="plus">Nouvelle vente</x-bouton>')
            ->assertSee('type="submit"', false)
            ->assertSee('bg-primaire text-primaire-texte', false)
            ->assertSee('h-11', false)
            ->assertSee('Nouvelle vente');

        $this->blade('<x-bouton href="/produits" variante="secondaire">Produits</x-bouton>')
            ->assertSee('<a href="/produits"', false)
            ->assertDontSee('<button', false);

        $this->blade('<x-bouton chargement>Enregistrer</x-bouton>')
            ->assertSee('disabled', false)
            ->assertSee('aria-busy="true"', false)
            ->assertSee('data-chargement="true"', false);

        $this->blade('<x-bouton variante="danger" icone="trash-2" aria-label="Supprimer" />')
            ->assertSee('bg-danger text-sur-danger', false)
            ->assertSee('size-11', false)
            ->assertSee('aria-label="Supprimer"', false);
    }

    public function test_champ_associe_label_aide_et_suffixe(): void
    {
        $this->blade('<x-champ nom="prix_vente" label="Prix de vente" suffixe="Ar" aide="Prix TTC." montant requis />')
            ->assertSee('<label for="champ-prix-vente"', false)
            ->assertSee('id="champ-prix-vente"', false)
            ->assertSee('aria-describedby="champ-prix-vente-aide"', false)
            ->assertSee('required', false)
            ->assertSee('chiffres text-right', false)
            ->assertSee('Ar');
    }

    public function test_champ_en_erreur_annonce_le_message(): void
    {
        $this->withViewErrors(['lignes.0.quantite' => 'La quantité doit être supérieure à 0.'])
            ->blade('<x-champ nom="lignes[0][quantite]" label="Quantité" />')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="champ-lignes-0-quantite-erreur"', false)
            ->assertSee('id="champ-lignes-0-quantite-erreur"', false)
            ->assertSee('La quantité doit être supérieure à 0.')
            ->assertSee('border-danger', false);
    }

    public function test_select_textarea_case_et_interrupteur(): void
    {
        $this->blade('<x-select nom="categorie_id" label="Catégorie" vide="Choisir…" :options="[1 => \'Plomberie\', 2 => \'Électricité\']" valeur="2" />')
            ->assertSee('<option value="">Choisir…</option>', false)
            ->assertSee('<option value="2" selected>Électricité</option>', false);

        $this->blade('<x-textarea nom="notes" label="Notes" valeur="Livraison mardi" />')
            ->assertSee('<label for="champ-notes"', false)
            ->assertSee('>Livraison mardi</textarea>', false);

        $this->blade('<x-case nom="actif" label="Produit actif" coche />')
            ->assertSee('type="checkbox"', false)
            ->assertSee('checked', false);

        $this->blade('<x-interrupteur nom="alerte_stock" label="Alerte de stock" actif />')
            ->assertSee('role="switch"', false)
            ->assertSee('aria-checked="true"', false)
            ->assertSee('aria-labelledby="champ-alerte-stock-libelle"', false)
            ->assertSee('<input type="hidden" name="alerte_stock"', false);
    }

    public function test_badge_depuis_un_enum(): void
    {
        $this->blade('<x-badge :statut="$etat" />', ['etat' => EtatStock::Rupture])
            ->assertSee('Rupture')
            ->assertSee('bg-danger-doux text-danger-texte', false);

        $this->blade('<x-badge :statut="$statut" />', ['statut' => StatutVente::Validee])
            ->assertSee('Validée')
            ->assertSee('bg-succes-doux', false);

        $this->blade('<x-badge couleur="inconnue">Autre</x-badge>')
            ->assertSee('bg-neutre-doux', false);
    }

    public function test_carte_stat_tendance_et_mini_graphique(): void
    {
        $this->blade('<x-carte-stat libelle="Ventes du jour" valeur="1 250 000 Ar" :tendance="8.4" :serie="[1, 3, 2, 5]" />')
            ->assertSee('Ventes du jour')
            ->assertSee('En hausse de 8,4')
            ->assertSee('text-succes-texte', false)
            ->assertSee('<polyline', false);

        // Pour les dépenses, une baisse est positive
        $this->blade('<x-carte-stat libelle="Dépenses" valeur="10" :tendance="-12" inverse />')
            ->assertSee('En baisse de 12')
            ->assertSee('text-succes-texte', false)
            ->assertDontSee('<polyline', false);
    }

    public function test_tableau_tri_cellules_et_pagination(): void
    {
        $this->get('/?tri=nom&ordre=asc');

        $pagination = new LengthAwarePaginator([1, 2], 30, 2, 1, ['path' => '/produits']);

        $this->blade(
            '<x-tableau legende="Produits" :pagination="$pagination" :colonnes="[\'nom\' => [\'libelle\' => \'Produit\', \'triable\' => true], \'actions\' => [\'libelle\' => \'Actions\', \'masque\' => true]]">
                <x-tableau.ligne><x-tableau.cellule libelle="Produit" principale>Ciment</x-tableau.cellule></x-tableau.ligne>
            </x-tableau>',
            ['pagination' => $pagination],
        )
            ->assertSee('<caption class="sr-only">Produits</caption>', false)
            // Tri courant par nom croissant : la colonne l'annonce et le lien inverse l'ordre
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('tri=nom&amp;ordre=desc', false)
            ->assertSee('<span class="sr-only">Actions</span>', false)
            ->assertSee('data-libelle="Produit"', false)
            ->assertSee('sticky top-0', false)
            ->assertSee('aria-label="Pagination"', false)
            ->assertSee('Affichage de');
    }

    public function test_menu_actions_accessible(): void
    {
        $this->blade('<x-menu-actions libelle="Actions pour Ciment"><x-menu-actions.element icone="pencil" href="/modifier">Modifier</x-menu-actions.element></x-menu-actions>')
            ->assertSee('aria-haspopup="menu"', false)
            ->assertSee('aria-label="Actions pour Ciment"', false)
            ->assertSee('role="menu"', false)
            ->assertSee('role="menuitem"', false)
            ->assertSee('verre', false);
    }

    public function test_modal_et_confirmation(): void
    {
        $this->blade('<x-modal id="nouvelle-categorie" titre="Nouvelle catégorie">Contenu</x-modal>')
            ->assertSee('<dialog', false)
            ->assertSee('aria-labelledby="nouvelle-categorie-titre"', false)
            ->assertSee('aria-label="Fermer"', false);

        $this->blade('<x-confirmation id="supprimer-1" action="/produits/1" titre="Supprimer ?" message="Action définitive." />')
            ->assertSee('role="alertdialog"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('autofocus', false)
            ->assertSee('x-chargement-envoi', false)
            ->assertSee('Action définitive.');
    }

    public function test_squelette_etat_vide_entete_et_recherche(): void
    {
        $this->blade('<x-squelette type="ligne" :lignes="2" />')
            ->assertSee('role="status"', false)
            ->assertSee('animate-squelette', false);

        $this->blade('<x-etat-vide titre="Aucun produit" texte="Ajoutez un article."><x-bouton>Ajouter</x-bouton></x-etat-vide>')
            ->assertSee('Aucun produit')
            ->assertSee('Ajouter');

        $this->blade('<x-entete-page titre="Produits" :fil="[\'Stock\' => \'/stock\', \'Produits\' => null]" />')
            ->assertSee('<h1', false)
            ->assertSee('aria-label="Fil d\'Ariane"', false)
            ->assertSee('aria-current="page"', false);

        $this->blade('<x-recherche placeholder="Nom ou code-barres…" />')
            ->assertSee('type="search"', false)
            ->assertSee('<label for="recherche-recherche" class="sr-only">Rechercher</label>', false)
            ->assertSee('aria-keyshortcuts', false)
            ->assertSee('Ctrl K');
    }
}
