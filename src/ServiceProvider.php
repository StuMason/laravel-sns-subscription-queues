<?php 

namespace Kirschbaum\LaravelSnsSubscriptionQueues;

use Illuminate\Queue\QueueServiceProvider;

class ServiceProvider extends QueueServiceProvider
{

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot() {
        $this->handleConfigs();
    }

    /**
     * Register the queue worker.
     *
     * @return void
     */
    protected function registerWorker()
    {
        $this->registerWorkCommand();

        $this->registerRestartCommand();

        $this->app->singleton('queue.worker', function ($app) {
            return new QueueWorker($app['queue'], $app['queue.failer'], $app['events']);
        });
    }

    protected function handleConfigs() {
        $configPath = __DIR__ . '/../config/queue-custom-handlers.php';
        $this->publishes([$configPath => config_path('queue-custom-handlers.php')]);
        $this->mergeConfigFrom($configPath, 'queue-custom-handlers');
    }

}
