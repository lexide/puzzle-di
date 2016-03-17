<?php
/**
 * @package Puzzle-DI
 * @copyright Copyright © 2015 Danny Smart
 */

namespace Downsider\PuzzleDI\Controller;

use Composer\Script\Event;
use Downsider\PuzzleDI\Compiler\PuzzleClassCompiler;
use Downsider\PuzzleDI\Exception\ConfigurationException;
use Downsider\PuzzleDI\Helper\PuzzleDataCollector;

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

        // Load the packages extra info so we can see if PuzzleDI needs additional information
        $package = $composer->getPackage();
        $extra = $package->getExtra();
        $puzzleConfig = empty($extra["downsider/puzzle-di"])? []: $extra["downsider/puzzle-di"];

        // find the path to the parent package's target directory
        $appNamespace = "";
        $appRootDir = getcwd(); // the cwd will always be the directory that the composer.json file is in
        $appSourceDir = $appRootDir . (empty($package->getTargetDir())? "": "/" . $package->getTargetDir());

        // if we're using PSR-x, use the target directory defined for that
        $autoload = $package->getAutoload();
        $autoloadType = isset($autoload["psr-4"])? "psr-4": (isset($autoload["psr-0"])? "psr-0": null);
        if (!empty($autoloadType)) {
            // if the main project namespace is not the first autoload entry, it needs to be set in puzzle-di config
            $appNamespace = !empty($puzzleConfig["namespace"])
                ? $puzzleConfig["namespace"]
                : key($autoload[$autoloadType]);

            if (empty($autoload[$autoloadType][$appNamespace])) {
                throw new ConfigurationException("Cannot compile PuzzleConfig. The application namespace '$appNamespace' is not registered with composer");
            }

            $autoloadSourceDir = $autoload[$autoloadType][$appNamespace];
            if (!empty($autoloadSourceDir)) {
                $appSourceDir .= "/" . $autoloadSourceDir;
            }
        }


        // As composer uses absolute paths when installing modules, we have to do extra work to get relative URLs
        // The path mask will remove itself from any path that starts with the mask
        // e.g. a path mask of '/home/puzzle-di/' will turn '/home/puzzle-di/app/config.yml' into 'app/config.yml'
        $pathMask = !empty($puzzleConfig["absolute-paths"])? "": $appRootDir . "/";

        // generate the PuzzleConfig class
        $output->write("<info>PuzzleDI: Compiling</info> <comment>{$appNamespace}PuzzleConfig</comment> <info>to</info> <comment>$appSourceDir/PuzzleConfig.php</comment>");
        $compiler->compile($data, $appNamespace, $appSourceDir, $pathMask);
    }

} 