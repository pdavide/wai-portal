<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWebsitesIndex;
use Illuminate\Console\Command;

/**
 * Update websites index.
 */
class UpdateWebsitesIndex extends Command
{
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->signature = 'app:update-websites';
        $this->description = 'Update Websites index for ' . config('app.name');
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Updating Websites index...');
        dispatch(new ProcessWebsitesIndex())->onConnection('sync');
        $this->info('Websites index updated');
    }
}
