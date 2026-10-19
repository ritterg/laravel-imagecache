<?php

namespace Ritterg\Imagecache\Console;

use Illuminate\Console\Command;
use Ritterg\Imagecache\Imagecache;

class ClearCommand extends Command
{
    protected $signature = 'imagecache:clear {--expired : Only delete images past their lifetime}';

    protected $description = 'Delete rendered imagecache images (run after changing closure templates)';

    public function handle(Imagecache $imagecache): int
    {
        $deleted = $imagecache->clear((bool) $this->option('expired'));

        $this->info("Deleted {$deleted} cached image(s).");

        return self::SUCCESS;
    }
}
