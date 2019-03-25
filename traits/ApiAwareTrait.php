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
     * @var string|null
     */
    protected $missingHeaders;

    /**
     * @var string|null
     */
    protected $invalidHeaders;

    /**
     * @var string|null
     */
    protected $invalidParams;

    /**
     * @var array
     */
    public $requiredHeaders = [];

    /**
     * @var array
     */
    public $headerDepends = [];

    /**
     * @var array
     */
    public $paramDepends = [];

    /**
     * @var array
     */
    public $headerMap = [];

    /**
     * @var array
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
            ->setHeaderDepends($this->headerDepends)
            ->setHeaderMap($this->headerMap)
            ->setParamDepends($this->paramDepends)
            ->setParamMap($this->paramMap)
            ->setHeaders($action)
        ;

        try {
            return $api->validateHeaders() && $api->validateParams();
        } catch (\Exception $e) {
            $this->missingHeaders = $api->getMissingHeaders();
            $this->invalidHeaders = $api->getInvalidHeaders();
            $this->invalidParams = $api->getInvalidParams();
            throw $e;
        }
    }

    /**
     * @return string|null
     */
    final protected function getInvalidHeaders()
    {
        return $this->invalidHeaders;
    }

    /**
     * @return string|null
     */
    final protected function getInvalidParams()
    {
        return $this->invalidParams;
    }

    /**
     * @return string|null
     */
    final protected function getMissingHeaders()
    {
        return $this->missingHeaders;
    }
}
