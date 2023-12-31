<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

namespace Fresns\ThemeManager\Commands\Traits;

use Fresns\ThemeManager\Support\Config\GenerateConfigReader;
use Fresns\ThemeManager\Support\Json;
use Fresns\ThemeManager\Support\Stub;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait StubTrait
{
    protected $runningAsRootDir = false;
    protected $buildClassName = null;

    protected function buildClass($fskey)
    {
        $this->runningAsRootDir = false;
        if (str_starts_with($fskey, 'App')) {
            $this->runningAsRootDir = true;
            $this->buildClassName = $fskey;
        }

        $content = $this->getStubContents($this->getStub());

        return $content;
    }

    protected function getPath($fskey)
    {
        $path = parent::getPath($fskey);

        $this->type = $path;

        return $path;
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace;
    }

    protected function getStubName(): ?string
    {
        return null;
    }

    /**
     * implement from \Illuminate\Console\GeneratorCommand.
     *
     * @return string
     *
     * @see \Illuminate\Console\GeneratorCommand
     */
    protected function getStub(): string
    {
        $stubName = $this->getStubName();
        if (! $stubName) {
            throw new \RuntimeException('Please provider stub fskey in getStubName method');
        }

        $baseStubPath = base_path("stubs/{$stubName}.stub");
        if (file_exists($baseStubPath)) {
            return $baseStubPath;
        }

        $stubPath = dirname(__DIR__)."/stubs/{$stubName}.stub";
        if (file_exists($stubPath)) {
            return $stubPath;
        }

        throw new \RuntimeException("stub path does not exists: {$stubPath}");
    }

    /**
     * Get class name.
     *
     * @return string
     */
    public function getClass()
    {
        return class_basename($this->argument('fskey'));
    }

    /**
     * Get the contents of the specified stub file by given stub fskey.
     *
     * @param  $stub
     * @return string
     */
    protected function getStubContents($stubPath)
    {
        $method = sprintf('get%sStubPath', Str::studly(strtolower($stubPath)));

        // custom stubPath
        if (method_exists($this, $method)) {
            $stubFilePath = $this->$method();
        } else {
            // run in command: fresns new Xxx
            $stubFilePath = dirname(__DIR__)."/stubs/{$stubPath}.stub";

            if (file_exists($stubFilePath)) {
                $stubFilePath = $stubFilePath;
            }
            // run in command: fresns make:xxx
            else {
                $stubFilePath = $stubPath;
            }
        }

        $mimeType = File::mimeType($stubFilePath);
        if (str_contains($mimeType, 'application/')) {
            $stubFile = new Stub($stubFilePath, $this->getReplacement($stubFilePath));
            $content = $stubFile->render();
        } else {
            $content = File::get($stubFilePath);
        }

        // format json style
        if (str_contains($stubPath, 'json')) {
            $content = Json::make()->decode($content)->encode();

            return $content;
        }

        return $content;
    }

    public function getReplaceKeys($content)
    {
        preg_match_all('/(\$[^\s.]*?\$)/', $content, $matches);

        $keys = $matches[1] ?? [];

        return $keys;
    }

    public function getReplacesByKeys(array $keys)
    {
        $replaces = [];
        foreach ($keys as $key) {
            $currentReplacement = str_replace('$', '', $key);

            $currentReplacementLower = Str::of($currentReplacement)->lower()->toString();
            $method = sprintf('get%sReplacement', Str::studly($currentReplacementLower));

            if (method_exists($this, $method)) {
                $replaces[$currentReplacement] = $this->$method();
            } else {
                \info($currentReplacement.' does match any replace content');
                // keep origin content
                $replaces[$currentReplacement] = $key;
            }
        }

        return $replaces;
    }

    public function getReplacedContent(string $content, array $keys = [])
    {
        if (! $keys) {
            $keys = $this->getReplaceKeys($content);
        }

        $replaces = $this->getReplacesByKeys($keys);

        return str_replace($keys, $replaces, $content);
    }

    /**
     * Get array replacement for the specified stub.
     *
     * @param  $stub
     * @return array
     */
    protected function getReplacement($stubPath)
    {
        if (! file_exists($stubPath)) {
            throw new \RuntimeException("stubPath $stubPath not exists");
        }

        $stubContent = @file_get_contents($stubPath);

        $keys = $this->getReplaceKeys($stubContent);

        $replaces = $this->getReplacesByKeys($keys);

        return $replaces;
    }

    public function getAuthorsReplacement()
    {
        return Json::make()->encode(config('themes.composer.author'));
    }

    /**
     * Get namespace for theme service provider.
     *
     * @return string
     */
    protected function getNamespaceReplacement()
    {
        if ($this->runningAsRootDir) {
            return Str::beforeLast($this->buildClassName, '\\');
        }

        $namespace = $this->theme->getClassNamespace();

        return str_replace('\\\\', '\\', $namespace);
    }

    public function getClassReplacement()
    {
        return $this->getClass();
    }

    /**
     * Get the theme name in lower case.
     *
     * @return string
     */
    protected function getLowerNameReplacement()
    {
        return $this->theme->getLowerName();
    }

    /**
     * Get the theme name in studly case.
     *
     * @return string
     */
    protected function getStudlyNameReplacement()
    {
        return $this->theme->getStudlyName();
    }

    /**
     * Get the theme name in studly case.
     *
     * @return string
     */
    protected function getSnakeNameReplacement()
    {
        return $this->theme->getSnakeName();
    }

    /**
     * Get the theme name in kebab case.
     *
     * @return string
     */
    protected function getKebabNameReplacement()
    {
        return $this->theme->getKebabName();
    }

    /**
     * Get replacement for $VENDOR$.
     *
     * @return string
     */
    protected function getVendorReplacement()
    {
        return $this->theme->config('composer.vendor');
    }

    /**
     * Get replacement for $THEME_NAMESPACE$.
     *
     * @return string
     */
    protected function getThemeNamespaceReplacement()
    {
        return str_replace('\\', '\\\\', $this->theme->config('namespace'));
    }

    protected function getProviderNamespaceReplacement(): string
    {
        return str_replace('\\', '\\\\', GenerateConfigReader::read('provider')->getNamespace());
    }

    public function __get($fskey)
    {
        if ($fskey === 'theme') {
            // get Theme Name from Namespace: Theme\DemoTest => DemoTest
            $namespace = str_replace('\\', '/', app()->getNamespace());
            $namespace = rtrim($namespace, '/');
            $themeFskey = basename($namespace);

            // when running in rootDir
            if ($themeFskey == 'App') {
                $themeFskey = null;
            }

            if (empty($this->theme)) {
                $this->theme = new \Fresns\ThemeManager\Theme($themeFskey);
            }

            return $this->theme;
        }

        throw new \RuntimeException("unknown property $fskey");
    }
}
