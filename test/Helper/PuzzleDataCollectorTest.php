<?php

namespace Lexide\PuzzleDI\Test\Helper;

use Composer\Installer\InstallationManager;
use Composer\Package\Package;
use Composer\Repository\RepositoryInterface;
use Lexide\PuzzleDI\Helper\PuzzleDataCollector;
use Mockery\Mock;

class PuzzleDataCollectorTest extends \PHPUnit_Framework_TestCase
{

    protected $rootDir = "root";

    protected $target = "target/lib";

    protected $secondTarget = "second/lib";

    /**
     * @dataProvider extraProvider
     *
     * @param $composerConfig
     * @param $whitelist
     * @param $expectedData
     */
    public function testWhitelists($composerConfig, $whitelist, $expectedData)
    {

        /** @var Package|Mock $package */
        $package = \Mockery::mock(Package::class);
        $package->shouldReceive("getName")->andReturnValues(array_keys($composerConfig));
        $package->shouldReceive("getExtra")->andReturnUsing(function () use (&$composerConfig) {
            $value = current($composerConfig);
            next($composerConfig);
            return [
                "lexide/puzzle-di" => $value
            ];
        });

        /** @var RepositoryInterface|Mock $repo */
        $repo = \Mockery::mock(RepositoryInterface::class);
        $repo->shouldReceive("getPackages")->andReturn(array_fill(0, count($composerConfig), $package));

        /** @var InstallationManager|Mock $installationManager */
        $installationManager = \Mockery::mock(InstallationManager::class)->shouldIgnoreMissing($this->rootDir);

        $collector = new PuzzleDataCollector($installationManager);

        $this->assertArraySubset($expectedData, $collector->collectData($repo, $whitelist));

    }

    public function extraProvider()
    {
        return [
            [ #0 no whitelist, no data
                [
                    "one" => [
                        "files" => $this->createFilesArray("path1"),
                        "whitelist" => $this->createWhitelist(["two"])
                    ],
                    "two" => [
                        "files" => $this->createFilesArray("path2")
                    ]
                ],
                [], // empty whitelist
                []
            ],
            [ #1 whitelist, single level
                [
                    "one" => [
                        "files" => $this->createFilesArray("path1"),
                        "whitelist" => $this->createWhitelist(["two"])
                    ],
                    "two" => [
                        "files" => $this->createFilesArray("path2")
                    ]
                ],
                $this->createWhitelist(["two"]),
                $this->createExpectedArray(["two" => "path2"])
            ],
            [ #2 Chained whitelist
                [
                    "one" => [
                        "files" => $this->createFilesArray("path1"),
                        "whitelist" => $this->createWhitelist(["two"])
                    ],
                    "two" => [
                        "files" => $this->createFilesArray("path2")
                    ]
                ],
                $this->createWhitelist(["one"]),
                $this->createExpectedArray([
                    "one" => "path1",
                    "two" => "path2"
                ])
            ],
            [ #3 complex chained whitelist
                [
                    "a" => [
                        "files" => $this->createFilesArray("pathA"),
                        "whitelist" => []
                    ],
                    "b" => [
                        "files" => $this->createFilesArray("pathB"),
                        "whitelist" => $this->createWhitelist(["c", "d", "e"])
                    ],
                    "c" => [
                        "files" => $this->createFilesArray("pathC"),
                        "whitelist" => $this->createWhitelist(["e"])
                    ],
                    "d" => [
                        "files" => $this->createFilesArray("pathD"),
                        "whitelist" => $this->createWhitelist(["g", "h"])
                    ],
                    "e" => [
                        "files" => $this->createFilesArray("pathE"),
                        "whitelist" => $this->createWhitelist(["i"])
                    ],
                    "f" => [
                        "files" => $this->createFilesArray("pathF"),
                        "whitelist" => []
                    ],
                    "g" => [
                        "files" => $this->createFilesArray("pathG"),
                        "whitelist" => $this->createWhitelist(["i"])
                    ],
                    "h" => [
                        "files" => $this->createFilesArray("pathH"),
                        "whitelist" => []
                    ],
                    "i" => [
                        "files" => $this->createFilesArray("pathI"),
                        "whitelist" => []
                    ],
                    "j" => [
                        "files" => $this->createFilesArray("pathJ"),
                        "whitelist" => $this->createWhitelist(["k"])
                    ],
                    "k" => [
                        "files" => $this->createFilesArray("pathK"),
                        "whitelist" => []
                    ]
                ],
                $this->createWhitelist(["a", "b"]),
                $this->createExpectedArray([
                    "a" => "pathA",
                    "b" => "pathB",
                    "c" => "pathC",
                    "d" => "pathD",
                    "e" => "pathE",
                    "g" => "pathG",
                    "h" => "pathH",
                    "i" => "pathI"
                ])
            ],
            [ #4 multiple targets, only one selected (first level)
                [
                    "one" => [
                        "files" => array_merge_recursive(
                            $this->createFilesArray("path1"),
                            $this->createFilesArray("path3", $this->secondTarget)
                        ),
                        "whitelist" => $this->createWhitelist(["two"])
                    ],
                    "two" => [
                        "files" => $this->createFilesArray("path2")
                    ]
                ],
                $this->createWhitelist(["one"]),
                $this->createExpectedArray([
                    "one" => "path1",
                    "two" => "path2"
                ])
            ],
            [ #5 multiple targets, only one selected (through the chain)
                [
                    "one" => [
                        "files" => array_merge_recursive(
                            $this->createFilesArray("path1")
                        ),
                        "whitelist" => $this->createWhitelist(["two"])
                    ],
                    "two" => [
                        "files" => array_merge_recursive(
                            $this->createFilesArray("path2"),
                            $this->createFilesArray("path3", $this->secondTarget)
                        )
                    ]
                ],
                $this->createWhitelist(["one"]),
                $this->createExpectedArray([
                    "one" => "path1",
                    "two" => "path2"
                ])
            ],
            [ #6 multiple targets, both selected
                [
                    "one" => [
                        "files" => array_merge_recursive(
                            $this->createFilesArray("path1"),
                            $this->createFilesArray("path3", $this->secondTarget)
                        ),
                        "whitelist" => array_merge_recursive(
                            $this->createWhitelist(["two"]),
                            $this->createWhitelist(["two"], $this->secondTarget)
                        )
                    ],
                    "two" => [
                        "files" => array_merge_recursive(
                            $this->createFilesArray("path2"),
                            $this->createFilesArray("path4", $this->secondTarget)
                        )
                    ]
                ],
                array_merge_recursive(
                    $this->createWhitelist(["one"]),
                    $this->createWhitelist(["one"], $this->secondTarget)
                ),
                array_merge_recursive(
                    $this->createExpectedArray([
                        "one" => "path1",
                        "two" => "path2"
                    ]),
                    $this->createExpectedArray([
                        "one" => "path3",
                        "two" => "path4"
                    ], $this->secondTarget)
                )
            ],
            [ #7 targets don't bleed through
                [
                    "one" => [
                        "files" => array_merge_recursive(
                            $this->createFilesArray("path1")
                        ),
                        "whitelist" => array_merge_recursive(
                            $this->createWhitelist(["two"]),
                            $this->createWhitelist(["two"], $this->secondTarget)
                        )
                    ],
                    "two" => [
                        "files" => array_merge_recursive(
                            $this->createFilesArray("path2"),
                            $this->createFilesArray("path3", $this->secondTarget)
                        ),
                        "whitelist" => array_merge_recursive(
                            $this->createWhitelist(["three"])
                        )
                    ],
                    "three" => [
                        "files" => array_merge_recursive(
                            $this->createFilesArray("path4"),
                            $this->createFilesArray("path5", $this->secondTarget)
                        )
                    ]
                ],
                array_merge_recursive(
                    $this->createWhitelist(["one"]),
                    $this->createWhitelist(["one"], $this->secondTarget)
                ),
                array_merge_recursive(
                    $this->createExpectedArray([
                        "one" => "path1",
                        "two" => "path2",
                        "three" => "path4"
                    ]),
                    $this->createExpectedArray([
                        "two" => "path3"
                    ], $this->secondTarget)
                )
            ]
        ];
    }

    protected function createFilesArray($path, $target = null)
    {
        if (empty($target)) {
            $target = $this->target;
        }
        return [
            $target => [
                "path" => $path
            ]
        ];

    }

    protected function createWhitelist($whitelist, $target = null)
    {
        if (empty($target)) {
            $target = $this->target;
        }
        return [
            $target => $whitelist
        ];
    }

    protected function createExpectedArray(array $files, $target = null)
    {
        if (empty($target)) {
            $target = $this->target;
        }

        $targetFiles = [];
        foreach ($files as $package => $path) {
            $targetFiles[] = [
                "name" => $package,
                "path" => $this->rootDir . "/$path"
            ];
        }
        return [
            $target => $targetFiles
        ];
    }

}
