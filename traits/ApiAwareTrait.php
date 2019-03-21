<?php

namespace pvsaintpe\jsonapi\traits;

use pvsaintpe\jsonapi\components\AbstractApi;
use pvsaintpe\jsonapi\configs\Configs;

/**
 * Trait ApiAwareTrait
 * @package pvsaintpe\jsonapi\traits
 */
trait ApiAwareTrait
{
    /**
     * @var array
     */
    protected $missingHeaders = [];

    /**
     * @var array
     */
    protected $invalidHeaders = [];

    /**
     * @var array
     */
    protected $invalidParams = [];

    /**
     * Обязательные заголовки
     * @return array
     *
     * @example ```php
     *   ['Accept-Language']
     * ```
     */
    public $requiredHeaders = [];

    /**
     * Зависимости заголовков
     * @return array
     *
     * @example ```php
     *    [
     *      'Auth-Token' => [
     *        'app_group_id' => [
     *           'relation' => 'playground',
     *           'attribute' => 'app_group_id',
     *           'errorCode' => Error::INVALID_PLAYGROUND_ID,
     *        ]
     *      ]
     *    ]
     * ```
     */
    public $depends = [];

    /**
     * @var array
     *
     * @example ```php
     *    ['Auth-Token' => 'Client']
     * ```
     *
     * Затем в вашем классе АПИ (extends AbstractApi) необходимо реализовать методы:
     *  -
     */
    public $headerMap = [];

    /**
     * @var array
     *
     * @example ```php
     *    ['Auth-Token' => 'Client']
     * ```
     *
     * Затем в вашем классе АПИ (extends AbstractApi) необходимо реализовать методы:
     *  -
     */
    public $paramMap = [];

    /**
     * @param mixed $action
     * @throws
     * @return bool
     */
    final protected function validateAction($action)
    {
        /** @var AbstractApi $apiClass */
        $apiClass = Configs::instance()->apiClass;
        $api = $apiClass::instance()
            ->setRequiredHeaders($this->requiredHeaders)
            ->setDepends($this->depends)
            ->setHeaderMap($this->headerMap)
            ->setParamMap($this->paramMap)
        ;

        try {
            return $api->validateHeaders($action) && $api->validateParams($action);
        } catch (\Exception $e) {
            $this->missingHeaders = $api->getMissingHeaders();
            $this->invalidHeaders = $api->getInvalidHeaders();
            $this->invalidParams = $api->getInvalidParams();
            throw $e;
        }
    }

    /**
     * @return mixed
     */
    final protected function getInvalidHeaders()
    {
        return $this->invalidHeaders;
    }

    /**
     * @return mixed
     */
    final protected function getInvalidParams()
    {
        return $this->invalidParams;
    }

    /**
     * @return mixed
     */
    final protected function getMissingHeaders()
    {
        return $this->missingHeaders;
    }
}
