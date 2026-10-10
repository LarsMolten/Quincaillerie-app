<?php

namespace App\Rapports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Période d'un rapport : raccourcis (aujourd'hui, 7 jours, ce mois, mois dernier) ou dates personnalisées
 * (366 jours au plus). Bornes incluses, au jour près, fuseau Indian/Antananarivo.
 */
final class Periode
{
    public const RACCOURCIS = [
        'aujourdhui' => 'Aujourd\'hui',
        '7j' => '7 jours',
        'mois' => 'Ce mois',
        'mois_dernier' => 'Mois dernier',
        'personnalise' => 'Personnalisé',
    ];

    public const JOURS_MAXIMUM = 366;

    private function __construct(
        public readonly string $choix,
        public readonly Carbon $debut,
        public readonly Carbon $fin,
    ) {}

    public static function depuis(Request $requete): self
    {
        $choix = (string) $requete->query('periode', 'mois');
        $date = function (string $cle) use ($requete): ?Carbon {
            $valeur = (string) $requete->query($cle);

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) && checkdate((int) substr($valeur, 5, 2), (int) substr($valeur, 8, 2), (int) substr($valeur, 0, 4))
                ? Carbon::parse($valeur)->startOfDay()
                : null;
        };

        if ($choix === 'personnalise') {
            $du = $date('du');
            $au = $date('au');

            if ($du && $au) {
                if ($du->greaterThan($au)) {
                    [$du, $au] = [$au, $du];
                }
                $au = $au->min(today());
                $du = $du->max($au->copy()->subDays(self::JOURS_MAXIMUM - 1))->min($au);

                return new self('personnalise', $du, $au);
            }
        }

        return self::raccourci(array_key_exists($choix, self::RACCOURCIS) && $choix !== 'personnalise' ? $choix : 'mois');
    }

    public static function raccourci(string $choix): self
    {
        $aujourdhui = today();

        return match ($choix) {
            'aujourdhui' => new self($choix, $aujourdhui->copy(), $aujourdhui->copy()),
            '7j' => new self($choix, $aujourdhui->copy()->subDays(6), $aujourdhui->copy()),
            'mois_dernier' => new self($choix, $aujourdhui->copy()->subMonthNoOverflow()->startOfMonth(), $aujourdhui->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()),
            default => new self('mois', $aujourdhui->copy()->startOfMonth(), $aujourdhui->copy()),
        };
    }

    public static function entre(Carbon $debut, Carbon $fin): self
    {
        return new self('personnalise', $debut->copy()->startOfDay(), $fin->copy()->startOfDay());
    }

    /** Nombre de jours, bornes incluses. */
    public function jours(): int
    {
        return (int) $this->debut->diffInDays($this->fin) + 1;
    }

    /** Période de même durée juste avant (tendances). */
    public function precedente(): self
    {
        return self::entre($this->debut->copy()->subDays($this->jours()), $this->debut->copy()->subDay());
    }

    /** Regroupement des séries : jour (≤ 31 jours), semaine (≤ 120 jours) ou mois. */
    public function granularite(): string
    {
        return match (true) {
            $this->jours() <= 31 => 'jour',
            $this->jours() <= 120 => 'semaine',
            default => 'mois',
        };
    }

    /** Clé de regroupement d'une date selon la granularité (début de semaine ou de mois). */
    public function groupe(Carbon $date): string
    {
        return match ($this->granularite()) {
            'jour' => $date->toDateString(),
            'semaine' => $date->copy()->startOfWeek()->toDateString(),
            default => $date->copy()->startOfMonth()->toDateString(),
        };
    }

    public function libelleGroupe(string $cle): string
    {
        $date = Carbon::parse($cle);

        return match ($this->granularite()) {
            'jour' => $date->translatedFormat('j M'),
            'semaine' => 'Sem. du '.$date->translatedFormat('j M'),
            default => ucfirst($date->translatedFormat('F Y')),
        };
    }

    /** Ex. « 12 octobre 2026 », « du 1er au 12 octobre 2026 », « du 15 septembre au 12 octobre 2026 ». */
    public function libelle(): string
    {
        $jour = fn (Carbon $d) => $d->day === 1 ? '1er' : (string) $d->day;

        if ($this->debut->isSameDay($this->fin)) {
            return $jour($this->debut).' '.$this->fin->translatedFormat('F Y');
        }
        if ($this->debut->isSameMonth($this->fin)) {
            return 'du '.$jour($this->debut).' au '.$jour($this->fin).' '.$this->fin->translatedFormat('F Y');
        }
        if ($this->debut->isSameYear($this->fin)) {
            return 'du '.$jour($this->debut).' '.$this->debut->translatedFormat('F').' au '.$jour($this->fin).' '.$this->fin->translatedFormat('F Y');
        }

        return 'du '.$jour($this->debut).' '.$this->debut->translatedFormat('F Y').' au '.$jour($this->fin).' '.$this->fin->translatedFormat('F Y');
    }

    /** Paramètres d'URL (exports, liens). */
    public function parametres(): array
    {
        return $this->choix === 'personnalise'
            ? ['periode' => 'personnalise', 'du' => $this->debut->toDateString(), 'au' => $this->fin->toDateString()]
            : ['periode' => $this->choix];
    }

    /** Suffixe de nom de fichier : 2026-10-01_2026-10-12. */
    public function suffixeFichier(): string
    {
        return $this->debut->toDateString().'_'.$this->fin->toDateString();
    }
}
