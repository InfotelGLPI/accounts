<?php

/**
 * -------------------------------------------------------------------------
 * accounts plugin for GLPI
 * Copyright (C) 2015-2026 by the accounts Development Team.
 *
 * https://github.com/InfotelGLPI/accounts
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of accounts.
 *
 * accounts is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * accounts is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with accounts. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Accounts\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Markup of the plugin under the Content Security Policy of GLPI: no inline event handler nor
 * javascript: URL in its templates, sources and scripts; the behaviours are attached by
 * delegated listeners (public/scripts/csp_handlers.js and the scripts of each feature).
 */
class CspMarkupTest extends TestCase
{
    private const HANDLER = '/[\s"\'](on(?:click|change|submit|load|input|key\w+|mouse\w+|focus\w*|blur|dblclick|error|select|reset|toggle|scroll|resize))\s*=\s*\\?["\'{]/i';

    /**
     * @return iterable<string, array{string}>
     */
    public static function fileProvider(): iterable
    {
        $root     = dirname(__DIR__);
        $patterns = [
            'templates' => '/\.twig$/',
            'public'    => '/(?<!\.min)\.js$/',
            'src'       => '/\.php$/',
            'ajax'      => '/\.php$/',
            'front'     => '/\.php$/',
        ];
        foreach ($patterns as $dir => $pattern) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir));
            foreach ($files as $file) {
                if ($file->isFile() && preg_match($pattern, $file->getFilename())
                    && !str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR)) {
                    yield substr($file->getPathname(), strlen($root) + 1) => [$file->getPathname()];
                }
            }
        }
    }

    #[DataProvider('fileProvider')]
    public function testNoInlineHandler(string $path): void
    {
        $content = (string) file_get_contents($path);
        // Comments only: Twig ones, and the block / line comments of the scripts and sources
        $content = (string) preg_replace('/\{#.*?#\}/s', '', $content);
        if (!str_ends_with($path, '.twig')) {
            $content = (string) preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $content);
        }

        $this->assertDoesNotMatchRegularExpression(self::HANDLER, $content);
        $this->assertDoesNotMatchRegularExpression('/(href|action)\s*=\s*\\?["\']\s*javascript:/i', $content);
    }
}
