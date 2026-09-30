<?php

namespace Tests\Feature\GAP059;

use App\Console\Commands\ConfigureSMTP;
use Dotenv\Parser\Parser;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * GAP-059: smtp:configure must write MAIL_* values that .env parsing reads
 * back exactly, must take the password from STDIN, and must refuse a
 * password given as a command-line argument.
 */
final class ConfigureSmtpWritesEnvSafelyTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/gap059-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
        file_put_contents($this->dir . '/.env', "APP_NAME=Zena\nMAIL_PASSWORD=old\nOTHER=\"keep me\"\n");
        chmod($this->dir . '/.env', 0640);
        $this->app->useEnvironmentPath($this->dir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    /** @return array{0:int,1:string} */
    private function runCommand(array $options, ?string $stdin = null): array
    {
        $command = $this->app->make(ConfigureSMTP::class);
        $command->setLaravel($this->app);
        $input = new ArrayInput($options, $command->getDefinition());
        $input->setInteractive(false);
        if ($stdin !== null) {
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $stdin);
            rewind($stream);
            $input->setStream($stream);
        }
        $output = new BufferedOutput();
        $code = $command->run($input, $output);

        return [$code, $output->fetch()];
    }

    /** @return array<string, string|null> */
    private function parsedEnv(): array
    {
        $values = [];
        foreach ((new Parser())->parse((string) file_get_contents($this->dir . '/.env')) as $entry) {
            $value = $entry->getValue();
            $values[$entry->getName()] = $value->isDefined() ? $value->get()->getChars() : null;
        }

        return $values;
    }

    public static function trickyPasswords(): array
    {
        return [
            'slash' => ['ab/cd'],
            'ampersand' => ['ab&cd'],
            'double quote' => ['pl"q'],
            'regex backreference' => ['p$1w'],
            'space and hash' => ['a b#c'],
            'backslash' => ['back\\slash'],
            'dollar brace' => ['x${HOME}y'],
        ];
    }

    /** @dataProvider trickyPasswords */
    public function test_password_from_stdin_round_trips_through_dotenv_parsing(string $password): void
    {
        [$code, $out] = $this->runCommand([
            '--provider' => 'custom',
            '--host' => 'smtp.example.test',
            '--port' => '587',
            '--encryption' => 'tls',
            '--username' => 'mailer@example.test',
            '--from-address' => 'noreply@example.test',
            '--from-name' => 'Zena & Co "HQ"',
            '--password-stdin' => true,
        ], $password . "\n");

        $this->assertSame(0, $code, $out);
        $env = $this->parsedEnv();
        $this->assertSame($password, $env['MAIL_PASSWORD']);
        $this->assertSame('Zena & Co "HQ"', $env['MAIL_FROM_NAME']);
        $this->assertSame('smtp.example.test', $env['MAIL_HOST']);
        $this->assertSame('Zena', $env['APP_NAME']);
        $this->assertSame('keep me', $env['OTHER']);
        $this->assertSame('0640', substr(sprintf('%o', fileperms($this->dir . '/.env')), -4));
    }

    public function test_password_on_the_command_line_is_refused_and_nothing_is_written(): void
    {
        $before = file_get_contents($this->dir . '/.env');

        [$code, $out] = $this->runCommand([
            '--provider' => 'gmail',
            '--username' => 'mailer@example.test',
            '--password' => 'visible-in-ps',
            '--from-address' => 'noreply@example.test',
            '--from-name' => 'Zena',
        ]);

        $this->assertNotSame(0, $code, $out);
        $this->assertStringContainsString('--password-stdin', $out);
        $this->assertSame($before, file_get_contents($this->dir . '/.env'));
    }
}
