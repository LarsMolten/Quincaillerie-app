{{-- Liste des clients débiteurs (rechargée seule : en-tête X-Fragment) --}}
@include('paiements._liste-tiers', [
    'textes' => [
        'titre' => 'Clients ayant une créance, du montant le plus élevé au plus faible',
        'tiers' => 'Client',
        'documents' => 'Factures impayées',
        'total' => 'Créances totales',
        'nombre' => 'Clients débiteurs',
        'vide' => 'Aucune créance en cours',
        'videTexte' => 'Tous les clients ont réglé leurs achats.',
        'recherche' => 'Aucune créance trouvée',
    ],
    'routes' => ['index' => 'creances.index', 'detail' => 'creances.show', 'fiche' => auth()->user()->can('clients.gerer') ? 'clients.show' : null],
])
