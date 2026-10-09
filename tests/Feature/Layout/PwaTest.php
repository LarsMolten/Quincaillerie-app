<?php

namespace Tests\Feature\Layout;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_le_manifeste_est_valide_et_ses_icones_existent(): void
    {
        $manifeste = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Quincaillerie', $manifeste['name']);
        $this->assertSame('/', $manifeste['start_url']);
        $this->assertSame('standalone', $manifeste['display']);
        $this->assertSame('fr', $manifeste['lang']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $manifeste['theme_color']);

        $tailles = collect($manifeste['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $tailles);
        $this->assertContains('512x512', $tailles);
        $this->assertContains('maskable', collect($manifeste['icons'])->pluck('purpose')->all());

        foreach ($manifeste['icons'] as $icone) {
            $chemin = public_path(ltrim($icone['src'], '/'));
            $this->assertFileExists($chemin);
            [$largeur, $hauteur] = getimagesize($chemin);
            $this->assertSame($icone['sizes'], "{$largeur}x{$hauteur}");
        }
    }

    public function test_le_layout_declare_la_pwa(): void
    {
        $this->withoutVite()
            ->get(route('connexion'))
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee('name="theme-color"', false);
    }

    public function test_le_service_worker_ne_met_en_cache_que_les_ressources_statiques(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // Liste blanche : ressources compilées, icônes et manifeste uniquement
        preg_match('/RESSOURCES_STATIQUES = \[(.*?)\];/s', $sw, $liste);
        $this->assertSame(['/^\/build\//', '/^\/icones\//', '/^\/manifest\.webmanifest$/'], array_map('trim', explode(',', $liste[1])));

        // Jamais les pages, ni les requêtes autres que GET, ni d'autres domaines
        $this->assertStringContainsString("requete.method === 'GET'", $sw);
        $this->assertStringContainsString("requete.mode !== 'navigate'", $sw);
        $this->assertStringContainsString('url.origin === self.location.origin', $sw);
        $this->assertSame(1, substr_count($sw, 'cache.put('), 'Une seule écriture en cache, derrière le filtre estStatique.');
    }
}
