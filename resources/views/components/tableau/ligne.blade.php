{{--
    Ligne de x-tableau : survol sur ordinateur, carte sous 768 px.
--}}
<tr {{ $attributes->class([
    'group/ligne transition-colors duration-150 hover:bg-fond',
    'max-md:block max-md:rounded-carte max-md:border max-md:border-bordure max-md:bg-surface max-md:p-4 max-md:shadow-doux',
]) }}>
    {{ $slot }}
</tr>
