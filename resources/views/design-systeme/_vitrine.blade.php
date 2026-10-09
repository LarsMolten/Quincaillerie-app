{{-- Vitrine des composants ; $p préfixe les identifiants (la vitrine est affichée deux fois) --}}
@php
    $section = 'space-y-4';
    $titreSection = 'text-base font-semibold text-texte';
@endphp

<div class="space-y-10">
    {{-- Couleurs --}}
    <section class="{{ $section }}" aria-labelledby="{{ $p }}-couleurs">
        <h3 id="{{ $p }}-couleurs" class="{{ $titreSection }}">Jetons de couleur</h3>
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
            @foreach (['fond', 'surface', 'surface-elevee', 'bordure', 'bordure-forte', 'texte-doux'] as $jeton)
                <div class="space-y-1.5">
                    <div class="h-12 rounded-controle border border-bordure" style="background: var(--{{ $jeton }})"></div>
                    <p class="text-xs text-texte-doux">{{ $jeton }}</p>
                </div>
            @endforeach
        </div>
        <div class="flex overflow-hidden rounded-controle">
            {{-- Classes écrites en entier : Tailwind n'émet que les nuances réellement utilisées --}}
            @foreach ([
                50 => 'bg-primaire-50 text-primaire-900', 100 => 'bg-primaire-100 text-primaire-900',
                200 => 'bg-primaire-200 text-primaire-900', 300 => 'bg-primaire-300 text-primaire-900',
                400 => 'bg-primaire-400 text-primaire-900', 500 => 'bg-primaire-500 text-primaire-900',
                600 => 'bg-primaire-600 text-primaire-50', 700 => 'bg-primaire-700 text-primaire-50',
                800 => 'bg-primaire-800 text-primaire-50', 900 => 'bg-primaire-900 text-primaire-50',
            ] as $nuance => $classes)
                <div class="flex h-12 flex-1 items-end p-1 text-[0.625rem] font-medium {{ $classes }}">{{ $nuance }}</div>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (['succes', 'alerte', 'danger', 'info', 'neutre'] as $couleur)
                <x-badge :couleur="$couleur">{{ ucfirst($couleur) }}</x-badge>
            @endforeach
        </div>
    </section>

    {{-- Typographie --}}
    <section class="{{ $section }}" aria-labelledby="{{ $p }}-typo">
        <h3 id="{{ $p }}-typo" class="{{ $titreSection }}">Typographie (Inter variable)</h3>
        <p class="text-3xl font-semibold tracking-tight">Titre de page</p>
        <p class="text-lg font-medium">Sous-titre de section</p>
        <p class="text-sm text-texte-doux">Texte secondaire : informations complémentaires, aides et légendes.</p>
        <p class="chiffres text-2xl font-semibold">{{ format_ar(1250000) }} · {{ format_ar(35000) }}</p>
        <p class="text-sm">Lien : <a href="#" class="font-medium text-lien underline-offset-4 hover:underline">voir le détail</a></p>
    </section>

    {{-- Boutons --}}
    <section class="{{ $section }}" aria-labelledby="{{ $p }}-boutons">
        <h3 id="{{ $p }}-boutons" class="{{ $titreSection }}">Boutons</h3>
        <div class="flex flex-wrap items-center gap-3">
            <x-bouton type="button" icone="plus">Nouvelle vente</x-bouton>
            <x-bouton type="button" variante="secondaire" icone="download">Exporter</x-bouton>
            <x-bouton type="button" variante="fantome">Annuler</x-bouton>
            <x-bouton type="button" variante="danger" icone="trash-2">Supprimer</x-bouton>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <x-bouton type="button" taille="sm" variante="secondaire">Petit</x-bouton>
            <x-bouton type="button" taille="lg" icone-fin="chevron-right">Grand</x-bouton>
            <x-bouton type="button" chargement>Enregistrement…</x-bouton>
            <x-bouton type="button" variante="secondaire" disabled>Désactivé</x-bouton>
            <x-bouton type="button" variante="secondaire" icone="printer" aria-label="Imprimer" />
        </div>
    </section>

    {{-- Formulaires --}}
    <section class="{{ $section }}" aria-labelledby="{{ $p }}-formulaires">
        <h3 id="{{ $p }}-formulaires" class="{{ $titreSection }}">Champs de formulaire</h3>
        <form class="grid gap-4 sm:grid-cols-2" x-chargement-envoi x-on:submit.prevent="$dispatch('toast', { type: 'succes', message: 'Formulaire de démonstration envoyé.' })">
            <x-champ :id="$p.'-nom'" nom="nom" label="Nom du produit" placeholder="Ex. Ciment 50 kg" requis />
            <x-champ :id="$p.'-prix'" nom="prix_vente" label="Prix de vente" type="number" inputmode="numeric" suffixe="Ar" montant valeur="37000" aide="Prix TTC affiché en caisse." />
            <x-champ :id="$p.'-code'" nom="code_barres" label="Code-barres" icone="barcode" placeholder="Scanner ou saisir" />
            <x-select :id="$p.'-categorie'" nom="categorie_id" label="Catégorie" vide="Choisir une catégorie"
                      :options="[1 => 'Matériaux de construction', 2 => 'Plomberie', 3 => 'Électricité']" />
            <x-textarea :id="$p.'-notes'" nom="notes" label="Notes" :lignes="3" class="sm:col-span-2" />
            <div class="space-y-1">
                <x-case :id="$p.'-actif'" nom="actif" label="Produit actif" coche />
                <x-case :id="$p.'-gros'" nom="vente_gros" label="Autoriser la vente en gros" aide="Applique le prix de gros dès 10 unités." />
            </div>
            <x-interrupteur :id="$p.'-alerte'" nom="alerte_stock" label="Alerte de stock faible" actif aide="Notification quand le stock passe sous le minimum." />
            <div class="sm:col-span-2">
                <x-bouton icone="save">Enregistrer (démo)</x-bouton>
            </div>
        </form>
        {{-- Champ en erreur (rendu tel qu'après une validation échouée) --}}
        <x-formulaire.groupe :id="$p.'-erreur'" label="Quantité" erreur="La quantité doit être supérieure à 0." requis>
            <div class="flex h-11 overflow-hidden rounded-controle border border-danger bg-surface focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-anneau">
                <input id="{{ $p }}-erreur" value="0" aria-invalid="true" aria-describedby="{{ $p }}-erreur-erreur"
                       class="chiffres min-w-0 flex-1 bg-transparent px-3 text-right text-sm text-texte focus:outline-none">
            </div>
        </x-formulaire.groupe>
        <x-recherche :id="$p.'-recherche'" placeholder="Nom, référence ou code-barres…" :raccourci="$p === 'clair'" />
    </section>

    {{-- Cartes --}}
    <section class="{{ $section }}" aria-labelledby="{{ $p }}-cartes">
        <h3 id="{{ $p }}-cartes" class="{{ $titreSection }}">Cartes et statistiques</h3>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-carte-stat libelle="Ventes du jour" :valeur="format_ar(1250000)" :tendance="8.4" :serie="$serie" icone="shopping-cart" />
            <x-carte-stat libelle="Dépenses du jour" :valeur="format_ar(185000)" :tendance="-12" inverse icone="wallet" periode="vs semaine dernière" />
            <x-carte-stat libelle="Produits actifs" valeur="66" :tendance="0" icone="package" />
        </div>
        <x-carte titre="Stock faible" description="Produits sous le stock minimum">
            <x-slot:actions>
                <x-bouton type="button" variante="fantome" taille="sm" icone-fin="chevron-right">Tout voir</x-bouton>
            </x-slot:actions>
            <p class="text-sm text-texte-doux">Contenu de la carte : listes, graphiques, formulaires…</p>
        </x-carte>
    </section>

    {{-- Badges --}}
    <section class="{{ $section }}" aria-labelledby="{{ $p }}-badges">
        <h3 id="{{ $p }}-badges" class="{{ $titreSection }}">Badges de statut (depuis les enums)</h3>
        <div class="flex flex-wrap gap-2">
            @foreach ($statuts as $statut)
                <x-badge :statut="$statut" />
            @endforeach
            <x-badge couleur="info" :point="false">Sans pastille</x-badge>
        </div>
    </section>

    {{-- Tableau --}}
    <section class="{{ $section }}" aria-labelledby="{{ $p }}-tableau">
        <h3 id="{{ $p }}-tableau" class="{{ $titreSection }}">Tableau (cartes sous 768 px)</h3>
        <x-tableau
            legende="Produits de démonstration"
            :pagination="$produits"
            :colonnes="[
                'reference' => ['libelle' => 'Référence', 'triable' => true],
                'nom' => ['libelle' => 'Produit', 'triable' => true],
                'prix' => ['libelle' => 'Prix', 'triable' => true, 'alignement' => 'droite'],
                'stock' => ['libelle' => 'Stock', 'alignement' => 'droite'],
                'etat' => 'État',
                'actions' => ['libelle' => 'Actions', 'masque' => true],
            ]"
        >
            @foreach ($produits as $produit)
                <x-tableau.ligne>
                    <x-tableau.cellule libelle="Référence" class="text-texte-doux">{{ $produit['reference'] }}</x-tableau.cellule>
                    <x-tableau.cellule libelle="Produit" principale class="font-medium">{{ $produit['nom'] }}</x-tableau.cellule>
                    <x-tableau.cellule libelle="Prix" alignement="droite">{{ format_ar($produit['prix']) }}</x-tableau.cellule>
                    <x-tableau.cellule libelle="Stock" alignement="droite">{{ $produit['stock'] }}</x-tableau.cellule>
                    <x-tableau.cellule libelle="État"><x-badge :statut="$produit['etat']" /></x-tableau.cellule>
                    <x-tableau.cellule libelle="Actions" alignement="droite">
                        <x-menu-actions :libelle="'Actions pour '.$produit['nom']">
                            <x-menu-actions.element icone="eye" href="#">Voir</x-menu-actions.element>
                            <x-menu-actions.element icone="pencil" x-on:click="$dispatch('toast', { type: 'info', message: 'Modification (démo)' })">Modifier</x-menu-actions.element>
                            <x-menu-actions.element icone="trash-2" danger x-on:click="$dispatch('ouvrir-modal', '{{ $p }}-confirmation')">Supprimer</x-menu-actions.element>
                        </x-menu-actions>
                    </x-tableau.cellule>
                </x-tableau.ligne>
            @endforeach
        </x-tableau>
    </section>

    {{-- Modales et toasts --}}
    <section class="{{ $section }}" aria-labelledby="{{ $p }}-modales">
        <h3 id="{{ $p }}-modales" class="{{ $titreSection }}">Modales et notifications</h3>
        <div class="flex flex-wrap gap-3">
            <x-bouton type="button" variante="secondaire" icone="circle-plus" x-data x-on:click="$dispatch('ouvrir-modal', '{{ $p }}-modal')">Ouvrir une modale</x-bouton>
            <x-bouton type="button" variante="danger" icone="trash-2" x-data x-on:click="$dispatch('ouvrir-modal', '{{ $p }}-confirmation')">Confirmation</x-bouton>
            <x-bouton type="button" variante="secondaire" x-data x-on:click="$dispatch('toast', { type: 'succes', message: 'Vente VTE-2026-00128 enregistrée.' })">Toast succès</x-bouton>
            <x-bouton type="button" variante="secondaire" x-data x-on:click="$dispatch('toast', { type: 'erreur', message: 'Stock insuffisant pour « Ciment 50 kg ».' })">Toast erreur</x-bouton>
            <x-bouton type="button" variante="secondaire" x-data x-on:click="$dispatch('toast', { type: 'alerte', message: '3 produits sont sous le stock minimum.' })">Toast alerte</x-bouton>
            <x-bouton type="button" variante="secondaire" x-data x-on:click="$dispatch('toast', { type: 'info', message: 'Sauvegarde terminée.' })">Toast info</x-bouton>
        </div>

        <x-modal :id="$p.'-modal'" titre="Nouvelle catégorie" description="Les catégories regroupent les produits du catalogue.">
            <div class="space-y-4">
                <x-champ :id="$p.'-modal-nom'" nom="categorie" label="Nom" autofocus />
                <x-textarea :id="$p.'-modal-description'" nom="description_categorie" label="Description" :lignes="2" />
            </div>
            <x-slot:pied>
                <x-bouton type="button" variante="secondaire" x-on:click="$dispatch('fermer-modal', '{{ $p }}-modal')">Annuler</x-bouton>
                <x-bouton type="button" icone="save" x-on:click="$dispatch('fermer-modal', '{{ $p }}-modal'); $dispatch('toast', { type: 'succes', message: 'Catégorie enregistrée (démo).' })">Enregistrer</x-bouton>
            </x-slot:pied>
        </x-modal>

        <x-confirmation :id="$p.'-confirmation'" :action="route('design-systeme')" methode="DELETE"
                        titre="Supprimer « Ciment 50 kg » ?"
                        message="Le produit sera désactivé et n'apparaîtra plus en caisse. L'historique est conservé." />
    </section>

    {{-- Squelettes et état vide --}}
    <section class="{{ $section }}" aria-labelledby="{{ $p }}-chargement">
        <h3 id="{{ $p }}-chargement" class="{{ $titreSection }}">Chargement et état vide</h3>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-squelette type="stat" />
            <x-squelette type="carte" />
        </div>
        <x-squelette type="ligne" :lignes="3" />
        <x-squelette :lignes="3" />
        <x-carte :padding="false">
            <x-etat-vide icone="package" titre="Aucun produit" texte="Commencez par ajouter votre premier article au catalogue.">
                <x-bouton type="button" icone="plus">Ajouter un produit</x-bouton>
            </x-etat-vide>
        </x-carte>
    </section>

    {{-- Icônes --}}
    <section class="{{ $section }}" aria-labelledby="{{ $p }}-icones">
        <h3 id="{{ $p }}-icones" class="{{ $titreSection }}">Icônes Lucide disponibles ({{ count($icones) }})</h3>
        <ul class="grid grid-cols-4 gap-2 sm:grid-cols-8 xl:grid-cols-10">
            @foreach ($icones as $icone)
                <li class="flex flex-col items-center gap-1.5 rounded-controle border border-bordure bg-surface p-2 text-center" title="{{ $icone }}">
                    <x-icone :nom="$icone" />
                    <span class="w-full truncate text-[0.625rem] text-texte-doux">{{ $icone }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
