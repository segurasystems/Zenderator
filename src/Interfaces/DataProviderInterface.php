<?php
/**
 * Created by PhpStorm.
 * User: wolfgang
 * Date: 12/03/19
 * Time: 12:36
 */

namespace Zenderator\Interfaces;


interface DataProviderInterface
{
    public function getModelData() : array;
    public function getAccessLayerData() : array;
    public function getNameSpace() : string;
    public function getAppName() : string;
    public function getBaseClassNameSpace() : string;
}