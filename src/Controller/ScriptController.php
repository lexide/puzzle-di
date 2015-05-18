<?php
namespace Downsider\PuzzleDI\Controller;
use Composer\Script\Event;
use Downsider\PuzzleDI\Compiler\PuzzleClassCompiler;
use Downsider\PuzzleDI\Helper\PuzzleDataCollector;

/**
 *
 */
class ScriptController 
{

    public static function compileConfigList(Event $event)
    {
        $output = $event->getIO();
        $composer = $event->getComposer();

        // find repos that are configured to use Puzzle-DI
        $dataCollector = new PuzzleDataCollector($composer->getInstallationManager());
        $repo = $composer->getRepositoryManager()->getLocalRepository();

        $data = $dataCollector->collectData($repo);

        if (empty($data)) {
            // don't throw an exception in this case as we may not have installed any modules that use Puzzle DI
            $output->write("No installed modules are configured for use with Puzzle DI");
            // we still need to create the PuzzleConfig class, so don't end the script here
        }

        $compiler = new PuzzleClassCompiler();

        // find the absolute path to the parent package's target directory
        $package = $composer->getPackage();
        $autoload = $package->getAutoload();
        $appNamespace = "";
        $appSourceDir = realpath($composer->getConfig()->get("vendor-dir") . "/../") . "/" . $package->getTargetDir();
        $autoloadType = isset($autoload["psr-4"])? "psr-4": (isset($autoload["psr-0"])? "psr-0": null);
        // if we're using PSR-x, use the target directory defined for that
        if (!empty($autoloadType)) {
            $appNamespace = key($autoload["psr-4"]);
            $appSourceDir .= current($autoload["psr-4"]);
        }

        // generate the PuzzleConfig class
        $output->write("<info>PuzzleDI: Compiling</info> <comment>{$appNamespace}PuzzleConfig</comment> <info>to</info> <comment>$appSourceDir/PuzzleConfig.php</comment>");
        $compiler->compile($data, $appNamespace, $appSourceDir);
    }

} 