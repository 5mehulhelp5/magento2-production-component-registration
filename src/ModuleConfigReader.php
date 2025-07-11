<?php
/**
 * Copyright (c) 2025. MageCloud.  All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\ProductionComponentRegistration;

use Composer\IO\IOInterface;

final class ModuleConfigReader
{
    public const CONFIG_FILE = 'app/etc/config.php';

    private string $projectRoot;
    private IOInterface $io;
    private array $cache = [];

    public function __construct(string $projectRoot, IOInterface $io)
    {
        $this->projectRoot = $projectRoot;
        $this->io = $io;
    }

    public function getEnabledModules(): array
    {
        if (isset($this->cache['enabled_modules'])) {
            return $this->cache['enabled_modules'];
        }

        $configFile = $this->projectRoot . DIRECTORY_SEPARATOR . self::CONFIG_FILE;

        if (!file_exists($configFile)) {
            $this->io->write('<warning>config.php file not found, including all modules</warning>');
            $this->cache['enabled_modules'] = [];
            return $this->cache['enabled_modules'];
        }

        try {
            $config = include $configFile;
            $modules = $config['modules'] ?? [];

            // Filter only enabled modules (value = 1)
            $enabledModules = array_filter($modules, function($enabled) {
                return $enabled === 1;
            });

            $this->cache['enabled_modules'] = array_keys($enabledModules);

            $this->io->write(sprintf(
                '<info>Found %d enabled modules in config.php</info>',
                count($this->cache['enabled_modules'])
            ));

            return $this->cache['enabled_modules'];

        } catch (\Throwable $e) {
            $this->io->writeError('<error>Failed to read config.php: ' . $e->getMessage() . '</error>');
            $this->cache['enabled_modules'] = [];
            return $this->cache['enabled_modules'];
        }
    }

    public function isModuleEnabled(string $moduleName): bool
    {
        $enabledModules = $this->getEnabledModules();
        return in_array($moduleName, $enabledModules, true);
    }

    public function shouldFilterDisabledModules(): bool
    {
        $configFile = $this->projectRoot . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
        return file_exists($configFile);
    }
}
