<?php

namespace pvsaintpe\jsonapi\components;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag\ParamTag;
use pvsaintpe\jsonapi\configs\Configs;
use pvsaintpe\search\components\ActiveRecord;
use pvsaintpe\helpers\Inflector;
use Yii;

/**
 * Class AbstractApi
 * @package pvsaintpe\jsonapi\components
 */
class AbstractApi
{
    /**
     * Обязательные заголовки
     * @var array
     */
    private $requiredHeaders = [];

    /**
     * Правила валидации параметров
     * @var array
     */
    private $paramRules = [];

    /**
     * Маппинг для параметров и методов их проверки
     * @var array
     */
    private $paramMap = [];

    /**
     * Правила валидации заголовков
     * @var array
     */
    private $headerRules = [];

    /**
     * Маппинг для заголовков и методов их проверки
     * @var array
     */
    private $headerMap = [];

    /**
     * Типы зависимостей заголовков
     * @var array
     */
    private $headerDepends = [];

    /**
     * Типы зависимостей параметров
     * @var array
     */
    private $paramDepends = [];

    /**
     * Пропущенные заголовки (не хватает)
     * @var array
     */
    private $missingHeaders = [];

    /**
     * Плохие заголовки (неверные значения)
     * @var array
     */
    private $invalidHeaders = [];

    /**
     * Плохие параметры (неверные значения)
     * @var array
     */
    private $invalidParams = [];

    /**
     * @var array
     */
    private $headers = [];

    /**
     * @var object
     */
    private $action;

    /**
     * @var self Instance of self
     */
    private static $instance;

    /**
     * @return AbstractApi|object
     * @throws
     */
    public static function instance()
    {
        if (self::$instance === null) {
            return self::$instance = Yii::createObject(['class' => static::class]);
        }
        return self::$instance;
    }

    /**
     * @param array $headerMap
     * @return $this
     */
    public function setHeaderMap($headerMap)
    {
        $this->headerMap = $headerMap;
        return $this;
    }

    /**
     * @param array $paramMap
     * @return $this
     */
    public function setParamMap($paramMap)
    {
        $this->paramMap = $paramMap;
        return $this;
    }

    /**
     * @param string $name
     * @param array $rule
     * @return $this
     */
    public function setHeaderRule($name, $rule)
    {
        $this->headerRules[$name] = $rule;
        return $this;
    }

    /**
     * @param array $rule
     * @return $this
     */
    public function setParamRule($rule)
    {
        $this->paramRules = array_merge($this->paramRules, $rule);
        return $this;
    }

    /**
     * @param array $requiredHeaders
     * @return $this
     */
    public function setRequiredHeaders($requiredHeaders)
    {
        $this->requiredHeaders = $requiredHeaders;
        return $this;
    }

    /**
     * @param array $depends
     * @return $this
     */
    public function setHeaderDepends($depends)
    {
        $this->headerDepends = $depends;
        return $this;
    }

    /**
     * @param array $depends
     * @return $this
     */
    public function setParamDepends($depends)
    {
        $this->paramDepends = $depends;
        return $this;
    }

    /**
     * Получение доп. заголовков вызываемого метода АПИ
     * @param $action
     * @return $this
     * @throws
     */
    final public function setHeaders($action)
    {
        $headers = [];
        $methodName = $action->actionMethod;
        $reflectionClass = new \ReflectionClass($action->controller);
        /** @var DocBlock $phpDoc */
        $phpDoc = new DocBlock($reflectionClass->getMethod($methodName)->getDocComment());
        foreach ($phpDoc->getTags() as $tag) {
            if ($tag->getName() == 'headers') {
                $paramTag = new ParamTag('param', $tag->getContent());
                $header = preg_replace('/^\$/', '', $paramTag->getVariableName());
                $headers[] = $header;
            }
            if ($tag->getName() == 'headerRule') {
                $rule = json_decode(trim($tag->getContent(), '()'), 1);
                $name = array_shift($rule);
                $this->setHeaderRule($name, $rule);
            }
            if ($tag->getName() == 'paramRule') {
                $rule = json_decode(trim($tag->getContent(), '()'), 1);
                $this->setParamRule($rule);
            }
        }
        $this->headers = array_merge($this->requiredHeaders, array_unique($headers));
        $this->action = $action;
        return $this;
    }

    /**
     * Проверка валидности всех необходимых заголовков вызываемого метода
     * @return bool
     * @throws
     */
    public function validateHeaders()
    {
        foreach ($this->headers as $header) {
            $value = Yii::$app->request->getHeaders()->get($header, 'null');
            if (!$value || $value === 'null') {
                if (!$this->validateRule($header, $value)) {
                    $this->missingHeaders[] = $header;
                }
                continue;
            }
            $methodCheck = Inflector::checkify($header);
            if (method_exists($this, $methodCheck)) {
                if (!call_user_func_array([$this, $methodCheck], [$value])) {
                    $this->invalidHeaders[] = $header;
                    continue;
                }
                $headerAlias = array_key_exists($header, $this->headerMap) ? $this->headerMap[$header] : $header;
                $component = Inflector::relatify($headerAlias);
                $methodGet = Inflector::gettify($headerAlias);
                if (method_exists($this, $methodGet)) {
                    /** @var ActiveRecord $model */
                    if (($model = call_user_func_array([$this, $methodGet], [$value]))) {
                        Yii::$app->setComponents([
                            $component => array_merge(
                                ['class' => $model::className()],
                                $model->getAttributes()
                            )
                        ]);
                        Yii::$app->get($component)->setIsNewRecord(false);
                    } else {
                        $this->invalidHeaders[] = $header;
                    }
                }
                $methodInit = Inflector::initify($headerAlias);
                if (method_exists($this, $methodInit)) {
                    call_user_func_array([$this, $methodInit], [$value]);
                }
            } else {
                $commonException = Configs::instance()->commonException;
                throw new $commonException(Configs::instance()->checkError);
            }
        }
        if ($this->getMissingHeaders() || $this->getInvalidHeaders()) {
            $commonException = Configs::instance()->commonException;
            throw new $commonException(Configs::instance()->headersError);
        }
        $this->checkHeaderDepends();
        return true;
    }

    /**
     * Проверка валидности всех необходимых параметров вызываемого метода
     * @return bool
     * @throws
     */
    public function validateParams()
    {
        foreach ($this->paramRules as $attribute => $rule) {
            $value = Yii::$app->getRequest()->get($attribute);
            if (empty($value) && isset($rule['skipEmpty']) && in_array($rule['skipEmpty'], [true, 'true'])) {
                continue;
            }
            switch ($rule['type']) {
                case 'enum':
                    if (!in_array($value, $rule['range'])) {
                        $this->invalidParams[] = $attribute;
                    }
                    break;
                case 'component':
                    $methodCheck = Inflector::checkify($attribute);
                    if (method_exists($this, $methodCheck)) {
                        if (!call_user_func_array([$this, $methodCheck], [$value])) {
                            $this->invalidParams[] = $attribute;
                            break;
                        }
                        $paramAlias = array_key_exists($attribute, $this->paramMap) ? $this->paramMap[$attribute] : $attribute;
                        $component = Inflector::relatify($paramAlias);
                        $methodGet = Inflector::gettify($paramAlias);
                        if (method_exists($this, $methodGet)) {
                            if (($model = call_user_func_array([$this, $methodGet], [$value]))) {
                                Yii::$app->setComponents([
                                    $component => array_merge(
                                        ['class' => $model::className()],
                                        $model->getAttributes()
                                    )
                                ]);
                                Yii::$app->get($component)->setIsNewRecord(false);
                            } else {
                                $this->invalidParams[] = $attribute;
                                break;
                            }
                        }
                        $methodInit = Inflector::initify($paramAlias);
                        if (method_exists($this, $methodInit)) {
                            call_user_func_array([$this, $methodInit], [$value]);
                        }
                    } else {
                        $commonException = Configs::instance()->commonException;
                        throw new $commonException(Configs::instance()->checkError);
                    }
                    break;
            }
        }
        if ($this->getInvalidParams()) {
            $commonException = Configs::instance()->commonException;
            throw new $commonException(Configs::instance()->paramsError);
        }
        $this->checkParamDepends();
        return true;
    }

    /**
     * @param string $header
     * @param mixed $value
     * @return bool
     */
    public function validateRule($header, $value)
    {
        $rule = $this->headerRules[$header] ?? [];
        $skipEmpty = $rule['skipEmpty'] ?? false;
        return ($skipEmpty == 'true' || $skipEmpty === true);
    }

    /**
     * @return string
     */
    public function getMissingHeaders()
    {
        if (is_array($this->missingHeaders) && count($this->missingHeaders) > 0) {
            return join(', ', $this->missingHeaders);
        }
        return null;
    }

    /**
     * @return string
     */
    public function getInvalidHeaders()
    {
        if (is_array($this->invalidHeaders) && count($this->invalidHeaders) > 0) {
            return join(', ', $this->invalidHeaders);
        }
        return null;
    }

    /**
     * @return string
     */
    public function getInvalidParams()
    {
        if (is_array($this->invalidParams) && count($this->invalidParams) > 0) {
            return join(', ', $this->invalidParams);
        }
        return null;
    }

    /**
     * Проверяем зависимости заголовков друг от друга
     * @throws
     */
    private function checkHeaderDepends()
    {
        foreach ($this->headerDepends as $header => $dependAttributes) {
            $headerAlias = array_key_exists($header, $this->headerMap) ? $this->headerMap[$header] : $header;
            $entity = Yii::$app->get(Inflector::relatify($headerAlias));
            if (!$entity->id) {
                continue;
            }
            foreach ($dependAttributes as $attribute => $depend) {
                $relation = Yii::$app->get($depend['relation']);
                if ($entity->{$attribute} != $relation->{$depend['attribute']}) {
                    $this->invalidHeaders[] = $header;
                    $commonException = Configs::instance()->commonException;
                    throw new $commonException(Configs::instance()->headersError);
                }
            }
        }
    }

    /**
     * Проверяем зависимости заголовков друг от друга
     * @throws
     */
    private function checkParamDepends()
    {
        foreach ($this->paramRules as $param => $rule) {
            $dependAttributes = $this->paramDepends[$param];
            $paramAlias = array_key_exists($param, $this->paramMap) ? $this->paramMap[$param] : $param;
            $entity = Yii::$app->get(Inflector::relatify($paramAlias));
            if (!$entity->id) {
                continue;
            }
            foreach ($dependAttributes as $attribute => $depend) {
                $relation = Yii::$app->get($depend['relation']);
                if ($entity->{$attribute} != $relation->{$depend['attribute']}) {
                    $this->invalidParams[] = $param;
                    $commonException = Configs::instance()->commonException;
                    throw new $commonException(Configs::instance()->paramsError);
                }
            }
            $ruleDepends = $rule['depends'] ?? [];
            foreach ($ruleDepends as $relationAttribute => $value) {
                if ($entity->{$relationAttribute} != $value) {
                    $this->invalidParams[] = $param;
                    $commonException = Configs::instance()->commonException;
                    throw new $commonException(Configs::instance()->paramsError);
                }
            }
        }
    }
}
