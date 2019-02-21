<?php

namespace Zenderator\Components;

use Camel\CaseTransformer;
use Camel\Format;
use Zenderator\Zenderator;

class Entity
{
    /** @var CaseTransformer */
    protected $transSnake2Studly;
    /** @var CaseTransformer */
    protected $transStudly2Camel;
    /** @var CaseTransformer */
    protected $transStudly2Studly;
    /** @var CaseTransformer */
    protected $transCamel2Camel;
    /** @var CaseTransformer */
    protected $transCamel2Studly;
    /** @var CaseTransformer */
    protected $transSnake2Camel;
    /** @var CaseTransformer */
    protected $transSnake2Spinal;
    /** @var CaseTransformer */
    protected $transCamel2Snake;
    /** @var CaseTransformer */
    protected $transField2Property;
    /** @var CaseTransformer */
    protected $transStudly2Snake;
    /** @var Zenderator */
    private $zenderator;

    public function __construct()
    {
        $this->transSnake2Studly  = new CaseTransformer(new Format\SnakeCase(), new Format\StudlyCaps());
        $this->transStudly2Camel  = new CaseTransformer(new Format\StudlyCaps(), new Format\CamelCase());
        $this->transStudly2Studly = new CaseTransformer(new Format\StudlyCaps(), new Format\StudlyCaps());
        $this->transCamel2Camel   = new CaseTransformer(new Format\CamelCase(), new Format\CamelCase());
        $this->transCamel2Studly  = new CaseTransformer(new Format\CamelCase(), new Format\StudlyCaps());
        $this->transSnake2Camel   = new CaseTransformer(new Format\SnakeCase(), new Format\CamelCase());
        $this->transSnake2Spinal  = new CaseTransformer(new Format\SnakeCase(), new Format\SpinalCase());
        $this->transCamel2Snake   = new CaseTransformer(new Format\CamelCase(), new Format\SnakeCase());
        $this->transStudly2Snake   = new CaseTransformer(new Format\StudlyCaps(), new Format\SnakeCase());

        $this->transField2Property = $this->transCamel2Camel;
    }

    /**
     * @return self
     */
    public static function Factory(Zenderator $zenderator)
    {
        $class = get_called_class();
        /** @var self $instance */
        $instance = new $class;
        $instance->setZenderator($zenderator);
        return $instance;
    }

    /**
     * @return Zenderator
     */
    protected function getZenderator(): Zenderator
    {
        return $this->zenderator;
    }

    /**
     * @param Zenderator $zenderator
     *
     * @return Entity
     */
    protected function setZenderator(Zenderator $zenderator): Entity
    {
        $this->zenderator = $zenderator;
        return $this;
    }
}
