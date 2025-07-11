<?php
/**
 * Copyright (c) 2025 Volodymyr Hryvinskyi, All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\ProductionComponentRegistration;

final class EnvironmentDetector
{
    public const PRODUCTION_MODES = ['production'];
    public const ENVIRONMENT_VARIABLES = ['MAGE_MODE', 'MAGENTO_MODE'];

    private string $projectRoot;
    private array $cache = [];

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
    }

    public function isProduction(): bool
    {
        if (isset($this->cache['is_production'])) {
            return $this->cache['is_production'];
        }

        $this->cache['is_production'] = $this->detectProductionEnvironment();
        return $this->cache['is_production'];
    }

    private function detectProductionEnvironment(): bool
    {
        // Priority 1: Check Magento's env.php configuration
        if ($this->checkMagentoEnvFile()) {
            return true;
        }

        // Priority 2: Check environment variables
        if ($this->checkEnvironmentVariables()) {
            return true;
        }

        return false;
    }

    private function checkMagentoEnvFile(): bool
    {
        $envFile = $this->projectRoot . DIRECTORY_SEPARATOR . 'app/etc/env.php';

        if (!file_exists($envFile)) {
            return false;
        }

        try {
            $config = include $envFile;

            if (isset($config['MAGE_MODE'])) {
                return in_array(strtolower($config['MAGE_MODE']), self::PRODUCTION_MODES, true);
            }
        } catch (\Throwable $e) {
            // Ignore errors in env.php parsing
        }

        return false;
    }

    private function checkEnvironmentVariables(): bool
    {
        foreach (self::ENVIRONMENT_VARIABLES as $envVar) {
            $value = getenv($envVar);
            if ($value && in_array(strtolower($value), self::PRODUCTION_MODES, true)) {
                return true;
            }
        }

        return false;
    }
}
