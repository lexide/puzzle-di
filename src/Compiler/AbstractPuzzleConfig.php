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

    public static function getConfigPaths($key) {
        if (empty(self::$configList[$key])) {
            // return an empty array
            return [];
        }
        return self::$configList[$key];
    }

} 