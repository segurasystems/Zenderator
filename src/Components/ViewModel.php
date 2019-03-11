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
            'isView' => true,
            'namespace' => $this->getNamespace(),
            'database' => $this->getDatabase(),
            'table' => $this->getView(),
            'app_name' => APP_NAME,
            'app_container' => APP_CORE_NAME,
            'class_name' => $this->getClassName(),
            'variable_name' => $this->transStudly2Camel->transform($this->getClassName()),
            'name' => $this->getClassName(),
            'object_name_plural' => Inflect::pluralize($this->getClassName()),
            'object_name_singular' => $this->getClassName(),
            'controller_route' => $this->transCamel2Snake->transform(Inflect::pluralize($this->getClassName())),
            'namespace_model' => "{$this->getNamespace()}\\Models\\{$this->getClassName()}Model",
            'columns' => $this->getColumns(),
            'related_objects' => $this->getRelatedObjects(),
            'related_objects_shared' => $this->getRelatedObjectsSharedAssets(),
            'remote_objects' => $this->getRemoteObjects(),
            'required_columns' => $this->getRequiredColumns(),
//
            'primary_keys' => $this->getPrimaryKeys(),
            'primary_parameters' => $this->getPrimaryParameters(),
            'autoincrement_keys' => $this->getAutoIncrements(),

            'skip_routes' => $this->getZenderator()->getRoutesToSkip(),

            "view_model_data" => $this->getViewModelData(),
            'propertyData' => $this->getPropertyData(),

            "baseModels" => $this->getBaseModels(),
        ];
    }

    public function getPropertyData()
    {
        $data = [];
        foreach ($this->getColumns() as $name => $column) {
            $data[$column->getField()] = $column->getPropertyData();
        }
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

    public function getColumns()
    {
        $columns = [];
        foreach ($this->baseModels as $modelName => $baseModel) {
            foreach ($baseModel->getColumns() as $propName => $column) {
                if (in_array($propName, $this->getSubModelIgnoreColumns($modelName))) {
                    continue;
                }
                $columns[$propName] = $column;
            }
        }
        return $columns;
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