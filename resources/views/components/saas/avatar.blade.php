@props([
    'employee' => null,
    'user' => null,
    'name' => null,
    'url' => null,
    'size' => 'md',
    'ring' => false,
])

@php
    use App\Support\AvatarPresenter;

    if ($employee) {
        $avatar = AvatarPresenter::forEmployee($employee, $size);
    } elseif ($user) {
        $avatar = AvatarPresenter::forUser($user, $size);
    } else {
        $avatar = AvatarPresenter::forName($name ?? '?', $size, $url);
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
    @else
        {{ $avatar['initials'] }}
    @endif
</span>
