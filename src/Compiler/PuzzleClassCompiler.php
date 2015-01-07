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

        $configList = [];
        $aliasList = "";
        foreach ($data as $key => $configs) {
            $keyConfigs = [];
            $keyAliases = [];
            foreach ($configs as $config) {
                if (!isset($config["path"])) {
                    throw new ConfigurationException("There was no file path for the key '$key'");
                }
                if (!is_file($config["path"]) || !is_readable($config["path"])) {
                    throw new ConfigurationException("The path '{$config["path"]}'' does not exist or is not readable");
                }

                $keyConfigs[] = "
            '{$config["path"]}'";

                if (isset($config["alias"])) {
                    $keyAliases[] = "
            '{$config["name"]} => '{$config["alias"]}'";
                }
            }

            $configList[] = "
        '$key' => [
            " . implode(",\n", $keyConfigs) . "
        ]";

            $aliasList[] = "
        '$key' => [
            " . implode(",\n", $keyAliases) . "
        ]";

        }
        $configList = implode(",\n", $configList);
        $aliasList = implode(",\n", $aliasList);




        $classSource = <<<SOURCE
<?php
/**
 * Puzzle Config - Auto-generated by PuzzleDI via composer
 */
$appNamespace

use Downsider\PuzzleDI\Compiler\AbstractPuzzleConfig;

class PuzzleConfig extends AbstractPuzzleConfig
{

    protected static \$configList = array(
$configList;
    );

    protected static \$aliasList = array(
$aliasList
    );

}

SOURCE;

        file_put_contents($appSourceDir . "/PuzzleConfig.php", $classSource);
    }

} 