<?php

namespace pvsaintpe\jsonapi\configs;

use pvsaintpe\jsonapi\exceptions\CommonException;
use pvsaintpe\jsonapi\exceptions\InvalidHeaderException;
use pvsaintpe\jsonapi\exceptions\MissingHeaderException;
use pvsaintpe\search\components\ActiveRecord;
use Yii;
use yii\base\BaseObject;
use pvsaintpe\helpers\ArrayHelper;

/**
 * Configs
 * Used to configure some values. To set config you can use [[\yii\base\Application::$params]]
 *
 * ```
 * return [
 *     'jsonapi.configs' => [
 *         'db' => 'customDb',
 *         'storageDb' => 'customDb',
 *     ]
 * ];
 * ```
 *
 * or use [[\Yii::$container]]
 *
 * ```
 * Yii::$container->set('pvsaintpe\jsonapi\components\Configs',[
 *     'db' => 'customDb',
 *     'storageDb' => 'customDb',
 *     ...
 * ]);
 * ```
 *
 * @author Pavel Veselov <pvsaintpe@icloud.com>
 * @since 1.0
 */
class Configs extends BaseObject
{
    /**
     * @var string
     */
    public $id = 'jsonapi';

    /**
     * @var string
     */
    public $compareError = 'Неверный заголовок';

    /**
     * @var string
     */
    public $checkError = 'Ошибка проверки заголовка';

    /**
     * @var string
     */
    public $activeRecord = ActiveRecord::class;

    /**
     * @var string Common Exception Class name.
     */
    public $commonException = CommonException::class;

    /**
     * @var string Common Exception Class name.
     */
    public $invalidHeaderException = InvalidHeaderException::class;

    /**
     * @var string Common Exception Class name.
     */
    public $missingHeaderException = MissingHeaderException::class;

    public $controllerClass = 'api\components\controllers\base\JsonRpcController';

    /**
     * @var self Instance of self
     */
    private static $instance;

    /**
     * @inheritdoc
     */
    public function init()
    {
    }

    /**
     * @return object|Configs
     * @throws \yii\base\InvalidConfigException
     */
    public static function instance()
    {
        if (self::$instance === null) {
            $type = ArrayHelper::getValue(Yii::$app->params, 'jsonapi.configs', []);
            if (is_array($type) && !isset($type['class'])) {
                $type['class'] = static::class;
            }

            return self::$instance = Yii::createObject($type);
        }

        return self::$instance;
    }

}
