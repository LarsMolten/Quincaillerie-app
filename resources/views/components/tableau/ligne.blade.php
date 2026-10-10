{{--
    Ligne de x-tableau : survol sur ordinateur, carte sous 768 px.
    Sur mobile, la carte est une colonne flex : la cellule « principale » en devient toujours le titre (order-first),
    quelle que soit sa position dans les colonnes (ex. date ou case à cocher avant le nom).
--}}
<tr {{ $attributes->class([
    'group/ligne transition-colors duration-150 hover:bg-fond',
    'max-md:flex max-md:flex-col max-md:rounded-carte max-md:border max-md:border-bordure max-md:bg-surface max-md:p-4 max-md:shadow-doux',
]) }}>
    {{ $slot }}
</tr>
