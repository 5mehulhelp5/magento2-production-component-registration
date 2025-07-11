<?php
/**
 * Copyright (c) 2025 Volodymyr Hryvinskyi, All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */
declare(strict_types=1);

namespace Hryvinskyi\ProductionComponentRegistration;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use RuntimeException;
use Throwable;

/**
 * Composer Plugin for Magento 2 Production Component Registration 
 */
final class ProductionRegistrationPlugin implements PluginInterface, EventSubscriberInterface
{
    private IOInterface $io;
    private string $projectRoot;
    private EnvironmentDetector $environmentDetector;
    private FileManager $fileManager;
    private RegistrationGenerator $registrationGenerator;
    private ConfigurationManager $configurationManager;
    private ModuleConfigReader $moduleConfigReader;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
        $this->projectRoot = getcwd() ?: throw new RuntimeException('Unable to determine project root');
        $this->environmentDetector = new EnvironmentDetector($this->projectRoot);
        $this->fileManager = new FileManager($this->projectRoot, $io);
        $this->configurationManager = new ConfigurationManager($composer);
        $this->moduleConfigReader = new ModuleConfigReader($this->projectRoot, $io);
        $this->registrationGenerator = new RegistrationGenerator(
            $this->projectRoot,
            $io,
            $this->configurationManager,
            $this->moduleConfigReader
        );
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Plugin cleanup if needed
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $fileManager = new FileManager(getcwd() ?: '', $io);
        $fileManager->restoreFromBackup();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'handleAutoloadDump'
        ];
    }

    public function handleAutoloadDump(Event $event): void
    {
        try {
            $this->io->write('<info>Production Component Registration Plugin</info>');
            
            if ($this->environmentDetector->isProduction()) {
                $this->handleProductionMode();
            } else {
                $this->handleNonProductionMode();
            }
        } catch (Throwable $e) {
            $this->io->writeError('<error>Plugin execution failed: ' . $e->getMessage() . '</error>');
            $this->io->writeError('<error>Stack trace: ' . $e->getTraceAsString() . '</error>');
        }
    }

    private function handleProductionMode(): void
    {
        $this->io->write('<info>Production mode detected - generating optimized registration file</info>');
        
        if ($this->configurationManager->shouldFilterDisabledModules()) {
            $this->io->write('<info>Module filtering enabled - only including enabled modules</info>');
        }

        $this->fileManager->createBackupIfNeeded();
        $result = $this->registrationGenerator->generateOptimizedRegistration();

        $this->io->write(sprintf(
            '<info>Generated optimized registration file with %d components</info>',
            $result['total']
        ));

        if ($result['filtered'] > 0) {
            $this->io->write(sprintf(
                '<comment>Filtered out %d disabled modules</comment>',
                $result['filtered']
            ));
        }

        $this->io->write('<comment>Performance optimization active for production environment</comment>');
    }

    private function handleNonProductionMode(): void
    {
        $this->io->write('<info>Non-production mode detected - restoring standard registration</info>');
        
        $restored = $this->fileManager->restoreOriginalRegistration();
        
        if ($restored) {
            $this->io->write('<info>Standard registration file restored for development</info>');
        } else {
            $this->io->write('<warning>No restoration needed - using default registration</warning>');
        }
    }
}
