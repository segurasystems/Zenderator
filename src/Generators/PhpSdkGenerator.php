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


        // Access Layer
        $this->generateAccessLayers();
        // Models
        $this->generateModels();
        die();
        // Tests
        $this->renderToFile(true, "/tests/AccessLayer/{$packName}Test.php", "SDK/Tests/AccessLayer/client.php.twig", $routeRenderData);

        $this->mkdir("/tests/fixtures/touch");

        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        echo str_pad("Generating Client Container:", 50);
        $this->renderToFile(true, "/src/Client.php", "SDK/client.php.twig", $renderData);
        echo " [" . ConsoleHelper::COLOR_GREEN . "DONE" . ConsoleHelper::COLOR_RESET . "]\n";

        echo str_pad("Generating Dependency Injector:", 50);
        $this->renderToFile(true, "/src/DependencyInjector.php", "SDK/dependencyinjector.php.twig", $renderData);
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

        return $this;
    }

    private function generateModels()
    {
        print "\nGenerating Models ...\n";
        $modelData = $this->getDataProvider()->getModelData();
        foreach ($modelData as $className => $class) {
            print "   > {$className} ...";
            print " Base...";
            $this->renderToFile(true, "/src/Models/Base/Base{$className}Model.php", "SDK/Models/basemodel.php.twig", ["class" => $class]);
            print " Main...";
            $this->renderToFile(false, "/src/Models/{$className}Model.php", "SDK/Models/model.php.twig", ["class" => $class]);
            print " Done\n";
        }
    }

    private function generateAccessLayers()
    {
        print "\nGenerating AccessLayers ...\n";
        $accessLayerData = $this->getDataProvider()->getAccessLayerData();
        foreach ($accessLayerData as $className => $class) {
            print "   > {$className} ...";
            print " Base...";
            $this->renderToFile(true, "/src/AccessLayer/Base/Base{$className}AccessLayer.php", "SDK/AccessLayer/baseaccesslayer.php.twig", ["class" => $class]);
            print " Main...";
            $this->renderToFile(false, "/src/AccessLayer/{$className}AccessLayer.php", "SDK/AccessLayer/accesslayer.php.twig", ["class" => $class]);
            print " Done\n";
        }
    }

    private function generateBaseFiles()
    {

    }
}