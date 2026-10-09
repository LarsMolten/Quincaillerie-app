<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Opération métier refusée (règle de gestion non respectée).
 * Le message, en français, est destiné à l'utilisateur (toast d'erreur).
 */
class OperationRefuseeException extends RuntimeException {}
