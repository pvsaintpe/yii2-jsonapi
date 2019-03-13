<?php

namespace pvsaintpe\jsonapi;

use pvsaintpe\jsonapi\configs\Configs;
use yii\base\BootstrapInterface;
use yii\base\Application;

/**
 * Class Bootstrap
 * @package pvsaintpe\log
 */
class Bootstrap implements BootstrapInterface
{
    /**
     * @param Application $app
     * @throws
     */
    public function bootstrap($app)
    {
        $app->setModule(Configs::instance()->id, ['class' => 'pvsaintpe\jsonapi\Module']);
    }
}
