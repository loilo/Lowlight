<?php namespace Lowlight\Test;

use Lowlight\Lowlight;
use Highlight\Language;
use Symfony\Component\Finder\Finder;
use PHPUnit\Framework\TestCase;

class HighlightTest extends TestCase
{
    private $languagesPath;

    public function setUp()
    {
        $this->languagesPath = dirname((new \ReflectionClass(\Highlight\Highlighter::class))->getFileName()) . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR;
    }

    public function testUnknownLanguageThrowsDomainException()
    {
        $this->expectException(\DomainException::class);

        $ll = new Lowlight();
        $ll->highlight("blah++", "als blurp eq z dan zeg 'flipper'");
    }

    public function testListLanguagesWithoutAliases()
    {
        $languageFinder = new Finder();
        $expectedLanguageCount = $languageFinder->in($this->languagesPath)->name('*.json')->count();

        $ll = new Lowlight();

        $availableLanguages = $ll->listLanguages();
        $this->assertEquals($expectedLanguageCount, count($availableLanguages));

        $availableLanguages = $ll->listLanguages(false);
        $this->assertEquals($expectedLanguageCount, count($availableLanguages));
    }

    public function testListLanguagesWithAliases()
    {
        $languageFinder = new Finder();
        $minimumLanguageCount = $languageFinder->in($this->languagesPath)->name('*.json')->count();

        $ll = new Lowlight();
        $availableLanguages = $ll->listLanguages(true);

        $this->assertGreaterThan($minimumLanguageCount, count($availableLanguages));

        // Verify some common aliases/names are present.
        $this->assertContains('yaml', $availableLanguages);
        $this->assertContains('yml', $availableLanguages);
        $this->assertContains('c++', $availableLanguages);
        $this->assertContains('cpp', $availableLanguages);
    }

    public function testGetAliasesForLanguageWhenUsingMainLanguageName()
    {
        $languageDefinitionFile = $this->languagesPath . 'php.json';
        $language = new Language('php', $languageDefinitionFile);
        $expected_aliases = $language->aliases;
        $expected_aliases[] = 'php';
        sort($expected_aliases);

        $ll = new Lowlight();
        $aliases = $ll->getAliasesForLanguage('php');
        sort($aliases);

        $this->assertEquals($expected_aliases, $aliases);
    }

    public function testGetAliasesForLanguageWhenLanguageHasNoAliases()
    {
        $languageDefinitionFile = $this->languagesPath . 'ada.json';
        $language = new Language('ada', $languageDefinitionFile);
        $expected_aliases = $language->aliases;
        $expected_aliases[] = 'ada';
        sort($expected_aliases);

        $ll = new Lowlight();
        $aliases = $ll->getAliasesForLanguage('ada');
        sort($aliases);

        $this->assertEquals($expected_aliases, $aliases);
    }

    public function testGetAliasesForLanguageWhenUsingLanguageAlias()
    {
        $languageDefinitionFile = $this->languagesPath . 'php.json';
        $language = new Language('php', $languageDefinitionFile);
        $expected_aliases = $language->aliases;
        $expected_aliases[] = 'php';
        sort($expected_aliases);

        $ll = new Lowlight();
        $aliases = $ll->getAliasesForLanguage('php3');
        sort($aliases);

        $this->assertEquals($expected_aliases, $aliases);
    }

    public function testGetAliasesForLanguageRaisesExceptionForNonExistingLanguage()
    {
        $this->expectException('\DomainException');

        $ll = new Lowlight();
        $ll->getAliasesForLanguage('blah+');
    }
}
