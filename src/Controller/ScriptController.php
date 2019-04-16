<?php
/**
 * @package Puzzle-DI
 * @copyright Copyright © 2015 Danny Smart
 */

namespace Lexide\PuzzleDI\Controller;

use Composer\Script\Event;
use Lexide\PuzzleDI\Compiler\PuzzleClassCompiler;
use Lexide\PuzzleDI\Exception\ConfigurationException;
use Lexide\PuzzleDI\Helper\PuzzleDataCollector;

class ScriptController
{

    public static function compileConfigList(Event $event)
    {
        $output = $event->getIO();
        $composer = $event->getComposer();


        // Load the packages extra info so we can see if PuzzleDI needs additional information
        $package = $composer->getPackage();
        $extra = $package->getExtra();

        // loop over the possible config keys until a puzzle config is found
        $puzzleConfigKeys = [
            "lexide/puzzle-di",
            "downsider/puzzle-di"
        ];
        foreach ($puzzleConfigKeys as $configKey) {
            $puzzleConfig = empty($extra[$configKey]) ? [] : $extra[$configKey];
            if (!empty($puzzleConfig)) {
                break;
            }
        }

        // find repos that are configured to use Puzzle-DI
        $dataCollector = new PuzzleDataCollector($composer->getInstallationManager());
        $repo = $composer->getRepositoryManager()->getLocalRepository();

        $whitelist = !empty($puzzleConfig["whitelist"])? $puzzleConfig["whitelist"]: [];

        $data = [];
        // If we don't have a whitelist, all packages would be filtered out so only collect data if one is present
        if (!empty($whitelist)) {
            $data = $dataCollector->collectData($repo, $whitelist);
        }

        if (empty($data)) {
            // don't throw an exception in this case as we may not have installed any modules that use Puzzle DI
            $output->write("No installed modules are configured (and whitelisted) for use with Puzzle DI");
            // we still need to create the PuzzleConfig class, so don't end the script here
        }

        $compiler = new PuzzleClassCompiler();

        // find the path to the parent package's target directory
        $appNamespace = "";
        $appRootDir = getcwd(); // the cwd will always be the directory that the composer.json file is in
        $appSourceDir = $appRootDir . (empty($package->getTargetDir())? "": DIRECTORY_SEPARATOR . $package->getTargetDir());

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
                $appSourceDir = realpath($appSourceDir . DIRECTORY_SEPARATOR . $autoloadSourceDir);
            }
        }


        // As composer uses absolute paths when installing modules, we have to do extra work to get relative URLs
        // The path mask will remove itself from any path that starts with the mask
        // e.g. a path mask of '/home/puzzle-di/' will turn '/home/puzzle-di/app/config.yml' into 'app/config.yml'
        $pathMask = !empty($puzzleConfig["absolute-paths"])? "": $appRootDir . DIRECTORY_SEPARATOR;

        // generate the PuzzleConfig class
        $output->write("<info>PuzzleDI: Compiling</info> <comment>{$appNamespace}PuzzleConfig</comment> <info>to</info> <comment>$appSourceDir/PuzzleConfig.php</comment>");
        $compiler->compile($data, $appNamespace, $appSourceDir, $pathMask);
    }

} 
