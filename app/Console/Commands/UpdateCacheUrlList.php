<?php

namespace App\Console\Commands;

use App\Jobs\UpdateSiteListOnRedis;
use Illuminate\Console\Command;

/**
 * Update users index.
 */
class UpdateCacheUrlList extends Command
{
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->signature = 'app:update-urls';
        $this->description = 'Update cache url list for ' . config('app.name');
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Updating url list...');
        dispatch(new UpdateSiteListOnRedis())->onConnection('sync');
        $this->info('Url list updated');
    }
}
