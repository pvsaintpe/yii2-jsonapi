<?php

namespace pvsaintpe\jsonapi\components;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag\ParamTag;
use pvsaintpe\jsonapi\configs\Configs;
use Yii;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use pvsaintpe\helpers\Inflector;

/**
 * Class AbstractSandbox
 * @package pvsaintpe\jsonapi\components
 */
abstract class AbstractSandbox
{
    public static $module;
    public static $controllers = [];
    public static $modules = [];

    /**
     * @return string
     */
    protected static function getAcceptLanguage()
    {
        return Yii::$app->language;
    }

    /**
     * @return array
     */
    abstract public static function getCustomParams();

    /**
     * @param $module
     * @return mixed
     * @throws \ReflectionException
     */
    public static function init($module)
    {
        static::$module = $module;

        $iterator = new RecursiveDirectoryIterator(Yii::getAlias(Configs::instance()->sourcePath . $module));
        $iterator = new RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $filename => $file) {
            if ($file->isFile() && preg_match('~Controller\.php$~', $file->getFilename())) {
                $className = static::parseClassFile($filename);
                if ($className && class_exists($className)) {
                    $reflectionClass = new ReflectionClass($className);
                    if ($reflectionClass->isSubclassOf(Configs::instance()->controllerClass)) {
                        static::$controllers[] = $className;
                    }
                }
            }
        }

        static::$modules = [];
        sort(static::$controllers);
        foreach (static::$controllers as $controller) {
            $reflectionClass = new ReflectionClass($controller);

            $module = [
                'name' => Inflector::camel2id(preg_replace('~Controller$~', '', $reflectionClass->getShortName())),
                'methods' => [],
            ];

            $headers = [];
            $requiredHeaders = $reflectionClass->getDefaultProperties()['requiredHeaders'] ?? [];
            foreach ($requiredHeaders as $requiredHeader) {
                $getter = Inflector::gettify($requiredHeader);
                $headers[$requiredHeader] = static::$getter();
            }

            foreach ($reflectionClass->getMethods() as $method) {
                if (!$method->isPublic()
                    || !preg_match('~^action~', $method->getName()) || $method->getName() == 'actions'
                ) {
                    continue;
                }

                $methodName = Inflector::camel2id(preg_replace('/^action/', '', $method->getName()));
                $action = [
                    'name' => $methodName,
                    'params' => [
                        "jsonrpc" => "2.0",
                        "id" => 1,
                        "method" => $methodName
                    ],
                    'headers' => $headers,
                    'todo' => 0,
                    'beta' => 1,
                    'deprecated' => 0,
                ];

                $argParams = [];
                foreach ($method->getParameters() as $arg) {
                    if ($arg->isOptional()) {
                        $val = $arg->getDefaultValue();
                        $argParams[$arg->name] = ($val === null) ? 'null' : $val;
                    }
                }

                $phpDoc = new DocBlock($method->getDocComment());
                $skip = false;
                foreach ($phpDoc->getTags() as $tag) {
                    if ($tag instanceof ParamTag) {
                        /**
                         * @var $tag ParamTag
                         */
                        $defaultValue = trim($tag->getDescription());
                        if (preg_match('~\(.+\)~', $defaultValue, $matches)) {
                            $defaultValue = trim($matches[0], '()');
                        }
                        if (empty($defaultValue)) {
                            $defaultValue = $tag->getType();
                        }

                        if (in_array($tag->getType(), ['int', 'integer'])) {
                            $defaultValue = (int)$defaultValue;
                        }
                        if (in_array($tag->getType(), ['bool', 'boolean'])) {
                            if ($defaultValue) {
                                if ($defaultValue == 'true') {
                                    $defaultValue = 'true';
                                } else {
                                    $defaultValue = 'false';
                                }
                            } else {
                                $defaultValue = 'false';
                            }
                        }

                        $param = preg_replace('/^\$/', '', $tag->getVariableName());
                        $value = array_key_exists($param, $argParams) ? $argParams[$param] : $defaultValue;
                        $action['params']['params'][$param] = preg_replace_callback(
                            '/{{(.*)}}/',
                            function ($matches) {
                                return isset(Yii::$app->params[$matches[1]])
                                    ? Yii::$app->params[$matches[1]]
                                    : "??{$matches[1]}??";
                            },
                            $value
                        );
                    }

                    if ($tag->getName() == 'headers') {
                        $paramTag = new ParamTag('param', $tag->getContent());
                        $header = preg_replace('/^\$/', '', $paramTag->getVariableName());
                        $action['headers'][$header] = $paramTag->getDescription();
                    }

                    if ($tag->getName() == 'todo') {
                        $action['todo'] = 1;
                    }

                    if ($tag->getName() == 'ready') {
                        $action['beta'] = 0;
                    }

                    if ($tag->getName() == 'deprecated') {
                        $action['deprecated'] = 1;
                    }

                    if ($tag->getName() == 'skip') {
                        $skip = true;
                    }
                }

                if (!$skip) {
                    $module['methods'][] = $action;
                }
            }

            if (count($module['methods']) > 0) {
                static::$modules[] = $module;
            }
        }

        return static::toJson(array_merge(['modules' => static::$modules], static::getCustomParams()));
    }

    /**
     * @param string $filename
     * @return string|false
     */
    public static function parseClassFile($filename)
    {
        $data = file_get_contents($filename);
        if ($data && preg_match('~class\s+(\w+)\s+extends\s+\w+\s*\{~i', $data, $classMatch)) {
            $namespace = '';
            if (preg_match('~namespace\s+([^\s;]+)\s*;~i', $data, $namespaceMatch)) {
                $namespace = $namespaceMatch[1] . '\\';
            }
            return $namespace . $classMatch[1];
        }
        return false;
    }

    /**
     * @param $content
     * @return mixed
     */
    public static function toJson($content)
    {
        return str_replace(
            ['"null"', '"true"', '"false"', '"[]"', '"{}"'],
            ['null', 'true', 'false', '[]', '{}'],
            json_encode($content)
        );
    }
}
