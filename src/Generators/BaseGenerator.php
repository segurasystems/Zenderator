<?php

namespace Zenderator\Generators;

use Gone\Twig\InflectionExtension;
use Gone\Twig\TransformExtension;
use Zenderator\Interfaces\DataProviderInterface;
use Zenderator\Interfaces\IZenderatorGenerator;
use Zenderator\Zenderator;
use Symfony\Component\Yaml\Yaml;

abstract class BaseGenerator implements IZenderatorGenerator
{
    private $twig;
    private $loader;
    protected $zenderator;
    private $outputPath;
    private $dataProvider;

    protected $baseTemplatePath = __DIR__ . "/../../generator/templates";

    public function __construct(Zenderator $zenderator, string $outputPath, DataProviderInterface $dataProvider)
    {
        $this->dataProvider = $dataProvider;
        $this->outputPath = rtrim($outputPath, "/") . "/";
        $this->zenderator = $zenderator;
        $this->loader = new \Twig_Loader_Filesystem($this->baseTemplatePath);
        $this->twig = new \Twig_Environment($this->loader, ['debug' => true]);
        $this->twig->addExtension(new \Twig_Extension_Debug());
        $this->twig->addExtension(new TransformExtension());
        $this->twig->addExtension(new InflectionExtension());

        $this->twig->addExtension(
            new \Gone\AppCore\Twig\Extensions\ArrayUniqueTwigExtension()
        );

        $fct = new \Twig_SimpleFunction('var_export', 'var_export');
        $this->twig->addFunction($fct);
    }

    protected function getDataProvider(): DataProviderInterface
    {
        return $this->dataProvider;
    }

    protected function renderToFile(bool $overwrite, string $path, string $template, array $data)
    {
        $output = $this->twig->render($template, $data);
        $this->putFile($overwrite, $path, $output);
        return $this;
    }

    protected function putJson(bool $overwrite, string $path, array $content)
    {
        $this->putFile($overwrite, $path, json_encode($content, JSON_PRETTY_PRINT));
    }

    protected function putYaml(bool $overwrite, string $path, array $content)
    {
        $this->putFile(
            $overwrite,
            $path,
            Yaml::dump($content, 10, 2)
        );
    }


    protected function putFile(bool $overwrite, string $path, string $content)
    {
        $this->mkdir($path);
        $path = $this->outputPath . ltrim($path, "/");
        if (!file_exists($path) || $overwrite) {
            file_put_contents($path, $content);
        }
    }

    protected function mkdir($path)
    {
        $path = $this->outputPath . ltrim($path, "/");
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
    }
}