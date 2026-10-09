@include('errors.gabarit', [
    'code' => 403,
    'icone' => 'shield',
    'titre' => 'Accès refusé',
    'message' => ($exception->getMessage() ?: 'Vous n\'avez pas le droit d\'accéder à cette page.')
        .' Si vous pensez qu\'il s\'agit d\'une erreur, contactez l\'administrateur.',
])
