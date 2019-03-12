<?php

namespace Zenderator\DataProviders;

use GuzzleHttp\Client as GuzzleClient;
use Zenderator\Interfaces\DataProviderInterface;

class HttpProvider implements DataProviderInterface
{
    private $guzzleClient;
    private $rawData;
    private $namespace;
    private $modelData;
    private $accessLayerData;

    public function __construct(string $baseURI,string $namespace)
    {
        $this->namespace = $namespace;
        $this->guzzleClient = new GuzzleClient([
            'base_uri' => $baseURI,
            'timeout'  => 30.0,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);
    }

    public function getNameSpace(): string
    {
        return $this->namespace;
    }

    public function getModelData() : array{
        if(empty($this->modelData)){
            $this->generateModelData();
        }
        return $this->modelData;
    }

    public function getAccessLayerData() : array{
        if(empty($this->accessLayerData)){
            $this->generateAccessLayerData();
        }
        return $this->accessLayerData;
    }

    private function generateAccessLayerData(){
        print "HTTP : GENERATING ACCESS LAYER DATA\n";
        $rawData = $this->getRawAccesLayerData();
        print "HTTP : GOT " . count($rawData) . " ROUTES\n";
        $accessLayerData = [];
        foreach ($rawData as $raw) {
            if(empty($raw["class"])){
                continue;
            }
            $class = $raw["class"];
            if(empty($accessLayerData[$class])){
                $accessLayerData[$class] = [
                    "namespace" =>  $this->getNameSpace(),
                    "name" => $class,
                    "methods" => []
                ];
            }
            $accessLayerData[$class]["methods"][] = $raw;
        }
        print "HTTP : GOT " . count($accessLayerData) . " ACCESS LAYER CLASSES\n";
        $this->accessLayerData = $accessLayerData;
    }

    private function generateModelData(){
        $rawModelData = $this->getRawModelData();
        $modelData = [];
        foreach ($rawModelData as $name => $raw){
            $raw["namespace"] = $this->getNameSpace();
            $raw["name"] = $name;
            $modelData[$name] = $raw;
        }
        $this->modelData = $modelData;
    }

    private function fetchRawData(){
        print "HTTP : FETCHING RAW DATA\n";
        $result = $this->guzzleClient->get("/v1")->getBody()->getContents();
        $result = json_decode($result,true);
        if(!is_array($result)){
            throw new \Exception("Response from api was not expected json");
        }

        if(empty($result["Routes"])){
            throw new \Exception("Response from api did not contain any routes");
        }

        if(empty($result["Models"])){
            throw new \Exception("Response from api did not contain any Models");
        }

        $this->rawData = [
            "Routes" => $result["Routes"],
            "Models" => $result["Models"],
        ];
    }

    private function getRawData(){
        if(empty($this->rawData)){
            $this->fetchRawData();
        }
        return $this->rawData;
    }

    private function getRawModelData(){
        return $this->getRawData()["Models"];
    }

    private function getRawAccesLayerData(){
        return $this->getRawData()["Routes"];
    }
}