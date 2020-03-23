<?php namespace Lowlight;

use Highlight\Highlighter;

/**
 * The Lowlight class handles the main load of the highlighting.
 * It's a fork of highlight.php's \Highlight\Highlighter class.
 */
class Lowlight extends Highlighter
{
    const END_SEQUENCE = "\033[39m";
    const DEFAULT_START_SEQUENCE = "\033[0m";
    protected $stack = [];

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
        parent::__construct();

        $this->setClassPrefix('');
    }

    /**
     * @inheritdoc
     */
    public function highlight($languageName, $code, $ignoreIllegals = true, $continuation = null)
    {
        $result = parent::highlight($languageName, $code, $ignoreIllegals, $continuation);
        $result->value = $this->convertToAnsi($result->value);
        return $result;
    }

    public function highlightAuto($code, $languageSubset = null)
    {
        $result = parent::highlightAuto($code, $languageSubset);
        $result->value = $this->convertToAnsi($result->value);
        return $result;
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

    protected function openSequence($token)
    {
        $result = '';
        if (!empty($this->stack)) {
            $result .= static::END_SEQUENCE;
        }

        $this->stack[] = $token;

        $result .= $this->createOpenerSequence($token);

        return $result;
    }

    protected function closeSequence()
    {
        $result = '';

        array_pop($this->stack);

        $result .= static::END_SEQUENCE;

        if (!empty($this->stack)) {
            $result .= $this->createOpenerSequence($this->stack[sizeof($this->stack) - 1]);
        }

        return $result;
    }

    protected function convertToAnsi($html)
    {
        $dom = new \DOMDocument();

        // If loading HTML fails, it probably already is ANSI-formatted (e.g. coming from `highlightAuto`)
        try {
            $dom->loadHTML('<pre>' . $html . '</pre>');
        } catch (\Exception $e) {
            return $html;
        }

        $body = $dom->lastChild->firstChild->firstChild;

        return $this->replaceTags($body);
    }

    protected function replaceTags(\DOMNode $node) {
        $output = '';
        foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeType === 1) {
                $output .=
                    $this->openSequence($childNode->getAttribute('class')) .
                    $this->replaceTags($childNode) .
                    $this->closeSequence();
            } else {
                $output .= $childNode->textContent;
            }
        }
        return $output;
    }
}
