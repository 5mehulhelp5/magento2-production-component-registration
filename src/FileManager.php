<?php
/**
 * Copyright (c) 2025 Volodymyr Hryvinskyi, All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\ProductionComponentRegistration;

use Composer\IO\IOInterface;
use RuntimeException;

final class FileManager
{
    public const REGISTRATION_FILE = 'app/etc/NonComposerComponentRegistration.php';
    public const BACKUP_FILE = 'app/etc/NonComposerComponentRegistration.php.backup';
    public const MAGENTO_BASE_FILE = 'vendor/magento/magento2-base/app/etc/NonComposerComponentRegistration.php';

    private string $projectRoot;
    private IOInterface $io;

    public function __construct(string $projectRoot, IOInterface $io)
    {
        $this->projectRoot = $projectRoot;
        $this->io = $io;
    }

    public function createBackupIfNeeded(): void
    {
        $registrationFile = $this->getRegistrationFilePath();
        $backupFile = $this->getBackupFilePath();

        if (file_exists($registrationFile) && !file_exists($backupFile)) {
            if (copy($registrationFile, $backupFile)) {
                $this->io->write('<comment>Created backup of existing registration file</comment>');
            } else {
                throw new RuntimeException('Failed to create backup file');
            }
        }
    }

    public function restoreOriginalRegistration(): bool
    {
        $registrationFile = $this->getRegistrationFilePath();
        $backupFile = $this->getBackupFilePath();
        $magentoBaseFile = $this->getMagentoBaseFilePath();

        // Remove existing generated file first
        if (file_exists($registrationFile)) {
            unlink($registrationFile);
        }

        // Try to restore from backup first
        if (file_exists($backupFile)) {
            if (copy($backupFile, $registrationFile)) {
                $this->io->write('<comment>Restored from backup file</comment>');
                return true;
            }
        }

        // Fallback to Magento base file
        if (file_exists($magentoBaseFile)) {
            if (copy($magentoBaseFile, $registrationFile)) {
                $this->io->write('<comment>Restored from Magento base package</comment>');
                return true;
            }
        }

        return false;
    }

    public function restoreFromBackup(): void
    {
        $registrationFile = $this->getRegistrationFilePath();
        $backupFile = $this->getBackupFilePath();

        if (file_exists($backupFile)) {
            if (file_exists($registrationFile)) {
                unlink($registrationFile);
            }
            rename($backupFile, $registrationFile);
        }
    }

    private function getRegistrationFilePath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . self::REGISTRATION_FILE;
    }

    private function getBackupFilePath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . self::BACKUP_FILE;
    }

    private function getMagentoBaseFilePath(): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . self::MAGENTO_BASE_FILE;
    }
}
