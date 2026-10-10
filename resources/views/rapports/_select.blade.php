{{-- Filtre en liste déroulante d'un rapport. Variables : $nom, $libelle, $options ([id => libellé]), $valeur, $vide --}}
<label class="sr-only" for="filtre-{{ $nom }}">{{ $libelle }}</label>
<select id="filtre-{{ $nom }}" name="{{ $nom }}" class="h-9 rounded-controle border border-bordure-forte bg-surface px-2.5 text-sm text-texte pointer-coarse:h-11">
    <option value="">{{ $vide }}</option>
    @foreach ($options as $id => $texte)
        <option value="{{ $id }}" @selected((string) $valeur === (string) $id)>{{ $texte }}</option>
    @endforeach
</select>
