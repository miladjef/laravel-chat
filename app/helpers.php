<?php

if (! function_exists('avatar_data_uri')) {
    /**
     * Generate a deterministic local SVG avatar without contacting a third party.
     */
    function avatar_data_uri(string $seed): string
    {
        $hash = hash('sha256', $seed);
        $hue = hexdec(substr($hash, 0, 4)) % 360;
        $accentHue = ($hue + 42) % 360;
        $cells = '';
        $index = 4;

        for ($row = 0; $row < 5; $row++) {
            for ($column = 0; $column < 3; $column++) {
                $visible = hexdec($hash[$index++ % strlen($hash)]) % 2 === 0;

                if (! $visible) {
                    continue;
                }

                foreach (array_unique([$column, 4 - $column]) as $x) {
                    $cells .= sprintf(
                        '<rect x="%d" y="%d" width="16" height="16" rx="3" fill="hsl(%d 75%% 54%%)"/>',
                        8 + ($x * 18),
                        8 + ($row * 18),
                        $accentHue,
                    );
                }
            }
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" rx="18" fill="hsl(%d 32%% 16%%)"/>%s</svg>',
            $hue,
            $cells,
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
