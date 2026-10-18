<?php

namespace App\Console\Commands;

use App\Models\CodeExample;
use App\Models\Lesson;
use App\Services\Content\CompanionRepository;
use Illuminate\Console\Command;

/**
 * Links every book code listing to its file in the official Apress
 * companion repository via code_examples.external_ref
 * ("src/ch03/batch01/ShopProduct.php"), preferring the repository's
 * own contents.txt map and falling back to listing comment markers.
 */
final class ContentImportCompanion extends Command
{
    protected $signature = 'content:import-companion {--dry-run : Report what would change without writing}';

    protected $description = 'Link book code listings to the official Apress companion repository files (external_ref)';

    public function handle(CompanionRepository $repository): int
    {
        $root = $repository->root();

        if ($root === null) {
            $this->error('Companion repository not found — clone github.com/Apress/php-8-objects-patterns-practice under storage/app/private/companion/ (or set COMPANION_PATH).');

            return self::FAILURE;
        }

        $contents = $repository->contentsMap();
        $markers = $repository->markerIndex();

        if ($contents === [] && $markers === []) {
            $this->error('No contents.txt entries or listing markers found under '.$root);

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $examples = CodeExample::query()->whereNotNull('listing_ref')->get(['id', 'listing_ref', 'external_ref', 'lesson_id']);

        $updated = 0;
        $unchanged = 0;
        $viaContents = 0;
        $viaMarkers = 0;
        $unmapped = [];
        $touchedLessons = [];

        foreach ($examples as $example) {
            $ref = (string) $example->listing_ref;
            $source = $contents[$ref] ?? null;

            if ($source !== null) {
                $viaContents++;
            } else {
                $source = $markers[$ref] ?? null;

                if ($source !== null) {
                    $viaMarkers++;
                }
            }

            if ($source === null) {
                $unmapped[$ref] = true;

                continue;
            }

            if ($example->external_ref === $source) {
                $unchanged++;

                continue;
            }

            $updated++;
            $touchedLessons[$example->lesson_id] = true;

            if (! $dryRun) {
                CodeExample::query()->whereKey($example->id)->update(['external_ref' => $source]);
            }
        }

        if ($touchedLessons !== [] && ! $dryRun) {
            // The lesson payload cache is keyed on lesson.updated_at — bump
            // affected lessons so the companion attribution renders now.
            Lesson::query()->whereIn('id', array_keys($touchedLessons))->touch();
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->line('Companion root: '.$root);
        $this->line('contents.txt entries: '.count($contents).' · listing markers: '.count($markers));
        $this->line($prefix.'updated='.$updated.' unchanged='.$unchanged.' unmapped='.count($unmapped));
        $this->line('  sources: contents.txt='.$viaContents.' markers='.$viaMarkers);

        if ($unmapped !== []) {
            $refs = array_keys($unmapped);
            $shown = implode(', ', array_slice($refs, 0, 15));
            $this->line('  unmapped: '.$shown.(count($refs) > 15 ? ' … ('.count($refs).' total)' : ''));
        }

        return self::SUCCESS;
    }
}
