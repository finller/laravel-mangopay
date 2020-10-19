<?php

namespace Finller\Mangopay\Commands;

use Illuminate\Console\Command;

class MangopayCommand extends Command
{
    public $signature = 'laravel-mangopay';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
