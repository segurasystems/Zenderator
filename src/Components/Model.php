<?php

namespace Zenderator\Components;

use Gone\Inflection\Inflect;
use Zend\Db\Adapter\Adapter as DbAdaptor;
use Zenderator\Zenderator;

class Model extends Entity
{

    /** @var DbAdaptor */
    protected $dbAdaptor;

    protected $namespace;
    /** @var string */
    protected $database;
    /** @var string */
    protected $table;
    /** @var Column[] */
    protected $columns = [];
    protected $constraints = [];
    protected $relatedObjects = [];
    protected $primaryKeys = [];
    protected $autoIncrements;

    /**
     * @return self
     */
    public static function Factory(Zenderator $zenderator)
    {
        return parent::Factory($zenderator);
    }

    /**
     * @return DbAdaptor
     */
    public function getDbAdaptor(): DbAdaptor
    {
        return $this->dbAdaptor;
    }

    /**
     * @param DbAdaptor $dbAdaptor
     *
     * @return Model
     */
    public function setDbAdaptor(DbAdaptor $dbAdaptor): Model
    {
        $this->dbAdaptor = $dbAdaptor;
        return $this;
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getColumn($name): Column
    {
        if (isset($this->columns[$name])) {
            return $this->columns[$name];
        }
        die("Cannot find a Column called {$name} in " . implode(", ", array_keys($this->getColumns())));
    }

    /**
     * @param Column[] $columns
     *
     * @return Model
     */
    public function setColumns(array $columns): Model
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * @return RelatedModel[]
     */
    public function getRelatedObjects(): array
    {
        return $this->relatedObjects;
    }

    /**
     * @return string[]
     */
    public function getRelatedObjectsClassNames()
    {
        $names = [];
        foreach ($this->getRelatedObjects() as $relatedObject) {
            $names[] = $relatedObject->getRemoteClass();
        }
        return $names;
    }

    /**
     * @param array $relatedObjects
     *
     * @return Model
     */
    public function setRelatedObjects(array $relatedObjects): Model
    {
        $this->relatedObjects = $relatedObjects;
        return $this;
    }

    public function getRelatedObjectsSharedAssets()
    {
        $sharedAssets = [];
        foreach ($this->getRelatedObjects() as $relatedObject) {
            $sharedAssets[$relatedObject->getRemoteClass()] = $relatedObject;
        }
        #if(count($this->getRelatedObjects())) {
        #    \Kint::dump($this->getRelatedObjects(), $sharedAssets);
        #    exit;
        #}
        return $sharedAssets;
    }

    /**
     * @return array
     */
    public function getPrimaryKeys(): array
    {
        return $this->primaryKeys;
    }

    public function getPrimaryParameters(): array
    {
        $parameters = [];
        foreach ($this->getPrimaryKeys() as $primaryKey) {
            foreach ($this->getColumns() as $column) {
                if ($primaryKey == $column->getField()) {
                    $parameters[] = $column->getPropertyFunction();
                }
            }
        }
        return $parameters;
    }

    /**
     * @param array $primaryKeys
     *
     * @return Model
     */
    public function setPrimaryKeys(array $primaryKeys): Model
    {
        $this->primaryKeys = $primaryKeys;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAutoIncrements()
    {
        return $this->autoIncrements;
    }

    /**
     * @param mixed $autoIncrements
     *
     * @return Model
     */
    public function setAutoIncrements($autoIncrements)
    {
        $this->autoIncrements = $autoIncrements;
        return $this;
    }

    public function setAdaptor(DbAdaptor $dbAdaptor)
    {
        $this->dbAdaptor = $dbAdaptor;
        return $this;
    }

    /**
     * @param \Zend\Db\Metadata\Object\ConstraintObject[] $zendConstraints
     *
     * @return Model
     */
    public function computeConstraints(array $zendConstraints)
    {
        #echo "Computing the constraints of {$this->getClassName()}\n";
        foreach ($zendConstraints as $zendConstraint) {
            if ($zendConstraint->getType() == "FOREIGN KEY") {
                $newRelatedObject = RelatedModel::Factory($this->getZenderator())
                    ->setSchema($zendConstraint->getReferencedTableSchema())
                    ->setLocalTable($zendConstraint->getTableName())
                    ->setRemoteTable($zendConstraint->getReferencedTableName())
                    ->setBindings(
                        $this->getDatabase(),
                        $zendConstraint->getColumns()[0],
                        Zenderator::schemaName2databaseName($zendConstraint->getReferencedTableSchema()),
                        $zendConstraint->getReferencedColumns()[0]
                    );
                $this->relatedObjects[] = $newRelatedObject;
            }
            if ($zendConstraint->getType() == "PRIMARY KEY") {
                $this->primaryKeys = $zendConstraint->getColumns();
                foreach ($this->columns as $column) {
                    $columnCount = count($zendConstraint->getColumns());
                    foreach ($zendConstraint->getColumns() as $affectedColumn) {
                        if ($column->getPropertyName() == $affectedColumn) {
                            if ($columnCount === 1) {
                                $column->setIsUnique(true);
                            }
                            $column->setIsNullable(false);
                        }
                    }
                }
            }
            if ($zendConstraint->getType() == "UNIQUE") {
                //if ($this->getClassName() == 'PermissionGroup') {
                foreach ($this->columns as $column) {
                    foreach ($zendConstraint->getColumns() as $affectedColumn) {
                        if ($column->getPropertyName() == $affectedColumn) {
                            $column->setIsUnique(true);
                        }
                    }
                }
                //}
            }
        }

        // Sort related objects into their column objects also
        if (count($this->relatedObjects) > 0) {
            foreach ($this->relatedObjects as $relatedObject) {
                /** @var $relatedObject RelatedModel */
                $localBoundVariable = $relatedObject->getLocalBoundColumn();
                #echo "In {$this->getClassName()} column {$localBoundVariable} has a related object called {$relatedObject->getLocalClass()}::{$relatedObject->getRemoteClass()}\n";
                $this->columns[$localBoundVariable]
                    ->addRelatedObject($relatedObject);
            }
        }

        // Calculate autoincrement fields
        $autoIncrements = Zenderator::getAutoincrementColumns($this->getAdaptor(), $this->getTable());
        $this->setAutoIncrements($autoIncrements);

        // Return a decked-out model
        return $this;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        if (Zenderator::isUsingClassPrefixes()) {
            return
                $this->transSnake2Studly->transform($this->getDatabase()) .
                $this->transStudly2Studly->transform($this->getTableSanitised());
        }
        return
            $this->transStudly2Studly->transform($this->getTableSanitised());
    }

    /**
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param string $database
     *
     * @return Model
     */
    public function setDatabase(string $database)
    {
        $this->database = $database;
        return $this;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param string $table
     *
     * @return Model
     */
    public function setTable(string $table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Get the table name, sanitised by removing any prefixes as per zenderator.yml.
     *
     * @return string
     */
    public function getTableSanitised()
    {
        return $this->getZenderator()->sanitiseTableName($this->getTable());
    }

    /**
     * @param Model[] $models
     */
    public function scanForRemoteRelations(array $models, $ignored)
    {
        #echo "Scan: {$this->getClassName()}\n";
        foreach ($this->getColumns() as $column) {
            #echo " > {$column->getField()}:\n";
            if (count($column->getRelatedObjects()) > 0) {
                foreach ($column->getRelatedObjects() as $relatedObject) {
                    #echo "Processing Related Objects for {$this->getClassName()}'s {$column->getField()}\n\n";
                    #echo "  > r: {$relatedObject->getRemoteClass()} :: {$relatedObject->getRemoteBoundColumn()}\n";
                    #echo "  > l: {$relatedObject->getLocalClass()} :: {$relatedObject->getLocalBoundColumn()}\n";
                    #echo "\n";
                    /** @var Model $remoteModel */
                    $models[$relatedObject->getRemoteClass()]
                        ->getColumn($relatedObject->getRemoteBoundColumn())
                        ->addRemoteObject($relatedObject);
                }
            }
        }
    }

    /**
     * @return RelatedModel[]
     */
    public function getRemoteObjects(): array
    {
        $remoteObjects = [];
        foreach ($this->getColumns() as $column) {
            if (count($column->getRemoteObjects()) > 0) {
                foreach ($column->getRemoteObjects() as $remoteObject) {
                    $remoteObjects[] = $remoteObject;
                }
            }
        }
        return $remoteObjects;
    }

    /**
     * @return array
     */
    public function getConstraints()
    {
        return $this->constraints;
    }

    /**
     * @param array $constraints
     *
     * @return Model
     */
    public function setConstraints(array $constraints)
    {
        $this->constraints = $constraints;
        return $this;
    }

    /**
     * @return array
     *
     * @to do verify this actually works.
     */
//    public function computeAutoIncrementColumns()
//    {
//        $sql     = "SHOW columns FROM `{$this->getTable()}` WHERE extra LIKE '%auto_increment%'";
//        $query   = $this->getAdaptor()->query($sql);
//        $columns = [];
//
//        foreach ($query->execute() as $aiColumn) {
//            $columns[] = $aiColumn['Field'];
//        }
//        return $columns;
//    }

    /**
     * @return DbAdaptor
     */
    public function getAdaptor()
    {
        return $this->dbAdaptor;
    }

    /**
     * @param \Zend\Db\Metadata\Object\ColumnObject[] $columns
     *
     * @return $this
     */
    public function computeColumns(array $columns)
    {
        $autoIncrementColumns = Zenderator::getAutoincrementColumns($this->dbAdaptor, $this->getTable());

        foreach ($columns as $column) {
            $typeFragments = explode(" ", $column->getDataType());
            $type = reset($typeFragments);
            $structure = null;
            $field = $column->getName();
            $customStruct = $this->getZenderator()->getModelFieldCustomStructure($this->getClassName(),$field);
            if(!empty($customStruct)){
                $type = $customStruct["type"] ?? $type;
                $structure = $customStruct["properties"] ?? null;
            }
            $oColumn = Column::Factory($this->getZenderator())
                ->setClassName($this->getClassName())
                ->setModel($this)
                ->setField($field)
                ->setDbType($type,$structure)
                ->setPermittedValues($column->getErrata('permitted_values'))
                ->setMaxDecimalPlaces($column->getNumericScale())
                ->setIsUnsigned($column->getNumericUnsigned())
                ->setIsNullable($column->isNullable())
                ->setDefaultValue($column->getColumnDefault());

            /**
             * If this column is in the AutoIncrement list, mark it as such.
             */
            if (in_array($oColumn->getField(), $autoIncrementColumns)) {
                $oColumn->setIsAutoIncrement(true);
            }

            /**
             * Calculate Max Length for field.
             */
            if (in_array($column->getDataType(), ['int', 'bigint', 'tinyint'])) {
                $oColumn->setMaxLength($column->getNumericPrecision());
            } else {
                $oColumn->setMaxLength($column->getCharacterMaximumLength());
            }

            switch ($column->getDataType()) {
                case 'bigint':
                    $oColumn->setMaxFieldLength(9223372036854775807);
                    break;
                case 'int':
                    $oColumn->setMaxFieldLength(2147483647);
                    break;
                case 'mediumint':
                    $oColumn->setMaxFieldLength(8388607);
                    break;
                case 'smallint':
                    $oColumn->setMaxFieldLength(32767);
                    break;
                case 'tinyint':
                    $oColumn->setMaxFieldLength(127);
                    break;
            }

            $this->columns[$oColumn->getPropertyName()] = $oColumn;
        }
        return $this;
    }

    public function getRenderDataset()
    {
        return [
            'class' => $this->getClassData(),
            'config' => $this->getZenderator()->getConfig(),
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
            "table"       => $this->getTable(),
            "properties"  => $this->getPropertyData(),
            "primaryKeys" => $this->getPrimaryKeys(),
            'routePKs'    => $this->getRoutePrimaryKeys(),
            "database"    => $this->getDatabase(),
            "remoteData"  => $this->getRemoteData(),
            "relatedData" => $this->getRelatedData(),
            "foreignClasses" => $this->getForeignClassList(),
            "conditions"  => $this->createConditionSet($this->getPropertyData(),$this->getPrimaryKeys()),
        ];
    }

    public function getForeignClassList(){
        $list = [];
        $relateds = $this->getRelatedData();
        $remotes = $this->getRemoteData();

        foreach ($relateds as $related){
            $list[lcfirst($related["class"]["name"])] = $related["class"]["name"];
        }

        foreach ($remotes as $remote){
            $list[lcfirst($remote["class"]["name"])] = $remote["class"]["name"];
        }

        ksort($list);

        return $list;
    }

    public function createConditionSet($properties,$primaryKeys){
        $conditions = [];
        foreach ($properties as $propertyName => $property){
            $type = $property["type"] === "enum" ? "enum" : $property["phpType"];
            $isPrimary = in_array($propertyName,$primaryKeys);
            $required = !$property["nullable"] && !$isPrimary;
            $rule =
                ( $required ? "required" : "nullable" )
                . "-" .
                ( $type ) . ( $type === "enum" ? "-" . implode(".",$property["options"]) : '' )
                . "-" .
                ( $property["length"] );
            if(empty($conditions[$rule])){
                $conditions[$rule] = [
                    "required" => $required,
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
        foreach ($conditions as $key => $condition){
            $conditions[$key]["key"] = trim(implode("-",$condition["fields"]) . "-" . $key,"- ");
        }
        foreach ($properties as $propertyName => $property) {
            if (!empty($property["related"])) {
                foreach ($property["related"] as $related) {
                    $localField = $related["field"]["local"]["name"];
                    $foreignField = $related["field"]["related"]["name"];
                    $foreignClass = $related["class"]["name"];
                    $conditions["{$propertyName}-related-{$foreignClass}-{$foreignField}"] = [
                        "type"    => "foreignKey",
                        "fields"  => [$propertyName],
                        "local"   => $localField,
                        "foreign" => $foreignField,
                        "class"   => $foreignClass,
                        "key"     => "{$propertyName}-related-{$foreignClass}-{$foreignField}",
                    ];
                }
            }
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
            $data[$column->getField()] = $column->getPropertyData();
        }
        return $data;
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

    /**
     * @return Column[]
     */
    public function getRequiredColumns()
    {
        $columns = [];
        foreach ($this->getColumns() as $column) {
            if (!$column->isNullable()) {
                $columns[] = $column->getField();
            }
        }
        return $columns;
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
     * @return Model
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }
}
