{{--
    No XML declaration here - SitemapController puts it in front. Any "<?" in this file, even inside
    an echoed string, is the start of PHP to a server with short_open_tag on, and Blade only compiles
    what PHP's lexer calls HTML: the line is left as it stands and the page dies of a parse error.

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
