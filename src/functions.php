<?php
/**
 * League Uri Query Parser (http://uri.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/uri-query-parser/blob/master/LICENSE (MIT License)
 * @version 2.0.0
 * @link    https://github.com/thephpleague/uri-query-parser
 */
declare(strict_types=1);

namespace League\Uri;

/**
 * Build a query string from an associative array.
 *
 * @see QueryBuilder::build
 *
 * @param mixed  $pairs     The query pairs
 * @param string $separator The query string separator
 * @param int    $enc_type  The query encoding type
 *
 * @return null|string
 */
function build_query($pairs, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986)
{
    static $builder;

    $builder = $builder ?? new QueryBuilder();

    return $builder->build($pairs, $separator, $enc_type);
}

/**
 * Parse a query string into an associative array of key/value pairs.
 *
 * @see QueryParser::parse
 *
 * @param mixed  $query     The query string to parse
 * @param string $separator The query string separator
 * @param int    $enc_type  The query encoding algorithm
 *
 * @return array
 */
function parse_query($query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
{
    static $parser;

    $parser = $parser ?? new QueryParser();

    return $parser->parse($query, $separator, $enc_type);
}

/**
 * Parse the query string like parse_str without mangling the results.
 *
 * @see QueryParser::extract
 *
 * @param mixed  $query     The query string to parse
 * @param string $separator The query string separator
 * @param int    $enc_type  The query encoding algorithm
 *
 * @return array
 */
function extract_query($query, string $separator = '&', int $enc_type = PHP_QUERY_RFC3986): array
{
    static $parser;

    $parser = $parser ?? new QueryParser();

    return $parser->extract($query, $separator, $enc_type);
}