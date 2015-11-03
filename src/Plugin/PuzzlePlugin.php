<?php
/**
 * @package Puzzle-DI
 * @copyright Copyright © 2015 Danny Smart
 */

namespace Downsider\PuzzleDI\Plugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Downsider\PuzzleDI\Controller\ScriptController;

class PuzzlePlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => ['runPlugin'],
            ScriptEvents::POST_UPDATE_CMD => ['runPlugin']
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        // move along, nothing to see here
    }

    /**
     * @param Event $event
     */
    public function runPlugin(Event $event)
    {
        $controller = new ScriptController();
        $controller->compileConfigList($event);
    }

} 