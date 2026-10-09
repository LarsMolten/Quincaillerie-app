<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Photos des produits : redimensionnées (800 px maximum) et converties en WebP avec GD,
 * puis stockées sur le disque privé « local » (servies par une route authentifiée).
 */
class PhotoProduitService
{
    public const DISQUE = 'local';

    public const DOSSIER = 'produits';

    public const TAILLE_MAXIMUM = 800;

    /** Enregistre la photo et renvoie son chemin relatif au disque. */
    public function enregistrer(UploadedFile $fichier): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));

        if ($image === false) {
            throw new RuntimeException('Image illisible : utilisez une photo JPEG, PNG ou WebP.');
        }

        $image = $this->redresser($image, $fichier);
        $image = $this->redimensionner($image);

        ob_start();
        imagewebp($image, null, 82);
        $contenu = ob_get_clean();
        imagedestroy($image);

        $chemin = self::DOSSIER.'/'.Str::uuid().'.webp';
        Storage::disk(self::DISQUE)->put($chemin, $contenu);

        return $chemin;
    }

    public function supprimer(?string $chemin): void
    {
        if ($chemin) {
            Storage::disk(self::DISQUE)->delete($chemin);
        }
    }

    /** Applique l'orientation EXIF des photos de téléphone (JPEG). */
    private function redresser(\GdImage $image, UploadedFile $fichier): \GdImage
    {
        if (! function_exists('exif_read_data') || $fichier->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $orientation = @exif_read_data($fichier->getRealPath())['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        return $angle ? imagerotate($image, $angle, 0) : $image;
    }

    private function redimensionner(\GdImage $image): \GdImage
    {
        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $echelle = min(1, self::TAILLE_MAXIMUM / max($largeur, $hauteur));

        if ($echelle >= 1) {
            return $image;
        }

        $nouvelle = imagecreatetruecolor((int) round($largeur * $echelle), (int) round($hauteur * $echelle));
        // Transparence des PNG conservée
        imagealphablending($nouvelle, false);
        imagesavealpha($nouvelle, true);
        imagecopyresampled($nouvelle, $image, 0, 0, 0, 0, imagesx($nouvelle), imagesy($nouvelle), $largeur, $hauteur);
        imagedestroy($image);

        return $nouvelle;
    }
}
