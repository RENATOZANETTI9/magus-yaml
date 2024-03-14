<?php

namespace Magus\Yaml\Console\Commands;

use Illuminate\Console\Command;

class DatabaseSeederCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'database:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Database Seeder';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->call('vendor:publish', ['--tag' => 'database-seeder', '--force' => true]);

        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--class' => 'DatabaseSeeder']);
    }
}