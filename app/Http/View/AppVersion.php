<?php

declare(strict_types=1);

namespace Wbpms\Http\View;

/**
 * Reads the current git commit information at runtime so collaborators
 * can verify they are running the same version as the repo.
 *
 * Falls back gracefully when git is not available (production deploys
 * that don't ship the .git directory).
 */
final class AppVersion
{
    private static ?string $hash      = null;
    private static ?string $shortHash = null;
    private static ?string $branch    = null;
    private static ?string $date      = null;

    /**
     * Full 40-char commit hash, or 'unknown'.
     */
    public static function hash(): string
    {
        self::load();
        return self::$hash ?? 'unknown';
    }

    /**
     * 7-char short hash — the standard "version token" shown in the UI.
     */
    public static function short(): string
    {
        self::load();
        return self::$shortHash ?? 'unknown';
    }

    /**
     * Current branch name, or 'unknown'.
     */
    public static function branch(): string
    {
        self::load();
        return self::$branch ?? 'unknown';
    }

    /**
     * Commit date as 'YYYY-MM-DD', or empty string.
     */
    public static function date(): string
    {
        self::load();
        return self::$date ?? '';
    }

    /**
     * Human-readable label, e.g. "WBPMS-dev@f1c14fd (2026-09-03)"
     */
    public static function label(): string
    {
        self::load();
        $parts = [];
        if (self::$branch !== null && self::$branch !== 'HEAD') {
            $parts[] = self::$branch;
        }
        if (self::$shortHash !== null) {
            $parts[] = '@' . self::$shortHash;
        }
        $prefix = implode('', $parts) ?: 'unknown';
        $suffix = self::$date ? ' (' . self::$date . ')' : '';
        return $prefix . $suffix;
    }

    // -----------------------------------------------------------------------

    private static function load(): void
    {
        if (self::$hash !== null) {
            return; // already loaded
        }

        // Strategy 1: read from a pre-built version file (for production
        // deploys without a .git directory — generate with:
        //   git log -1 --format="%H %D %ci" > version.txt
        $versionFile = defined('APP_ROOT') ? APP_ROOT . '/version.txt' : dirname(__DIR__, 3) . '/version.txt';
        if (is_file($versionFile)) {
            self::parseVersionFile((string) file_get_contents($versionFile));
            return;
        }

        // Strategy 2: read .git/HEAD and the packed-refs / loose ref directly
        // (no exec() / shell_exec() needed — pure file reads).
        $gitDir = defined('APP_ROOT') ? APP_ROOT . '/.git' : dirname(__DIR__, 3) . '/.git';
        if (!is_dir($gitDir)) {
            return;
        }

        $headFile = $gitDir . '/HEAD';
        if (!is_file($headFile)) {
            return;
        }

        $head = trim((string) file_get_contents($headFile));

        // Detached HEAD → HEAD contains the hash directly
        if (preg_match('/^[0-9a-f]{40}$/', $head)) {
            self::$hash      = $head;
            self::$shortHash = substr($head, 0, 7);
            self::$branch    = 'HEAD';
            self::resolveDate($gitDir, $head);
            return;
        }

        // Symbolic ref → "ref: refs/heads/branch-name"
        if (!str_starts_with($head, 'ref: ')) {
            return;
        }

        $ref    = substr($head, 5); // e.g. refs/heads/WBPMS-dev
        $parts  = explode('/', $ref);
        self::$branch = end($parts);

        // Try loose ref first
        $refFile = $gitDir . '/' . $ref;
        if (is_file($refFile)) {
            $hash = trim((string) file_get_contents($refFile));
            if (preg_match('/^[0-9a-f]{40}$/', $hash)) {
                self::$hash      = $hash;
                self::$shortHash = substr($hash, 0, 7);
                self::resolveDate($gitDir, $hash);
                return;
            }
        }

        // Try packed-refs
        $packedRefs = $gitDir . '/packed-refs';
        if (is_file($packedRefs)) {
            foreach (explode("\n", (string) file_get_contents($packedRefs)) as $line) {
                $line = trim($line);
                if (str_ends_with($line, ' ' . $ref) || str_ends_with($line, "\t" . $ref)) {
                    $hash = explode(' ', $line)[0];
                    if (preg_match('/^[0-9a-f]{40}$/', $hash)) {
                        self::$hash      = $hash;
                        self::$shortHash = substr($hash, 0, 7);
                        self::resolveDate($gitDir, $hash);
                        return;
                    }
                }
            }
        }
    }

    /**
     * Try to read the commit date from .git/objects (loose object only).
     * Skip silently if not found — date is decorative, not critical.
     */
    private static function resolveDate(string $gitDir, string $hash): void
    {
        // Loose object path: .git/objects/AB/CDEFxxx…
        $objPath = $gitDir . '/objects/' . substr($hash, 0, 2) . '/' . substr($hash, 2);
        if (!is_file($objPath)) {
            return;
        }
        // Decompress and scan for "committer" or "author" line
        $raw = @gzuncompress((string) file_get_contents($objPath));
        if ($raw === false) {
            return;
        }
        // committer Name <email> TIMESTAMP OFFSET
        if (preg_match('/(?:committer|author)[^\n]+\s(\d{10})\s[+-]\d{4}/', $raw, $m)) {
            self::$date = date('Y-m-d', (int) $m[1]);
        }
    }

    private static function parseVersionFile(string $content): void
    {
        // Expected: "<hash> <ref_list> <iso_date>"
        // e.g. "f1c14fd... HEAD -> WBPMS-dev 2026-09-03 12:00:00 +0800"
        $parts = preg_split('/\s+/', trim($content), 3);
        if (!$parts || count($parts) < 1) {
            return;
        }
        $hash = $parts[0];
        if (preg_match('/^[0-9a-f]{7,40}$/', $hash)) {
            self::$hash      = str_pad($hash, 40, '0');
            self::$shortHash = substr($hash, 0, 7);
        }
        if (isset($parts[2]) && preg_match('/(\d{4}-\d{2}-\d{2})/', $parts[2], $m)) {
            self::$date = $m[1];
        }
        if (isset($parts[1]) && preg_match('/->\\s*(\\S+)/', $parts[1], $m)) {
            self::$branch = $m[1];
        }
    }
}
