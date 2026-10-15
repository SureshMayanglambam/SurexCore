<?php

namespace App\Libraries;

/**
 * IPs blocked from the admin panel, kept in plain text files so they can also be edited by hand:
 *
 *   writable/security/blocked-ips.txt   added automatically after repeated failed logins (cms.ipBlockAfter);
 *                                       delete a line (or use Settings → Security) to unblock that IP
 *   writable/security/allowed-ips.txt   never blocked, e.g. your office
 *
 * One IP per line; text after "#" is a note. writable/ is never served to the web, and deploy
 * packages don't overwrite it, so a server keeps its own lists.
 */
class IpBlocklist
{
    public const BLOCKED = WRITEPATH . 'security' . DIRECTORY_SEPARATOR . 'blocked-ips.txt';
    public const ALLOWED = WRITEPATH . 'security' . DIRECTORY_SEPARATOR . 'allowed-ips.txt';

    private const HEADERS = [
        self::BLOCKED => "# IPs blocked from the admin panel, one per line. Delete a line to unblock that IP.\n"
            . "# Added automatically after repeated failed logins (cms.ipBlockAfter). Text after # is a note.\n",
        self::ALLOWED => "# IPs that are never blocked from the admin panel (e.g. your office), one per line.\n"
            . "# Text after # is a note.\n",
    ];

    public function isBlocked(string $ip): bool
    {
        $ip = self::normalize($ip);

        return $ip !== null && isset($this->read(self::BLOCKED)[$ip]) && ! $this->isAllowed($ip);
    }

    public function isAllowed(string $ip): bool
    {
        $ip = self::normalize($ip);

        return $ip !== null && isset($this->read(self::ALLOWED)[$ip]);
    }

    /**
     * Add the IP to the block list. False when it is on the allow list, invalid, or already blocked.
     */
    public function block(string $ip, string $note): bool
    {
        $ip = self::normalize($ip);
        if ($ip === null || $this->isAllowed($ip)) {
            return false;
        }

        $list = $this->read(self::BLOCKED);
        if (isset($list[$ip])) {
            return false;
        }
        $list[$ip] = date('Y-m-d H:i') . '  ' . $note;

        return $this->write(self::BLOCKED, $list);
    }

    public function unblock(string $ip): bool
    {
        $ip   = self::normalize($ip) ?? $ip;
        $list = $this->read(self::BLOCKED);
        unset($list[$ip]);

        return $this->write(self::BLOCKED, $list);
    }

    /**
     * @return array<string, string> ip => note
     */
    public function blocked(): array
    {
        return $this->read(self::BLOCKED);
    }

    /**
     * @return array<string, string> ip => note
     */
    public function allowed(): array
    {
        return $this->read(self::ALLOWED);
    }

    /**
     * @param array<string, string> $list ip => note (IPs already validated)
     */
    public function setAllowed(array $list): bool
    {
        return $this->write(self::ALLOWED, $list);
    }

    /**
     * Canonical form of a valid IPv4/IPv6 address (so "::1" and "0:0::1" match), or null.
     */
    public static function normalize(string $ip): ?string
    {
        $ip = trim($ip);

        return filter_var($ip, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($ip)) : null;
    }

    /**
     * @return array<string, string> ip => note
     */
    private function read(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $list = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$value, $note] = array_pad(explode('#', $line, 2), 2, '');
            if (($ip = self::normalize($value)) !== null) {
                $list[$ip] = trim($note);
            }
        }

        return $list;
    }

    private function write(string $file, array $list): bool
    {
        $dir = dirname($file);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            return false;
        }
        if (! is_file($dir . DIRECTORY_SEPARATOR . 'index.html')) {
            @file_put_contents($dir . DIRECTORY_SEPARATOR . 'index.html', "<!DOCTYPE html>\n<html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>\n");
        }

        $body = self::HEADERS[$file];
        foreach ($list as $ip => $note) {
            $body .= $ip . ($note !== '' ? str_repeat(' ', max(1, 40 - strlen($ip))) . '# ' . str_replace(["\r", "\n"], ' ', $note) : '') . "\n";
        }

        return file_put_contents($file, $body, LOCK_EX) !== false;
    }
}
