<?php

namespace Pheicloud\Aruba;

use Illuminate\Support\ServiceProvider;

class ArubaServiceProvider extends ServiceProvider
{
    /**
     * 在注册后进行服务的启动
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/aruba.php' => app()->basePath() . '/config/aruba.php',
        ]);
    }

    /**
     * 注册服务
     */
    public function register()
    {
        $this->app->singleton('aruba', function () {
            return new Aruba;
        });
    }
}
