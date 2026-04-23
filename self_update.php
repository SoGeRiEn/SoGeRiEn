<?php
declare(strict_types=1);

function sogerien_self_update_maybe_run(): void
{
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return;
    }

    $root = __DIR__;
    $statePath = $root . '/cache/self_update_state.json';
    $lockPath = $root . '/cache/self_update.lock';
    $intervalSeconds = 900;

    if (!is_dir($root . '/cache')) {
        @mkdir($root . '/cache', 0775, true);
    }

    $lastCheck = 0;
    if (is_file($statePath)) {
        $raw = @file_get_contents($statePath);
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['last_check']) && is_numeric($json['last_check'])) {
                $lastCheck = (int)$json['last_check'];
            }
        }
    }

    $now = time();
    if (($now - $lastCheck) < $intervalSeconds) {
        return;
    }

    $lockFp = @fopen($lockPath, 'c+');
    if (!is_resource($lockFp)) {
        return;
    }
    if (!@flock($lockFp, LOCK_EX | LOCK_NB)) {
        @fclose($lockFp);
        return;
    }

    try {
        sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => null, 'status' => 'check_started']);

        $remoteVersion = sogerien_self_update_read_remote_version('https://raw.githubusercontent.com/SoGeRiEn/SoGeRiEn/main/version.json');
        $localVersion = sogerien_self_update_read_local_version($root . '/version.json');
        if ($remoteVersion === '' || $remoteVersion === $localVersion) {
            sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => null, 'status' => 'up_to_date']);
            return;
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'sgr_upd_');
        if (!is_string($tmpZip) || $tmpZip === '') {
            sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => null, 'status' => 'tmp_zip_fail']);
            return;
        }

        $zipData = sogerien_self_update_http_get('https://api.github.com/repos/SoGeRiEn/SoGeRiEn/zipball/main');
        if ($zipData === '') {
            @unlink($tmpZip);
            sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => null, 'status' => 'download_fail']);
            return;
        }
        if (@file_put_contents($tmpZip, $zipData) === false) {
            @unlink($tmpZip);
            sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => null, 'status' => 'write_zip_fail']);
            return;
        }

        $extractDir = sys_get_temp_dir() . '/sgr_upd_' . bin2hex(random_bytes(8));
        if (!@mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
            @unlink($tmpZip);
            sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => null, 'status' => 'extract_dir_fail']);
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);
            sogerien_self_update_delete_tree($extractDir);
            sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => null, 'status' => 'zip_open_fail']);
            return;
        }
        $zip->extractTo($extractDir);
        $zip->close();
        @unlink($tmpZip);

        $dirs = @scandir($extractDir);
        if (!is_array($dirs)) {
            sogerien_self_update_delete_tree($extractDir);
            sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => null, 'status' => 'scandir_fail']);
            return;
        }

        $repoRoot = '';
        foreach ($dirs as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $candidate = $extractDir . '/' . $entry;
            if (is_dir($candidate)) {
                $repoRoot = $candidate;
                break;
            }
        }
        if ($repoRoot === '') {
            sogerien_self_update_delete_tree($extractDir);
            sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => null, 'status' => 'repo_root_fail']);
            return;
        }

        sogerien_self_update_sync_tree($repoRoot, $root, [
            '.git',
            '.gitignore',
            'cache',
            'logs',
            'tmp',
            'storage',
        ]);

        sogerien_self_update_delete_tree($extractDir);
        sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => $now, 'status' => 'updated_to_' . $remoteVersion]);
    } catch (Throwable $e) {
        sogerien_self_update_write_state($statePath, ['last_check' => $now, 'last_update' => null, 'status' => 'exception']);
    } finally {
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
    }
}

function sogerien_self_update_read_local_version(string $path): string
{
    if (!is_file($path)) {
        return '';
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return '';
    }
    $json = json_decode($raw, true);
    if (!is_array($json) || !isset($json['version']) || !is_string($json['version'])) {
        return '';
    }
    return trim($json['version']);
}

function sogerien_self_update_read_remote_version(string $url): string
{
    $raw = sogerien_self_update_http_get($url);
    if ($raw === '') {
        return '';
    }
    $json = json_decode($raw, true);
    if (!is_array($json) || !isset($json['version']) || !is_string($json['version'])) {
        return '';
    }
    return trim($json['version']);
}

function sogerien_self_update_http_get(string $url): string
{
    $headers = "User-Agent: SogerienSelfUpdater/1.0\r\nAccept: application/json,text/plain,*/*\r\n";
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => $headers,
        ],
    ]);

    $data = @file_get_contents($url, false, $context);
    if (!is_string($data)) {
        return '';
    }
    return $data;
}

function sogerien_self_update_write_state(string $path, array $state): void
{
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return;
    }
    @file_put_contents($path, $json);
}

function sogerien_self_update_sync_tree(string $sourceRoot, string $targetRoot, array $preserve): void
{
    $preserveMap = [];
    foreach ($preserve as $item) {
        $preserveMap[str_replace('\\', '/', trim($item, '/\\'))] = true;
    }

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $seen = [];
    foreach ($rii as $item) {
        $sourcePath = (string)$item->getPathname();
        $rel = str_replace('\\', '/', substr($sourcePath, strlen($sourceRoot) + 1));
        if ($rel === '' || isset($preserveMap[$rel])) {
            continue;
        }
        $targetPath = $targetRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $seen[$rel] = true;

        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                @mkdir($targetPath, 0775, true);
            }
            continue;
        }

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }
        @copy($sourcePath, $targetPath);
    }

    $tRii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($targetRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($tRii as $item) {
        $targetPath = (string)$item->getPathname();
        $rel = str_replace('\\', '/', substr($targetPath, strlen($targetRoot) + 1));
        if ($rel === '' || isset($preserveMap[$rel])) {
            continue;
        }
        if (!isset($seen[$rel])) {
            if ($item->isDir()) {
                @rmdir($targetPath);
            } else {
                @unlink($targetPath);
            }
        }
    }
}

function sogerien_self_update_delete_tree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($rii as $item) {
        if ($item->isDir()) {
            @rmdir((string)$item->getPathname());
        } else {
            @unlink((string)$item->getPathname());
        }
    }
    @rmdir($dir);
}
