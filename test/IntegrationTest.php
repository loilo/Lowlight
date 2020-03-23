<?php namespace Lowlight\Test;

use PHPUnit\Framework\TestCase;
use Lowlight\Lowlight;

class IntegrationTest extends TestCase
{
    public function testSequential()
    {
        $ll = new Lowlight();

        $this->assertSame(
            "\033[33mecho\033[39m \033[34m\"foo\"\033[39m;",
            $ll->highlight('php', 'echo "foo";')->value
        );
    }

    public function testNested()
    {
        $ll = new Lowlight();

        $this->assertSame(
            "\033[0m\033[39m\033[33mfunction\033[39m\033[0m\033[39m\033[0m()\033[39m\033[0m \033[39m{ \033[33mreturn\033[39m \033[33mtrue\033[39m; }",
            $ll->highlight('php', 'function() { return true; }')->value
        );
    }

    public function testAutoSequential()
    {
        $ll = new Lowlight();
        $result = $ll->highlightAuto('echo "foo";');

        $this->assertSame('php', $result->language);

        $this->assertSame(
            "\033[33mecho\033[39m \033[34m\"foo\"\033[39m;",
            $result->value
        );
    }

    public function testAutoNested()
    {
        $ll = new Lowlight();
        $result = $ll->highlightAuto('function() { return true; }');

        $this->assertSame('javascript', $result->language);

        $this->assertSame(
            "\033[0m\033[39m\033[33mfunction\033[39m\033[0m(\033[39m\033[0m\033[39m\033[0m) \033[39m{ \033[33mreturn\033[39m \033[92mtrue\033[39m; }",
            $result->value
        );
    }
}
