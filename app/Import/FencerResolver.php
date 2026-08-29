<?php

namespace App\Import;

use App\Models\Fencer;
use App\Models\Group;
use Illuminate\Support\Collection;

/**
 * Finds the fencer behind a name written in an imported file.
 *
 * Names alone are far too weak to decide this.
 *
 * The club is what actually separates people, so it is used first: within a single club a
 * surname is nearly unique, and a first name typo is then safe to accept. Only if that fails
 * does a global search run, and its results always need confirming.
 */
class FencerResolver
{
    /** Within a known club, this is enough - the club has already done the hard filtering. */
    private const LIKELY_IN_GROUP = 0.80;

    /** Across the whole database, only a near-identical name may be suggested at all. */
    private const LIKELY_GLOBAL = 0.85;

    /** Below this a global candidate is not worth showing. */
    private const WORTH_SHOWING = 0.60;

    /** Everyone, so an id in a review file still resolves. */
    private Collection $fencers;

    /** Everyone who can be recognised by name. */
    private Collection $candidates;

    public function __construct()
    {
        $this->fencers = Fencer::with('group')->get();

        // An anonymised record has no identity left to recognise. Its placeholder name matches
        // every further anonymous row, which would quietly pile unrelated results onto one
        // person - and as an exact match it would not even be flagged for review.
        //
        // The name is checked as well as the flag. A row the organisers had already anonymised is
        // imported with create_inactive, which disables the record but sets no anonymisation date,
        // so the flag alone lets the placeholder back in. It did: the three anonymous starters of
        // 2024 all landed on a placeholder that had stood for someone else since 2023.
        $this->candidates = $this->fencers->reject(
            fn (Fencer $fencer) => $fencer->isAnonymized() || $fencer->display_name === Fencer::ANONYMOUS_NAME,
        );
    }

    public function resolve(string $name, ?Group $group): Suggestion
    {
        $name = trim($name);

        if ($name === '') {
            return Suggestion::missing($name);
        }

        $normalized = NameMatcher::normalize($name);

        foreach ($this->candidates as $fencer) {
            if (NameMatcher::normalize($fencer->display_name) === $normalized) {
                return new Suggestion($name, $fencer, MatchConfidence::Exact, 1.0);
            }
        }

        // The name under which a person competed before a legal name change. Usually the name at birth.
        foreach ($this->candidates as $fencer) {
            if ($fencer->birth_name && NameMatcher::normalize($this->bornAs($fencer)) === $normalized) {
                return new Suggestion($name, $fencer, MatchConfidence::Likely, 1.0);
            }
        }

        if ($group !== null) {
            $inGroup = $this->bestWithin($name, $this->candidates->where('group_id', $group->id));

            if ($inGroup !== null && $inGroup['score'] >= self::LIKELY_IN_GROUP) {
                return new Suggestion($name, $inGroup['fencer'], MatchConfidence::Likely, $inGroup['score']);
            }
        }

        $global = $this->bestWithin($name, $this->candidates);

        if ($global === null) {
            return Suggestion::missing($name);
        }

        if ($global['score'] >= self::LIKELY_GLOBAL) {
            return new Suggestion($name, $global['fencer'], MatchConfidence::Likely, $global['score']);
        }

        if ($global['score'] >= self::WORTH_SHOWING) {
            return new Suggestion($name, $global['fencer'], MatchConfidence::Unsure, $global['score']);
        }

        return Suggestion::missing($name);
    }

    /** What this person was called before the name change - first name plus birth name. */
    private function bornAs(Fencer $fencer): string
    {
        return trim($fencer->first_name . ' ' . $fencer->birth_name);
    }

    /**
     * Scored against the current name and, where there is one, against the name someone was born
     * with - whichever fits better. A sheet from before the change says the old one.
     *
     * @param  Collection<int, Fencer>  $candidates
     * @return array{fencer: Fencer, score: float}|null
     */
    private function bestWithin(string $name, Collection $candidates): ?array
    {
        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $fencer) {
            $score = NameMatcher::score($name, $fencer->display_name);

            if ($fencer->birth_name) {
                $score = max($score, NameMatcher::score($name, $this->bornAs($fencer)));
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $fencer;
            }
        }

        return $best === null ? null : ['fencer' => $best, 'score' => $bestScore];
    }

    public function byPublicId(string $publicId): ?Fencer
    {
        return $this->fencers->firstWhere('public_id', $publicId);
    }

    /** Take a newly created fencer into account without rebuilding the resolver. */
    public function remember(Fencer $fencer): void
    {
        $this->fencers->push($fencer);

        if (!$fencer->isAnonymized() && $fencer->display_name !== Fencer::ANONYMOUS_NAME) {
            $this->candidates->push($fencer);
        }
    }

    /**
     * Split a full name into first and last name. Everything before the last word is the first
     * name, which is the best a single column allows.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) <= 1) {
            return ['', trim($name)];
        }

        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }
}
