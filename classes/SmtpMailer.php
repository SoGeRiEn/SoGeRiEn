<?php
declare(strict_types=1);

/**
 * Universal SMTP mailer (no deps) - STARTTLS/SMTPS, AUTH LOGIN/PLAIN/XOAUTH2, MIME, attachments, optional DKIM.
 * Object is created with no parameters; configuration can be set later via public properties / setters.
 */
final class SmtpMailer
{
    // Connection
    public string $host = '';
    public int $port = 587;
    public string $encryption = 'tls'; // 'none' | 'tls' (STARTTLS) | 'ssl' (SMTPS)
    public int $timeout = 20;

    // TLS verification (recommended true in prod)
    public bool $tlsVerifyPeer = true;
    public bool $tlsVerifyPeerName = true;
    public bool $tlsAllowSelfSigned = false;
    public ?string $tlsCaFile = null; // e.g. /etc/ssl/certs/ca-certificates.crt (Linux)
    public ?string $tlsCaPath = null; // e.g. /etc/ssl/certs

    // Auth
    public ?string $username = null;
    public ?string $password = null;

    /**
     * authMode:
     * - 'auto'    - detect from EHLO (AUTH PLAIN/LOGIN)
     * - 'plain'   - AUTH PLAIN
     * - 'login'   - AUTH LOGIN
     * - 'xoauth2' - AUTH XOAUTH2 (requires $oauthAccessToken)
     * - 'none'    - no auth
     */
    public string $authMode = 'auto';
    public ?string $oauthAccessToken = null; // for XOAUTH2

    // HELO/EHLO
    public ?string $heloHost = null; // if null - use gethostname() fallback

    // Debug
    public bool $debug = false;

    // DKIM (optional)
    public ?string $dkimDomain = null;
    public ?string $dkimSelector = null;
    public ?string $dkimPrivateKeyPem = null; // PEM string
    public string $dkimHeaderCanon = 'relaxed'; // 'relaxed' or 'simple'
    public string $dkimBodyCanon = 'relaxed';   // 'relaxed' or 'simple'

    /** @var resource|null */
    private $socket = null;

    /** @var array<string,mixed> */
    private array $caps = [];

    private string $connKey = '';

    public function __construct() { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); }
}

    private function computeConnKey(): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $parts = [
            $this->host,
            (string)$this->port,
            $this->encryption,
            (string)$this->timeout,
            $this->tlsVerifyPeer ? '1' : '0',
            $this->tlsVerifyPeerName ? '1' : '0',
            $this->tlsAllowSelfSigned ? '1' : '0',
            (string)($this->tlsCaFile ?? ''),
            (string)($this->tlsCaPath ?? ''),
            (string)($this->heloHost ?? ''),
            strtolower(trim($this->authMode)),
            (string)($this->username ?? ''),
            // пароль/токен тоже влияет на сессию, иначе можно “переехать” на старой авторизации
            (string)($this->password ?? ''),
            (string)($this->oauthAccessToken ?? ''),
        ];

        return   Sogerien::Debager()->capture_return(hash('sha256', implode('|', $parts)), __CLASS__, __FUNCTION__);
}

    private function ensureConnected(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->assertConfigured();

        $newKey = $this->computeConnKey();

        if ($this->socket !== null && is_resource($this->socket) && $this->connKey === $newKey) {
            if ($this->isAlive()) do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
            $this->close();
        }

        // настройки изменились или сокет умер - пересоздаём
        $this->close();

        $this->socket = $this->connect();
        $this->expect($this->socket, [220], 'connect');

        $ehloHost = $this->getHeloHost();
        $this->cmd($this->socket, "EHLO " . $ehloHost);
        $ehlo = $this->readMultiline($this->socket, 250);
        $this->caps = $this->parseEhloCapabilities($ehlo);

        if ($this->encryption === 'tls') {
            if (!isset($this->caps['STARTTLS'])) {
                throw new \RuntimeException('Server does not support STARTTLS');
            }
            $this->cmd($this->socket, "STARTTLS");
            $this->expect($this->socket, [220], 'STARTTLS');

            $this->enableCrypto($this->socket);

            $this->cmd($this->socket, "EHLO " . $ehloHost);
            $ehlo = $this->readMultiline($this->socket, 250);
            $this->caps = $this->parseEhloCapabilities($ehlo);
        }

        $this->smtpAuth($this->socket, $this->caps);

        $this->connKey = $newKey;
}

    public function close(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($this->socket !== null && is_resource($this->socket)) {
            try {
                $this->cmd($this->socket, "QUIT");
                $this->readLine($this->socket);
            } catch (\Throwable $e) {
                // игнорируем
            }
            fclose($this->socket);
        }
        $this->socket = null;
        $this->caps = [];
        $this->connKey = '';
}

    public function __destruct()
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->close();
}

    public function setAuth(string $username, string $password): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->username = $username;
        $this->password = $password;
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}

    public function setXoauth2(string $username, string $accessToken): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->username = $username;
        $this->oauthAccessToken = $accessToken;
        $this->authMode = 'xoauth2';
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}

    public function setDkim(string $domain, string $selector, string $privateKeyPem): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->dkimDomain = $domain;
        $this->dkimSelector = $selector;
        $this->dkimPrivateKeyPem = $privateKeyPem;
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}

    public function setDebug(bool $debug): self
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->debug = $debug;
        return   Sogerien::Debager()->capture_return($this, __CLASS__, __FUNCTION__);
}

    /**
     * Send email.
     *
     * $from: ['email'=>'a@domain','name'=>'Name'] OR 'a@domain'
     * $to/$cc/$bcc: string OR array of strings OR array of ['email'=>..,'name'=>..]
     * $options: [
     *   'text' => '...',
     *   'html' => '...',
     *   'reply_to' => ['email'=>..,'name'=>..] OR '...',
     *   'cc' => ...,
     *   'bcc' => ...,
     *   'headers' => ['X-Custom'=>'1', ...], // additional headers
     *   'attachments' => [
     *      ['filename'=>'a.pdf','contentType'=>'application/pdf','content'=> (raw bytes string)],
     *      ['filename'=>'a.pdf','contentType'=>'application/pdf','path'=>'/abs/file.pdf'],
     *   ],
     *   'envelope_from' => 'bounce@domain', // optional bounce address for MAIL FROM
     * ]
     *
     * @throws \Random\RandomException
     */
    public function send($from, $to, string $subject, array $options = []): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $fromArr = $this->normalizeSingleAddress($from, 'From');

        $toList  = $this->normalizeRecipients($to);
        $ccList  = $this->normalizeRecipients($options['cc'] ?? []);
        $bccList = $this->normalizeRecipients($options['bcc'] ?? []);

        if (empty($toList) && empty($ccList) && empty($bccList)) {
            throw new \RuntimeException('No recipients provided');
        }

        $text = (string)($options['text'] ?? '');
        $html = (string)($options['html'] ?? '');
        $attachments = (array)($options['attachments'] ?? []);
        $replyTo = $options['reply_to'] ?? null;
        $extraHeaders = $options['headers'] ?? [];
        $envelopeFrom = isset($options['envelope_from']) && is_string($options['envelope_from']) && trim($options['envelope_from']) !== ''
            ? trim($options['envelope_from'])
            : $fromArr['email'];

        [$rawHeaders, $body] = $this->buildMessage(
            $fromArr,
            $toList,
            $ccList,
            $subject,
            $text,
            $html,
            $attachments,
            $replyTo,
            $extraHeaders
        );

        $rcpts = array_merge($toList, $ccList, $bccList);

        // 1 ретрай на сетевой разрыв/421/таймаут
        $this->smtpSendWithRetry($envelopeFrom, $rcpts, $rawHeaders, $body);
}

    // -------------------- Build MIME --------------------

    /**
     * @throws \Random\RandomException
     */
    private function buildMessage(
        array $from,
        array $toList,
        array $ccList,
        string $subject,
        string $text,
        string $html,
        array $attachments,
        $replyTo,
        $extraHeaders
    ): array { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $fromEmail = $from['email'];
        $fromName  = $from['name'] ?? null;

        $date = gmdate('D, d M Y H:i:s') . ' +0000';
        $messageId = $this->makeMessageId($fromEmail);

        $headers = [];
        $headers['Date'] = $date;
        $headers['Message-ID'] = '<' . $messageId . '>';
        $headers['From'] = $this->formatAddress($fromEmail, $fromName);

        if (!empty($toList)) $headers['To'] = $this->formatAddressList($toList);
        if (!empty($ccList)) $headers['Cc'] = $this->formatAddressList($ccList);

        if ($replyTo !== null) {
            $rt = $this->normalizeSingleAddress($replyTo, 'Reply-To');
            $headers['Reply-To'] = $this->formatAddress($rt['email'], $rt['name'] ?? null);
        }

        $headers['Subject'] = $this->encodeHeader($subject);
        $headers['MIME-Version'] = '1.0';

        // extra headers (X-*, List-*, etc.)
        if (is_array($extraHeaders)) {
            foreach ($extraHeaders as $k => $v) {
                if (!is_string($k) || $k === '') continue;
                if (!is_scalar($v)) continue;
                $kk = trim($k);
                if ($kk === '') continue;
                // avoid overriding critical headers
                $lk = strtolower($kk);
                if (in_array($lk, ['from','to','cc','bcc','subject','date','message-id','mime-version','content-type','content-transfer-encoding'], true)) {
                    continue;
                }
                $headers[$kk] = $this->encodeHeader((string)$v);
            }
        }

        $hasAttachments = !empty($attachments);
        $hasText = ($text !== '');
        $hasHtml = ($html !== '');

        if (!$hasText && !$hasHtml) {
            $hasText = true;
            $text = ' ';
        }

        if ($hasAttachments) {
            $mixedBoundary = $this->boundary('mixed');
            $headers['Content-Type'] = 'multipart/mixed; boundary="' . $mixedBoundary . '"';

            $parts = [];

            if ($hasText && $hasHtml) {
                $altBoundary = $this->boundary('alt');
                $parts[] = $this->multipartAlternative($altBoundary, $text, $html);
            } elseif ($hasHtml) {
                $parts[] = $this->partHtml($html);
            } else {
                $parts[] = $this->partText($text);
            }

            foreach ($attachments as $att) {
                $parts[] = $this->partAttachment($att);
            }

            $body = $this->renderMultipart($mixedBoundary, $parts);
        } else {
            if ($hasText && $hasHtml) {
                $altBoundary = $this->boundary('alt');
                $headers['Content-Type'] = 'multipart/alternative; boundary="' . $altBoundary . '"';
                $body = $this->renderMultipart($altBoundary, [
                    $this->partText($text),
                    $this->partHtml($html),
                ]);
            } elseif ($hasHtml) {
                $headers['Content-Type'] = 'text/html; charset=UTF-8';
                $headers['Content-Transfer-Encoding'] = 'quoted-printable';
                $body = $this->qpEncode($html);
            } else {
                $headers['Content-Type'] = 'text/plain; charset=UTF-8';
                $headers['Content-Transfer-Encoding'] = 'quoted-printable';
                $body = $this->qpEncode($text);
            }
        }

        // DKIM (optional) - sign final headers and body
        if ($this->dkimDomain && $this->dkimSelector && $this->dkimPrivateKeyPem) {
            $dkimHeaderValue = $this->buildDkimHeader($headers, $body);
            $headers = ['DKIM-Signature' => $dkimHeaderValue] + $headers;
        }

        $rawHeaders = $this->flattenHeaders($headers);
        return   Sogerien::Debager()->capture_return([$rawHeaders, $body], __CLASS__, __FUNCTION__);
}

    private function partText(string $text): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $h = [
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
        ];
        return Sogerien::Debager()->capture_return(implode("\r\n", $h) . "\r\n\r\n" . $this->qpEncode($text), __CLASS__, __FUNCTION__);
    }

    private function partHtml(string $html): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $h = [
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
        ];
        return Sogerien::Debager()->capture_return(implode("\r\n", $h) . "\r\n\r\n" . $this->qpEncode($html), __CLASS__, __FUNCTION__);
    }

    private function multipartAlternative(string $boundary, string $text, string $html): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $h = ['Content-Type: multipart/alternative; boundary="' . $boundary . '"'];
        $inner = $this->renderMultipart($boundary, [$this->partText($text), $this->partHtml($html)]);
        return Sogerien::Debager()->capture_return(implode("\r\n", $h) . "\r\n\r\n" . $inner, __CLASS__, __FUNCTION__);
}

    private function partAttachment(array $att): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $filename = $att['filename'] ?? null;
        $ctype = $att['contentType'] ?? 'application/octet-stream';

        if (!is_string($filename) || trim($filename) === '') {
            throw new \RuntimeException('Attachment filename is required');
        }

        $content = null;

        if (isset($att['content']) && is_string($att['content'])) {
            $content = $att['content'];
        } elseif (isset($att['path']) && is_string($att['path'])) {
            $path = $att['path'];
            if (!is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('Attachment path not readable: ' . $path);
            }
            $content = file_get_contents($path);
            if ($content === false) {
                throw new \RuntimeException('Failed to read attachment: ' . $path);
            }
        } else {
            throw new \RuntimeException('Attachment requires content or path');
        }

        $encoded = chunk_split(base64_encode($content));

        // RFC2231 + ASCII fallback for filename
        $asciiFallback = $this->toAsciiFallback($filename);
        $filenameStar = "UTF-8''" . rawurlencode($filename);

        $h = [];
        $h[] = 'Content-Type: ' . $ctype . '; name="' . $this->stripQuotes($asciiFallback) . '"; name*=' . $filenameStar;
        $h[] = 'Content-Transfer-Encoding: base64';
        $h[] = 'Content-Disposition: attachment; filename="' . $this->stripQuotes($asciiFallback) . '"; filename*=' . $filenameStar;

        return Sogerien::Debager()->capture_return(implode("\r\n", $h) . "\r\n\r\n" . $encoded, __CLASS__, __FUNCTION__);
    }

    private function renderMultipart(string $boundary, array $parts): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $out = '';
        foreach ($parts as $p) {
            $out .= '--' . $boundary . "\r\n" . $p . "\r\n";
        }
        $out .= '--' . $boundary . "--\r\n";
        return Sogerien::Debager()->capture_return($out, __CLASS__, __FUNCTION__);
    }

    private function flattenHeaders(array $headers): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $lines = [];
        foreach ($headers as $k => $v) {
            if ($k === 'DKIM-Signature') {
                $lines[] = 'DKIM-Signature: ' . $v; // already folded
                continue;
            }
            $lines[] = $k . ': ' . $v;
        }
        return Sogerien::Debager()->capture_return(implode("\r\n", $lines) . "\r\n", __CLASS__, __FUNCTION__);
}

    // -------------------- SMTP --------------------

    private function smtpSendWithRetry(string $mailFrom, array $rcpts, string $headers, string $body): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $attempt = 0;
        $last = null;

        while ($attempt < 2) {
            $attempt++;
            try {
                $this->smtpSendOnce($mailFrom, $rcpts, $headers, $body);
                do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
            } catch (\Throwable $e) {
                $last = $e;

                // ретраим только на "сетевое" или 421
                $msg = $e->getMessage();
                $is421 = (strpos($msg, ' 421') !== false) || (str_starts_with($msg, 'SMTP failed at') && preg_match('/\b421\b/', $msg));
                $isNetworkish =
                    str_contains($msg, 'timed out') ||
                    str_contains($msg, 'EOF') ||
                    str_contains($msg, 'read failed') ||
                    str_contains($msg, 'write failed') ||
                    str_contains($msg, 'empty response') ||
                    str_contains($msg, 'Broken pipe') ||
                    str_contains($msg, 'Connection reset');

                if ($attempt >= 2 || (!$is421 && !$isNetworkish)) {
                    throw $e;
                }

                // закрываем и пробуем заново
                $this->close();
            }
        }

        if ($last) throw $last;
}

    private function smtpSendOnce(string $mailFrom, array $rcpts, string $headers, string $body): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->ensureConnected();

        try {
            $socket = $this->socket;
            if ($socket === null || !is_resource($socket)) {
                throw new \RuntimeException('SMTP socket not available');
            }

            $this->cmd($socket, "MAIL FROM:<" . $this->sanitizePath($mailFrom) . ">");
            $this->expect($socket, [250], 'MAIL FROM');

            foreach ($rcpts as $r) {
                $this->cmd($socket, "RCPT TO:<" . $this->sanitizePath($r['email']) . ">");
                $this->expect($socket, [250, 251], 'RCPT TO');
            }

            $this->cmd($socket, "DATA");
            $this->expect($socket, [354], 'DATA');

            $data = $headers . "\r\n" . $body;
            $data = $this->normalizeEol($data);
            $data = $this->dotStuff($data);

            $this->write($socket, $data . "\r\n.\r\n");
            $this->expect($socket, [250], 'message body');
        } catch (\Throwable $e) {
            try {
                if ($this->socket !== null && is_resource($this->socket)) {
                    $this->cmd($this->socket, "RSET");
                    $this->readLine($this->socket);
                }
            } catch (\Throwable $e2) {
                $this->close();
            }
            throw $e;
        }
}

    private function isAlive(): bool
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($this->socket === null || !is_resource($this->socket)) return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);

        $meta = stream_get_meta_data($this->socket);
        if (!empty($meta['timed_out'])) return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        if (!empty($meta['eof'])) return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);

        try {
            $this->cmd($this->socket, "NOOP");
            $this->expect($this->socket, [250], 'NOOP');
            return   Sogerien::Debager()->capture_return(true, __CLASS__, __FUNCTION__);
        } catch (\Throwable $e) {
            return   Sogerien::Debager()->capture_return(false, __CLASS__, __FUNCTION__);
        }
}

    private function smtpAuth($socket, array $caps): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $mode = strtolower(trim($this->authMode));

        if ($mode === 'none') do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);

        if ($mode === 'xoauth2') {
            if ($this->username === null || $this->oauthAccessToken === null) {
                throw new \RuntimeException('XOAUTH2 requires username + oauthAccessToken');
            }
            $this->authXoauth2($socket, $this->username, $this->oauthAccessToken);
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        // PLAIN/LOGIN/AUTO require username+password
        if ($this->username === null || $this->password === null) {
            // if not configured - skip auth
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        $authLine = $caps['AUTH'] ?? '';
        $authLine = is_string($authLine) ? strtoupper($authLine) : '';

        if ($mode === 'plain') {
            $this->authPlain($socket, $this->username, $this->password);
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }
        if ($mode === 'login') {
            $this->authLogin($socket, $this->username, $this->password);
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        // auto
        if (str_contains($authLine, 'PLAIN')) {
            $this->authPlain($socket, $this->username, $this->password);
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }
        if (str_contains($authLine, 'LOGIN') || $authLine === '') {
            $this->authLogin($socket, $this->username, $this->password);
            do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        throw new \RuntimeException('No supported AUTH mechanism. Server AUTH: ' . $authLine);
}

    private function authPlain($socket, string $user, string $pass): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $token = base64_encode("\0" . $user . "\0" . $pass);
        $this->cmd($socket, "AUTH PLAIN " . $token);
        $this->expect($socket, [235], 'AUTH PLAIN');
    }

    private function authLogin($socket, string $user, string $pass): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $this->cmd($socket, "AUTH LOGIN");
        $this->expect($socket, [334], 'AUTH LOGIN username');
        $this->cmd($socket, base64_encode($user));
        $this->expect($socket, [334], 'AUTH LOGIN password');
        $this->cmd($socket, base64_encode($pass));
        $this->expect($socket, [235], 'AUTH LOGIN');
    }

    private function authXoauth2($socket, string $user, string $accessToken): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } // base64("user=<email>\x01auth=Bearer <token>\x01\x01")
        $s = "user=" . $user . "\x01auth=Bearer " . $accessToken . "\x01\x01";
        $this->cmd($socket, "AUTH XOAUTH2 " . base64_encode($s));
        $line = $this->readLine($socket);
        if ($line === '') throw new \RuntimeException('SMTP failed at AUTH XOAUTH2: empty response');

        $code = (int)substr($line, 0, 3);
        if ($code === 235) do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);

        if (isset($line[3]) && $line[3] === '-') {
            $this->readMultiline($socket, $code);
        }
        throw new \RuntimeException('SMTP failed at AUTH XOAUTH2: ' . $line);
}

    private function connect()
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->assertConfigured();

        $scheme = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $remote = $scheme . $this->host . ':' . $this->port;

        $ssl = [
            'verify_peer' => $this->tlsVerifyPeer,
            'verify_peer_name' => $this->tlsVerifyPeerName,
            'allow_self_signed' => $this->tlsAllowSelfSigned,
            'SNI_enabled' => true,
            'peer_name' => $this->host,
        ];
        if ($this->tlsCaFile) $ssl['cafile'] = $this->tlsCaFile;
        if ($this->tlsCaPath) $ssl['capath'] = $this->tlsCaPath;

        $ctx = stream_context_create(['ssl' => $ssl]);

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$socket) {
            throw new \RuntimeException("SMTP connect failed: $errstr ($errno)");
        }

        stream_set_timeout($socket, $this->timeout);
        return   Sogerien::Debager()->capture_return($socket, __CLASS__, __FUNCTION__);
}

    private function enableCrypto($socket): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $methods = [];

        // universal default
        $methods[] = STREAM_CRYPTO_METHOD_TLS_CLIENT;

        // fallbacks where available
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $methods[] = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) $methods[] = STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT')) $methods[] = STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;

        foreach ($methods as $m) {
            $ok = @stream_socket_enable_crypto($socket, true, $m);
            if ($ok === true) do { Sogerien::Debager()->capture_void(__CLASS__, __FUNCTION__); return; } while (false);
        }

        throw new \RuntimeException('Failed to enable TLS crypto');
}

    private function cmd($socket, string $cmd): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $this->write($socket, $cmd . "\r\n");
        if ($this->debug) error_log('SMTP C: ' . $cmd);
}

    private function write($socket, string $data): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $len = strlen($data);
        $w = 0;
        while ($w < $len) {
            $n = fwrite($socket, substr($data, $w));
            if ($n === false || $n === 0) {
                $meta = stream_get_meta_data($socket);
                $why = '';
                if (!empty($meta['timed_out'])) $why = ' (timed out)';
                if (!empty($meta['eof'])) $why = ' (EOF)';
                throw new \RuntimeException('SMTP write failed' . $why);
            }
            $w += $n;
        }
}

    private function readLine($socket): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $line = fgets($socket, 8192);
        if ($line === false) {
            $meta = stream_get_meta_data($socket);
            if (!empty($meta['timed_out'])) throw new \RuntimeException('SMTP read failed (timed out)');
            if (!empty($meta['eof'])) throw new \RuntimeException('SMTP read failed (EOF)');
            throw new \RuntimeException('SMTP read failed');
        }
        $line = rtrim($line, "\r\n");
        if ($this->debug) error_log('SMTP S: ' . $line);
        return Sogerien::Debager()->capture_return($line, __CLASS__, __FUNCTION__);
    }

    private function readMultiline($socket, int $expectedCode): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $all = '';
        while (true) {
            $line = $this->readLine($socket);
            $all .= $line . "\r\n";
            if (preg_match('/^' . $expectedCode . '\s/', $line)) break; // "250 " ends
        }
        return Sogerien::Debager()->capture_return($all, __CLASS__, __FUNCTION__);
    }

    private function expect($socket, array $codes, string $stage): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $line = $this->readLine($socket);
        if ($line === '') throw new \RuntimeException("SMTP failed at $stage: empty response");

        $code = (int)substr($line, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException("SMTP failed at $stage: $line");
        }

        if (isset($line[3]) && $line[3] === '-') {
            $this->readMultiline($socket, $code);
        }
}

    private function parseEhloCapabilities(string $ehloText): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $lines = preg_split('/\r\n|\n|\r/', trim($ehloText));
        $caps = [];
        foreach ($lines as $ln) {
            $ln = preg_replace('/^\d{3}[ -]/', '', (string)$ln);
            $ln = trim((string)$ln);
            if ($ln === '') continue;

            $parts = preg_split('/\s+/', $ln, 2);
            $key = strtoupper($parts[0] ?? '');
            $val = $parts[1] ?? true;

            if ($key !== '') $caps[$key] = $val;
        }
        return Sogerien::Debager()->capture_return($caps, __CLASS__, __FUNCTION__);
    }

    private function dotStuff(string $data): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $data = preg_replace('/\r\n\./', "\r\n..", $data);
        if (str_starts_with($data, '.')) $data = '.' . $data;
        return Sogerien::Debager()->capture_return((string)$data, __CLASS__, __FUNCTION__);
    }

    private function sanitizePath(string $email): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return(str_replace(['<', '>', "\r", "\n"], '', trim($email)), __CLASS__, __FUNCTION__);
}

    private function assertConfigured(): void
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (trim($this->host) === '') throw new \RuntimeException('SMTP host is empty');
        if ($this->port <= 0 || $this->port > 65535) throw new \RuntimeException('SMTP port is invalid: ' . $this->port);
        if (!in_array($this->encryption, ['none', 'tls', 'ssl'], true)) throw new \RuntimeException('SMTP encryption must be none|tls|ssl');
        if ($this->timeout <= 0) throw new \RuntimeException('SMTP timeout must be > 0');
}

    private function getHeloHost(): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($this->heloHost && trim($this->heloHost) !== '') return   Sogerien::Debager()->capture_return(trim($this->heloHost), __CLASS__, __FUNCTION__);
        $h = gethostname();
        if (is_string($h) && $h !== '') return   Sogerien::Debager()->capture_return($h, __CLASS__, __FUNCTION__);
        return   Sogerien::Debager()->capture_return('localhost', __CLASS__, __FUNCTION__);
}

    // -------------------- Addresses + Headers --------------------

    private function normalizeRecipients($input): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if ($input === null) return   Sogerien::Debager()->capture_return([], __CLASS__, __FUNCTION__);
        if (is_string($input)) $input = [$input];
        if (!is_array($input)) throw new \InvalidArgumentException('Recipients must be string or array');

        $out = [];
        foreach ($input as $item) {
            if (is_string($item)) {
                $email = trim($item);
                if ($email === '') continue;
                $out[] = ['email' => $email];
            } elseif (is_array($item)) {
                $email = $item['email'] ?? '';
                $email = is_string($email) ? trim($email) : '';
                if ($email === '') continue;
                $name = $item['name'] ?? null;
                $name = is_string($name) ? $name : null;
                $out[] = ['email' => $email, 'name' => $name];
            }
        }

        // de-dup by email
        $uniq = [];
        $seen = [];
        foreach ($out as $r) {
            $k = strtolower($r['email']);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $uniq[] = $r;
        }
        return   Sogerien::Debager()->capture_return($uniq, __CLASS__, __FUNCTION__);
}

    private function normalizeSingleAddress($input, string $label): array
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (is_string($input)) {
            $email = trim($input);
            if ($email === '') throw new \InvalidArgumentException("$label email is empty");
            return   Sogerien::Debager()->capture_return(['email' => $email], __CLASS__, __FUNCTION__);
        }
        if (is_array($input)) {
            $email = $input['email'] ?? '';
            $email = is_string($email) ? trim($email) : '';
            if ($email === '') throw new \InvalidArgumentException("$label email is required");
            $name = $input['name'] ?? null;
            $name = is_string($name) ? $name : null;
            return   Sogerien::Debager()->capture_return(['email' => $email, 'name' => $name], __CLASS__, __FUNCTION__);
        }
        throw new \InvalidArgumentException("$label must be string or array");
}

    private function formatAddress(string $email, ?string $name): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $email = trim($email);
        if ($name === null || $name === '') return   Sogerien::Debager()->capture_return($email, __CLASS__, __FUNCTION__);

        $nameEnc = $this->encodeHeader($name);
        $nameEnc = $this->maybeQuoteDisplayName($nameEnc);
        return   Sogerien::Debager()->capture_return($nameEnc . ' <' . $email . '>', __CLASS__, __FUNCTION__);
}

    private function formatAddressList(array $list): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $out = [];
        foreach ($list as $r) {
            $out[] = $this->formatAddress($r['email'], $r['name'] ?? null);
        }
        return   Sogerien::Debager()->capture_return(implode(', ', $out), __CLASS__, __FUNCTION__);
}

    private function maybeQuoteDisplayName(string $name): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        // already encoded-word
        if (str_starts_with($name, '=?') && str_ends_with($name, '?=')) return   Sogerien::Debager()->capture_return($name, __CLASS__, __FUNCTION__);

        // quote if contains specials/whitespace
        if (preg_match('/[",<>@;:\\\\\(\)\[\]\.]/', $name) || preg_match('/\s/', $name)) {
            $name = str_replace('"', '\"', $name);
            return Sogerien::Debager()->capture_return('"' . $name . '"', __CLASS__, __FUNCTION__);
        }
        return Sogerien::Debager()->capture_return($name, __CLASS__, __FUNCTION__);
    }

    private function encodeHeader(string $value): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } if ($value === '') return Sogerien::Debager()->capture_return('', __CLASS__, __FUNCTION__);
        if (preg_match('/^[\x20-\x7E]*$/', $value)) return Sogerien::Debager()->capture_return($value, __CLASS__, __FUNCTION__); // ASCII
        return Sogerien::Debager()->capture_return('=?UTF-8?B?' . base64_encode($value) . '?=', __CLASS__, __FUNCTION__);
}

    private function qpEncode(string $text): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $text = $this->normalizeEol($text);
        $enc = quoted_printable_encode($text);
        return  Sogerien::Debager()->capture_return($this->normalizeEol($enc), __CLASS__, __FUNCTION__);
}

    private function normalizeEol(string $s): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        return Sogerien::Debager()->capture_return(str_replace("\n", "\r\n", $s), __CLASS__, __FUNCTION__);
    }

    private function stripQuotes(string $s): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } return Sogerien::Debager()->capture_return(trim($s, "\" \t\r\n"), __CLASS__, __FUNCTION__);
}

    private function toAsciiFallback(string $s): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $s = trim($s);
        if ($s === '') return   Sogerien::Debager()->capture_return('file', __CLASS__, __FUNCTION__);
        if (preg_match('/^[\x20-\x7E]+$/', $s)) return Sogerien::Debager()->capture_return($s, __CLASS__, __FUNCTION__);
        $out = preg_replace('/[^\x20-\x7E]+/', '_', $s);
        $out = trim((string)$out);
        return Sogerien::Debager()->capture_return($out !== '' ? $out : 'file', __CLASS__, __FUNCTION__);
}

    /**
     * @throws \Random\RandomException
     */
    private function boundary(string $prefix): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        return   Sogerien::Debager()->capture_return($prefix . '_' . bin2hex(random_bytes(12)), __CLASS__, __FUNCTION__);
}

    /**
     * @throws \Random\RandomException
     */
    private function makeMessageId(string $fromEmail): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        $domain = 'localhost';
        if (str_contains($fromEmail, '@')) $domain = substr(strrchr($fromEmail, '@'), 1);
        return   Sogerien::Debager()->capture_return(bin2hex(random_bytes(10)) . '.' . time() . '@' . $domain, __CLASS__, __FUNCTION__);
}

    // -------------------- DKIM --------------------

    private function buildDkimHeader(array $headersAssoc, string $body): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } 
        if (!$this->dkimDomain || !$this->dkimSelector || !$this->dkimPrivateKeyPem) {
            throw new \RuntimeException('DKIM not configured');
        }

        // safe minimal set
        $signHeaders = ['from','to','subject','date','message-id','mime-version','content-type','content-transfer-encoding'];

        $present = [];
        foreach ($headersAssoc as $k => $v) {
            $lk = strtolower($k);
            if (in_array($lk, $signHeaders, true)) $present[$lk] = $k;
        }
        if (!isset($present['from'])) throw new \RuntimeException('DKIM requires From header');

        $hList = [];
        foreach ($signHeaders as $hk) if (isset($present[$hk])) $hList[] = $hk;

        $bh = base64_encode(hash('sha256', $this->canonicalizeBody($body, $this->dkimBodyCanon), true));

        $dkim = 'v=1; a=rsa-sha256; c=' . $this->dkimHeaderCanon . '/' . $this->dkimBodyCanon .
            '; d=' . $this->dkimDomain .
            '; s=' . $this->dkimSelector .
            '; t=' . time() .
            '; h=' . implode(':', $hList) .
            '; bh=' . $bh .
            '; b=';

        $dkimFolded = $this->foldDkimValue($dkim);

        $signingData = '';
        foreach ($hList as $hk) {
            $origKey = $present[$hk];
            $signingData .= $this->canonicalizeHeaderLine($origKey, $headersAssoc[$origKey], $this->dkimHeaderCanon) . "\r\n";
        }
        $signingData .= $this->canonicalizeHeaderLine('DKIM-Signature', $dkimFolded, $this->dkimHeaderCanon);

        $pkey = openssl_pkey_get_private($this->dkimPrivateKeyPem);
        if ($pkey === false) throw new \RuntimeException('Invalid DKIM private key PEM');

        $sig = '';
        $ok = openssl_sign($signingData, $sig, $pkey, OPENSSL_ALGO_SHA256);
        if (!$ok) throw new \RuntimeException('openssl_sign failed for DKIM');

        $b = rtrim(chunk_split(base64_encode($sig), 73, "\r\n\t"));
        return Sogerien::Debager()->capture_return($this->foldDkimValue($dkim . $b), __CLASS__, __FUNCTION__);
    }

    private function foldDkimValue(string $v): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $v = $this->normalizeEol($v);
        $v = str_replace("\r\n", '', $v);

        $parts = array_map('trim', explode(';', $v));
        $out = '';
        foreach ($parts as $i => $p) {
            if ($p === '') continue;
            $out .= ($i === 0) ? ($p . ';') : ("\r\n\t" . $p . ';');
        }
        return Sogerien::Debager()->capture_return(rtrim($out), __CLASS__, __FUNCTION__);
    }

    private function canonicalizeBody(string $body, string $mode): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $body = $this->normalizeEol($body);

        if ($mode === 'relaxed') {
            $lines = explode("\r\n", $body);
            foreach ($lines as &$ln) {
                $ln = preg_replace('/[ \t]+/', ' ', (string)$ln);
                $ln = rtrim((string)$ln, " \t");
            }
            unset($ln);
            $body = implode("\r\n", $lines);
        }

        return Sogerien::Debager()->capture_return((string)preg_replace("/(\r\n)*$/", "\r\n", $body), __CLASS__, __FUNCTION__);
    }

    private function canonicalizeHeaderLine(string $name, string $value, string $mode): string
    { if (Sogerien::$debag) { Sogerien::Debager()->log_input(__CLASS__, __FUNCTION__, func_get_args()); } $nameLower = strtolower($name);

        $value = $this->normalizeEol($value);
        $value = preg_replace("/\r\n[ \t]+/", ' ', (string)$value);

        if ($mode === 'relaxed') {
            $value = preg_replace('/[ \t]+/', ' ', (string)$value);
            $value = trim((string)$value, " \t");
            return   Sogerien::Debager()->capture_return($nameLower . ':' . $value, __CLASS__, __FUNCTION__);
        }
        return   Sogerien::Debager()->capture_return($name . ':' . $value, __CLASS__, __FUNCTION__);
    }
}
