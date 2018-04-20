<?php
/**
 * League Uri Query Parser (http://uri.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/uri-query-parser/blob/master/LICENSE (MIT License)
 * @version 1.0.0
 * @link    https://github.com/thephpleague/uri-query-parser
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace League\Uri\Parser;

use League\Uri\EncodingInterface;
use Traversable;
use TypeError;

/**
 * A class to build a URI query string from a collection of key/value pairs.
 *
 * @package  League\Uri
 * @author   Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since    1.0.0
 * @see      https://tools.ietf.org/html/rfc3986#section-3.4
 * @internal Use the function League\Uri\query_build instead
 */
final class QueryBuilder implements EncodingInterface
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
    const CHARS_LIST = [
        'pattern' => [
            "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07", "\x08", "\x09",
            "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F", "\x10", "\x11", "\x12", "\x13",
            "\x14", "\x15", "\x16", "\x17", "\x18", "\x19", "\x1A", "\x1B", "\x1C", "\x1D",
            "\x1E", "\x1F", "\x7F", '#',
        ],
        'replace' => [
            '%00', '%01', '%02', '%03', '%04', '%05', '%06', '%07', '%08', '%09',
            '%0A', '%0B', '%0C', '%0D', '%0E', '%0F', '%10', '%11', '%12', '%13',
            '%14', '%15', '%16', '%17', '%18', '%19', '%1A', '%1B', '%1C', '%1D',
            '%1E', '%1F', '%7F', '%23',
        ],
    ];

    /**
     * @internal
     */
    const REGEXP_UNRESERVED_CHAR = '/[^A-Za-z0-9_\-\.~]/';

    /**
     * @var callable
     */
    private $encoder;

    /**
     * Build a query string from an associative array.
     *
     * The method expects the return value from Query::parse to build
     * a valid query string. This method differs from PHP http_build_query as:
     *
     *    - it does not modify parameters keys
     *
     * @param mixed  $pairs     Query pairs
     * @param string $separator Query string separator
     * @param int    $enc_type  Query encoding type
     *
     * @throws InvalidArgument If a query pair is malformed
     *
     * @return null|string
     */
    public function build($pairs, string $separator = '&', int $enc_type = self::RFC3986_ENCODING)
    {
        if (!\is_array($pairs) && !$pairs instanceof Traversable) {
            throw new TypeError('the pairs collection must be an array or a Traversable object');
        }
        $this->encoder = $this->getEncoder($separator, $enc_type);
        $res = [];
        foreach ($pairs as $pair) {
            $pair = $this->filterPair($pair);
            if (!empty($pair)) {
                $res[] = $this->buildPair($pair);
            }
        }

        return empty($res) ? null : \implode($separator, $res);
    }

    /**
     * Returns the query string encoding mechanism.
     *
     * @param string $separator
     * @param int    $enc_type
     *
     * @throws UnknownEncoding If the encoding type is invalid
     *
     * @return callable
     */
    private function getEncoder(string $separator, int $enc_type): callable
    {
        if (!isset(self::ENCODING_LIST[$enc_type])) {
            throw new UnknownEncoding(\sprintf('Unsupported or Unknown Encoding: %s', $enc_type));
        }

        $subdelim = \str_replace(\html_entity_decode($separator, ENT_HTML5, 'UTF-8'), '', "!$'()*+,;=:@?/&%");
        $regexp = '/(%[A-Fa-f0-9]{2})|[^A-Za-z0-9_\-\.~'.\preg_quote($subdelim, '/').']+/u';

        if (self::RFC3986_ENCODING == $enc_type) {
            return function (string $str) use ($regexp): string {
                return \preg_replace_callback($regexp, [$this, 'encodeMatches'], $str) ?? \rawurlencode($str);
            };
        }

        if (self::RFC1738_ENCODING == $enc_type) {
            return function (string $str) use ($regexp): string {
                return \str_replace(
                    ['+', '~'],
                    ['%2B', '%7E'],
                    \preg_replace_callback($regexp, [$this, 'encodeMatches'], $str) ?? \rawurlencode($str)
                );
            };
        }

        if (self::RFC3987_ENCODING == $enc_type) {
            $pattern = self::CHARS_LIST['pattern'];
            $pattern[] = $separator;
            $replace = self::CHARS_LIST['replace'];
            $replace[] = \rawurlencode($separator);
            return function (string $str) use ($pattern, $replace): string {
                return \str_replace($pattern, $replace, $str);
            };
        }

        //NO ENCODING
        return function (string $str): string {
            return $str;
        };
    }

    /**
     * Encode Matches sequence.
     *
     * @param array $matches
     *
     * @return string
     */
    private function encodeMatches(array $matches): string
    {
        if (\preg_match(self::REGEXP_UNRESERVED_CHAR, \rawurldecode($matches[0]))) {
            return \rawurlencode($matches[0]);
        }

        return $matches[0];
    }

    /**
     * validate the submitted pair.
     *
     * @param array $pair
     *
     * @throws InvalidArgument If the pair contains invalid value
     *
     * @return array
     */
    private function filterPair(array $pair)
    {
        if (empty($pair)) {
            return $pair;
        }

        list($key, $value) = \array_values($pair) + [1 => null];
        if (null === $key || (!\is_scalar($key) && !\method_exists($key, '__toString'))) {
            throw new InvalidArgument(\sprintf('A pair key must a stringable object or a scalar value `%s` given', \gettype($key)));
        }

        if (null !== $value && !\is_scalar($value) && !\method_exists($value, '__toString')) {
            throw new InvalidArgument(\sprintf('A pair value must a stringable object, a scalar or the null value `%s` given', \gettype($value)));
        }

        return [(string) $key, $value];
    }

    /**
     * Build a query key/pair association.
     *
     * @param array $pair
     *
     * @return string
     */
    private function buildPair(array $pair): string
    {
        $key = ($this->encoder)($pair[0]);
        if (null === $pair[1]) {
            return $key;
        }

        if (\is_bool($pair[1])) {
            return $key.'='.($pair[1] ? '1' : '0');
        }

        return $key.'='.($this->encoder)((string) $pair[1]);
    }
}
