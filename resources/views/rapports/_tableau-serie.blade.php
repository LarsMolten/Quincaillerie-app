{{-- Tableau de données d'un graphique, pour les lecteurs d'écran. Variables : $legende, $colonnes ([clé => libellé]), $lignes (liste de tableaux) --}}
<table class="sr-only">
    <caption>{{ $legende }}</caption>
    <thead><tr>@foreach ($colonnes as $libelle)<th scope="col">{{ $libelle }}</th>@endforeach</tr></thead>
    <tbody>
        @foreach ($lignes as $ligne)
            <tr>
                @foreach (array_keys($colonnes) as $i => $cle)
                    @if ($i === 0)<th scope="row">{{ $ligne[$cle] }}</th>@else<td>{{ is_numeric($ligne[$cle]) ? format_ar($ligne[$cle]) : $ligne[$cle] }}</td>@endif
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
