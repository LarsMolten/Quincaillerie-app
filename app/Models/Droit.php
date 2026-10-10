<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Droit d'accès de la forme « module.action » (ex. « ventes.creer »).
 */
class Droit extends Model
{
    use HasFactory;

    /** Modules de la matrice « Rôles et droits », dans l'ordre du menu : code => [libellé, icône]. */
    public const MODULES = [
        'produits' => ['Produits', 'package'],
        'categories' => ['Catégories et unités', 'tags'],
        'stock' => ['Stock', 'warehouse'],
        'inventaires' => ['Inventaires', 'clipboard-list'],
        'ventes' => ['Ventes', 'shopping-cart'],
        'factures' => ['Factures', 'file-text'],
        'retours' => ['Retours', 'undo-2'],
        'achats' => ['Achats', 'truck'],
        'fournisseurs' => ['Fournisseurs', 'store'],
        'clients' => ['Clients', 'users'],
        'paiements' => ['Paiements', 'credit-card'],
        'depenses' => ['Dépenses', 'wallet'],
        'finances' => ['Finances', 'banknote'],
        'rapports' => ['Rapports', 'chart-column'],
        'utilisateurs' => ['Utilisateurs', 'user'],
        'roles' => ['Rôles et droits', 'shield'],
        'parametres' => ['Paramètres', 'settings'],
        'journal' => ['Journal d\'activité', 'scroll-text'],
    ];

    protected $table = 'droits';

    protected $fillable = [
        'code',
        'libelle',
        'module',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_droit', 'droit_id', 'role_id');
    }
}
