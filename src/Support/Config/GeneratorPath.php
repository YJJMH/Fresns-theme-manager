<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

namespace Fresns\ThemeManager\Support\Config;

class GeneratorPath
{
    private $inMulti;
    private $path;
    private $generate;
    private $namespace;

    public function __construct($config)
    {
        if (is_array($config)) {
            $this->inMulti = $config['in_multi'];
            $this->path = $config['path'];
            $this->generate = $config['generate'];
            $this->namespace = $config['namespace'] ?? $this->convertPathToNamespace($config['path']);

            return;
        }
        $this->path = $config;
        $this->generate = (bool) $config;
        $this->namespace = $config;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function generate(): bool
    {
        return $this->generate;
    }

    public function inMulti(): bool
    {
        return $this->inMulti && config('themes.multi', false);
    }

    public function getNamespace()
    {
        return $this->namespace;
    }

    private function convertPathToNamespace($path)
    {
        return str_replace('/', '\\', $path);
    }
}
