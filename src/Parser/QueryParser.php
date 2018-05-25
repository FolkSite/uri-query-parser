<?php

/**
 * League.Uri (http://uri.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version 1.0.0
 * @link    https://github.com/thephpleague/uri-query-parser
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Uri\Parser;

use League\Uri\EncodingInterface;
use League\Uri\Exception\MalformedUriComponent;
use League\Uri\Exception\UnknownEncoding;
use TypeError;

/**
 * A class to parse a URI query string.
 *
 * @package  League\Uri
 * @author   Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since    1.0.0
 * @see      https://tools.ietf.org/html/rfc3986#section-3.4
 * @internal Use the function League\Uri\query_parse and League\Uri\query_extract instead
 */
final class QueryParser implements EncodingInterface
{
    /**
     * @internal
     */
    const ENCODING_LIST = [
        self::RFC1738_ENCODING => 1,
        self::RFC3986_ENCODING => 1,
        self::RFC3987_ENCODING => 1,
        self::NO_ENCODING => 1,
    ];

    /**
     * @internal
     */
    const REGEXP_INVALID_CHARS = '/[\x00-\x1f\x7f]/';

    /**
     * @internal
     */
    const REGEXP_ENCODED_PATTERN = ',%[A-Fa-f0-9]{2},';

    /**
     * @internal
     */
    const REGEXP_DECODED_PATTERN = ',%2[D|E]|3[0-9]|4[1-9|A-F]|5[0-9|A|F]|6[1-9|A-F]|7[0-9|E],i';

    /**
     * @var int
     */
    private static $enc_type;

    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Parse a query string into an associative array.
     *
     * Multiple identical key will generate an array. This function
     * differ from PHP parse_str as:
     *    - it does not modify or remove parameters keys
     *    - it does not create nested array
     *
     * @param mixed  $query     The query string to parse
     * @param int    $enc_type  The query encoding algorithm
     * @param string $separator The query string separator
     *
     * @throws TypeError             If the query string is a resource, an array or an object without the `__toString` method
     * @throws MalformedUriComponent If the query string is invalid
     * @throws UnknownEncoding       If the encoding type is invalid
     *
     * @return array
     */
    public static function parse($query, int $enc_type = self::RFC3986_ENCODING, string $separator = '&'): array
    {
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new UnknownEncoding(\sprintf('Unknown Encoding: %s', $enc_type));
        }
        self::$enc_type = $enc_type;

        if (null === $query) {
            return [];
        }

        if (!\is_scalar($query) && !\method_exists($query, '__toString')) {
            throw new TypeError(\sprintf('The query must be a scalar, a stringable object or the `null` value, `%s` given', \gettype($query)));
        }

        $query = (string) $query;
        if ('' === $query) {
            return [['', null]];
        }

        if (\preg_match(self::REGEXP_INVALID_CHARS, $query)) {
            throw new MalformedUriComponent(\sprintf('Invalid query string: %s', $query));
        }

        $pairs = [];
        foreach (\explode($separator, $query) as $pair) {
            $pairs[] = self::parsePair($pair);
        }

        return $pairs;
    }

    /**
     * Parse a query string pair.
     *
     * @param string $pair The query string pair
     *
     * @return array
     */
    private static function parsePair(string $pair): array
    {
        list($key, $value) = \explode('=', $pair, 2) + [1 => null];
        if (\preg_match(self::REGEXP_ENCODED_PATTERN, $key)) {
            $key = \preg_replace_callback(self::REGEXP_ENCODED_PATTERN, [QueryParser::class, 'decodeMatch'], $key);
        }

        if (self::RFC1738_ENCODING === self::$enc_type && false !== \strpos($key, '+')) {
            $key = \str_replace('+', ' ', $key);
        }

        if (null === $value) {
            return [$key, $value];
        }

        if (\preg_match(self::REGEXP_ENCODED_PATTERN, $value)) {
            $value = \preg_replace_callback(self::REGEXP_ENCODED_PATTERN, [QueryParser::class, 'decodeMatch'], $value);
        }

        if (self::RFC1738_ENCODING === self::$enc_type && false !== \strpos($value, '+')) {
            $value = \str_replace('+', ' ', $value);
        }

        return [$key, $value];
    }

    /**
     * Decode a match string.
     *
     * @param array $matches
     *
     * @return string
     */
    private static function decodeMatch(array $matches): string
    {
        if (\preg_match(self::REGEXP_DECODED_PATTERN, $matches[0])) {
            return \strtoupper($matches[0]);
        }

        return \rawurldecode($matches[0]);
    }

    /**
     * Returns the store PHP variables as elements of an array.
     *
     * The result is similar as PHP parse_str when used with its
     * second argument with the difference that variable names are
     * not mangled.
     *
     * @see http://php.net/parse_str
     * @see https://wiki.php.net/rfc/on_demand_name_mangling
     *
     * @param null|string $str       the query string
     * @param int         $enc_type  the query encoding
     * @param string      $separator a the query string single character separator
     *
     * @return array
     */
    public static function extract($str, int $enc_type = self::RFC3986_ENCODING, string $separator = '&'): array
    {
        $variables = [];
        foreach (self::parse($str, $enc_type, $separator) as $pair) {
            self::extractPhpVariable($pair[0], \rawurldecode((string) $pair[1]), $variables);
        }

        return $variables;
    }

    /**
     * Parse a query pairs like parse_str but without mangling the results array keys.
     *
     * <ul>
     * <li>empty name are not saved</li>
     * <li>If the value from name is duplicated its corresponding value will
     * be overwritten</li>
     * <li>if no "[" is detected the value is added to the return array with the name as index</li>
     * <li>if no "]" is detected after detecting a "[" the value is added to the return array with the name as index</li>
     * <li>if there's a mismatch in bracket usage the remaining part is dropped</li>
     * <li>“.” and “ ” are not converted to “_”</li>
     * <li>If there is no “]”, then the first “[” is not converted to becomes an “_”</li>
     * <li>no whitespace trimming is done on the key value</li>
     * </ul>
     *
     * @see https://php.net/parse_str
     * @see https://wiki.php.net/rfc/on_demand_name_mangling
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic1.phpt
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic2.phpt
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic3.phpt
     * @see https://github.com/php/php-src/blob/master/ext/standard/tests/strings/parse_str_basic4.phpt
     *
     * @param string $name  the query pair key
     * @param string $value the formatted value
     * @param array  $data  the result array passed by reference
     */
    private static function extractPhpVariable(string $name, string $value, array &$data)
    {
        if ('' === $name) {
            return;
        }

        if (false === ($left_bracket_pos = \strpos($name, '['))) {
            $data[$name] = $value;
            return;
        }

        if (false === ($right_bracket_pos = \strpos($name, ']', $left_bracket_pos))) {
            $data[$name] = $value;
            return;
        }

        $key = \substr($name, 0, $left_bracket_pos);
        if (!\array_key_exists($key, $data) || !\is_array($data[$key])) {
            $data[$key] = [];
        }

        $index = \substr($name, $left_bracket_pos + 1, $right_bracket_pos - $left_bracket_pos - 1);
        if ('' === $index) {
            $data[$key][] = $value;
            return;
        }

        $remaining = \substr($name, $right_bracket_pos + 1);
        if ('[' !== \substr($remaining, 0, 1) || false === \strpos($remaining, ']', 1)) {
            $remaining = '';
        }

        self::extractPhpVariable($index.$remaining, $value, $data[$key]);
    }
}
