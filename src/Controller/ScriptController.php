<?php
namespace Downsider\PuzzleDI\Controller;
use Composer\Script\Event;
use Downsider\PuzzleDI\Compiler\PuzzleClassCompiler;
use Downsider\PuzzleDI\Exception\ConfigurationException;
use Composer\Repository\RepositoryInterface;
use Composer\Package\Package;
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

        $dataCollector = new PuzzleDataCollector($composer->getInstallationManager());
        $repo = $composer->getRepositoryManager()->getLocalRepository();

        $data = $dataCollector->collectData($repo);

        if (empty($data)) {
            // don't throw an exception in this case as we may not have installed any modules that use Puzzle DI
            $output->write("No installed modules are configured for use with Puzzle DI");
            return;
        }

        $compiler = new PuzzleClassCompiler();

        $package = $composer->getPackage();
        $autoload = $package->getAutoload();
        $appNamespace = "";
        $appSourceDir = realpath($composer->getConfig()->get("vendor-dir") . "/../") . "/" . $package->getTargetDir();
        $autoloadType = isset($autoload["psr-4"])? "psr-4": (isset($autoload["psr-0"])? "psr-0": null);
        if (!empty($autoloadType)) {
            $appNamespace = key($autoload["psr-4"]);
            $appSourceDir .= current($autoload["psr-4"]);
        }
        $output->write("Compiling {$appNamespace}PuzzleConfig to $appSourceDir/PuzzleConfig.php");
        $compiler->compile($data, $appNamespace, $appSourceDir);
        $output->write("Compiling successful");
    }

} 