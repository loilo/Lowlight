<?php namespace Lowlight\Test;

use Lowlight\Lowlight;

trait ParseExpected
{
    protected function parseExpected($expected, $ll)
    {
        $classReflection = new \ReflectionClass(Lowlight::class);
        $colorCodesProperty = $classReflection->getProperty('colorCodes');
        $colorCodesProperty->setAccessible(true);
        $colorCodes = $colorCodesProperty->getValue($ll);

        $expected = str_replace('[[end]]', "\033[39m", $expected);
        $expected = preg_replace_callback("/\\[\\[start:([a-zA-Z0-9_-]+)\\]\\]/", function ($matches) use (&$colorCodes, $ll) {
            $token = $matches[1];

            // Not set in theme, use default
            if (!isset($ll->theme[$token])) {
                return "\033[0m";
            } else {
                $colorName = $ll->theme[$token];

                if (!isset($colorCodes[$colorName])) {
                    return "\033[0m";
                } else {
                    return "\033[" . $colorCodes[$colorName] . 'm';
                }
            }
        }, $expected);

        return $expected;
    }
}
