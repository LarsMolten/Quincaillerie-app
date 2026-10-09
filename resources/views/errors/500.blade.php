@include('errors.gabarit', [
    'code' => 500,
    'icone' => 'triangle-alert',
    'titre' => 'Erreur interne',
    'message' => 'Un problème inattendu est survenu. Aucune donnée n\'a été perdue : réessayez dans un instant, et prévenez l\'administrateur si le problème persiste.',
])
