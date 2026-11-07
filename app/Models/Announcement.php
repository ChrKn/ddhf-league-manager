<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;

/**
 * Something the federation wants readers to know, most often that a result was corrected.
 *
 * Shown on the site as a list and nowhere else: no page of its own, nothing in the API, and only
 * for a year. After that it has done its job, and a list that keeps growing buries the one that
 * matters today.
 *
 * The text is Markdown, and only a little of it - the panel offers bold, italic and links. Raw HTML
 * in it is escaped rather than passed on, so whatever somebody pastes in, the page shows it as text.
 */
class Announcement extends Model
{
    /** How far back the site looks. */
    public const SHOWN_FOR_MONTHS = 12;

    protected $fillable = [
        'title',
        'body',
        'title_en',
        'body_en',
        'user_id',
        'revised_at',
    ];

    protected function casts(): array
    {
        return [
            'revised_at' => 'datetime',
        ];
    }

    /** Who wrote it. For the panel; the site never prints it. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** What the site shows: the last twelve months, newest first. */
    public function scopeRecent(Builder $query): Builder
    {
        return $query
            ->where('created_at', '>=', now()->subMonths(self::SHOWN_FOR_MONTHS))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** Whether it is still old enough to be on the site - the same line as recent(). */
    public function isShown(): bool
    {
        return $this->created_at !== null
            && $this->created_at->gte(now()->subMonths(self::SHOWN_FOR_MONTHS));
    }

    /**
     * Whether there is a version in this language. German always is; English only when both the
     * heading and the text have been filled in, so a reader never gets an English heading over a
     * German text.
     */
    public function isTranslated(string $locale): bool
    {
        return $locale !== 'en' || (filled($this->title_en) && filled($this->body_en));
    }

    /** The language the shown text is actually in, for the lang attribute. */
    public function languageFor(string $locale): string
    {
        return $this->isTranslated($locale) ? $locale : 'de';
    }

    public function titleFor(string $locale): string
    {
        return $this->isTranslated($locale) && $locale === 'en' ? $this->title_en : $this->title;
    }

    /**
     * The text as HTML. Safe to print unescaped: raw HTML in the Markdown is escaped and links
     * to javascript: and the like are dropped.
     *
     * A link that leaves this site opens in a new tab, with rel="noopener noreferrer". Only this
     * site's own host counts as inside - ddhf.de is a different site, and a reader in the middle
     * of a standing should not lose it for the federation's front page. The view marks those
     * links, so the new tab is announced rather than sprung on anybody.
     */
    public function bodyHtmlFor(string $locale): string
    {
        $markdown = $this->isTranslated($locale) && $locale === 'en' ? $this->body_en : $this->body;

        return Str::markdown($markdown, [
            'html_input'         => 'escape',
            'allow_unsafe_links' => false,
            'external_link'      => [
                'internal_hosts'     => array_filter([parse_url((string) config('app.url'), PHP_URL_HOST)]),
                'open_in_new_window' => true,
                'noopener'           => 'external',
                'noreferrer'         => 'external',
            ],
        ], [new ExternalLinkExtension()]);
    }
}
