<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Security;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class FilePathResolver
{
    public function resolvePrivateFile(string $configuredPath): string
    {
        $configuredPath = trim($configuredPath);
        if ($configuredPath === '') {
            throw new \RuntimeException('A required EUDI key/certificate file path is empty.');
        }
        if (str_starts_with($configuredPath, 'EXT:')) {
            $path = GeneralUtility::getFileAbsFileName($configuredPath);
        } elseif (str_starts_with($configuredPath, DIRECTORY_SEPARATOR)) {
            $path = $configuredPath;
        } else {
            $path = rtrim(Environment::getProjectPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($configuredPath, DIRECTORY_SEPARATOR);
        }
        $real = realpath($path);
        if ($real === false || !is_file($real) || !is_readable($real)) {
            throw new \RuntimeException('Required EUDI file is missing or unreadable: ' . $configuredPath);
        }
        $public = realpath(Environment::getPublicPath());
        if (is_string($public) && str_starts_with($real . DIRECTORY_SEPARATOR, rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('EUDI private/trust material must not be stored below the public web root: ' . $configuredPath);
        }
        return $real;
    }

    public function readPrivateFile(string $configuredPath): string
    {
        $path = $this->resolvePrivateFile($configuredPath);
        $contents = file_get_contents($path);
        if (!is_string($contents) || trim($contents) === '') {
            throw new \RuntimeException('EUDI file is empty: ' . $configuredPath);
        }
        return $contents;
    }
}
