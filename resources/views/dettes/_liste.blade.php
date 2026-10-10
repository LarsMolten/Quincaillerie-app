{{-- Liste des fournisseurs à payer (rechargée seule : en-tête X-Fragment) --}}
@include('paiements._liste-tiers', [
    'textes' => [
        'titre' => 'Fournisseurs à payer, du montant le plus élevé au plus faible',
        'tiers' => 'Fournisseur',
        'documents' => 'Achats impayés',
        'total' => 'Dettes totales',
        'nombre' => 'Fournisseurs à payer',
        'vide' => 'Aucune dette fournisseur',
        'videTexte' => 'Tous les achats sont réglés.',
        'recherche' => 'Aucune dette trouvée',
    ],
    'routes' => ['index' => 'dettes.index', 'detail' => 'dettes.show', 'fiche' => auth()->user()->can('fournisseurs.gerer') ? 'fournisseurs.show' : null],
])
