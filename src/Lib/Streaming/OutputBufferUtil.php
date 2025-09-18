<?php

namespace LiquidRazor\DtoApiBundle\Lib\Streaming;


final class OutputBufferUtil
{
    /** Disable as many buffering layers as we reasonably can before streaming. */
    public static function disableAll(): void
    {
        // 1) Drain and close all userland output buffers
        while (ob_get_level() > 0) {
            // send what’s buffered, then close that buffer
            if (!@ob_end_flush()) {
                break;
            }
        }

        // 2) Kill PHP-level buffering/compression knobs (best-effort)
        // Some INI settings may be PHP_INI_PER_DIR and ignored at runtime, that’s fine.
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', '0');
            @ini_set('implicit_flush', '1');
        }
        if (function_exists('ob_implicit_flush')) {
            @ob_implicit_flush();
        }

        // 3) Apache-specific tweaks (only if ext-apache is present)
        // Works when running as Apache module (mod_php). Safe no-op otherwise.
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');   // disable mod_deflate
            @apache_setenv('dont-vary', '1'); // avoid Apache adding/altering Vary
        }

        // 4) Initial flush to push headers/body ASAP
        if (function_exists('flush')) {
            @flush();
        }
    }

    /** True if the client disconnected (best-effort) */
    public static function aborted(): bool
    {
        return function_exists('connection_aborted') && connection_aborted() === 1;
    }
}
