@props([
    'column',
    'label',
])

<th
    class="saas-sortable"
    x-bind:class="{ 'is-active': column === '{{ $column }}' }"
    x-bind:aria-sort="column === '{{ $column }}' ? (dir === 'asc' ? 'ascending' : 'descending') : 'none'"
>
    <button type="button" x-on:click.stop="sortBy('{{ $column }}')">
        {{ $label }}
        <x-saas.sort-icon
            x-bind:class="column === '{{ $column }}' ? (dir === 'asc' ? 'is-active is-asc' : 'is-active is-desc') : ''"
        />
    </button>
</th>
