<?php
/**
 * Silktide Nibbler. Copyright 2013-2014 Silktide Ltd. All Rights Reserved.
 */
namespace Downsider\PuzzleDI\Compiler;

/**
 *
 */
abstract class AbstractPuzzleConfig
{

    protected static $configList = [];

    public static function getConfigPaths($key) {
        if (empty(static::$configList[$key])) {
            // return an empty array
            return [];
        }
        return static::$configList[$key];
    }

} 