<?php namespace Highlight;

use Mockery;

class Highlighter
{
    public $mock;

    public function __construct()
    {
        $this->mock = Mockery::mock();
    }

    public function setClassPrefix($prefix)
    {
    }

    public function highlight($languageName, $code, $ignoreIllegals = true, $continuation = null)
    {
        return $this->mock->highlight(
            $languageName,
            $code,
            $ignoreIllegals,
            $continuation
        );
    }

    public function highlightAuto($code, $languageSubset = null)
    {
        return $this->mock->highlightAuto(
            $code,
            $languageSubset
        );
    }
}
