<?php

if (! function_exists('vite_tags')) {
    /**
     * Render Vite assets for CI4 views.
     */
    function vite_tags(string $entry = 'resources/js/app.js'): string
    {
        $entry = ltrim($entry, '/');
        $manifestPath = FCPATH . 'build/.vite/manifest.json';
        $devServer = rtrim((string) env('vite.devServer', 'http://localhost:5173'), '/');
        $environment = defined('ENVIRONMENT') ? ENVIRONMENT : (string) env('CI_ENVIRONMENT', 'production');

        // Development mode (HMR) when dev server is reachable.
        if ($environment === 'development' && vite_server_reachable($devServer)) {
            return implode("\n", [
                '<script type="module" src="' . htmlspecialchars($devServer . '/@vite/client', ENT_QUOTES, 'UTF-8') . '"></script>',
                '<script type="module" src="' . htmlspecialchars($devServer . '/' . $entry, ENT_QUOTES, 'UTF-8') . '"></script>',
            ]);
        }

        // Production/build mode.
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (! is_array($manifest) || ! isset($manifest[$entry])) {
                return '';
            }

            $chunk = $manifest[$entry];
            $html = [];

            if (! empty($chunk['css']) && is_array($chunk['css'])) {
                foreach ($chunk['css'] as $cssFile) {
                    $baseUrl = rtrim((string) config('App')->baseURL, '/');
                    $href = $baseUrl . '/build/' . ltrim((string) $cssFile, '/');
                    $html[] = '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
                }
            }

            if (! empty($chunk['file'])) {
                $baseUrl = rtrim((string) config('App')->baseURL, '/');
                $src = $baseUrl . '/build/' . ltrim((string) $chunk['file'], '/');
                $html[] = '<script type="module" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
            }

            return implode("\n", $html);
        }

        return '';
    }
}

if (! function_exists('vite_server_reachable')) {
    function vite_server_reachable(string $server): bool
    {
        $parts = parse_url($server);
        if (! is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? 5173);

        $socket = @fsockopen($host, $port, $errno, $errstr, 0.15);
        if ($socket === false) {
            return false;
        }

        fclose($socket);
        return true;
    }
}
