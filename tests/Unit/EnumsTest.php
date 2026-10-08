<?php

namespace Tests\Unit;

use App\Enums\CategorieDepense;
use App\Enums\ModePaiement;
use App\Enums\SensMouvement;
use App\Enums\StatutAchat;
use App\Enums\StatutFacture;
use App\Enums\StatutInventaire;
use App\Enums\StatutRetour;
use App\Enums\StatutVente;
use App\Enums\TypeMouvementStock;
use App\Enums\TypeRetour;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnumsTest extends TestCase
{
    private const COULEURS = ['succes', 'alerte', 'danger', 'info', 'neutre'];

    public static function enums(): array
    {
        return array_map(fn (string $enum) => [$enum], [
            'CategorieDepense' => CategorieDepense::class,
            'ModePaiement' => ModePaiement::class,
            'SensMouvement' => SensMouvement::class,
            'StatutAchat' => StatutAchat::class,
            'StatutFacture' => StatutFacture::class,
            'StatutInventaire' => StatutInventaire::class,
            'StatutRetour' => StatutRetour::class,
            'StatutVente' => StatutVente::class,
            'TypeMouvementStock' => TypeMouvementStock::class,
            'TypeRetour' => TypeRetour::class,
        ]);
    }

    #[DataProvider('enums')]
    public function test_chaque_cas_a_un_libelle_et_une_couleur_du_theme(string $enum): void
    {
        foreach ($enum::cases() as $cas) {
            $this->assertNotSame('', $cas->libelle());
            $this->assertContains($cas->couleur(), self::COULEURS);
        }
    }

    public function test_les_libelles_sont_en_francais(): void
    {
        $this->assertSame('Validée', StatutVente::Validee->libelle());
        $this->assertSame('Espèces', ModePaiement::Especes->libelle());
        $this->assertSame('Électricité', CategorieDepense::Electricite->libelle());
    }

    public function test_le_type_de_mouvement_impose_le_sens(): void
    {
        $this->assertSame(SensMouvement::Entree, TypeMouvementStock::Achat->sens());
        $this->assertSame(SensMouvement::Entree, TypeMouvementStock::RetourClient->sens());
        $this->assertSame(SensMouvement::Sortie, TypeMouvementStock::Vente->sens());
        $this->assertSame(SensMouvement::Sortie, TypeMouvementStock::Perte->sens());
    }

    public function test_les_encaissements_excluent_le_credit(): void
    {
        $this->assertNotContains(ModePaiement::Credit, ModePaiement::encaissements());
        $this->assertCount(4, ModePaiement::encaissements());
    }
}
