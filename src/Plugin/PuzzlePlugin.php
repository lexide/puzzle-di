<?php
/**
 * @package Puzzle-DI
 * @copyright Copyright © 2015 Danny Smart
 */

namespace Lexide\PuzzleDI\Plugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Lexide\PuzzleDI\Compiler\PuzzleClassCompiler;
use Lexide\PuzzleDI\Controller\ScriptController;
use Lexide\PuzzleDI\Exception\ConfigurationException;
use Lexide\PuzzleDI\Helper\PuzzleDataCollector;

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
     * {@inheritDoc}
     * @throws ConfigurationException
     */
    public function runPlugin(Event $event)
    {
        $this->buildScriptController($event->getComposer(), $event->getIO())->compileConfigList();
    }

    /**
     * {@inheritDoc}
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        // move along, nothing to see here
    }

    /**
     * {@inheritDoc}
     * @throws ConfigurationException
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
        $this->buildScriptController($composer, $io)->uninstall();
    }

    /**
     * @param Composer $composer
     * @param IOInterface $io
     * @return ScriptController
     */
    protected function buildScriptController(Composer $composer, IOInterface $io)
    {
        $dataCollector = new PuzzleDataCollector(
            $composer->getInstallationManager(),
            $composer->getRepositoryManager()->getLocalRepository()
        );
        $compiler = new PuzzleClassCompiler();

        return new ScriptController($compiler, $dataCollector, $composer->getPackage(), $io);
    }

} 
