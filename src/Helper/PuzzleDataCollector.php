<?php
/**
 * @package Puzzle-DI
 * @copyright Copyright © 2015 Danny Smart
 */

namespace Lexide\PuzzleDI\Helper;

use Composer\Installer\InstallationManager;
use Composer\Repository\RepositoryInterface;
use Composer\Package\Package;
use Lexide\PuzzleDI\Exception\ConfigurationException;

class PuzzleDataCollector
{

    protected $installationManager;

    /**
     * PuzzleDataCollector constructor.
     * @param InstallationManager $installationManager
     */
    public function __construct(InstallationManager $installationManager)
    {
        $this->installationManager = $installationManager;
    }

    /**
     * @param RepositoryInterface $repo
     * @param array $whitelist
     * @return array
     * @throws ConfigurationException
     */
    public function collectData(RepositoryInterface $repo, array $whitelist)
    {
        $puzzleData = [];
        $whitelistChain = [];
        foreach ($repo->getPackages() as $package) {
            /** @var Package $package */
            $extra = $package->getExtra();
            $packageName = $package->getName();
            $puzzleConfigKeys = [
                "downsider-puzzle-di",
                "lexide/puzzle-di"
            ];
            foreach ($puzzleConfigKeys as $configKey) {
                if (!empty($extra[$configKey]) && is_array($extra[$configKey])) {
                    $repoConfig = $extra[$configKey];

                    // handle version 1.* config formats
                    if (!isset($repoConfig["files"]) && !isset($repoConfig["whitelist"])) {
                        $repoConfig = ["files" => $repoConfig];
                    }

                    // prepare the whitelist chain
                    if (!empty($repoConfig["whitelist"]) && is_array($repoConfig["whitelist"])) {
                        if (empty($whitelistChain[$packageName])) {
                            $whitelistChain[$packageName] = [];
                        }
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
                            "path" => $this->installationManager->getInstallPath($package) . "/" . $config["path"]
                        ];
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
        }

        // build complete list of white listed packages
        $whitelist = $this->flattenWhitelistChain($whitelistChain, $whitelist);

        // filter for whitelisted packages
        foreach ($puzzleData as $target => $files) {
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
