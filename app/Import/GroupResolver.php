<?php

namespace App\Import;

use App\Models\Group;
use App\Models\GroupAlias;
use Illuminate\Support\Collection;

/**
 * Finds the club behind a name written in an imported file.
 *
 * The order matters: an exact name or a recorded alias is a decision, similarity is only ever
 * a suggestion.
 */
class GroupResolver
{
    /** Below this, a similarity hit is shown but flagged as unsure. */
    private const LIKELY = 0.75;

    /** Below this, a candidate is not even worth showing. */
    private const WORTH_SHOWING = 0.45;

    private Collection $groups;

    /** @var array<string, int> normalized alias => group id */
    private array $aliases;

    public function __construct()
    {
        $this->groups = Group::all();

        $this->aliases = GroupAlias::all()
            ->mapWithKeys(fn (GroupAlias $alias) => [NameMatcher::normalize($alias->alias) => $alias->group_id])
            ->all();
    }

    /**
     * @param  string  $name  the club as spelled in the file
     * @param  bool  $expectedInDatabase  true when the file marks the club as a DDHF member;
     *                                    such a club has to exist, so a weak hit is still worth
     *                                    showing. Non-members are usually genuinely unknown.
     */
    public function resolve(string $name, bool $expectedInDatabase = true): Suggestion
    {
        $name = trim($name);

        if ($name === '') {
            return Suggestion::missing($name);
        }

        $normalized = NameMatcher::normalize($name);

        foreach ($this->groups as $group) {
            if (NameMatcher::normalize($group->name) === $normalized) {
                return new Suggestion($name, $group, MatchConfidence::Exact, 1.0);
            }
        }

        if (isset($this->aliases[$normalized])) {
            $group = $this->groups->firstWhere('id', $this->aliases[$normalized]);

            if ($group) {
                return new Suggestion($name, $group, MatchConfidence::Alias, 1.0);
            }
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($this->groups as $group) {
            $score = NameMatcher::score($name, $group->name);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $group;
            }
        }

        if ($bestScore >= self::LIKELY) {
            return new Suggestion($name, $best, MatchConfidence::Likely, $bestScore);
        }

        // A club the file marks as a member must exist somewhere, so show the nearest candidate
        // even when it is weak. For a non-member, a weak hit is noise.
        if ($expectedInDatabase && $bestScore >= self::WORTH_SHOWING) {
            return new Suggestion($name, $best, MatchConfidence::Unsure, $bestScore);
        }

        return Suggestion::missing($name);
    }

    /**
     * Record a decision so the next import resolves this spelling on its own.
     *
     * A spelling belongs to one club. The lookup above folds before it compares, so it is the
     * folded form that has to be unique and not the string - "ESK Augsburg" and "ESK-Augsburg
     * e. V." are one key, and the alias table has nineteen such pairs already. They are harmless
     * because each pair sits on one club; the case this guards is the other one.
     *
     * When the key already belongs to a different club, nothing is stored and that club is
     * returned. The stored decision wins over the one being made now, because the older one has
     * been resolving imports since somebody made it, and silently repointing it would move the
     * other club's results without anybody being asked. The row still counts towards the club
     * the review screen picked - that decision is about this file and stands.
     *
     * @return Group|null the club that already holds this spelling, when it is not this one
     */
    public function rememberAlias(string $alias, Group $group): ?Group
    {
        $alias = trim($alias);
        $key = NameMatcher::normalize($alias);

        if ($alias === '' || $key === NameMatcher::normalize($group->name)) {
            return null;
        }

        if (isset($this->aliases[$key]) && $this->aliases[$key] !== $group->id) {
            return $this->owner($this->aliases[$key]);
        }

        // A name beats an alias in resolve(), so a spelling that is another club's name would be
        // stored and then never used. Reported rather than written, because a dead row is worse
        // than none: it reads like the case is handled.
        $named = $this->groups->first(fn (Group $other) => NameMatcher::normalize($other->name) === $key);

        if ($named && $named->id !== $group->id) {
            return $named;
        }

        $stored = GroupAlias::firstOrCreate(['alias' => $alias], ['group_id' => $group->id]);

        // firstOrCreate returns a row it found without touching it, so the same string sitting on
        // another club comes back here rather than moving. The map must not claim otherwise.
        if ($stored->group_id !== $group->id) {
            $this->aliases[$key] = $stored->group_id;

            return $this->owner($stored->group_id);
        }

        $this->aliases[$key] = $group->id;

        return null;
    }

    private function owner(int $groupId): ?Group
    {
        return $this->groups->firstWhere('id', $groupId) ?? Group::find($groupId);
    }

    public function byPublicId(string $publicId): ?Group
    {
        return $this->groups->firstWhere('public_id', $publicId);
    }

    /** Take a newly created club into account without rebuilding the resolver. */
    public function remember(Group $group): void
    {
        $this->groups->push($group);
    }
}
