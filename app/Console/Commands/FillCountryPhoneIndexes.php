<?php

namespace App\Console\Commands;

use App\Models\Country;
use Illuminate\Console\Command;

class FillCountryPhoneIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'countries:fill-phone-indexes {--force : Overwrite existing phone_index values} {--dry-run : Show changes without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time fill/normalize country phone indexes (e.g. +995)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $map = config('country_phone_indexes', []);
        $normalizedMap = [];
        foreach ($map as $name => $code) {
            $normalizedMap[$this->normalizeCountryName((string) $name)] = $this->normalizeDialCode($code);
        }

        $updated = 0;
        $unchanged = 0;
        $unresolved = [];

        Country::query()
            ->select(['id', 'name', 'phone_index'])
            ->orderBy('id')
            ->chunkById(200, function ($countries) use ($force, $dryRun, $normalizedMap, &$updated, &$unchanged, &$unresolved) {
                foreach ($countries as $country) {
                    $current = $this->normalizeDialCode($country->phone_index);
                    $mapped = $normalizedMap[$this->normalizeCountryName((string) $country->name)] ?? null;

                    $target = $force ? ($mapped ?? $current) : ($current ?? $mapped);

                    if (!$target) {
                        $unresolved[] = [
                            'id' => $country->id,
                            'name' => $country->name,
                        ];
                        continue;
                    }

                    if ($current === $target) {
                        $unchanged++;
                        continue;
                    }

                    if (!$dryRun) {
                        $country->update(['phone_index' => $target]);
                    }

                    $updated++;
                }
            });

        $this->info($dryRun ? 'Dry-run finished.' : 'Phone index fill finished.');
        $this->line("Updated: {$updated}");
        $this->line("Unchanged: {$unchanged}");
        $this->line('Unresolved: ' . count($unresolved));

        if (!empty($unresolved)) {
            $this->warn('Countries without resolvable index:');
            foreach ($unresolved as $item) {
                $this->line("- #{$item['id']} {$item['name']}");
            }
        }

        return self::SUCCESS;
    }

    protected function normalizeDialCode($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }

        return '+' . $digits;
    }

    protected function normalizeCountryName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9 ]/u', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        return trim($name);
    }
}
