<?php
/**
 * Created by PhpStorm.
 * User: wolfgang
 * Date: 08/03/19
 * Time: 17:40
 */

namespace Zenderator\Components;

use Gone\Inflection\Inflect;
use Zend\Db\Adapter\Adapter as DbAdaptor;
use Zenderator\Zenderator;

/**
 * Class ViewModel
 *
 * @package Zenderator\Components
 */
class ViewModel extends Entity
{
    /** @var Model[] */
    private $baseModels = [];
    /** @var DbAdaptor */
    protected $dbAdaptor;
    private $config = [];
    private $view;
    private $className;
    private $namespace;
    private $database;

    /**
     * @return self
     */
    public static function Factory(Zenderator $zenderator)
    {
        return parent::Factory($zenderator);
    }

    public function addBaseModel(Model $model)
    {
        $this->baseModels[$model->getClassName()] = $model;
        return $this;
    }

    /**
     * @return Model[]
     */
    public function getBaseModels(): array
    {
        return $this->baseModels;
    }


    public function setConfig($config)
    {
        $this->config = $config;
        $this->setClassName($config["name"] ?? null);
        return $this;
    }

    public function getRenderDataset()
    {
        return [
            'class'       => $this->getClassData(),
            'config'      => $this->getZenderator()->getConfig(),
            'skip_routes' => $this->getZenderator()->getRoutesToSkip(),
        ];
    }

    public function getClassData()
    {
        return [
            "namespace"   => $this->getNamespace(),
            "name"        => $this->getClassName(),
            "variable"    => lcfirst($this->getClassName()),
            "singular"    => $this->getClassName(),
            "plural"      => Inflect::pluralize($this->getClassName()),
            "table"       => $this->getView(),
            "properties"  => $this->getPropertyData(),
            "primaryKeys" => $this->getPrimaryKeys(),
            'routePKs'    => $this->getRoutePrimaryKeys(),
            'database'    => $this->getDatabase(),
            "remoteData"  => $this->getRemoteData(),
            "relatedData" => $this->getRelatedData(),
            "isView"      => true,
            "viewData"    => $this->getViewModelData(),
            "conditions"  => $this->getConditions(),
        ];
    }

    public function getConditions(){
        $properties = $this->getPropertyData();
        $conditions = [];
        foreach ($properties as $propertyName => $property){
            $type = $property["type"] === "enum" ? "enum" : $property["phpType"];
            $rule =
                ( $property["nullable"] ? "nullable" : "required" )
                . "-" .
                ( $type ) . ( $type === "enum" ? "-" . implode(".",$property["options"]) : '' )
                . "-" .
                ( $property["length"] );
            if(empty($conditions[$rule])){
                $conditions[$rule] = [
                    "required" => !$property["nullable"],
                    "type" => $type,
                    "length" => $property["length"],
                    "fields" => [],
                ];
                if($type === "enum"){
                    $conditions[$rule]["options"] = $property["options"];
                }
            }
            $conditions[$rule]["fields"][] = $propertyName;
        }
        return array_values($conditions);
    }

    public function getRoutePrimaryKeys(){
        $keys = $this->getPrimaryKeys();
        return array_diff($keys,$this->getZenderator()->getRouteIgnoreKeys());
    }

    public function getRemoteData()
    {
        $data = [];
        foreach ($this->getPropertyData() as $propertyName => $property) {
            foreach ($property["remote"] as $remote) {
                $data[$remote["class"]["name"]]["class"] = $remote["class"];
                $data[$remote["class"]["name"]]["fields"][] = $remote["field"];
            }
        }
        return $data;
    }

    public function getRelatedData()
    {
        $data = [];
        foreach ($this->getPropertyData() as $propertyName => $property) {
            foreach ($property["related"] as $related) {
                $data[$related["class"]["name"]]["class"] = $related["class"];
                $data[$related["class"]["name"]]["fields"][] = $related["field"];
            }
        }
        return $data;
    }

    public function getRemoteObjectsKeyd()
    {
        $keyd = [];
        foreach ($this->getRemoteObjects() as $remoteObject) {
            $keyd[$remoteObject->getRemoteClass()] = $remoteObject;
        }
        return $keyd;
    }

    public function getRelatedObjectsKeyd()
    {
        $keyd = [];
        foreach ($this->getRelatedObjects() as $relatedObject) {
            $keyd[$relatedObject->getRemoteClass()] = $relatedObject;
        }
        return $keyd;
    }

    public function getPropertyData()
    {
        $data = [];
        foreach ($this->getColumns() as $name => $column) {
            $data[$column->getField()] = $this->getSafeColumnData($column);
        }
        return $data;
    }

    public function getSafeColumnData(Column $column)
    {
        $data = $column->getPropertyData();
        $_remote = [];
        foreach ($data["remote"] as $remote) {
            if ($remote["class"]["name"] == $this->getClassName()) {
                if ($this->ignoreField($remote["field"]["remote"]["name"], $remote["class"]["trueClass"])) {
                    continue;
                }
            }
            $_remote[] = $remote;
        }
        $data["remote"] = $_remote;
        return $data;
    }

    public function getRelatedObjects()
    {
        return [];
    }

    public function getRelatedObjectsSharedAssets()
    {
        return [];
    }

    public function getRemoteObjects()
    {
        return [];
    }

    public function getViewModelData()
    {
        $data = $this->config["sub_models"];
        foreach ($data as $table => $datum) {
            $data[$table]["name"] = $data[$table]["name"] ?? $table;
            $data[$table]["columns"] = [];
            $data[$table]["dependent"] = $data[$table]["dependent"] ?? [];
            if (!empty($this->getBaseModels()[$table])) {
                foreach ($this->getBaseModels()[$table]->getColumns() as $column) {
                    $data[$table]["columns"][] = $column->getField();
                }
            }
        }
        uasort($data, function ($a, $b) {
            $aName = $a["name"];
            $bName = $b["name"];
            $a = array_values($a["dependant"] ?? []);
            $b = array_values($b["dependant"] ?? []);
            if (in_array($aName, $b)) {
                return -1;
            }
            if (in_array($bName, $a)) {
                return 1;
            }
            return 0;
        });
        return $data;
    }

    public function getRelatedObjectsClassNames()
    {
        return [];
    }

    /**
     * @return Column[]
     */
    public function getColumns()
    {
        $columns = [];
        foreach ($this->baseModels as $modelName => $baseModel) {
            foreach ($baseModel->getColumns() as $propName => $column) {
                if (!$this->ignoreField($propName, $modelName)) {
                    $columns[$propName] = $column;
                }
            }
        }
        return $columns;
    }

    public function ignoreField($fieldName, $modelName)
    {
        return in_array($fieldName, $this->getSubModelIgnoreColumns($modelName));
    }

    public function scanForRemoteRelations($models)
    {
        // TODO  : scan for relation models
    }

    public function getColumn($name): Column
    {
        foreach ($this->getBaseModels() as $baseModel) {
            if (isset($baseModel->getColumns()[$name])) {
                return $baseModel->getColumns()[$name];
            }
        }
        die("Cannot find a Column called {$name} in baseModels");
    }

    public function hasField($field)
    {
        $field = strtolower($field);
        foreach ($this->getColumns() as $column) {
            if (strtolower($column->getField()) === $field) {
                return true;
            }
        }
        return false;
    }

    public function getRequiredColumns()
    {
        $columns = [];
        foreach ($this->baseModels as $base) {
            foreach ($base->getColumns() as $column) {
                if (!$column->isNullable()) {
                    $columns[] = $column->getField();
                }
            }
        }
        return $columns;
    }

    public function getPrimaryKeys()
    {
        $subModelConfig = $this->config["sub_models"];
        $pks = [];
        foreach ($subModelConfig as $table => $data) {
            if (!empty($data["pk"])) {
                if (!is_array($data["pk"])) {
                    $data["pk"] = [$data["pk"]];
                }
                $pks = array_merge($pks, $data["pk"]);
            }
        }
        return $pks;
    }

    public function getPrimaryParameters()
    {
        $keys = [];
        foreach ($this->baseModels as $base) {
            foreach ($base->getPrimaryParameters() as $key) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    public function getAutoIncrements()
    {
        $keys = [];
        foreach ($this->baseModels as $base) {
            foreach ($base->getAutoIncrements() as $key) {
                $keys[] = $key;
            }
        }
        return $keys;
    }


    private function getSubModelIgnoreColumns($name)
    {
        return $this->config["sub_models"][$name]["ignore"] ?? [];
    }

    /**
     * @return mixed
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param mixed $className
     *
     * @return ViewModel
     */
    private function setClassName($className = null)
    {
        if (empty($className)) {
            throw new \Exception("View as model is missing name");
        }
        $this->className = $className;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @param mixed $view
     *
     * @return ViewModel
     */
    public function setView($view)
    {
        $this->view = $view;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param mixed $namespace
     *
     * @return ViewModel
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param mixed $database
     *
     * @return ViewModel
     */
    public function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }

    /**
     * @return DbAdaptor
     */
    public function getAdaptor(): DbAdaptor
    {
        return $this->dbAdaptor;
    }

    /**
     * @param DbAdaptor $dbAdaptor
     *
     * @return ViewModel
     */
    public function setAdaptor(DbAdaptor $dbAdaptor): ViewModel
    {
        $this->dbAdaptor = $dbAdaptor;
        return $this;
    }

}