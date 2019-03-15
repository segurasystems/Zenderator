<?php

namespace Zenderator\DataProviders;

use GuzzleHttp\Client as GuzzleClient;
use Zenderator\Components\Column;
use Zenderator\Interfaces\DataProviderInterface;

class HttpProvider implements DataProviderInterface
{
    private $guzzleClient;
    private $rawData;
    private $namespace;
    private $appName;
    private $modelData;
    private $accessLayerData;

    public function __construct(string $baseURI, string $namespace, string $appName)
    {
        $this->namespace = $namespace;
        $this->appName = $appName;
        $this->guzzleClient = new GuzzleClient([
            'base_uri' => $baseURI,
            'timeout'  => 30.0,
            'headers'  => [
                'Accept' => 'application/json'
            ]
        ]);
    }

    public function getBaseClassNameSpace($jsonSafe = false): string
    {
        return $this->getNamespace() .
            ($jsonSafe ? "\\" : "") .
            "\\SDK\\" .
            ($jsonSafe ? "\\" : "") .
            $this->getAppName();
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getAppName(): string
    {
        return $this->appName;
    }


    public function getModelData(): array
    {
        if (empty($this->modelData)) {
            $this->generateModelData();
        }
        return $this->modelData;
    }

    public function getAccessLayerData(): array
    {
        if (empty($this->accessLayerData)) {
            $this->generateAccessLayerData();
        }
        return $this->accessLayerData;
    }

    private function generateAccessLayerData()
    {
        print "HTTP : GENERATING ACCESS LAYER DATA\n";
        $rawData = $this->getRawAccesLayerData();
        print "HTTP : GOT " . count($rawData) . " ROUTES\n";
        $accessLayerData = [];
        foreach ($rawData as $raw) {
            if (empty($raw["class"])) {
                continue;
            }
            $class = $raw["class"];
            if (empty($accessLayerData[$class])) {
                $accessLayerData[$class] = [
                    "namespace" => $this->getBaseClassNameSpace(),
                    "name"      => $class,
                    "variable"  => lcfirst($class),
                    "methods"   => [],
                ];
            }
            $accessLayerData[$class]["methods"][] = $this->processRawMethod($raw);
        }
        print "HTTP : GOT " . count($accessLayerData) . " ACCESS LAYER CLASSES\n";
        $this->accessLayerData = $accessLayerData;
    }

    private function processRawMethod($raw)
    {
        $_arguments = $raw["arguments"] ?? [];
        $groupedArguments = [];
        $arguments = [];
        $defaults = [];
        foreach ($_arguments as $name => $argument) {
            if ($argument["type"] == "Gone\\SDK\\Common\\Filters\\Filter") {
                $argument["type"] = "Filter";
            }
            $phpType = $argument["type"];
            $phpType = preg_match("/\[\]$/", $phpType) ? "array" : $phpType;
            if ($phpType === "password") {
                $phpType = "string";
            }
            $argument["phpType"] = $phpType;
            $groupedArguments[$argument["in"]][$name] = $argument;
            $arguments[$name] = $argument;
        }
        $raw["groupedArguments"] = $groupedArguments;
        $raw["arguments"] = $arguments;
        return $raw;
    }

    private function generateModelData()
    {
        $rawModelData = $this->getRawModelData();
        $modelData = [];
        foreach ($rawModelData as $name => $raw) {
            $raw["namespace"] = $this->getBaseClassNameSpace();
            $raw["name"] = $name;
            if(empty($raw["variable"])){
                $raw["variable"] = lcfirst($name);
            }
            $properties = [];
            foreach ($raw["properties"] as $propName => $property) {
                $property["name"] = ucfirst($propName);
                $property["phpType"] = Column::convertColumnType($property["type"]);
                $property["remote"] = $this->setupRemoteProperties($property["remote"] ?? []);
                $property["related"] = $this->setupRelatedProperties($property["related"] ?? []);
                $properties[$propName] = $property;
            }
            $raw["properties"] = $properties;
            $raw["conditions"] = $this->createConditionSet($properties);
            $modelData[$name] = $raw;
        }
        $this->modelData = $modelData;
    }

    public function createConditionSet($properties){
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

    private function fetchRawData()
    {
        print "HTTP : FETCHING RAW DATA\n";
        $result = $this->guzzleClient->get("/v1")->getBody()->getContents();
        $result = json_decode($result, true);
        if (!is_array($result)) {
            throw new \Exception("Response from api was not expected json");
        }

        if (empty($result["Routes"])) {
            throw new \Exception("Response from api did not contain any routes");
        }

        if (empty($result["Models"])) {
            throw new \Exception("Response from api did not contain any Models");
        }

        $this->rawData = [
            "Routes" => $result["Routes"],
            "Models" => $result["Models"],
        ];
    }

    private function getRawData()
    {
        if (empty($this->rawData)) {
            $this->fetchRawData();
        }
        return $this->rawData;
    }

    private function getRawModelData()
    {
        return $this->getRawData()["Models"];
    }

    private function getRawAccesLayerData()
    {
        return $this->getRawData()["Routes"];
    }

    /**
     * @param array $remotes
     *
     * @return array
     */
    private function setupRemoteProperties(array $remotes): array
    {
        $remote = [];
        foreach ($remotes as $rem) {
            $remote[] = [
                "class" => [
                    "name"     => $rem["name"],
                    "variable" => $rem["variable"],
                ],
                "field" => [
                    "remote" => [
                        "name" => $rem["column"],
                    ],
                ],
            ];
        }
        return $remote;
    }

    /**
     * @param array $remotes
     *
     * @return array
     */
    private function setupRelatedProperties(array $remotes): array
    {
        $remote = [];
        foreach ($remotes as $rem) {
            $remote[] = [
                "class" => [
                    "name"     => $rem["name"],
                    "variable" => $rem["variable"],
                ],
                "field" => [
                    "related" => [
                        "name" => $rem["column"],
                    ],
                ],
            ];
        }
        return $remote;
    }
}