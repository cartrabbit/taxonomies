<?php namespace Cartrabbit\Taxonomies;

use Illuminate\Support\ServiceProvider;

class TaxonomiesServiceProvider extends ServiceProvider
{
    protected $migrations = [
        'CreateTaxonomiesTable' => 'create_taxonomies_table'
    ];

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->handleConfig();
        $this->handleMigrations();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
//	    $this->app->singleton(Taxonomies::class);
    }

    /**
     * @inheritdoc
     */
    public function provides()
    {
        return [];
    }

    /**
     * Publish and merge the config file
     *
     * @return void
     */
    private function handleConfig()
    {
//        $configPath = __DIR__ . '/../config/config.php';
//        $configPath = '/var/www/cartrabbit_framework/administrator/components/com_joomlarabbit/cartrabbit.config.php';
//       echo "<pre>";print_r($this->app);exit;
//        $this->publishes([$configPath => config_path('lecturize.php')]);
//        $this->publishes([$configPath => '/var/www/cartrabbit_framework/administrator/components/com_joomlarabbit/cartrabbit.config.php']);
//        $this->mergeConfigFrom($configPath, 'cartrabbit');

        /*app('config')->taxonomies([
            //Terms table
            'table_terms' => 'terms',
            //Taxonomies table
            'table_taxonomies' => 'taxonomies',
            //Relationship table
            'table_pivot' => 'taxables',
        ]);*/
    }

    /**
     * Publish migrations
     *
     * @return void
     */
    private function handleMigrations()
    {
        foreach ($this->migrations as $class => $file) {
            if (!class_exists($class)) {
                $timestamp = date('Y_m_d_His', time());
//                $this->publishes([
//                    __DIR__ .'/../database/migrations/'. $file .'.php.stub' =>
//                        database_path('migrations/'. $timestamp .'_'. $file .'.php')
//                ], 'migrations');
            }
        }
    }
}