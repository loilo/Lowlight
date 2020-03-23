<?php namespace Lowlight\Test;

use PHPUnit\Framework\TestCase;
use Lowlight\Lowlight;

class UnitTest extends TestCase
{
    use Util\HighlightAutoloader;

    public function testSequential()
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

    public function testNested()
    {
        $code = 'function() { return true; }';

        $ll = new Lowlight();
        $ll->mock
            ->shouldReceive('highlight')
            ->with('php', $code, true, null)
            ->andReturn((object) [
                'value' => '<span class="function"><span class="keyword">function</span><span class="params">()</span> </span>{ <span class="keyword">return</span> <span class="keyword">true</span>; }'
            ]);

        $this->assertSame(
            "\033[0m\033[39m\033[33mfunction\033[39m\033[0m\033[39m\033[0m()\033[39m\033[0m \033[39m{ \033[33mreturn\033[39m \033[33mtrue\033[39m; }",
            $ll->highlight('php', $code)->value
        );
    }

    public function testAutoSequential()
    {
        $code = 'echo "foo";';

        $ll = new Lowlight();
        $ll->mock
            ->shouldReceive('highlightAuto')
            ->with($code, null)
            ->andReturn((object) [
                'value' => '<span class="keyword">echo</span> <span class="string">"foo"</span>;'
            ]);

        $this->assertSame(
            "\033[33mecho\033[39m \033[34m\"foo\"\033[39m;",
            $ll->highlightAuto($code)->value
        );
    }

    public function testAutoNested()
    {
        $code = 'function() { return true; }';

        $ll = new Lowlight();
        $ll->mock
            ->shouldReceive('highlightAuto')
            ->with($code, null)
            ->andReturn((object) [
                'value' => '<span class="function"><span class="keyword">function</span><span class="params">()</span> </span>{ <span class="keyword">return</span> <span class="keyword">true</span>; }'
            ]);

        $this->assertSame(
            "\033[0m\033[39m\033[33mfunction\033[39m\033[0m\033[39m\033[0m()\033[39m\033[0m \033[39m{ \033[33mreturn\033[39m \033[33mtrue\033[39m; }",
            $ll->highlightAuto($code)->value
        );
    }
}
