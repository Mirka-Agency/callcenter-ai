@props(['meta'])

<title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}">
<meta name="robots" content="{{ $meta['robots'] }}">
<link rel="canonical" href="{{ $meta['canonical'] }}">
@foreach ($meta['og'] as $property => $content)
    <meta property="{{ $property }}" content="{{ $content }}">
@endforeach
@foreach ($meta['twitter'] as $name => $content)
    <meta name="{{ $name }}" content="{{ $content }}">
@endforeach
@foreach ($meta['json_ld'] as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endforeach
