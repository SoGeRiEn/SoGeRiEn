<?php
declare(strict_types=1);

final class AEAD
{
    public bool $status = true;
    public string $error = '';

    /** @var array<string,string> alias => absolute_path */
    public array $keys = [];

    public function status(): bool { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }  return   Sogerien::Debager()->capture_return($this->status, __CLASS__, __FUNCTION__);
}
    public function error(): string { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }  return   Sogerien::Debager()->capture_return($this->error, __CLASS__, __FUNCTION__);
}

    private function setOk(): void { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }  $this->status = true; $this->error = '';
}
    private function setFail(string $msg): void { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }  $this->status = false; $this->error = $msg;
}

    /**
     * 100% явная проверка наличия sodium + инструкция установки прямо в error.
     * Возвращает false и выставляет error, если sodium недоступен.
     */
    private function requireSodium(): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        // Быстрые проверки
        $ok = extension_loaded('sodium')
            && defined('SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES')
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt');

        if ($ok) return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);

        // Диагностика окружения, чтобы ошибка была самодостаточной
        $ini     = php_ini_loaded_file() ?: 'none';
        $scanDir = (defined('PHP_CONFIG_FILE_SCAN_DIR') && PHP_CONFIG_FILE_SCAN_DIR) ? PHP_CONFIG_FILE_SCAN_DIR : 'none';
        $extDir  = ini_get('extension_dir') ?: 'none';
        $sapi    = php_sapi_name();
        $phpv    = PHP_VERSION;

        $msg =
            "AEAD FAIL - PHP ext-sodium is not installed/enabled.\n" .
            "Detected:\n" .
            "- PHP_VERSION={$phpv}\n" .
            "- SAPI={$sapi}\n" .
            "- loaded_ini={$ini}\n" .
            "- scan_dir={$scanDir}\n" .
            "- extension_dir={$extDir}\n\n" .
            "Fix (FastPanel / /opt/phpXX):\n" .
            "- if you have sodium.so already in extension_dir, enable it via ini:\n" .
            "  echo \"extension=sodium\" > {$scanDir}/20-sodium.ini\n" .
            "  then restart the php-fpm pool that serves your site (FastPanel socket).\n" .
            "- check module in web:\n" .
            "  phpinfo() or var_dump(extension_loaded('sodium')) in FPM context.\n\n" .
            "Fix (Ubuntu system PHP via apt, if you use system php-fpm):\n" .
            "- sudo apt update\n" .
            "- sudo apt install -y php-sodium\n" .
            "- sudo systemctl restart php-fpm (or your phpX.Y-fpm)\n";

        $this->setFail($msg);
        return Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
    }

    public function generate_key(string $folder, string $filename): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if (!$this->requireSodium()) do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);

        try {
            if ($folder === '' || $filename === '') { $this->setFail('Folder and filename must be non-empty'); do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false); }

            $folder = rtrim($folder, "/\\");
            if ($folder === '') { $this->setFail('Invalid folder'); do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false); }

            if (!is_dir($folder)) {
                if (!mkdir($folder, 0700, true) && !is_dir($folder)) {
                    $this->setFail('Cannot create folder: ' . $folder);
                    do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
                }
            }

            $path = $folder . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($path)) { $this->setFail('AEAD key file already exists: ' . $path); do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false); }

            $keyBytes = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;
            $key = random_bytes($keyBytes);
            $tmp = $path . '.tmp';

            $bytes = file_put_contents($tmp, $key, LOCK_EX);
            if ($bytes !== $keyBytes) { @unlink($tmp); $this->setFail('Key write failed'); do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false); }

            @chmod($tmp, 0600);
            if (!rename($tmp, $path)) { @unlink($tmp); $this->setFail('Key finalize failed'); do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false); }

            $this->setOk();
} catch (\Throwable $e) {
            // не “молчим” - возвращаем тип и сообщение, если включён show_errors на уровне проекта
            $this->setFail('Key generation failed - ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    /**
     * $keyRef:
     * - 'token' (alias in $this->keys)
     * - '/abs/path/to/keyfile.key' (direct path)
     */
    private function resolveKeyPath(string $patch_to_keyFile): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $patch_to_keyFile = trim($patch_to_keyFile);
        if ($patch_to_keyFile === '') {
            $this->setFail('Key reference must be non-empty');
            return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }

        if (isset($this->keys[$patch_to_keyFile]) && $this->keys[$patch_to_keyFile] !== '') {
            return  Sogerien::Debager()->capture_return($this->keys[$patch_to_keyFile], __CLASS__, __FUNCTION__);
        }

        return  Sogerien::Debager()->capture_return($patch_to_keyFile, __CLASS__, __FUNCTION__);
    }

    private function loadKey(string $patch_to_keyFile): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if (!$this->requireSodium()) return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);

        try {
            $path = $this->resolveKeyPath($patch_to_keyFile);
            if (!$this->status) return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);

            if (!is_file($path)) { $this->setFail('Key file not found: ' . $path); return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__); }

            $key = file_get_contents($path);
            if ($key === false) { $this->setFail('Key read failed: ' . $path); return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__); }

            if (strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                $this->setFail('Invalid key length in file: ' . $path);
                return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
            }

            $this->setOk();
            return  Sogerien::Debager()->capture_return($key, __CLASS__, __FUNCTION__);
        } catch (\Throwable $e) {
            $this->setFail('Key load failed - ' . get_class($e) . ': ' . $e->getMessage());
            return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }
    }

    public function encryptBin(string $plainText, string $patch_to_keyFile): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if (!$this->requireSodium()) return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);

        try {
            $key = $this->loadKey($patch_to_keyFile);
            if (!$this->status) return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);

            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $plainText,
                '', // AAD
                $nonce,
                $key
            );

            if (function_exists('sodium_memzero')) sodium_memzero($key);

            $this->setOk();
            return  Sogerien::Debager()->capture_return($nonce . $cipher, __CLASS__, __FUNCTION__);
        } catch (\Throwable $e) {
            $this->setFail('AEAD encrypt failed - ' . get_class($e) . ': ' . $e->getMessage());
            return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }
    }

    public function decryptBin(string $cipherBin, string $patch_to_keyFile): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if (!$this->requireSodium()) return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);

        try {
            $key = $this->loadKey($patch_to_keyFile);
            if (!$this->status) return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);

            $nonceSize = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
            if (strlen($cipherBin) <= $nonceSize) {
                if (function_exists('sodium_memzero')) sodium_memzero($key);
                $this->setFail('Cipher too short');
                return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
            }

            $nonce  = substr($cipherBin, 0, $nonceSize);
            $cipher = substr($cipherBin, $nonceSize);

            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $cipher,
                '',
                $nonce,
                $key
            );

            if (function_exists('sodium_memzero')) sodium_memzero($key);

            if ($plain === false) {
                $this->setFail('AEAD authentication failed');
                return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
            }

            $this->setOk();
            return  Sogerien::Debager()->capture_return($plain, __CLASS__, __FUNCTION__);
        } catch (\Throwable $e) {
            $this->setFail('AEAD decrypt failed - ' . get_class($e) . ': ' . $e->getMessage());
            return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }
    }

    public function encrypt_base64url(string $plainText, string $patch_to_keyFile): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $bin = $this->encryptBin($plainText, $patch_to_keyFile);
        if (!$this->status) return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        return  Sogerien::Debager()->capture_return(rtrim(strtr(base64_encode($bin), '+/', '-_'), '='), __CLASS__, __FUNCTION__);
    }

    public function decrypt_base64Url(string $cipherB64Url, string $patch_to_keyFile): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if (!$this->requireSodium()) return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);

        try {
            $b64 = strtr($cipherB64Url, '-_', '+/');
            $pad = strlen($b64) % 4;
            if ($pad) $b64 .= str_repeat('=', 4 - $pad);

            $bin = base64_decode($b64, true);
            if ($bin === false) { $this->setFail('Invalid base64url'); return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__); }

            return  Sogerien::Debager()->capture_return($this->decryptBin($bin, $patch_to_keyFile), __CLASS__, __FUNCTION__);
        } catch (\Throwable $e) {
            $this->setFail('Invalid base64url - ' . get_class($e) . ': ' . $e->getMessage());
            return  Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        }
    }
}



////пример использования
//
//Sogerien::AEAD()->keys['token']        = '/path/outside_docroot/perm_key/token.key';
//Sogerien::AEAD()->keys['token_server'] = '/path/outside_docroot/perm_key/token_server.key';
//
//$tok = Sogerien::AEAD()->encrypt_base64url($payloadJson, 'token');
