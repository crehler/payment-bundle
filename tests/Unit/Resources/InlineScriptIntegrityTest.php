<?php

/**
 * @copyright 2026 Crehler Sp. z o.o.
 * @link https://crehler.com/
 * @license proprietary
 * support@crehler.com
 */

declare(strict_types=1);

namespace Crehler\PaymentBundle\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

use function file_get_contents;
use function iterator_to_array;
use function preg_match_all;
use function str_replace;

use const DIRECTORY_SEPARATOR;

/**
 * An inline script must not contain the literal closing script tag anywhere — not even in a
 * comment.
 *
 * The HTML tokenizer has no idea what a JavaScript comment is: the first closing tag it sees
 * inside a script element ends that element, whatever it is nested in. A comment in the
 * payment transition page spelled the tag out while explaining that a raw one in the gateway
 * body would be dangerous, and cut its own script in half. Everything after it — including
 * the function that performs the redirect — became page text, so the storefront stopped
 * forwarding customers to their gateway entirely. The page looked completely normal; only
 * the console said anything, and only "Unexpected end of input".
 *
 * A count is enough to catch it: one opening tag needs exactly one closing tag, and the extra
 * literal shows up as an imbalance. Writing it as <\/script> keeps the text readable and the
 * tokenizer none the wiser.
 */
final class InlineScriptIntegrityTest extends TestCase
{
    public function testNoTemplateEndsItsOwnScriptEarly(): void
    {
        $templates = $this->templates();

        self::assertNotEmpty($templates, 'no templates found — the glob is wrong, not the templates');

        foreach ($templates as $path => $source) {
            $opened = preg_match_all('/<script\b/i', $source);
            $closed = preg_match_all('#</script\s*>#i', $source);

            self::assertSame(
                $opened,
                $closed,
                sprintf(
                    '%s has %d <script> and %d </script>. An extra closing tag is usually one written out '
                    . 'inside the script itself, which ends it there; write <\\/script> instead.',
                    $path,
                    $opened,
                    $closed,
                ),
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function templates(): array
    {
        $root = __DIR__ . '/../../../src/Resources/views';

        $files = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)),
            '/\.html\.twig$/',
        );

        $templates = [];
        foreach (iterator_to_array($files) as $file) {
            $path = str_replace($root . DIRECTORY_SEPARATOR, '', (string) $file);
            $templates[$path] = (string) file_get_contents((string) $file);
        }

        return $templates;
    }
}
