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
    private $config = [];

    public function __construct(string $baseURI, string $namespace, string $appName, $config = [])
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
        $this->config = $config;
    }

    public function getBaseClassNameSpace($jsonSafe = false): string
    {
        return $this->getNamespace() .
            ($jsonSafe ? "\\" : "") .
            "\\SDK\\" .
            ($jsonSafe ? "\\" : "") .
            $this->getAppName();
    }

    public function getBaseClassNameSpaceNONSDK($jsonSafe = false): string
    {
        return $this->getNamespace() .
            ($jsonSafe ? "\\" : "") .
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
            $argument["subType"] = $argument["type"];
            if ($argument["type"] == "Gone\\SDK\\Common\\Filters\\Filter") {
                $argument["subType"] = "Filter";
            }
            if ($argument["type"] == "Gone\\SDK\\Common\\QueryBuilder\\Query") {
                $argument["subType"] = "Query";
            }
            if ($argument["type"] == "Gone\\AppCore\\QueryBuilder\\Query") {
                $argument["subType"] = "Query";
            }
            if (!empty($argument["subType"])) {
                if ($argument["subType"] == "Gone\\SDK\\Common\\Abstracts\\AbstractModel") {
                    $argument["subType"] = "AbstractModel";
                }
            }
            if(empty($argument["format"])){
                $argument["format"] = null;
            }
            $phpType = $argument["subType"];
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
            $raw["namespaceNONSDK"] = $this->getBaseClassNameSpaceNONSDK();
            $raw["name"] = $name;
            if (empty($raw["variable"])) {
                $raw["variable"] = lcfirst($name);
            }
            $properties = [];
            foreach ($raw["properties"] as $propName => $property) {
                if (in_array($propName, $this->getSkippedArgsConfig())) {
                    continue;
                }
                $property["name"] = ucfirst($propName);
                if (!empty($property["structure"])) {
                    $property["structure"] = json_decode($property["structure"], true);
                }
                $type = $property["type"];
                $phpType = Column::convertColumnType($type);
                if ($phpType === null) {
                    $phpType = $property["type"];
                    $property["type"] = null;
                    if ($phpType !== "array") {
                        $phpType = "\\" . $this->getBaseClassNameSpace() . "\\Models\\" . $phpType;
                    }
                }
                $property["phpType"] = $phpType;
                $property["remote"] = $this->setupRemoteProperties($property["remote"] ?? []);
                $property["related"] = $this->setupRelatedProperties($property["related"] ?? []);
                $properties[$propName] = $property;
            }
            $raw["properties"] = $properties;
            $raw["conditions"] = $this->createConditionSet($properties, $raw);
            $modelData[$name] = $raw;
        }
        $this->modelData = $modelData;
    }

    public function createConditionSet($properties, $raw)
    {
        $conditions = [];
        foreach ($properties as $propertyName => $property) {
            $type = $property["type"] === "enum" ? "enum" : $property["phpType"];
            $required = $this->fieldRequired($propertyName, !$property["nullable"], $raw);
            $length = $property["length"] ?? null;
            $rule
                = ($required ? "required" : "nullable")
                . "-" .
                ($type) . ($type === "enum" ? "-" . implode(".", $property["options"]) : '')
                . "-" .
                ($length);
            if (empty($conditions[$rule])) {
                $conditions[$rule] = [
                    "required" => $required,
                    "type"     => $type,
                    "length"   => $length,
                    "fields"   => [],
                ];
                if ($type === "enum") {
                    $conditions[$rule]["options"] = $property["options"];
                }
            }
            $conditions[$rule]["fields"][] = $propertyName;
        }
        foreach ($conditions as $key => $condition) {
            $conditions[$key]["key"] = trim(implode("-", $condition["fields"]) . "-" . $key, "- ");
        }
        return array_values($conditions);
    }

    private function fieldRequired($field, $required, $raw)
    {
        if (in_array($field, $raw["primaryKeys"])) {
            return false;
        }
        return $required;
    }

    private function getSkippedArgsConfig()
    {
        return $this->getRoutesConfig()["skip_argument"] ?? [];
    }

    private function getRoutesConfig()
    {
        return $this->getConfig()["routes"] ?? [];
    }

    public function getConfig()
    {
        return $this->config;
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