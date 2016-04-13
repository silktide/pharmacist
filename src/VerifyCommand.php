<?php

namespace Silktide\SyringeVerifier;

use Downsider\PuzzleDI\Helper\PuzzleDataCollector;
use Monolog\Logger;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Silktide\Syringe\ContainerBuilder;
use Silktide\Syringe\Loader\JsonLoader;
use Silktide\Syringe\Loader\YamlLoader;
use Silktide\Syringe\ReferenceResolver;
use Silktide\Syringe\SyringeServiceProvider;
use Silktide\SyringeVerifier\Parser\ComposerParser;
use Silktide\SyringeVerifier\Parser\ComposerParserResult;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VerifyCommand extends Command
{
    protected $composerParser;
    protected $log;
    protected $input;
    protected $output;

    public function __construct()
    {
        parent::__construct();
        $this->composerParser = new ComposerParser();
        $this->log = new Logger("SyringeVerifier");
    }

    public function configure()
    {
        // By setting the name as list, it's the default thing that will be run
        $this->setName("verify")
             ->addOption("configs", "c", InputOption::VALUE_IS_ARRAY + InputOption::VALUE_OPTIONAL, "Any additional configs we want to add manually", [])
             ->addOption("force", "f", InputOption::VALUE_NONE, "Whether we want to force through trying it regardless of whether it looks like we're using Puzzle-DI in the parent project");
    }

    public function execute(InputInterface $inputInterface, OutputInterface $outputInterface)
    {
        $this->input = $inputInterface;
        $this->output = $outputInterface;

        $this->log->setHandlers([new ConsoleHandler($outputInterface, null, [LogLevel::DEBUG])]);

        // 1. Work out what directory we're caring about
        $directory = getcwd();
        // That was easy

        // 2. Work out what base config we're meant to be using
        $parserResult = $this->composerParser->parse($directory."/composer.json");

        if (!$parserResult->usesSyringe() && !$inputInterface->getOption("force")) {
            $this->log->critical("The project in this directory '{$directory}' is not a library includable by syringe via Puzzle-DI");
            return 1;
        }

        $container = $this->setupContainer($parserResult);

        $this->log->info("Attempting to build all services!");
        $exceptions = [];
        foreach ($container->keys() as $key) {
            try{
                $build = $container[$key];
            } catch (\Exception $e) {
                $exceptions[] = [$e->getMessage(), $e->getFile(), $e->getLine()];
            }
        }

        if (count($exceptions) > 0) {
            $this->log->crit("Failed to successfully build ".count($exceptions)." bits of DI config");
            $table = new Table($outputInterface);
            $table->setHeaders(["Message", "File", "Line"]);
            $table->setRows($exceptions);
            $table->render();
            return 1;
        } else {
            $this->log->info("Succeeded!");
            return 0;
        }
    }

    public function setupContainer(ComposerParserResult $parserResult)
    {
        $directory = $parserResult->getDirectory();

        $resolver = new ReferenceResolver();
        $loaders = [
            new JsonLoader(),
            new YamlLoader()
        ];

        include($directory."/vendor/autoload.php");
        $builder = new ContainerBuilder($resolver, [$directory]);
        foreach ($loaders as $loader) {
            $builder->addLoader($loader);
        }

        $builder->setApplicationRootDirectory($directory);
        if ($parserResult->usesSyringe()) {
            $builder->addConfigFile($parserResult->getAbsoluteSyringeConfig());

            // This is a hack regarding the somewhat naff way Namespaces can end up working
            $builder->addConfigFiles([
                $parserResult->getNamespace() => $parserResult->getAbsoluteSyringeConfig()
            ]);
        }

        $additionalConfigs = $this->input->getOption("configs");
        $builder->addConfigFiles($parserResult->getConfigList());


        return $builder->createContainer();

    }
}