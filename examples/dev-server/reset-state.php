<?php

declare(strict_types=1);

foreach ([
    sys_get_temp_dir() . '/http-smoke-dev-state.json',
    sys_get_temp_dir() . '/http-smoke-dev-sessions.json',
] as $file) {
    if (is_file($file)) {
        unlink($file);
        echo "removed: {$file}\n";
    }
}
