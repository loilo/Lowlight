<?php namespace Lowlight;

/**
 * The Lowlight class handles the main load of the highlighting.
 * It's a fork of highlight.php's \Highlight\Highlighter class.
 */
class Lowlight
{
    const END_SEQUENCE = "\033[39m";
    const DEFAULT_START_SEQUENCE = "\033[0m";

    protected $stack = [];
    protected $modeBuffer = '';
    protected $result = '';
    protected $top = null;
    protected $language = null;
    protected $keywordCount = 0;
    protected $relevance = 0;
    protected $ignoreIllegals = false;
    protected static $classMap = [];
    protected static $languages = null;
    protected static $aliases = null;
    protected static $colorCodes = [
        'default' => 0,
        'black' => 30,
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'blue' => 34,
        'magenta' => 35,
        'cyan' => 36,
        'white' => 37,
        'gray' => 90,
        'grey' => 90,
        'bright-red' => 91,
        'bright-green' => 92,
        'bright-yellow' => 93,
        'bright-blue' => 94,
        'bright-magenta' => 95,
        'bright-cyan' => 96,
        'bright-white' => 97
    ];

    /**
     * Languages that are by default used for auto-detection
     */
    protected $defaultAutodetectSet = [
        'xml', 'json', 'javascript', 'css', 'php', 'http',
    ];

    public $theme = [
        'subst' => 'red',
        'comment' => 'gray',

        'keyword' => 'yellow',
        'attribute' => 'yellow',
        'selector-tag' => 'yellow',
        'meta-keyword' => 'yellow',
        'doctag' => 'yellow',
        'name' => 'yellow',

        'type' => 'red',
        'string' => 'blue',
        'number' => 'green',
        'selector-id' => 'red',
        'selector-class' => 'red',
        'quote' => 'red',
        'template-tag' => 'red',
        'deletion' => 'red',

        'title' => 'magenta',
        'section' => 'magenta',
        'built_in' => 'magenta',

        'regexp' => 'bright-red',
        'symbol' => 'bright-red',
        'variable' => 'bright-red',
        'template-variable' => 'bright-red',
        'link' => 'bright-red',
        'selector-attr' => 'bright-red',
        'selector-pseudo' => 'bright-red',

        'literal' => 'bright-green',

        'built' => 'green',
        'bullet' => 'green',
        'code' => 'green',
        'addition' => 'green',

        'meta' => 'cyan',

        'meta-string' => 'bright-cyan',

        'emphasis' => 'red',
        'strong' => 'red',
    ];

    public function __construct()
    {
        static::registerLanguages();
    }

    protected static function registerLanguages()
    {
        // Languages that take precedence in the classMap array.
        $languagePath = dirname((new \ReflectionClass(\Highlight\Highlighter::class))->getFileName()) . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR;
        foreach ([ 'xml', 'django', 'javascript', 'matlab', 'cpp' ] as $languageId) {
            $filePath = $languagePath . $languageId . '.json';
            if (is_readable($filePath)) {
                static::registerLanguage($languageId, $filePath);
            }
        }
        $d = @dir($languagePath);
        if ($d) {
            while (($entry = $d->read()) !== false) {
                if (substr($entry, -5) === '.json') {
                    $languageId = substr($entry, 0, -5);
                    $filePath = $languagePath . $entry;
                    if (is_readable($filePath)) {
                        static::registerLanguage($languageId, $filePath);
                    }
                }
            }
            $d->close();
        }
        static::$languages = array_keys(static::$classMap);
    }

    /**
     * Register a language definition with the Highlighter's internal language
     * storage. Languages are stored in a static variable, so they'll be available
     * across all instances. You only need to register a language once.
     *
     * @param string $languageId The unique name of a language
     * @param string $filePath   The file path to the language definition
     * @param bool   $overwrite  Overwrite language if it already exists
     *
     * @return \Highlight\Language The object containing the definition for a language's markup
     */
    public static function registerLanguage($languageId, $filePath, $overwrite = false)
    {
        if (!isset(static::$classMap[$languageId]) || $overwrite) {
            $lang = new \Highlight\Language($languageId, $filePath);
            static::$classMap[$languageId] = $lang;
            if (isset($lang->mode->aliases)) {
                foreach ($lang->mode->aliases as $alias) {
                    static::$aliases[$alias] = $languageId;
                }
            }
        }
        return static::$classMap[$languageId];
    }

    protected function testRe($re, $lexeme)
    {
        if (!$re) {
            return false;
        }
        $test = preg_match($re, $lexeme, $match, PREG_OFFSET_CAPTURE);
        if ($test === false) {
            throw new \Exception('Invalid regexp: ' . var_export($re, true));
        }
        return count($match) && ($match[0][1] == 0);
    }

    protected function subMode($lexeme, $mode)
    {
        for ($i = 0; $i < count($mode->contains); ++$i) {
            if ($this->testRe($mode->contains[$i]->beginRe, $lexeme)) {
                return $mode->contains[$i];
            }
        }
    }

    protected function endOfMode($mode, $lexeme)
    {
        if ($this->testRe($mode->endRe, $lexeme)) {
            while ($mode->endsParent && $mode->parent) {
                $mode = $mode->parent;
            }
            return $mode;
        }
        if ($mode->endsWithParent) {
            return $this->endOfMode($mode->parent, $lexeme);
        }
    }

    protected function isIllegal($lexeme, $mode)
    {
        return !$this->ignoreIllegals && $this->testRe($mode->illegalRe, $lexeme);
    }

    protected function keywordMatch($mode, $match)
    {
        $kwd = $this->language->caseInsensitive ? mb_strtolower($match[0], 'UTF-8') : $match[0];
        return isset($mode->keywords[$kwd]) ? $mode->keywords[$kwd] : null;
    }

    protected function createOpenerSequence($token)
    {
        if (!isset($this->theme[$token])) {
            return static::DEFAULT_START_SEQUENCE;
        } else {
            $codeOrRgb = $this->theme[$token];

            if (is_string($codeOrRgb)) {
                if (isset(static::$colorCodes[$codeOrRgb])) {
                    return "\033[" . static::$colorCodes[$codeOrRgb] . 'm';
                } else {
                    return static::DEFAULT_START_SEQUENCE;
                }
            } elseif (is_array($codeOrRgb)) {
                return "\033[38;2;" . join(';', $codeOrRgb) . 'm';
            } else {
                return static::DEFAULT_START_SEQUENCE;
            }
        }
    }

    protected function open($token)
    {
        $result = '';
        if (sizeof($this->stack)) {
            $result .= static::END_SEQUENCE;
        }

        $this->stack[] = $token;

        $result .= $this->createOpenerSequence($token);

        return $result;
    }

    protected function close()
    {
        $result = '';

        array_pop($this->stack);

        $result .= static::END_SEQUENCE;

        if (sizeof($this->stack)) {
            $result .= $this->createOpenerSequence($this->stack[sizeof($this->stack) - 1]);
        }

        return $result;
    }

    protected function buildSequence($token, $content, $leaveOpen = false)
    {
        if (in_array($token, static::$languages) && !$leaveOpen) {
            return $content;
        }

        $openSequence = $this->open($token);

        if ($leaveOpen) {
            $closeSequence = '';
        } else {
            $closeSequence = $this->close();
        }

        return $openSequence . $content . $closeSequence;
    }

    protected function processKeywords()
    {
        if (empty($this->top->keywords)) {
            return $this->modeBuffer;
        }
        $result = '';
        $lastIndex = 0;

        if ($this->top->lexemesRe) {
            while (preg_match($this->top->lexemesRe, $this->modeBuffer, $match, PREG_OFFSET_CAPTURE, $lastIndex)) {
                $result .= substr($this->modeBuffer, $lastIndex, $match[0][1] - $lastIndex);
                $keyword_match = $this->keywordMatch($this->top, $match[0]);
                if ($keyword_match) {
                    $this->relevance += $keyword_match[1];
                    $result .= $this->buildSequence($keyword_match[0], $match[0][0]);
                } else {
                    $result .= $match[0][0];
                }
                $lastIndex = strlen($match[0][0]) + $match[0][1];
            }
        }
        return $result . substr($this->modeBuffer, $lastIndex);
    }

    protected function processSubLanguage()
    {
        try {
            $ll = new Lowlight();
            $ll->setDefaultAutodetectLanguages($this->defaultAutodetectSet);
            $explicit = is_string($this->top->subLanguage);
            if ($explicit && !in_array($this->top->subLanguage, static::$languages)) {
                return $this->modeBuffer;
            }
            if ($explicit) {
                $res = $ll->doHighlight(
                    $this->top->subLanguage,
                    $this->modeBuffer,
                    true,
                    isset($this->continuations[$this->top->subLanguage]) ? $this->continuations[$this->top->subLanguage] : null
                );
            } else {
                $res = $ll->highlightAuto(
                    $this->modeBuffer,
                    count($this->top->subLanguage) ? $this->top->subLanguage : null
                );
            }
            // Counting embedded language score towards the host language may
            // be disabled with zeroing the containing mode relevance. Usecase
            // in point is Markdown that allows XML everywhere and makes every
            // XML snippet to have a much larger Markdown score.
            if ($this->top->relevance > 0) {
                $this->relevance += $res->relevance;
            }
            if ($explicit) {
                $this->continuations[$this->top->subLanguage] = $res->top;
            }
            return $this->buildSequence($res->language, $res->value, false);
        } catch (\Exception $_e) {
            return $this->modeBuffer;
        }
    }

    protected function processBuffer()
    {
        $this->result .= $this->top->subLanguage ? $this->processSubLanguage() : $this->processKeywords();
        $this->modeBuffer = '';
    }

    protected function startNewMode($mode)
    {
        $this->result .= $mode->className ? $this->buildSequence($mode->className, '', true) : '';
        $t = clone $mode;
        $t->parent = $this->top;
        $this->top = $t;
    }

    protected function processLexeme($buffer, $lexeme = null)
    {
        $this->modeBuffer .= $buffer;
        if ($lexeme === null) {
            $this->processBuffer();
            return 0;
        }
        $new_mode = $this->subMode($lexeme, $this->top);
        if ($new_mode) {
            if ($new_mode->skip) {
                $this->modeBuffer .= $lexeme;
            } else {
                if ($new_mode->excludeBegin) {
                    $this->modeBuffer .= $lexeme;
                }
                $this->processBuffer();
                if (!$new_mode->returnBegin && !$new_mode->excludeBegin) {
                    $this->modeBuffer = $lexeme;
                }
            }
            $this->startNewMode($new_mode, $lexeme);
            return $new_mode->returnBegin ? 0 : strlen($lexeme);
        }
        $end_mode = $this->endOfMode($this->top, $lexeme);
        if ($end_mode) {
            $origin = $this->top;
            if ($origin->skip) {
                $this->modeBuffer .= $lexeme;
            } else {
                if (!($origin->returnEnd || $origin->excludeEnd)) {
                    $this->modeBuffer .= $lexeme;
                }
                $this->processBuffer();
                if ($origin->excludeEnd) {
                    $this->modeBuffer = $lexeme;
                }
            }
            do {
                if ($this->top->className) {
                    $this->result .= $this->close();
                }
                if (!$this->top->skip) {
                    $this->relevance += $this->top->relevance;
                }
                $this->top = $this->top->parent;
            } while ($this->top != $end_mode->parent);
            if ($end_mode->starts) {
                $this->startNewMode($end_mode->starts, '');
            }
            return $origin->returnEnd ? 0 : strlen($lexeme);
        }
        if ($this->isIllegal($lexeme, $this->top)) {
            $className = $this->top->className ? $this->top->className : 'unnamed';
            $err = "Illegal lexeme \"{$lexeme}\" for mode \"{$className}\"";
            throw new \Exception($err);
        }
        // Parser should not reach this point as all types of lexemes should
        // be caught earlier, but if it does due to some bug make sure it
        // advances at least one character forward to prevent infinite looping.
        $this->modeBuffer .= $lexeme;
        $l = strlen($lexeme);
        return $l ? $l : 1;
    }

    /**
     * Set the set of languages used for autodetection. When using
     * autodetection the code to highlight will be probed for every language
     * in this set. Limiting this set to only the languages you want to use
     * will greatly improve highlighting speed.
     *
     * @param array $set An array of language games to use for autodetection. This defaults
     *                   to a typical set Web development languages.
     */
    public function setDefaultAutodetectLanguages(array $set)
    {
        $this->defaultAutodetectSet = array_unique($set);
        static::registerLanguages();
    }

    /**
     * @throws \DomainException if the requested language was not in this
     *                          Highlighter's language set
     */
    protected function getLanguage($name)
    {
        if (isset(static::$classMap[$name])) {
            return static::$classMap[$name];
        } elseif (isset(static::$aliases[$name]) && isset(static::$classMap[static::$aliases[$name]])) {
            return static::$classMap[static::$aliases[$name]];
        }
        throw new \DomainException("Unknown language: $name");
    }

    /**
     * Core highlighting function. Accepts a language name, or an alias, and a
     * string with the code to highlight. Returns an object with the following
     * properties:
     * - relevance (int)
     * - value (an HTML string with highlighting markup).
     *
     * @throws \DomainException if the requested language was not in this
     *                          Highlighter's language set
     * @throws \Exception       if an invalid regex was given in a language file
     */
    public function doHighlight($language, $code, $ignoreIllegals = true, $continuation = null)
    {
        $this->language = $this->getLanguage($language);
        $this->language->compile();
        $this->top = $continuation ? $continuation : $this->language->mode;
        $this->continuations = [];
        $this->result = '';
        for ($current = $this->top; $current != $this->language->mode; $current = $current->parent) {
            if ($current->className) {
                $this->result = $this->buildSequence($current->className, '', true) . $this->result;
            }
        }
        $this->modeBuffer = '';
        $this->relevance = 0;
        $this->ignoreIllegals = $ignoreIllegals;
        $res = new \stdClass();
        $res->relevance = 0;
        $res->value = '';
        $res->language = '';
        try {
            $match = null;
            $count = 0;
            $index = 0;
            while ($this->top && $this->top->terminators) {
                $test = preg_match($this->top->terminators, $code, $match, PREG_OFFSET_CAPTURE, $index);
                if ($test === false) {
                    throw new \Exception('Invalid regExp ' . var_export($this->top->terminators, true));
                } elseif ($test === 0) {
                    break;
                }
                $count = $this->processLexeme(substr($code, $index, $match[0][1] - $index), $match[0][0]);
                $index = $match[0][1] + $count;
            }
            $this->processLexeme(substr($code, $index));
            for ($current = $this->top; isset($current->parent); $current = $current->parent) {
                if ($current->className) {
                    $this->result .= $this->close();
                }
            }
            $res->relevance = $this->relevance;

            $res->value = preg_replace("/\033\\[[0-9]+m\033\\[39m/", '', $this->result);
            // $res->value = $this->result;
            $res->language = $this->language->name;
            $res->top = $this->top;
            return $res;
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Illegal') !== false) {
                $res->value = $code;
                return $res;
            }
            throw $e;
        }
    }

    /**
     * Highlight the given code in a certain language
     */
    public function highlight($language, $code)
    {
        return $this->doHighlight($language, $code)->value;
    }

    /**
     * Highlight the given code by highlighting the given code with each
     * registered language and then finding the match with highest accuracy.
     *
     * @param string        $code
     * @param string[]|null $languageSubset When set to null, this method will
     *                                      attempt to highlight $code with each language (170+). Set this to
     *                                      an array of languages of your choice to limit the amount of languages
     *                                      to try.
     *
     * @throws \DomainException if the attempted language to check does not exist
     * @throws \Exception       if an invalid regex was given in a language file
     *
     * @return \stdClass
     */
    public function highlightAuto($code, $languageSubset = null)
    {
        $res = new \stdClass();
        $res->relevance = 0;
        $res->value = $code;
        $res->language = '';
        $scnd = clone $res;
        $tmp = $languageSubset ? $languageSubset : $this->defaultAutodetectSet;
        foreach ($tmp as $l) {
            // don't fail if we run into a non-existent language
            try {
                $current = $this->doHighlight($l, $code, false);
            } catch (\DomainException $_e) {
                continue;
            }
            if ($current->relevance > $scnd->relevance) {
                $scnd = $current;
            }
            if ($current->relevance > $res->relevance) {
                $scnd = $res;
                $res = $current;
            }
        }
        if ($scnd->language) {
            $res->secondBest = $scnd;
        }
        return $res;
    }

    /**
     * Return a list of all supported languages. Using this list in
     * setDefaultAutodetectLanguages will turn on autodetection for all supported
     * languages.
     *
     * @param bool $include_aliases specify whether language aliases
     *                              should be included as well
     *
     * @return string[] An array of language names
     */
    public function listLanguages($include_aliases = false)
    {
        if ($include_aliases === true) {
            return array_merge(static::$languages, array_keys(static::$aliases));
        }
        return static::$languages;
    }

    /**
     * Returns list of all available aliases for given language name.
     *
     * @param string $language name or alias of language to look-up
     *
     * @throws \DomainException if the requested language was not in this
     *                          Highlighter's language set
     *
     * @return string[] An array of all aliases associated with the requested
     *                  language name language. Passed-in name is included as
     *                  well.
     */
    public function getAliasesForLanguage($language)
    {
        $language = static::getLanguage($language);
        if ($language->aliases === null) {
            return [ $language->name ];
        }
        return array_merge(array($language->name), $language->aliases);
    }
}
