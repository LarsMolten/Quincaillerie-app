{{--
    Liste commune des pages Créances (clients) et Dettes fournisseurs (rechargée seule : en-tête X-Fragment).
    Synthèse en tête (rafraîchie après un paiement), puis une ligne par tiers, dépliable sur ses documents impayés.

    Variables : $liste (paginateur de tiers avec du, documents, plus_ancienne, jours), $synthese, $recherche, $anciennete,
    $textes : [titre, tiers, documents, total, nombre, vide, videTexte, recherche, effacer], $routes : [index, detail, fiche (ou null)].
--}}
<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <x-carte-stat :libelle="$textes['total']" :valeur="format_ar($synthese['total'])" icone="banknote" />
    <x-carte-stat :libelle="$textes['nombre']" :valeur="$synthese['nombre']" icone="users" />
    <x-carte-stat libelle="Dues depuis plus de 60 jours" :valeur="format_ar($synthese['plusDe60'])" icone="triangle-alert" />
</div>

@if ($liste->isEmpty())
    <x-carte>
        @if ($recherche !== '' || $anciennete)
            <x-etat-vide icone="search" :titre="$textes['recherche']" texte="Aucun résultat ne correspond à cette recherche.">
                <x-bouton :href="route($routes['index'])" variante="secondaire" icone="x" data-liste-lien>Effacer les filtres</x-bouton>
            </x-etat-vide>
        @else
            <x-etat-vide icone="circle-check" :titre="$textes['vide']" :texte="$textes['videTexte']" />
        @endif
    </x-carte>
@else
    <x-tableau
        :legende="$textes['titre']"
        :pagination="$liste"
        :colonnes="[
            'tiers' => $textes['tiers'],
            'documents' => ['libelle' => $textes['documents'], 'alignement' => 'droite'],
            'depuis' => 'Plus ancien',
            'anciennete' => 'Ancienneté',
            'du' => ['libelle' => 'Montant dû', 'alignement' => 'droite'],
            'actions' => ['libelle' => 'Détail', 'masque' => true, 'alignement' => 'droite'],
        ]"
    >
        @foreach ($liste as $tiers)
            @php([$libelle, $couleur] = \App\Http\Controllers\CreanceController::TRANCHES[\App\Http\Controllers\CreanceController::tranche($tiers->jours)])
            <x-tableau.ligne>
                <x-tableau.cellule :libelle="$textes['tiers']" principale>
                    <div class="flex items-center gap-3">
                        <x-avatar :nom="$tiers->nom" />
                        <span class="min-w-0">
                            @if ($routes['fiche'])
                                <a href="{{ route($routes['fiche'], $tiers) }}" class="block truncate font-medium text-texte hover:underline">{{ $tiers->nom }}</a>
                            @else
                                <span class="block truncate font-medium">{{ $tiers->nom }}</span>
                            @endif
                            @if ($tiers->telephone)<span class="chiffres block text-xs font-normal text-texte-doux">{{ $tiers->telephone }}</span>@endif
                        </span>
                    </div>
                </x-tableau.cellule>
                <x-tableau.cellule :libelle="$textes['documents']" alignement="droite">{{ $tiers->documents }}</x-tableau.cellule>
                <x-tableau.cellule libelle="Plus ancien" class="whitespace-nowrap text-texte-doux">
                    {{ \Illuminate\Support\Carbon::parse($tiers->plus_ancienne)->translatedFormat('j M Y') }}
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Ancienneté">
                    <x-badge :couleur="$couleur" title="Plus ancien impayé : il y a {{ $tiers->jours }} jour(s)">{{ $libelle }}</x-badge>
                    <span class="sr-only">({{ $tiers->jours }} jours)</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Montant dû" alignement="droite">
                    <span class="font-semibold text-danger-texte">{{ format_ar($tiers->du) }}</span>
                </x-tableau.cellule>
                <x-tableau.cellule libelle="Détail" alignement="droite">
                    <x-bouton type="button" variante="secondaire" taille="sm" icone="chevron-down"
                              x-on:click="basculer({{ $tiers->id }}, {{ \Illuminate\Support\Js::from(route($routes['detail'], $tiers)) }})"
                              x-bind:aria-expanded="(!! details[{{ $tiers->id }}]?.ouvert).toString()" aria-controls="detail-{{ $tiers->id }}">
                        Détail <span class="sr-only">de {{ $tiers->nom }}</span>
                    </x-bouton>
                </x-tableau.cellule>
            </x-tableau.ligne>
            <tr id="detail-{{ $tiers->id }}" x-show="details[{{ $tiers->id }}]?.ouvert" x-cloak class="max-md:-mt-2 max-md:block">
                <td colspan="6" class="border-b border-bordure bg-fond px-4 py-4 max-md:block max-md:rounded-b-carte max-md:border max-md:border-t-0">
                    <div x-show="! details[{{ $tiers->id }}]?.html && ! details[{{ $tiers->id }}]?.erreur" class="h-16 animate-squelette rounded-controle bg-neutre-doux" role="status" aria-label="Chargement du détail…"></div>
                    <p x-show="details[{{ $tiers->id }}]?.erreur" x-cloak class="text-sm text-danger-texte">Le détail n'a pas pu être chargé. Réessayez.</p>
                    <div x-html="details[{{ $tiers->id }}]?.html ?? ''"></div>
                </td>
            </tr>
        @endforeach
    </x-tableau>
@endif
