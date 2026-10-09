{{--
    Enveloppe commune des champs de formulaire : libellé associé, aide et message d'erreur.
    Utilisé par x-champ, x-select et x-textarea (pas destiné à être appelé directement).

    Props : id, label, aide, erreur, requis
--}}
@props(['id', 'label' => null, 'aide' => null, 'erreur' => null, 'requis' => false])

<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-texte">
            {{ $label }}
            @if ($requis)
                <span class="text-danger-texte" aria-hidden="true">*</span>
                <span class="sr-only">(obligatoire)</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($aide)
        <p id="{{ $id }}-aide" class="text-sm text-texte-doux">{{ $aide }}</p>
    @endif

    @if ($erreur)
        <p id="{{ $id }}-erreur" class="flex items-start gap-1.5 text-sm font-medium text-danger-texte">
            <x-icone nom="circle-alert" taille="size-4" class="mt-0.5" />
            {{ $erreur }}
        </p>
    @endif
</div>
