{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{{--
    The declaration comes first because nothing may stand before it, not even a line break, and is
    echoed because Blade would read a literal "<?" as the start of PHP.

    One <url> per address, and in each the other language as well as its own - which is what
    hreflang in a sitemap asks for, and the same pair the layout names in every page's head.
--}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($entries as $entry)
@foreach ($entry as $url)
    <url>
        <loc>{{ $url }}</loc>
        <xhtml:link rel="alternate" hreflang="de" href="{{ $entry['de'] }}"/>
        <xhtml:link rel="alternate" hreflang="en" href="{{ $entry['en'] }}"/>
    </url>
@endforeach
@endforeach
</urlset>
