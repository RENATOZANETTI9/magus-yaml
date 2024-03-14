<?php

namespace Magus\Yaml\Providers;


use Magus\Yaml\Console\Commands\DatabaseSeederCommand;
use \Magus\Yaml\Console\Commands\ProcessQueue;
use Illuminate\View\View;
use \Magus\Yaml\Services\ApiService;
use Illuminate\Support\ServiceProvider;

// v1.0
class MagusServiceProvider extends ServiceProvider
{
    
    public const ROOT_PATH = __DIR__ . '/../../';

    public function register()
    {
        $this->mergeConfigFrom(self::ROOT_PATH . 'config/magus.php', 'magus');
    }

    public function boot() 
    {
        $this->loadMigrationsFrom(self::ROOT_PATH . 'database/migrations');

        $this->loadRoutesFrom(self::ROOT_PATH . 'routes/web.php');

        $this->publishes([
            self::ROOT_PATH . 'config/magus.php' => config_path('magus.php'),
        ]);

        $this->publishes([
            self::ROOT_PATH . 'database/migrations' => database_path('migrations'),
        ]);

        $this->publishes([
            self::ROOT_PATH . 'database/seeders' => database_path('seeders'),
        ]);

        $this->app->bind(ApiService::class, function ($app) {
            return new ApiService();
        });

        if (app()->isProduction()) {
            \Symfony\Component\VarDumper\VarDumper::setHandler(function($var) {});
        }	

        $this->configureCommands();
    }

    protected function configureCommands() {
        if(!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            ProcessQueue::class,
            DatabaseSeederCommand::class
        ]);
    }

    protected function isCurrentCommand(string $name): bool
    {
        global $argv;
        return isset($argv[1]) && $argv[1] === $name;
    }
}