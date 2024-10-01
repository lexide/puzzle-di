<?php

namespace Lexide\PuzzleDI\Helper;

use Composer\Installer\InstallationManager;
use Composer\Repository\RepositoryInterface;
use Composer\Package\Package;
use Lexide\PuzzleDI\Exception\ConfigurationException;

class PuzzleDataCollector
{

    protected InstallationManager $installationManager;
    protected RepositoryInterface $repo;

    /**
     * @param InstallationManager $installationManager
     * @param RepositoryInterface $repo
     */
    public function __construct(InstallationManager $installationManager, RepositoryInterface $repo)
    {
        $this->installationManager = $installationManager;
        $this->repo = $repo;
    }

    /**
     * @param array $whitelist
     * @return array
     * @throws ConfigurationException
     */
    public function collectData(array $whitelist): array
    {
        $puzzleData = [];
        $whitelistChain = [];
        foreach ($this->repo->getPackages() as $package) {
            /** @var Package $package */
            $extra = $package->getExtra();
            $packageName = $package->getName();
            $configKey = "lexide/puzzle-di";

            if (!empty($extra[$configKey]) && is_array($extra[$configKey])) {
                $repoConfig = $extra[$configKey];

                // prepare the whitelist chain
                if (!empty($repoConfig["whitelist"]) && is_array($repoConfig["whitelist"])) {
                    $whitelistChain[$packageName] = $repoConfig["whitelist"];
                }

                // if we don't have any files, we're done now
                if (empty($repoConfig["files"])) {
                    continue;
                }

                foreach ($repoConfig["files"] as $targetLibrary => $config) {
                    // ignore numeric keys
                    if ($targetLibrary == (string)(int)$targetLibrary) {
                        continue;
                    }

                    $puzzleConfig = [
                        "name" => $packageName,
                    ];

                    if (!empty($config["class"])) {
                        $puzzleConfig["class"] = $config["class"];
                    } elseif (!empty($config["path"])) {
                        $puzzleConfig["path"] = $this->installationManager->getInstallPath($package) . "/" . $config["path"];
                        $puzzleConfig["alias"] = str_replace("/", "_", $config["name"]);
                    }

                    if (!empty($config["alias"])) {
                        $puzzleConfig["alias"] = $config["alias"];
                    }

                    if (!array_key_exists($targetLibrary, $puzzleData)) {
                        $puzzleData[$targetLibrary] = [];
                    }
                    $puzzleData[$targetLibrary][$packageName] = $puzzleConfig;
                }
            }
        }

        // build complete list of white listed packages
        $whitelist = $this->flattenWhitelistChain($whitelistChain, $whitelist);

        // filter for whitelisted packages
        foreach ($puzzleData as $target => $files) {
            $whitelistedFiles = [];
            if (!empty($whitelist[$target])) {
                $whitelistedFiles = array_intersect_key($files, array_flip($whitelist[$target]));
            }

            // remove data for targets that don't have any whitelisted packages
            if (empty($whitelistedFiles)) {
                unset($puzzleData[$target]);
                continue;
            }

            $puzzleData[$target] = array_values($whitelistedFiles);
        }

        return $puzzleData;
    }

    /**
     * @param array $whitelistChain
     * @param array $whitelistedPackages
     * @return array
     * @throws ConfigurationException
     */
    protected function flattenWhitelistChain(array $whitelistChain, array $whitelistedPackages)
    {
        $whitelist = $whitelistedPackages;
        foreach ($whitelistedPackages as $targetLibrary => $libraryWhitelist) {

            if (!is_array($libraryWhitelist)) {
                throw new ConfigurationException("The whitelist for library '$targetLibrary' is not an array. Found '" . (string) $libraryWhitelist . "'");
            }

            foreach ($libraryWhitelist as $package) {
                if (!empty($whitelistChain[$package][$targetLibrary])) {
                    // merge the whitelist with any from further down the chain
                    $whitelist = array_merge_recursive(
                        $whitelist,
                        $this->flattenWhitelistChain(
                            $whitelistChain,
                            // only pass through the whitelist for the target library
                            [$targetLibrary => $whitelistChain[$package][$targetLibrary]]
                        )
                    );
                }
            }
        }
        return $whitelist;
    }

} 
