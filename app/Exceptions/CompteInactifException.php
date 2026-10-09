<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Identifiants corrects, mais compte désactivé par un administrateur.
 */
class CompteInactifException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Votre compte est désactivé. Contactez l\'administrateur.');
    }
}
