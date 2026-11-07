{{--
    Announcements as headings that open in place, for the news page and the front page alike.
    There is no page per announcement, so everything there is to read is here.

    The text is printed unescaped because it is HTML already: Markdown rendered by
    Announcement::bodyHtmlFor(), which escapes any raw HTML and drops unsafe links. Nothing else
    goes in there.

    lang is set where an English reader gets the German text because there is no translation yet,
    so a screen reader does not pronounce German as English.
--}}
<ul class="news">
    @foreach ($announcements as $announcement)
        <li @if ($announcement['lang'] !== app()->getLocale()) lang="{{ $announcement['lang'] }}" @endif>
            <details>
                <summary>
                    <span class="when"><time datetime="{{ $announcement['datetime'] }}">{{ $announcement['date'] }}</time>@if ($announcement['revised']) · <time datetime="{{ $announcement['revised_time'] }}" lang="{{ app()->getLocale() }}">{{ __('site.news.revised', ['date' => $announcement['revised']]) }}</time>@endif</span>
                    <span class="headline">{{ $announcement['title'] }}</span>
                </summary>
                <div class="body">{!! $announcement['html'] !!}</div>
            </details>
        </li>
    @endforeach
</ul>
