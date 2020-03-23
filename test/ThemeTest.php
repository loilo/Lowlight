<?php namespace Lowlight\Test;

use PHPUnit\Framework\TestCase;
use Lowlight\Lowlight;

class ThemeTest extends TestCase
{
    use Util\HighlightAutoloader;

    public function testDefaultTheme()
    {
        $code = 'echo "foo";';

        $ll = new Lowlight();
        $ll->mock
            ->shouldReceive('highlight')
            ->with('php', $code, true, null)
            ->andReturn((object) [
                'value' => '<span class="keyword">echo</span> <span class="string">"foo"</span>;'
            ]);

        $this->assertSame(
            "\033[33mecho\033[39m \033[34m\"foo\"\033[39m;",
            $ll->highlight('php', $code)->value
        );
    }

    public function testCustomTheme()
    {
        $code = 'echo "foo";';

        $ll = new Lowlight();
        $ll->theme['keyword'] = 'red';
        $ll->theme['string'] = [0, 255, 0];

        $ll->mock
            ->shouldReceive('highlight')
            ->with('php', $code, true, null)
            ->andReturn((object) [
                'value' => '<span class="keyword">echo</span> <span class="string">"foo"</span>;'
            ]);

        $this->assertSame(
            "\033[31mecho\033[39m \033[38;2;0;255;0m\"foo\"\033[39m;",
            $ll->highlight('php', $code)->value
        );
    }
}
