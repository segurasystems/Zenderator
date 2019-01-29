<?php

namespace Zenderator\Generators;

class SwaggerGenerator extends BaseGenerator
{
    private $baseSwagger
        = [
            'openapi'      => '3.0.3',
            'info'         => [
                'title'          => 'Swagger Test',
                'description'    => 'Beep',
                'termsOfService' => 'TERMS',
                'contact'        => [
                    "name"  => "name",
                    "url"   => "url",
                    "email" => "email",
                ],
                'license'        => [
                    'name' => 'AN LICENCE',
                    'url'  => 'AN URL',
                ],
                'version'        => '1.0.0',
            ],
            'servers'      => [
                [
                    "url"         => "http://{instanceName}.segurasystems.com",
                    "description" => "description",
                    "variables"   => [
                        "instanceName" => [
                            "description" => "the instance id / domain"
                        ],
                        //                        "versionString" => [
                        //                            "enum"        => ["v1"],
                        //                            "default"     => "v1",
                        //                            "description" => "Version of the api you are accessing",
                        //                        ],
                    ],
                ],
            ],
            'paths'        => [],
            'components'   => [],
            'security'     => [],
            'tags'         => [],
            'externalDocs' => [],
        ];

    const FILTER_PARAMETER
        = [
        ];

    private $schemas = [];
    private $parameters = [];

    public function generateFromRoutes(array $routes)
    {

        $groupedRoutes = $this->groupRoutesByPath($routes);

        $swaggerPaths = [];
        foreach ($groupedRoutes as $path => $methods) {
            $swaggerPaths[$path] = $this->generateSwaggerMethods($methods);
        }
        ksort($swaggerPaths);

        $swagger = $this->baseSwagger;

        $swagger["paths"] = $swaggerPaths;

        $this->putJson(true, "swagger.json", $swagger);
        $this->putYaml(true, "swagger.yml", $swagger);
    }

    private function groupRoutesByPath($routes)
    {
        $grouped = [];
        foreach ($routes as $route) {
            $path = $route["pattern"];
            if (!isset($grouped[$path])) {
                $grouped[$path] = [];
            }
            $grouped[$path][$route["method"]] = $route;
        }
        return $grouped;
    }

    private function generateSwaggerMethods($methods)
    {
        $swaggerMethods = [];
        foreach ($methods as $method => $route) {
            $swaggerMethods[strtolower($method)] = $this->generateSwaggerMethod($route);
        }
        ksort($swaggerMethods);
        return $swaggerMethods;
    }

    public function generateSwaggerMethod($route)
    {
        $swagger = [];
        $swagger["summary"] = $route["name"] ?? "Missing Name [{$route["pattern"]}]";
        $swagger["tags"] = [$route["class"] ?? "Missing Class [{$route["pattern"]}]"];
        if (!empty($route["callbackProperties"])) {
            $swagger["parameters"] = $this->generateSwaggerParameters($route);
        }
        ksort($swagger);
        return $swagger;
    }

    private function generateSwaggerParameters($route)
    {
        $parameters = [];
        foreach ($route["callbackProperties"] as $name => $property) {
            $parameters[] = $this->generateSwaggerParameter($property,$route);
        }
        return $parameters;
    }

    private function generateSwaggerParameter($property,$route){
        return  [
            "name"        => $property["name"],
            "in"          => $property["in"] ?? $this->swaggerPropertyIn($route),
            "description" => $property["description"] ?? "MISSING DESCRIPTION !!!!",
            "required"    => $property["isMandatory"],
            "schema"      => $this->swaggerPropertySchema($property),
        ];
    }

//"name" => "Filter",
//"in"   => "header",
//"description" => "Filter for the list",
////        "required" => false,
////        "deprecated" => false,
////        "allowEmptyValue" => false,
//"style" => "json",
//"schema" =>['$ref' => "#/components/schemas/Filter"]
    private function swaggerPropertyIn($route)
    {
        $in = "query";
        switch ($route["method"]) {
            case "POST":
                $in = "body";
                break;
            default:
                $in = "path";
                break;
        }
        return $in;
    }

    private function swaggerPropertySchema($property)
    {
        if(!empty($property["type"]) && is_array($property["type"])){
            return $property["type"];
        }
        $type = null;
        $format = null;
        switch (strtolower($property["type"] ?? "")) {
            case "integer":
            case "int":
                $type = "integer";
                $format = "int32";
                break;
            case "string":
                $type = "string";
                break;
            case "base64":
                $type = "string";
                $format = "byte";
                break;
            case "float":
                $type = "number";
                $format = "float";
                break;
            case "boolean":
            case "bool":
                $type = "number";
                $format = "float";
                break;
            case "date":
                $type = "string";
                $format = "date";
                break;
            case "datetime":
            case "date-time":
                $type = "string";
                $format = "date-time";
                break;
            case "password":
                $type = "string";
                $format = "password";
                break;
            default:
                break;
        }
        $schema = null;
        if (!empty($type)) {
            $schema = ["type" => $type];
            if (!empty($format)) {
                $schema["format"] = $format;
            }
        }
        return $schema;
    }
}