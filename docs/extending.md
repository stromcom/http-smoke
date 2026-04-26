# Extending stromcom/http-smoke

The package is built around small, focused interfaces. Anything you don't like
about the defaults can be replaced without forking.

## Variable sources

Where do `{KEY}` placeholders come from? Implement
`Stromcom\HttpSmoke\Variable\VariableSourceInterface`:

```php
final class AwsSecretsSource implements VariableSourceInterface
{
    public function __construct(private SecretsManagerClient $client) {}

    public function get(string $name): ?string
    {
        try {
            return $this->client->getSecretValue(['SecretId' => $name])['SecretString'];
        } catch (\Throwable) {
            return null;
        }
    }

    public function all(): array
    {
        return [];  // optional — only needed for diagnostics
    }
}
```

Register it via `smoke.config.php`:

```php
$config->extraVariableSources[] = new AwsSecretsSource($client);
```

Sources are queried in reverse order they were added — last added wins.

## Custom assertions

Implement `Stromcom\HttpSmoke\Assertion\AssertionInterface`:

```php
final class XmlValidAssertion implements AssertionInterface
{
    public function evaluate(\Stromcom\HttpSmoke\Http\Response $response): ?string
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($response->body);

        return $doc === false ? 'Body is not valid XML' : null;
    }
}
```

You can wire it in by adding it to a request builder via the public callback
hook:

```php
->expect(fn ($r) => (new XmlValidAssertion())->evaluate($r))
```

## Custom captures

```php
use Stromcom\HttpSmoke\Capture\CaptureInterface;
use Stromcom\HttpSmoke\Http\Response;

final class CookieCapture implements CaptureInterface
{
    public function __construct(private string $name, private string $cookieName) {}

    public function name(): string { return $this->name; }

    public function extract(Response $response): ?string
    {
        $cookies = $response->getHeader('set-cookie');
        // parse...
        return $value;
    }
}
```

## Custom reporter

```php
use Stromcom\HttpSmoke\Reporting\ReporterInterface;

final class SlackReporter implements ReporterInterface
{
    public function onStart(array $groups, int $totalTests): void {}
    public function onResult(\Stromcom\HttpSmoke\Execution\Result $r, int $c, int $t): void {}
    public function onEnd(\Stromcom\HttpSmoke\Execution\Report $report): void
    {
        if (!$report->isSuccessful()) {
            // post summary to Slack
        }
    }
}

// smoke.config.php
$config->extraReporters[] = new SlackReporter();
```

## Custom HTTP client

```php
use Stromcom\HttpSmoke\Http\HttpClientInterface;
use Stromcom\HttpSmoke\Container\Container;

$config->configureContainer = function (Container $container): void {
    $container->set(HttpClientInterface::class, fn () => new MyClient());
};
```

The default `CurlMultiClient` ships with the package and requires only
`ext-curl`. A Guzzle adapter is planned for v1.1.
