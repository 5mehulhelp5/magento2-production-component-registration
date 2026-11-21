<?php
/**
 * Copyright (c) 2025 Volodymyr Hryvinskyi, All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\ProductionComponentRegistration;

use Composer\IO\IOInterface;
use Magento\Framework\Component\ComponentRegistrar;
use RuntimeException;

final class RegistrationGenerator
{
    public const REGISTRATION_GLOB_FILE = 'app/etc/registration_globlist.php';

    private string $projectRoot;
    private IOInterface $io;
    private ConfigurationManager $configurationManager;
    private ModuleConfigReader $moduleConfigReader;

    public function __construct(
        string $projectRoot,
        IOInterface $io,
        ConfigurationManager $configurationManager,
        ModuleConfigReader $moduleConfigReader
    ) {
        $this->projectRoot = $projectRoot;
        $this->io = $io;
        $this->configurationManager = $configurationManager;
        $this->moduleConfigReader = $moduleConfigReader;
    }

    public function generateOptimizedRegistration(): array
    {
        $registrationFile = $this->projectRoot . DIRECTORY_SEPARATOR . FileManager::REGISTRATION_FILE;
        $components = $this->discoverComponents();
        $filteredComponents = $this->filterComponents($components);

        $this->writeRegistrationFile($registrationFile, $filteredComponents['included']);

        return [
            'total' => count($filteredComponents['included']),
            'filtered' => count($filteredComponents['excluded'])
        ];
    }

    private function discoverComponents(): array
    {
        $components = [];
        $existingComponents = $this->getExistingComponents();

        foreach ($this->getRegistrationFiles() as $registrationFile) {
            $tempComponents = $this->extractComponentsFromFile($registrationFile, $existingComponents);
            array_push($components, ...$tempComponents);
        }

        return $components;
    }

    private function filterComponents(array $components): array
    {
        $included = [];
        $excluded = [];

        if (!$this->configurationManager->shouldFilterDisabledModules()) {
            return ['included' => $components, 'excluded' => []];
        }

        $enabledModules = $this->moduleConfigReader->getEnabledModules();

        foreach ($components as $component) {
            // Only filter modules, not themes or other component types
            if ($component['type'] === ComponentRegistrar::MODULE) {
                if (in_array($component['name'], $enabledModules, true)) {
                    $included[] = $component;
                } else {
                    $excluded[] = $component;
                }
            } else {
                // Include all non-module components (themes, etc.)
                $included[] = $component;
            }
        }

        if (count($excluded) > 0) {
            $this->io->write(sprintf(
                '<comment>Filtered out %d disabled modules</comment>',
                count($excluded)
            ));
        }

        return ['included' => $included, 'excluded' => $excluded];
    }

    private function getRegistrationFiles(): array
    {
        $globFile = $this->projectRoot . DIRECTORY_SEPARATOR . self::REGISTRATION_GLOB_FILE;

        if (!file_exists($globFile)) {
            return [];
        }

        $globList = include $globFile;
        $registrationFiles = [];

        foreach ($globList as $glob) {
            $files = glob($glob, GLOB_NOSORT) ?: [];
            array_push($registrationFiles, ...$files);
        }

        return $registrationFiles;
    }

    private function getExistingComponents(): array
    {
        $reflection = new \ReflectionClass(ComponentRegistrar::class);
        $pathsProperty = $reflection->getProperty('paths');
        if (PHP_VERSION_ID < 80100) {
            // Reflection reads a private property without this call from PHP 8.1 on, and PHP 8.5 deprecates it.
            $pathsProperty->setAccessible(true);
        }

        return $pathsProperty->getValue() ?? [];
    }

    private function extractComponentsFromFile(string $file, array $existingComponents): array
    {
        $components = [];

        // Capture current state
        $beforeComponents = $this->getExistingComponents();

        // Include the registration file
        include $file;

        // Get new state and find differences
        $afterComponents = $this->getExistingComponents();

        foreach ($afterComponents as $type => $typeComponents) {
            foreach ($typeComponents as $name => $path) {
                if (!isset($beforeComponents[$type][$name]) && !isset($existingComponents[$type][$name])) {
                    $components[] = [
                        'type' => $type,
                        'name' => $name,
                        'path' => $path
                    ];
                }
            }
        }

        return $components;
    }

    private function writeRegistrationFile(string $filePath, array $components): void
    {
        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * Auto-generated component registration file for production\n";
        $content .= " * Generated by Hryvinskyi Production Component Registration Plugin\n";
        $content .= " * Plugin link: https://github.com/hryvinskyi/magento2-production-component-registration\n";
        $content .= " * Generated at: " . date('Y-m-d H:i:s') . "\n";
        $content .= " */\n\n";
        $content .= "declare(strict_types=1);\n\n";

        foreach ($components as $component) {
            $registrationFile = $component['path'] . '/registration.php';
            if (file_exists($registrationFile)) {
                $relativePath = str_replace($this->projectRoot . DIRECTORY_SEPARATOR, '', $registrationFile);
                $content .= sprintf("require_once __DIR__ . '/../../%s';\n", $relativePath);
            }
        }

        if (file_put_contents($filePath, $content) === false) {
            throw new RuntimeException('Failed to write registration file');
        }
    }

    private function exportValue($value): string
    {
        return var_export($value, true);
    }
}
