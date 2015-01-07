<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Downsider\PuzzleDI\Compiler;
use Downsider\PuzzleDI\Exception\ConfigurationException;

/**
 *
 */
class PuzzleClassCompiler 
{

    public function compile(array $data, $appNamespace, $appSourceDir)
    {
        if (!empty($appNamespace)) {
            // trim any trailing slashes
            $appNamespace = rtrim($appNamespace, "\\");
            $appNamespace = "\nnamespace $appNamespace;";
        }

        $configList = array();
        foreach ($data as $key => $configs) {
            $keyConfigs = array();
            foreach ($configs as $config) {
                // validate path
                if (!isset($config["path"])) {
                    throw new ConfigurationException("There was no file path for the key '$key'");
                }
                if (!is_file($config["path"]) || !is_readable($config["path"])) {
                    throw new ConfigurationException("The path '{$config["path"]}'' does not exist or is not readable");
                }
                if (empty($config["name"])) {
                    throw new ConfigurationException("There is no name associated with the path '{$config["path"]}'");
                }
                $configKey = isset($config["alias"])? $config["alias"]: str_replace("/", "_", $config["name"]);
                $keyConfigs[] = "
            '$configKey' => '{$config["path"]}'";
            }

            $configList[] = "
        '$key' => array(" . implode(",", $keyConfigs) . "
        )";

        }
        $configList = implode(",", $configList);




        $classSource = <<<SOURCE
<?php
/**
 * Puzzle Config - Auto-generated by PuzzleDI via composer
 */
$appNamespace

use Downsider\PuzzleDI\Compiler\AbstractPuzzleConfig;

class PuzzleConfig extends AbstractPuzzleConfig
{

    protected static \$configList = array($configList;
    );

}

SOURCE;

        file_put_contents($appSourceDir . "/PuzzleConfig.php", $classSource);
    }

} 