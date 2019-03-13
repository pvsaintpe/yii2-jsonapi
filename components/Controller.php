<?php

namespace api\components\controllers;

use api\dic\Error;
use api\exceptions\InvalidHeaderException;
use api\exceptions\MissingHeaderException;
use api\helpers\Api;
use api\traits\CommonTrait;
use api\exceptions\CommonException;
use JsonRpc2\Exception;
use api\components\controllers\base\JsonRpcController as BaseController;

/***
 * Class JsonRpcController
 * @package api\components\controllers
 */
class Controller extends BaseController
{
    /**
     * Обязательные заголовки
     * @var array
     */
    protected $requiredHeaders = [
        'Accept-Language',
    ];

    /**
     * Зависимости заголовков
     * @var array
     *
     * @example ```php
     *    'Auth-Token' => [
     *        'app_group_id' => [
     *           'relation' => 'playground',
     *           'attribute' => 'app_group_id',
     *           'errorCode' => Error::INVALID_PLAYGROUND_ID,
     *        ]
     *    ]
     * ```
     */
    protected $depends = [];

    /**
     * @var array
     *
     * @example ```php
     *    'Auth-Token' => 'Client'
     * ```
     */
    protected $headerMap = [];

    /**
     * @param $action
     * @return bool
     * @throws CommonException
     * @throws Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            $api = Api::instance();
            $api->setRequiredHeaders($this->requiredHeaders);
            $api->setDepends($this->depends);
            $api->setHeaderMap($this->headerMap);
            try {
                $api->validateHeaders($action);
            } catch (MissingHeaderException $e) {
                throw Error::jsonRpcException(Error::MISSING_REQUEST_HEADERS, $api->getMissingHeaders());
            } catch (InvalidHeaderException $e) {
                throw Error::jsonRpcException(Error::INVALID_REQUEST_HEADERS, $api->getInvalidHeaders());
            }
            return true;
        }
        return false;
    }
}
