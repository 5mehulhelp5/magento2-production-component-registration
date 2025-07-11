<?php
/**
 * Copyright (c) 2025. MageCloud.  All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\ProductionComponentRegistration;

use Composer\Composer;

final class ConfigurationManager
{
    private const CONFIG_KEY = 'hryvinskyi-production-component-registration';
    private const FILTER_DISABLED_MODULES_KEY = 'filter-disabled-modules';

    private Composer $composer;

    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
    }

    public function shouldFilterDisabledModules(): bool
    {
        $extra = $this->composer->getPackage()->getExtra();
        $config = $extra[self::CONFIG_KEY] ?? [];

        return (bool) ($config[self::FILTER_DISABLED_MODULES_KEY] ?? false);
    }

    public function getConfiguration(): array
    {
        $extra = $this->composer->getPackage()->getExtra();
        return $extra[self::CONFIG_KEY] ?? [];
    }
}
