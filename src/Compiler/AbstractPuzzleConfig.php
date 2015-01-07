<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Downsider\PuzzleDI\Compiler;
use Downsider\PuzzleDI\Exception\ConfigurationException;

/**
 *
 */
abstract class AbstractPuzzleConfig
{

    protected static $configList = [];
    protected static $aliasList = [];

    public static function getConfigPaths($key) {
        if (empty(self::$configList[$key])) {
            // return an empty array
            return [];
        }
        return self::$configList[$key];
    }

    public static function getConfigAlias($key, $package)
    {
        if (empty(self::$aliasList[$key][$package])) {
            throw new ConfigurationException("There is not alias for the package '$package'");
        }
        return self::$aliasList[$key][$package];
    }

} 