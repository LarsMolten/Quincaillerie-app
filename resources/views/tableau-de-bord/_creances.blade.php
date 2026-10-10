{{-- Carte « Créances en cours » : total, part ancienne et principaux débiteurs --}}
@if ($creances['total'] <= 0)
    <x-etat-vide icone="circle-check" titre="Aucune créance" texte="Tous les clients ont réglé leurs achats." />
@else
    <div class="mb-4 grid grid-cols-2 gap-3 text-sm">
        <div class="rounded-controle bg-fond p-3">
            <p class="text-texte-doux">Total dû</p>
            <p class="chiffres text-lg font-semibold text-danger-texte">{{ format_ar($creances['total']) }}</p>
            <p class="chiffres text-xs text-texte-doux">{{ $creances['debiteurs'] }} client(s)</p>
        </div>
        <div class="rounded-controle bg-fond p-3">
            <p class="text-texte-doux">Depuis plus de 60 jours</p>
            <p @class(['chiffres text-lg font-semibold', 'text-alerte-texte' => $creances['plus_de_60'] > 0, 'text-texte-doux' => $creances['plus_de_60'] <= 0])>{{ format_ar($creances['plus_de_60']) }}</p>
        </div>
    </div>
    <ul class="divide-y divide-bordure text-sm" aria-label="Principaux débiteurs">
        @foreach ($creances['principaux'] as $client)
            <li class="flex items-center justify-between gap-3 py-2">
                <span class="flex min-w-0 items-center gap-2">
                    <x-avatar :nom="$client->nom" taille="sm" />
                    <span class="truncate">{{ $client->nom }}</span>
                    <span class="chiffres shrink-0 text-xs text-texte-doux">({{ $client->factures }})</span>
                </span>
                <span class="chiffres shrink-0 font-semibold">{{ format_ar($client->du) }}</span>
            </li>
        @endforeach
    </ul>
@endif
