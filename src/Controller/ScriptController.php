<?php
/**
 * @package Puzzle-DI
 * @copyright Copyright © 2015 Danny Smart
 */

namespace Downsider\PuzzleDI\Controller;

use Composer\Script\Event;
use Downsider\PuzzleDI\Compiler\PuzzleClassCompiler;
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

        // find the path to the parent package's target directory
        $package = $composer->getPackage();
        $appNamespace = "";
        $appRootDir = getcwd(); // the cwd will always be the directory that the composer.json file is in
        $appSourceDir = $appRootDir . (empty($package->getTargetDir())? "": "/" . $package->getTargetDir());

        // if we're using PSR-x, use the target directory defined for that
        $autoload = $package->getAutoload();
        $autoloadType = isset($autoload["psr-4"])? "psr-4": (isset($autoload["psr-0"])? "psr-0": null);
        if (!empty($autoloadType)) {
            // main project namespace MUST be the first entry
            // TODO: fix this so we intelligently select the namespace and source dir from all possible autoload entries
            $appNamespace = key($autoload[$autoloadType]);
            $autoloadSourceDir = current($autoload[$autoloadType]);
            if (!empty($autoloadSourceDir)) {
                $appSourceDir .= "/" . $autoloadSourceDir;
            }
        }

        // Load the packages extra info so we can see if PuzzleDI needs to use absolute paths
        $extra = $composer->getPackage()->getExtra();
        // As composer uses absolute paths when installing modules, we have to do extra work to get relative URLs
        // The path mask will remove itself from any path that starts with the mask
        // e.g. a path mask of '/home/puzzle-di/' will turn '/home/puzzle-di/app/config.yml' into 'app/config.yml'
        $pathMask = !empty($extra["puzzle-di"]["absolute-paths"])? "": $appRootDir . "/";

        // generate the PuzzleConfig class
        $output->write("<info>PuzzleDI: Compiling</info> <comment>{$appNamespace}PuzzleConfig</comment> <info>to</info> <comment>$appSourceDir/PuzzleConfig.php</comment>");
        $compiler->compile($data, $appNamespace, $appSourceDir, $pathMask);
    }

} 