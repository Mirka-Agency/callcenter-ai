@props([
    'employee' => null,
    'user' => null,
    'name' => null,
    'url' => null,
    'gender' => null,
    'agent' => false,
    'size' => 'md',
    'ring' => false,
    'person' => false,
])

@php
    use App\Support\AvatarPresenter;

    if ($employee) {
        $avatar = AvatarPresenter::forEmployee($employee, $size);
    } elseif ($user) {
        $avatar = AvatarPresenter::forUser($user, $size);
    } else {
        $avatar = AvatarPresenter::forName($name ?? '?', $size, $url, $gender, (bool) $agent);
    }

    $sizes = AvatarPresenter::sizeClasses($size);
    $roundedClass = ($user && ! $employee) ? 'rounded-full' : 'rounded-lg';
@endphp

<span
    {{ $attributes->class([
        'saas-avatar bg-gradient-to-br',
        $roundedClass,
        $avatar['gradient'],
        $sizes['box'],
        $ring ? 'ring-white dark:ring-zinc-900 '.$sizes['ring'] : '',
    ]) }}
    title="{{ $avatar['name'] }}"
    role="img"
    aria-label="{{ $avatar['name'] }}"
>
    @if ($avatar['url'])
        <img src="{{ $avatar['url'] }}" alt="" class="h-full w-full object-cover" loading="lazy">
    @elseif ($person)
        <x-saas.icon name="user" class="{{ $sizes['icon'] }} text-white" />
    @elseif ($avatar['use_agent_icon'] && ($avatar['icon'] ?? null))
        <x-saas.icon :name="$avatar['icon']" class="{{ $sizes['icon'] }} text-white" />
    @else
        {{ $avatar['initials'] }}
    @endif
</span>
