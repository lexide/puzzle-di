<?php
/**
 * @package Puzzle-DI
 * @copyright Copyright © 2015 Danny Smart
 */

namespace Lexide\PuzzleDI\Controller;

use Composer\Compiler;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use Lexide\PuzzleDI\Compiler\PuzzleClassCompiler;
use Lexide\PuzzleDI\Exception\ConfigurationException;
use Lexide\PuzzleDI\Helper\PuzzleDataCollector;

class ScriptController
{

    /**
     * @var PuzzleClassCompiler
     */
    protected $compiler;

    /**
     * @var PuzzleDataCollector
     */
    protected $dataCollector;

    /**
     * @var PackageInterface
     */
    protected $package;

    /**
     * @var IOInterface
     */
    protected $output;

    public function __construct(PuzzleClassCompiler $compiler, PuzzleDataCollector $dataCollector, PackageInterface $package, IOInterface $output)
    {
        $this->compiler = $compiler;
        $this->dataCollector = $dataCollector;
        $this->package = $package;
        $this->output = $output;
    }

    /**
     * @param Event $event
     * @throws ConfigurationException
     */
    public function compileConfigList()
    {
        // Load the packages puzzle config, if it has any
        $puzzleComposerConfig = $this->getPuzzleComposerConfig($this->package);

        // find repos that are configured to use Puzzle-DI
        $whitelist = !empty($puzzleComposerConfig["whitelist"])? $puzzleComposerConfig["whitelist"]: [];

        $data = [];
        // If we don't have a whitelist, all packages would be filtered out so only collect data if one is present
        if (!empty($whitelist)) {
            $data = $this->dataCollector->collectData($whitelist);
        }

        if (empty($data)) {
            // don't throw an exception in this case as we may not have installed any modules that use Puzzle DI
            $this->output->write("</info><info>Lexide/PuzzleDI:</info> No installed modules are configured (and whitelisted) for use with Puzzle DI");
            // we still need to create the PuzzleConfig class, so don't end the script here
        }

        // find the path to the parent package's target directory
        $appNamespace = $this->getAppNamespace($this->package, $puzzleComposerConfig);
        $appRootDir = getcwd(); // the cwd will always be the directory that the composer.json file is in

        $appSourceDir = $this->getAppSourceDir($this->package, $appRootDir, $appNamespace);

        // As composer uses absolute paths when installing modules, we have to do extra work to get relative URLs
        // The path mask will remove itself from any path that starts with the mask
        // e.g. a path mask of '/home/puzzle-di/' will turn '/home/puzzle-di/app/config.yml' into 'app/config.yml'
        $pathMask = !empty($puzzleComposerConfig["absolute-paths"])? "": $appRootDir . DIRECTORY_SEPARATOR;

        // generate the PuzzleConfig class
        $this->output->write("</info><info>Lexide/PuzzleDI:</info> Compiling <comment>{$appNamespace}PuzzleConfig</comment> to <comment>{$this->compiler->getPuzzleConfigFilepath($appSourceDir)}</comment>");
        $this->compiler->compile($data, $appNamespace, $appSourceDir, $pathMask);
    }

    /**
     * @param PackageInterface $package
     * @throws ConfigurationException
     */
    public function uninstall()
    {
        $puzzleComposerConfig = $this->getPuzzleComposerConfig($this->package);

        $appNamespace = $this->getAppNamespace($this->package, $puzzleComposerConfig);
        $appRootDir = getcwd(); // the cwd will always be the directory that the composer.json file is in

        $appSourceDir = $this->getAppSourceDir($this->package, $appRootDir, $appNamespace);

        // remove the PuzzleConfig class if it exists
        $puzzleConfigPath = $this->compiler->getPuzzleConfigFilepath($appSourceDir);
        $this->output->write("</info><info>Lexide/PuzzleDI:</info> Removing <comment>{$appNamespace}PuzzleConfig</comment> from <comment>$puzzleConfigPath</comment>");
        unlink($puzzleConfigPath);

    }

    /**
     * @param PackageInterface $package
     * @return array
     */
    protected function getPuzzleComposerConfig(PackageInterface $package)
    {
        $extra = $package->getExtra();

        // loop over the possible config keys until a puzzle config is found
        $puzzleConfigKeys = [
            "lexide/puzzle-di",
            "downsider/puzzle-di"
        ];

        $puzzleConfig = [];

        foreach ($puzzleConfigKeys as $configKey) {
            $puzzleConfig = empty($extra[$configKey]) ? [] : $extra[$configKey];
            if (!empty($puzzleConfig)) {
                break;
            }
        }

        return $puzzleConfig;
    }

    /**
     * @param PackageInterface $package
     * @param array $puzzleComposerConfig
     * @return string
     * @throws ConfigurationException
     */
    protected function getAppNamespace(PackageInterface $package, array $puzzleComposerConfig)
    {
        $appNamespace = "";

        // if we're using PSR-x, use the target directory defined for that
        $autoload = $this->getAutoload($package);
        if (!empty($autoload)) {
            // if the main project namespace is not the first autoload entry, it needs to be set in puzzle-di config
            $appNamespace = !empty($puzzleComposerConfig["namespace"])
                ? $puzzleComposerConfig["namespace"]
                : key($autoload);

            if (empty($autoload[$appNamespace])) {
                throw new ConfigurationException("Cannot compile PuzzleConfig. The application namespace '$appNamespace' is not registered with composer");
            }
        }

        return $appNamespace;
    }

    protected function getAppSourceDir(PackageInterface $package, $appRootDir, $appNamespace)
    {
        $appSourceDir = $appRootDir . (empty($package->getTargetDir())? "": DIRECTORY_SEPARATOR . $package->getTargetDir());

        $autoload = $this->getAutoload($package);

        if (!empty($autoload)) {
            $autoloadSourceDir = $autoload[$appNamespace];
            if (!empty($autoloadSourceDir)) {
                $appSourceDir = $appSourceDir . DIRECTORY_SEPARATOR . $autoloadSourceDir;

                // run realpath against this value, but it might not exist so don't overwrite in that case
                $realPath = realpath($appSourceDir . DIRECTORY_SEPARATOR . $autoloadSourceDir);
                if ($realPath !== false) {
                    $appSourceDir = $realPath;
                }
            }
        }

        return $appSourceDir;
    }

    /**
     * @param PackageInterface $package
     * @return array
     */
    protected function getAutoload(PackageInterface $package)
    {
        $autoload = $package->getAutoload();

        foreach (["psr-4", "psr-0"] as $autoloadType) {
            if (isset($autoload[$autoloadType])) {
                return $autoload[$autoloadType];
            }
        }

        return null;
    }

} 
