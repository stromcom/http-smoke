<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Tests\Unit\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Stromcom\HttpSmoke\Exception\ConfigException;
use Stromcom\HttpSmoke\Variable\Source\EnvFileSource;

#[CoversClass(EnvFileSource::class)]
final class EnvFileSourceTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    #[Test]
    #[TestWith(['FOO', 'bar'])]
    #[TestWith(['QUOTED', 'hello world'])]
    #[TestWith(['SINGLE', 'value'])]
    #[TestWith(['BAZ', 'qux'])]
    public function returns_value_for_known_keys(string $key, string $expected): void
    {
        $source = new EnvFileSource($this->fixtureFile());

        self::assertSame($expected, $source->get($key));
    }

    #[Test]
    #[TestWith(['EMPTY'])]
    #[TestWith(['SSM_REF'])]
    #[TestWith(['UNKNOWN'])]
    public function returns_null_for_empty_unresolved_or_missing_keys(string $key): void
    {
        $source = new EnvFileSource($this->fixtureFile());

        self::assertNull($source->get($key));
    }

    #[Test]
    public function throws_config_exception_when_file_does_not_exist(): void
    {
        $this->expectException(ConfigException::class);

        new EnvFileSource(__DIR__ . '/does-not-exist.env');
    }

    private function fixtureFile(): string
    {
        return $this->tempFile(<<<'ENV'
            # comment
            FOO=bar
            QUOTED="hello world"
            SINGLE='value'
            EMPTY=
            SSM_REF=${ssm:/path}
            BAZ=qux
            ENV);
    }

    private function tempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'envtest_');
        if ($path === false) {
            self::fail('Failed to create temp file');
        }
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }
}
