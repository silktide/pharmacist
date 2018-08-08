<?php

namespace Silktide\Pharmacist;

use Silktide\Syringe\ContainerBuilder;
use Silktide\Syringe\Loader\JsonLoader;
use Silktide\Syringe\Loader\PhpLoader;
use Silktide\Syringe\Loader\YamlLoader;
use Silktide\Syringe\ReferenceResolver;
use Silktide\Syringe\Syringe;

class VerifyCommand
{
    use Loggable;

    /**
     * A quick summary of what this class needs to do:
     *  1. It needs to look to see whether we are a child project, and have a extra/downsider-puzzle-di/silktide-syringe section
     *     for the time being, if it hasn't, we want to die
     *  2. It needs to look to see if we have a PuzzleConfig.php, if so, we want to use it
     *
     * @return int
     * @throws \Exception
     */
    public function run()
    {
        // 1. Work out what directory we're caring about
        $directory = getcwd();

        $composerFilename = $directory . "/composer.json";

        // Does it have a reference to what syringe file it's meant to be using?
        if (!file_exists($composerFilename)) {
            $this->log("No composer.json file found");
            exit(1);
        }

        $this->log("Finding DI configuration");

        $decoded = json_decode(file_get_contents($composerFilename), true);

        $configFiles = [];

        // Work out if we're using PuzzleConfig
        // Atm, we're only going to support PSR-4 'cause let's face it, this is pretty much entirely internal and
        // nobody uses PSR-0
        try{
            $psr4 = $this->arrayByArrayPath($decoded, ["autoload", "psr-4"]);
            if (count($psr4) == 0) {
                throw new \Exception("No namespaces found");
            }

            $key = key($psr4);
        } catch (\Exception $e) {
            $this->log("Project is not using PSR-4");
            exit(1);
        }

        $puzzleClassName = $key . "PuzzleConfig";
        if (class_exists($puzzleClassName)) {
            // Then we're using the PuzzleConfig.php
            $this->log("Successfully found PuzzleDI PuzzleConfig data");
            $configFiles = array_merge($configFiles, array_flip($puzzleClassName::getConfigPaths("silktide/syringe")));
        }


        // Find the syringe path
        try{
            $configFiles[] = [$this->arrayByArrayPath($decoded, ["extra", "downsider-puzzle-di", "silktide/syringe", "path"]) => null];
            $this->log("Successfully found PuzzleDI composer.json data");
        } catch (\Exception $e) {
            $this->log("No Downsider Puzzle DI config found in composer.json");
            exit(1);
        }

        try{
            $container = Syringe::build([
                "files" => $configFiles
            ]);
        } catch (\Throwable $e) {
            $this->error("DI config failed at initial container creation");
            $this->error("  " . $e->getMessage());
            exit(1);
        }

        $this->log("Attempting to build the ".count($container->keys())." found services/parameters");
        /** @var \Exception[] $exceptions */
        $exceptions = [];
        foreach ($container->keys() as $key) {
            try{
                $build = $container[$key];
            } catch (\Throwable $e) {
                $exceptions[] = ["Key" => $key, "Exception" => $e];
            }
        }

        if (count($exceptions) > 0) {
            $this->error("DI config failed on ".count($exceptions)." services");
            $this->error("----------------------");
            foreach ($exceptions as $k => $row) {
                /**
                 * @var string $key
                 */
                $key = $row["Key"];
                /**
                 * @var \Throwable $exception
                 */
                $exception = $row["Exception"];

                $this->error("  Key: '{$key}'");
                $this->error("  Message: ".$exception->getMessage());
                $this->error("  File: ".$exception->getFile());
                $this->error("  Line: ".$exception->getLine());
                $this->error("----------------------");
            }
            exit(1);
        }
        
        $this->log("No configuration problems found");
        exit(0);
    }

    protected function arrayByArrayPath($array, array $path)
    {
        $key = array_shift($path);

        if (!isset($array[$key])) {
            throw new \Exception("The path key '{$key}' does not exist");
        }

        if (count($path) == 0) {
            return $array[$key];
        }

        return $this->arrayByArrayPath($array[$key], $path);
    }
}
