<?php
declare(strict_types=1);

/**
 * Project-level auto deploy is handled by /git_auto_update.php from the private
 * Forgejo repository. The old core updater from public GitHub is intentionally
 * disabled for this project to keep one source of truth.
 */
function sogerien_self_update_maybe_run(): void
{
    return;
}

