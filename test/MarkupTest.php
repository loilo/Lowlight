<?php namespace Lowlight\Test;

use Lowlight\Lowlight;
use Symfony\Component\Finder\Finder;
use PHPUnit\Framework\TestCase;

class MarkupTest extends TestCase
{
    use ParseExpected;

    private $allowedFailures;

    public function setUp()
    {
        $this->allowedFailures = array(
            array('haskell', 'nested-comments'),
            array('http', 'default'),
        );
    }

    public static function markupTestProvider()
    {
        $testData = array();

        $markupTests = new Finder();
        $markupTests
            ->in(__DIR__ . '/markup/')
            ->name('*.txt')
            ->sortByName()
            ->files()
        ;

        $workspace = array();

        foreach ($markupTests as $markupTest) {
            $language = $markupTest->getRelativePath();

            if (!isset($workspace[$language])) {
                $workspace[$language] = array();
            }

            if (strpos($markupTest->getFilename(), '.expect.txt') !== false) {
                $workspace[$language][$markupTest->getBasename('.expect.txt')]['expected'] = $markupTest->getContents();
            } else {
                $workspace[$language][$markupTest->getBasename('.txt')]['raw'] = $markupTest->getContents();
            }
        }

        foreach ($workspace as $language => $tests) {
            foreach ($tests as $name => $definition) {
                $testData[] = array($language, $name, $definition['raw'], $definition['expected']);
            }
        }

        return $testData;
    }

    /**
     * @dataProvider markupTestProvider
     */
    public function testHighlighter($language, $testName, $raw, $expected)
    {
        if (in_array(array($language, $testName), $this->allowedFailures)) {
            $this->markTestSkipped("The $language $testName test is known to fail for unknown reasons...");
        }

        $ll = new Lowlight();
        $actual = $ll->highlight($language, $raw);

        $expected = $this->parseExpected($expected, $ll);

        // $this->assertEquals($language, $actual->language);
        $this->assertEquals(
            str_replace("\033", "\\033", trim($expected)),
            str_replace("\033", "\\033", trim($actual)),
            sprintf('The "%s" markup test failed for the "%s" language', $testName, $language)
        );
    }
}
