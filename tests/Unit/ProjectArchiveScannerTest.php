<?php

namespace Tests\Unit;

use App\Services\ProjectArchiveScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ProjectArchiveScannerTest extends TestCase
{
    public function test_clean_archive_returns_a_clean_report(): void
    {
        $path = $this->writeArchive([
            'src/App.php' => '<?php echo "hello";',
            'README.md' => "# Demo\n",
        ]);

        $report = app(ProjectArchiveScanner::class)->scan($path);

        $this->assertSame('clean', $report['verdict']);
        $this->assertSame(2, $report['files']);
        $this->assertSame([], $report['issues']);
        unlink($path);
    }

    public function test_dotenv_with_real_password_is_flagged(): void
    {
        $path = $this->writeArchive([
            '.env' => "APP_KEY=base64:abcdef==\nDB_PASSWORD=hunter2\nAPP_NAME=Demo\n",
        ]);

        $report = app(ProjectArchiveScanner::class)->scan($path);

        $this->assertSame('flagged', $report['verdict']);
        $this->assertNotSame([], array_filter($report['issues'], function (array $issue): bool {
            return $issue['rule'] === 'env-secret';
        }));
        unlink($path);
    }

    public function test_dotenv_with_placeholder_values_is_not_flagged(): void
    {
        $path = $this->writeArchive([
            '.env' => "APP_KEY=base64:abcdef==\nDB_PASSWORD=your_password_here",
        ]);

        $report = app(ProjectArchiveScanner::class)->scan($path);

        $this->assertSame([], array_filter($report['issues'], function (array $issue): bool {
            return $issue['rule'] === 'env-secret';
        }));
        unlink($path);
    }

    public function test_env_example_is_ignored_but_real_env_is_flagged(): void
    {
        $path = $this->writeArchive([
            '.env.example' => "DB_PASSWORD=secret\n",
            '.env' => "DB_PASSWORD=secret\n",
        ]);

        $report = app(ProjectArchiveScanner::class)->scan($path);

        $this->assertNotSame([], array_filter($report['issues'], function (array $issue): bool {
            return $issue['rule'] === 'env-secret';
        }));
        $this->assertSame([], array_filter($report['issues'], function (array $issue): bool {
            return $issue['path'] === '.env.example';
        }));
        unlink($path);
    }

    public function test_leaked_stripe_live_key_is_flagged(): void
    {
        $path = $this->writeArchive([
            '.env' => 'STRIPE_SECRET_KEY='.'sk_'.'live_'.'testDummySecretKey00',
        ]);

        $report = app(ProjectArchiveScanner::class)->scan($path);

        $this->assertNotSame([], array_filter($report['issues'], function (array $issue): bool {
            return $issue['rule'] === 'stripe-live-key';
        }));
        unlink($path);
    }

    #[DataProvider('dangerousPhpSnippets')]
    public function test_dangerous_php_patterns_are_flagged(string $snippet, string $rule): void
    {
        $path = $this->writeArchive([
            'app/backdoor.php' => "<?php\n{$snippet}",
        ]);

        $report = app(ProjectArchiveScanner::class)->scan($path);

        $this->assertNotSame([], array_filter($report['issues'], function (array $issue) use ($rule): bool {
            return $issue['rule'] === $rule;
        }));
        unlink($path);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dangerousPhpSnippets(): iterable
    {
        yield 'eval' => ['eval($_GET["code"]);', 'dangerous-eval'];
        yield 'base64' => ['echo base64_decode(base64_encode("x"));', 'dangerous-base64_decode'];
        yield 'shell_exec' => ['shell_exec("whoami");', 'dangerous-command-exec'];
        yield 'system' => ['system("ls");', 'dangerous-command-exec'];
    }

    public function test_path_traversal_entries_are_flagged_high(): void
    {
        $path = $this->writeArchive([
            'src/App.php' => '<?php // ok',
            '../escape.php' => '<?php echo 1;',
        ]);

        $report = app(ProjectArchiveScanner::class)->scan($path);

        $this->assertNotSame([], array_filter($report['issues'], function (array $issue): bool {
            return $issue['rule'] === 'path-traversal' && $issue['severity'] === 'high';
        }));
        unlink($path);
    }

    public function test_absolute_path_entries_are_flagged(): void
    {
        $path = $this->writeArchive([
            '/etc/passwd' => 'root:x:0:0:root:/root:/bin/sh',
        ]);

        $report = app(ProjectArchiveScanner::class)->scan($path);

        $this->assertNotSame([], array_filter($report['issues'], function (array $issue): bool {
            return $issue['rule'] === 'absolute-path';
        }));
        unlink($path);
    }

    public function test_archive_with_many_php_files_is_flagged(): void
    {
        $entries = [];
        for ($i = 0; $i <= 100; $i++) {
            $entries["scripts/file{$i}.php"] = '<?php // noop';
        }

        $path = $this->writeArchive($entries);

        $report = app(ProjectArchiveScanner::class)->scan($path);

        $this->assertNotSame([], array_filter($report['issues'], function (array $issue): bool {
            return $issue['rule'] === 'many-scripts';
        }));
        unlink($path);
    }

    public function test_unreadable_archive_is_flagged(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'scan').'.zip';
        file_put_contents($path, 'definitely not a zip');

        $report = app(ProjectArchiveScanner::class)->scan($path);

        $this->assertSame('flagged', $report['verdict']);
        $this->assertSame('unreadable-archive', $report['issues'][0]['rule']);
        unlink($path);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function writeArchive(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'scan').'.zip';

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Could not create scanner fixture zip.');
        }

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $path;
    }
}
