<?php namespace Lowlight\Test\Util;

use Mockery;

trait HighlightAutoloader
{
    public static function autoloader($class)
    {
        if ($class === 'Highlight\\Highlighter') {
            require_once __DIR__ . '/MockHighlighter.php';
        }
    }

    public static function setUpBeforeClass(): void
    {
        spl_autoload_register([ static::class, 'autoloader' ], true, true);
    }

    public static function tearDownAfterClass(): void
    {
        spl_autoload_unregister([ static::class, 'autoloader' ]);
    }

    public function tearDown(): void {
        Mockery::close();
    }
}
