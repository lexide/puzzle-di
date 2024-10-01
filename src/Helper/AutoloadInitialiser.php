<?php

namespace Lexide\PuzzleDI\Helper;

use Composer\Autoload\AutoloadGenerator;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Repository\RepositoryManager;
use Lexide\PuzzleDI\Exception\InitialisationException;

class AutoloadInitialiser
{

    const IGNORED_DEPENDENCIES = [
        "php" => true,
        "composer-plugin-api" => true
    ];

    protected RepositoryManager $repositoryManager;
    protected InstallationManager $installationManager;
    protected AutoloadGenerator $generator;
    protected PackageInterface $rootPackage;
    protected string $vendorDirectory;

    /**
     * @param RepositoryManager $repositoryManager
     * @param InstallationManager $installationManager
     * @param AutoloadGenerator $generator
     * @param string $vendorDirectory
     */
    public function __construct(
        RepositoryManager $repositoryManager,
        InstallationManager $installationManager,
        AutoloadGenerator $generator,
        string $vendorDirectory
    ) {
        $this->repositoryManager = $repositoryManager;
        $this->installationManager = $installationManager;
        $this->generator = $generator;
        $this->vendorDirectory = $vendorDirectory;
    }

    /**
     * @param PackageInterface $rootPackage
     * @param array $puzzleData
     * @throws InitialisationException
     */
    public function initAutoloaderIfRequired(PackageInterface $rootPackage, array $puzzleData): void
    {
        // parse puzzleData for "class" attributes
        $classPackages = [];
        foreach ($puzzleData as $packageData) {
            foreach ($packageData as $packageConfig) {
                if (!empty($packageConfig["class"])) {
                    $classPackages[$packageConfig["name"]] = true;
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
            $package = $this->findInstalledPackage($packageName);
            if (empty($package)) {
                throw new InitialisationException("Couldn't find installed version of '$packageName', from Puzzle composer config");
            }

            $autoloadMap[$packageName] = [$package, $this->installationManager->getInstallPath($package)];
            $autoloadMap = $this->collectDependencies($autoloadMap, $package, $packageName);
        }

        $map = $this->generator->parseAutoloads($autoloadMap, $rootPackage);
        $loader = $this->generator->createLoader($map, $this->vendorDirectory);
        $loader->register();

    }

    /**
     * @param array $autoloadMap
     * @param PackageInterface $package
     * @param string $parentPackageName
     * @return array
     * @throws InitialisationException
     */
    protected function collectDependencies(array $autoloadMap, PackageInterface $package, string $parentPackageName): array
    {
        $requires = $package->getRequires();

        foreach ($requires as $requiredLink) {
            $targetName = $requiredLink->getTarget();
            if (isset(self::IGNORED_DEPENDENCIES[$targetName]) || str_starts_with($targetName, "ext")) {
                continue;
            }
            $requiredPackage = $this->findInstalledPackage($targetName);
            if (empty($requiredPackage)) {
                throw new InitialisationException("Can't find package '$targetName', required by '$parentPackageName'");
            }

            $autoloadMap[$targetName] ??= [$requiredPackage, $this->installationManager->getInstallPath($requiredPackage)];
            $autoloadMap = $this->collectDependencies($autoloadMap, $requiredPackage, $parentPackageName);
        }
        return $autoloadMap;
    }

    /**
     * @param string $packageName
     * @return ?PackageInterface
     */
    protected function findInstalledPackage(string $packageName): ?PackageInterface
    {
        $packages = $this->repositoryManager->findPackages($packageName, "*");
        $repo = $this->repositoryManager->getLocalRepository();
        foreach ($packages as $package) {
            if ($this->installationManager->isPackageInstalled($repo, $package)) {
                return $package;
            }
        }
        return null;
    }

}