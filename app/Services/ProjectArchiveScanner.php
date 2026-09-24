<?php

namespace App\Services;

use ZipArchive;

class ProjectArchiveScanner
{
    private const int MAX_BYTES_TO_READ = 262_144;

    private const int MAX_ENTRY_BYTES = 104_857_600;

    private const int MAX_PHP_FILES = 100;

    private const int MAX_REPORTED_ISSUES = 25;

    private const array DANGEROUS_PHP_PATTERNS = [
        'eval' => ['/\\beval\\s*\\(/i', 'high'],
        'assert' => ['/\\bassert\\s*\\(/i', 'medium'],
        'base64_decode' => ['/\\bbase64_decode\\s*\\(/i', 'medium'],
        'command-exec' => ['/\\b(shell_exec|system|exec|passthru|proc_open|popen)\\s*\\(/i', 'medium'],
    ];

    private const array LEAKED_CREDENTIAL_PATTERNS = [
        'stripe-live-key' => '/sk_live_[A-Za-z0-9]{10,}/',
        'aws-access-key' => '/AKIA[0-9A-Z]{16}/',
        'github-token' => '/ghp_[A-Za-z0-9]{20,}/',
        'slack-token' => '/xox[bap]-[A-Za-z0-9-]{10,}/',
    ];

    /**
     * Scan a zip archive and return a moderation report.
     *
     * @return array{verdict: string, files: int, issues: array<int, array{severity: string, rule: string, path: string|null, message: string}>, issues_truncated: bool, scanned_at: string}
     */
    public function scan(string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            return $this->report([
                $this->issue('high', 'unreadable-archive', null, 'The archive could not be read.'),
            ], 0);
        }

        /** @var array<int, array{severity: string, rule: string, path: string|null, message: string}> $issues */
        $issues = [];
        $phpFiles = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);

            if ($stat === false) {
                continue;
            }

            $name = (string) $stat['name'];
            $size = (int) $stat['size'];

            $issues = [...$issues, ...$this->inspectEntryName($name)];

            if ($size > self::MAX_ENTRY_BYTES) {
                $issues[] = $this->issue(
                    'high',
                    'oversized-entry',
                    $name,
                    'An entry is larger than 100 MB; highly unusual for a source archive.',
                );
            }

            if ($this->isDotEnv($name)) {
                $issues = [...$issues, ...$this->inspectDotEnv($zip, $index, $name)];
            } elseif ($this->isPhpFile($name)) {
                $phpFiles[] = $name;
            }
        }

        if (count($phpFiles) > self::MAX_PHP_FILES) {
            $issues[] = $this->issue(
                'medium',
                'many-scripts',
                null,
                'The archive contains more than 100 PHP files.',
            );
        }

        foreach ($phpFiles as $name) {
            $issues = [...$issues, ...$this->inspectPhpFile($zip, $name)];
        }

        $files = $zip->numFiles;

        $zip->close();

        return $this->report($issues, $files);
    }

    /**
     * @param  array<int, array{severity: string, rule: string, path: string|null, message: string}>  $issues
     * @return array{verdict: string, files: int, issues: array<int, array{severity: string, rule: string, path: string|null, message: string}>, issues_truncated: bool, scanned_at: string}
     */
    private function report(array $issues, int $files): array
    {
        $issues = array_values($issues);

        return [
            'verdict' => $issues === [] ? 'clean' : 'flagged',
            'files' => $files,
            'issues' => array_slice($issues, 0, self::MAX_REPORTED_ISSUES),
            'issues_truncated' => count($issues) > self::MAX_REPORTED_ISSUES,
            'scanned_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{severity: string, rule: string, path: string|null, message: string}>
     */
    private function inspectEntryName(string $name): array
    {
        $issues = [];

        if (str_contains($name, '/../') || str_starts_with($name, '../')) {
            $issues[] = $this->issue('high', 'path-traversal', $name, 'Entry escapes the project directory (../ traversal).');
        }

        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $name) === 1) {
            $issues[] = $this->issue('low', 'absolute-path', $name, 'Entry references an absolute filesystem path.');
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity: string, rule: string, path: string|null, message: string}>
     */
    private function inspectDotEnv(ZipArchive $zip, int $index, string $name): array
    {
        $content = $this->readEntry($zip, $index);

        if ($content === '') {
            return [];
        }

        $issues = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(\S+)\s*$/', $line, $match) !== 1) {
                continue;
            }

            $key = $match[1];
            $value = $match[2];

            if (! $this->looksLikeSensitiveKey($key)) {
                continue;
            }

            if (preg_match('/^(?:your_|example_|changeme|xxx|\.{3}|<.+>)/i', $value) === 1) {
                continue;
            }

            $issues[] = $this->issue(
                'high',
                'env-secret',
                $name,
                "Environment variable \"{$key}\" appears to contain a real secret.",
            );
        }

        foreach (self::LEAKED_CREDENTIAL_PATTERNS as $rule => $pattern) {
            if (preg_match($pattern, $content) === 1) {
                $issues[] = $this->issue('high', $rule, $name, 'Entry contains a leaked credential pattern.');
            }
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity: string, rule: string, path: string|null, message: string}>
     */
    private function inspectPhpFile(ZipArchive $zip, string $name): array
    {
        $index = $zip->locateName($name);

        if ($index === false) {
            return [];
        }

        $content = $this->readEntry($zip, $index);

        if ($content === '') {
            return [];
        }

        $issues = [];

        foreach (self::DANGEROUS_PHP_PATTERNS as $rule => [$pattern, $severity]) {
            if (preg_match($pattern, $content) === 1) {
                $issues[] = $this->issue(
                    $severity,
                    'dangerous-'.$rule,
                    $name,
                    "File calls {$rule}(...) which may execute code at runtime.",
                );
            }
        }

        return $issues;
    }

    private function readEntry(ZipArchive $zip, int $index): string
    {
        $content = $zip->getFromIndex($index, self::MAX_BYTES_TO_READ);

        return $content === false ? '' : $content;
    }

    private function isDotEnv(string $name): bool
    {
        $basename = basename($name);

        return $basename === '.env'
            || (str_starts_with($basename, '.env.') && ! str_starts_with($basename, '.env.example'));
    }

    private function isPhpFile(string $name): bool
    {
        return preg_match('/\.php$/i', $name) === 1;
    }

    private function looksLikeSensitiveKey(string $key): bool
    {
        if (preg_match('/^(?:db_|database_|mail_|stripe_|paypal_|aws_|azure_)password$/i', $key) === 1) {
            return true;
        }

        return preg_match('/(?:secret|password|passwd|api[_-]?key|auth[_-]?token|access[_-]?token|private[_-]?key)/i', $key) === 1
            || preg_match('/_(?:secret|token|password)$/i', $key) === 1;
    }

    /**
     * @return array{severity: string, rule: string, path: string|null, message: string}
     */
    private function issue(string $severity, string $rule, ?string $path, string $message): array
    {
        return [
            'severity' => $severity,
            'rule' => $rule,
            'path' => $path,
            'message' => $message,
        ];
    }
}
