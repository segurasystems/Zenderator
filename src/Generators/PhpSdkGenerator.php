<?php

namespace Zenderator\Generators;

use Zend\Stdlib\ConsoleHelper;
use Zenderator\Components\Column;
use Zenderator\Interfaces\IZenderatorGenerator;
use Zenderator\Zenderator;

class PhpSdkGenerator extends BaseGenerator
{
    protected $baseTemplatePath = __DIR__ . "/../../generator/templates";

    public function generate()
    {
        $this->generateAccessLayers();
        $this->generateValidators();
        $this->generateModels();
        //$this->generateTests($packName, $routeRenderData);
        $this->generateBaseFiles();

        return $this;
    }

    private function generateBaseFiles()
    {
        $renderData = [
            "accessLayers" => array_keys($this->getDataProvider()->getAccessLayerData()),
            "defaultUrl" => strtolower("http://" . $this->getDataProvider()->getAppName() . ".segurasystems.test"),
            "namespace" => $this->getDataProvider()->getNameSpace(),
            "appName" => $this->getDataProvider()->getAppName(),
            "classNamespace" => $this->getDataProvider()->getBaseClassNameSpace(),
            "classNamespaceJSONSAFE" => $this->getDataProvider()->getBaseClassNameSpace(true),
            "releaseTime" => date("Y-m-d H:i:s"),
        ];
        echo "\n";
        echo str_pad("Generating Dependency Injector:", 50);
        $this->renderToFile(true, "/src/DependencyInjector.php", "SDK/dependencyinjector.php.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        echo str_pad("Generating Client Container:", 50);
        $this->renderToFile(true, "/src/Client.php", "SDK/client.php.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        echo str_pad("Generating Composer.json:", 50);
        $this->renderToFile(true, "/composer.json", "SDK/composer.json.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        echo str_pad("Generating Test Bootstrap:", 50);
        $this->renderToFile(true, "/bootstrap.php", "SDK/bootstrap.php.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        echo str_pad("Generating phpunit.xml, documentation, etc:", 50);
        $this->renderToFile(true, "/phpunit.xml.dist", "SDK/phpunit.xml.twig", $renderData);
        $this->renderToFile(true, "/Readme.md", "SDK/readme.md.twig", $renderData);
        $this->renderToFile(true, "/.gitignore", "SDK/gitignore.twig", $renderData);
        $this->renderToFile(true, "/Dockerfile.tests", "SDK/Dockerfile.twig", $renderData);
        $this->renderToFile(true, "/test-compose.yml", "SDK/docker-compose.yml.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";
    }

    private function generateModels()
    {
        print "\nGenerating Models ...\n";
        $modelData = $this->getDataProvider()->getModelData();
        foreach ($modelData as $className => $class) {
            print str_pad("   > {$className}",40);
            print " Base";
            $this->renderToFile(true, "/src/Models/Base/Base{$className}Model.php", "Classes/Models/Base/Base{classname}Model.php.twig", ["class" => $class]);
            print " Main";
            $this->renderToFile(false, "/src/Models/{$className}Model.php", "Classes/Models/{classname}Model.php.twig", ["class" => $class]);
            echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";
        }
    }

    private function generateValidators(){
        print "\nGenerating Validators ...\n";
        $modelData = $this->getDataProvider()->getModelData();
        foreach ($modelData as $className => $class) {
            print str_pad("   > {$className}",40);
            print " Base";
            $this->renderToFile(true, "/src/Validators/Base/Base{$className}Validator.php", "Classes/Validators/Base/Base{classname}Validator.php.twig", ["class" => $class]);
            print " Main";
            $this->renderToFile(false, "/src/Validators/{$className}Validator.php", "Classes/Validators/{classname}Validator.php.twig", ["class" => $class]);
            echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";
        }
    }

    private function generateAccessLayers()
    {
        print "\nGenerating AccessLayers ...\n";
        $accessLayerData = $this->getDataProvider()->getAccessLayerData();
        foreach ($accessLayerData as $className => $class) {
            print str_pad("   > {$className}",40);
            print " Base";
            $this->renderToFile(true, "/src/AccessLayers/Base/Base{$className}AccessLayer.php", "SDK/AccessLayers/baseaccesslayer.php.twig", ["class" => $class]);
            print " Main";
            $this->renderToFile(false, "/src/AccessLayers/{$className}AccessLayer.php", "SDK/AccessLayers/accesslayer.php.twig", ["class" => $class]);
            echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";;
        }
    }

    /**
     * @param $packName
     * @param $routeRenderData
     */
    private function generateTests($packName, $routeRenderData): void
    {
// Tests
        $this->renderToFile(true, "/tests/AccessLayer/{$packName}Test.php", "SDK/Tests/AccessLayer/client.php.twig", $routeRenderData);

        $this->mkdir("/tests/fixtures/touch");
    }
}