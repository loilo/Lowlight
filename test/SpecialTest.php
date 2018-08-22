<?php namespace Lowlight\Test;

use PHPUnit\Framework\TestCase;
use Lowlight\Lowlight;

class SpecialTest extends TestCase
{
    use ParseExpected;

    private function getTestData($name)
    {
        return (object) array(
            'code' => file_get_contents(__DIR__ . DIRECTORY_SEPARATOR .
                'special' . DIRECTORY_SEPARATOR . "{$name}.txt"),
            'expected' => file_get_contents(__DIR__ . DIRECTORY_SEPARATOR .
                'special' . DIRECTORY_SEPARATOR . "{$name}.expect.txt"),
        );
    }

    public function testLanguageAlias()
    {
        $ll = new Lowlight();

        $data = $this->getTestData('languagealias');
        $actual = $ll->highlight('js', $data->code);

        $this->assertEquals(
            $this->parseExpected($data->expected, $ll),
            $actual
        );
    }

    public function testSubLanguage()
    {
        $ll = new Lowlight();

        $data = $this->getTestData('sublanguages');
        $actual = $ll->highlight('xml', $data->code);

        $this->assertEquals(
            $this->parseExpected($data->expected, $ll),
            $actual
        );
    }

    public function testWindowsCRLF()
    {
        $ll = new Lowlight();

        $data = $this->getTestData('line-endings.crlf');
        $actual = $ll->highlight('js', $data->code);

        $this->assertEquals(
            $this->parseExpected($data->expected, $ll),
            $actual
        );
    }
}
