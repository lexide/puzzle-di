<?php

namespace Lexide\PuzzleDI\Helper;

use Composer\Autoload\AutoloadGenerator;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryManager;

class AutoloadInitialiser
{

    protected RepositoryManager $repositoryManager;
    protected InstallationManager $installationManager;
    protected AutoloadGenerator $generator;
    protected PackageInterface $rootPackage;
    protected string $vendorDirectory;

    public function __construct(RepositoryManager $repositoryManager, InstallationManager $installationManager, AutoloadGenerator $generator, string $vendorDirectory)
    {
        $this->repositoryManager = $repositoryManager;
        $this->installationManager = $installationManager;
        $this->generator = $generator;
        $this->vendorDirectory = $vendorDirectory;
    }

    public function initAutoloaderIfRequired(PackageInterface $rootPackage, array $puzzleData): void
    {
        // parse puzzleData for "class" attributes
        $classPackages = [];
        foreach ($puzzleData as $packageData) {
            foreach ($packageData as $packageName => $packageConfig) {
                if (!empty($packageConfig["class"])) {
                    $classPackages[$packageName] = true;
                }
            }
        }

        // if we find no classes are used, return early
        if (empty($classPackages)) {
            return;
        }

        // init the autoloader for all packages which use them, and their dependencies
        $autoloadMap = [];
        foreach (array_keys($classPackages) as $packageName) {
            $package = $this->repositoryManager->findPackage($packageName, "*");
            $autoloadMap[$packageName] = [$package, $this->installationManager->getInstallPath($package)];
            $autoloadMap = $this->collectDependencies($autoloadMap, $package);
        }

        $map = $this->generator->parseAutoloads($autoloadMap, $rootPackage);
        $loader = $this->generator->createLoader($map, $this->vendorDirectory);
        $loader->register();

    }

    /**
     * @param array $autoloadMap
     * @param PackageInterface $package
     * @return array
     */
    protected function collectDependencies(array $autoloadMap, PackageInterface $package)
    {
        foreach ($package->getRequires() as $requireName => $requiredPackage) {
            /** @var PackageInterface $requiredPackage */
            $autoloadMap[$requireName] ??= [$requiredPackage, $this->installationManager->getInstallPath($requiredPackage)];
            $autoloadMap = $this->collectDependencies($autoloadMap, $requiredPackage);
        }
        return $autoloadMap;
    }

}