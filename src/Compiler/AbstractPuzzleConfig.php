<?php
/**
 * @package Puzzle-DI
 * @copyright Copyright © 2015 Danny Smart
 */

namespace Lexide\PuzzleDI\Compiler;

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
