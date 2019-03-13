<?php

namespace pvsaintpe\jsonapi\configs;

use pvsaintpe\jsonapi\components\AbstractApi;
use pvsaintpe\jsonapi\components\AbstractController;
use pvsaintpe\jsonapi\exceptions\CommonException;
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
 *          'sourcePath' => '@app/modules/'
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
    public $sourcePath;

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
    public $headersError = 'Неверные заголовки';

    /**
     * @var string
     */
    public $requestError = 'Неверный запрос';

    /**
     * @var string
     */
    public $methodError = 'Метод не найден';

    /**
     * @var string
     */
    public $pageError = 'Страница не найдена';

    /**
     * @var string
     */
    public $apiClass = AbstractApi::class;

    /**
     * @var string
     */
    public $activeRecord = ActiveRecord::class;

    /**
     * @var string Common Exception Class name.
     */
    public $commonException = CommonException::class;

    /**
     * @var string
     */
    public $controllerClass = AbstractController::class;

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
