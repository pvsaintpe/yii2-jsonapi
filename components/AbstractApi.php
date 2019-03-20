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
    private $depends = [];

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
    public function setDepends($depends)
    {
        $this->depends = $depends;
        return $this;
    }

    /**
     * Получение доп. заголовков вызываемого метода АПИ
     * @param $action
     * @return array
     * @throws
     */
    private function getHeaders($action)
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
        }
        return array_merge($this->requiredHeaders, array_unique($headers));
    }

    /**
     * Проверка валидности всех необходимых заголовков вызываемого метода
     * @param $action
     * @return bool
     * @throws
     */
    public function validateHeaders($action)
    {
        foreach ($this->getHeaders($action) as $header) {
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
        $this->checkDepends();
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
     * Проверяем зависимости заголовков друг от друга
     * @throws
     */
    private function checkDepends()
    {
        foreach ($this->depends as $header => $dependAttributes) {
            $headerAlias = array_key_exists($header, $this->headerMap) ? $this->headerMap[$header] : $header;
            $entity = Yii::$app->get(Inflector::relatify($headerAlias));
            if (!$entity->getId()) {
                continue;
            }
            foreach ($dependAttributes as $attribute => $depend) {
                $relation = Yii::$app->get($depend['relation']);
                if ($entity->{$attribute} != $relation->{$depend['attribute']}) {
                    $commonException = Configs::instance()->commonException;
                    throw new $commonException($depend['errorCode'] ?? Configs::instance()->compareError);
                }
            }
        }
    }
}