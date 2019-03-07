<?php

namespace Zenderator\Generators;

use Zend\Stdlib\ConsoleHelper;
use Zenderator\Components\Column;
use Zenderator\Interfaces\IZenderatorGenerator;
use Zenderator\Zenderator;

class PhpSdkGenerator extends BaseGenerator
{
    protected $baseTemplatePath = __DIR__ . "/../../generator/templates";

    public function generateFromRoutes(array $routes)
    {
        $packs = [];
        $routeCount = 0;
        $sharedRenderData = [
            'app_namespace'    => APP_NAMESPACE,
            'app_name'         => APP_NAME,
            'app_container'    => APP_CORE_NAME,
            'default_base_url' => strtolower("http://" . APP_NAME . ".segurasystems.test"),
            'release_time'     => date("Y-m-d H:i:s"),
        ];


        foreach ($routes as $route) {
            if (isset($route['name'])) {
                if (isset($route['class'])) {
                    $packs[(string)$route['class']][(string)$route['function']] = $route;
                    $routeCount++;
                }
            }
        }

        echo "Generating SDK for {$routeCount} routes...\n";
        // "SDK" suite
        foreach ($packs as $packName => $routes) {
            echo str_pad(" > Pack [{$packName}]:",50);
            $scopeName = $packName;
            $scopeName[0] = strtolower($scopeName[0]);
            $properties = [];
            $propertiesOptions = [];
            $singular = null;
            $plural = null;
            $tableName = null;
            $propertyData = [];
            foreach ($routes as $k=>$route) {
                if (isset($route['properties'])) {
                    foreach ($route['properties'] as $property) {
                        $properties[] = $property;
                    }
                }
                if(isset($route["tableName"])){
                    $tableName = $route["tableName"];
                }
                if(isset($route["plural"])){
                    $plural = $route["plural"];
                    $routes[$k]["pluralLC"] = lcfirst($route["plural"]);
                }
                if(isset($route["singular"])){
                    if(empty($route["responseKey"])){
                        $routes[$k]["responseKey"] = $route["singular"];
                    }
                    $singular = $route["singular"];
                    $routes[$k]["singularLC"] = lcfirst($route["singular"]);
                }
                if (isset($route['propertiesOptions'])) {
                    foreach ($route['propertiesOptions'] as $propertyName => $propertyOption) {
                        $propertiesOptions[$propertyName] = $propertyOption;
                    }
                }
                if(isset($route["propertyData"]) && in_array($route["template"],["get","create"])){
                    foreach ($route['propertyData'] as $propertyName => $data) {
                        $data["nameLC"] = lcfirst($propertyName);
                        if(!empty($data["type"])){
                            $data["phpType"] = Column::convertColumnType($data["type"]);
                        }
                        if(!empty($data["related"])){
                            foreach ($data["related"] as $index => $related) {
                                $data["related"][$index]["modelName"] = preg_replace('/Model$/', '', $related["model"]);
                            }
                        }
                        $data["cleanName"] = Column::cleanName($propertyName);
                        $propertyData[ucfirst($propertyName)] = $data;
                    }
                }
            }

            //var_dump($propertyData);die();

            $routeRenderData = [
                'pack_name'  => $packName,
                'scope_name' => $scopeName,
                'routes'     => $routes,
            ];
            $properties = array_unique($properties);
            $routeRenderData['properties'] = $properties;
            $routeRenderData['propertiesOptions'] = $propertiesOptions;
            $routeRenderData['propertyData'] = $propertyData;
            $routeRenderData['plural'] = $plural;
            $routeRenderData['singular'] = $singular;
            $routeRenderData['tableName'] = $tableName;
            $routeRenderData = array_merge($sharedRenderData, $routeRenderData);
            #\Kint::dump($routeRenderData);

            // Access Layer
            $this->renderToFile(true, "/src/AccessLayer/Base/Base{$packName}AccessLayer.php", "SDK/AccessLayer/baseaccesslayer.php.twig", $routeRenderData);
            $this->renderToFile(false, "/src/AccessLayer/{$packName}AccessLayer.php", "SDK/AccessLayer/accesslayer.php.twig", $routeRenderData);

            // Models
            $this->renderToFile(true, "/src/Models/Base/Base{$packName}Model.php", "SDK/Models/basemodel.php.twig", $routeRenderData);
            $this->renderToFile(false, "/src/Models/{$packName}Model.php", "SDK/Models/model.php.twig", $routeRenderData);

            // Tests
            $this->renderToFile(true, "/tests/AccessLayer/{$packName}Test.php", "SDK/Tests/AccessLayer/client.php.twig", $routeRenderData);

            $this->mkdir("/tests/fixtures/touch");

            echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";
        }

        $renderData = array_merge(
            $sharedRenderData,
            [
                'packs'  => $packs,
                'config' => $this->zenderator->getConfig(),
            ]
        );

        echo str_pad("Generating Client Container:",50);
        $this->renderToFile(true, "/src/Client.php", "SDK/client.php.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        echo str_pad("Generating Dependency Injector:",50);
        $this->renderToFile(true, "/src/DependencyInjector.php", "SDK/dependencyinjector.php.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        echo str_pad("Generating Composer.json:",50);
        $this->renderToFile(true, "/composer.json", "SDK/composer.json.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        echo str_pad("Generating Test Bootstrap:",50);
        $this->renderToFile(true, "/bootstrap.php", "SDK/bootstrap.php.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        echo str_pad("Generating phpunit.xml, documentation, etc:",50);
        $this->renderToFile(true, "/phpunit.xml.dist", "SDK/phpunit.xml.twig", $renderData);
        $this->renderToFile(true, "/Readme.md", "SDK/readme.md.twig", $renderData);
        $this->renderToFile(true, "/.gitignore", "SDK/gitignore.twig", $renderData);
        $this->renderToFile(true, "/Dockerfile.tests", "SDK/Dockerfile.twig", $renderData);
        $this->renderToFile(true, "/test-compose.yml", "SDK/docker-compose.yml.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        return $this;
    }
}