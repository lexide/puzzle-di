<?php
/**
 * @package Puzzle-DI
 * @copyright Copyright © 2015 Danny Smart
 */

namespace Lexide\PuzzleDI\Compiler;

abstract class AbstractPuzzleConfig
{

    protected static array $configList = [];

    /**
     * @param string $key
     * @return array
     */
    public static function getConfigItems(string $key): array
    {
        if (empty(static::$configList[$key])) {
            // return an empty array
            return [];
        }
        return static::$configList[$key];
    }

} 
