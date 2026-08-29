<?php

namespace App\Models;

use Filament\Actions\Exports\Models\Export as FilamentExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * How long a finished export stays downloadable.
 *
 * Filament's own model declares Prunable but implements no prunable() - Laravel's trait throws
 * if you call it, so the retention was never anybody's decision, and nothing was ever deleted.
 * Thirty exports had piled up here, the oldest from March.
 *
 * This subclass exists for that one purpose. It sits on the same table, so `php artisan
 * model:prune` finds it and prunes every export, whichever class wrote the row; Filament goes on
 * using its own model everywhere else and needs no rebinding.
 */
class Export extends FilamentExport
{
    /** Four weeks. Long enough to find a file again, short enough not to keep it forever. */
    public const KEEP_FOR_WEEKS = 4;

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subWeeks(self::KEEP_FOR_WEEKS));
    }

    /**
     * Runs for each record before it is deleted.
     *
     * Two things go with it. The files, through Filament's own method, because the row is only a
     * pointer and deleting it would otherwise leave the csv and xlsx behind for good. And the
     * notification that carried the download links, because once the files are gone the links in
     * it lead nowhere - an inbox of dead links is worse than an empty one.
     */
    protected function pruning(): void
    {
        $this->deleteFileDirectory();

        DB::table('notifications')->whereIn('id', $this->notificationIds())->delete();
    }

    /**
     * The notifications whose download links point at this export.
     *
     * Read and decoded rather than matched with LIKE. The column holds JSON, and json_encode
     * writes "\/filament\/exports\/43\/download" - so a pattern with plain slashes matches
     * nothing, and one with backslashes runs into LIKE treating the backslash as its own escape
     * character. Both were tried; both silently deleted nothing.
     *
     * @return list<string>
     */
    private function notificationIds(): array
    {
        $needle = '/exports/' . $this->getKey() . '/download';

        return DB::table('notifications')
            ->where('data', 'like', '%exports%')
            ->pluck('data', 'id')
            ->filter(function (string $data) use ($needle): bool {
                foreach (json_decode($data, true)['actions'] ?? [] as $action) {
                    if (str_contains($action['url'] ?? '', $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->keys()
            ->all();
    }
}
