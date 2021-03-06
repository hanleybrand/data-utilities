<?php
/** DataUtilities and related classes */

namespace Battis;

/**
 * A collection of generally useful little hacks and tweaks for working with
 * string data.
 *
 * @author Seth Battis <seth@battis.net>
 */
class DataUtilities
{
    /**
     * Append the src[key] array to the dest array, if key is present
     * @param array $src
     * @param string $key
     * @param array $dest
     * @return array
     */
    private static function appendIfPresent($src, $key, $dest)
    {
        if (!empty($src[$key])) {
            return array_merge($dest, $src[$key]);
        } else {
            return $dest;
        }
    }

    /**
     * Converts $title to Title Case, and returns the result
     *
     * @param string $title
     * @param array $params An associative array of additional special cases,
     *                      e.g. (with any subset of the keys below)
     *                      ```
     * [
     *     'lowerCaseWords' => ['foo', 'bar'],
     *     'allCapsWords' => ['BAZ'],
     *     'camelCaseWords' => ['foobarbaz' => 'fooBarBaz'],
     *     'spaceEquivalents' => ['\t']
     * ]
     * ```
     *
     * @return string
     *
     * @see http://www.sitepoint.com/title-case-in-php/ SitePoint
     **/
    public static function titleCase($title, $params = [])
    {
        /*
         * Our array of 'small words' which shouldn't be capitalised if they
         * aren't the first word.  Add your own words to taste.
         */
        $lowerCaseWords = self::appendIfPresent($params, 'lowerCaseWords', [
            'of','a','the','and','an','or','nor','but','is','if','then','else',
            'when','at','from','by','on','off','for','in','out','over','to',
            'into','with'
        ]);

        $allCapsWords = self::appendIfPresent($params, 'allCapsWords', [
            'i', 'ii', 'iii', 'iv', 'v', 'vi', 'sis', 'csv', 'php', 'html',
            'lti'
        ]);

        $camelCaseWords = self::appendIfPresent($params, 'camelCaseWords', [
            'github' => 'GitHub'
        ]);

        $spaceEquivalents = self::appendIfPresent($params, 'spaceEquivalents', [
            '\s', '_'
        ]);

        /* add a space after each piece of punctuation */
        $title = preg_replace('/([^a-z0-9])\s*/i', '$1 ', strtolower($title));

        // TODO smart em- and en-dashes
        // TODO superscripts for 1st, 2nd, 3rd

        /* Split the string into separate words */
        $words = preg_split('/[' . implode('', $spaceEquivalents) . ']+/', $title, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $key => $word) {
            if (in_array($word, $allCapsWords)) {
                $words[$key] = strtoupper($word);
            } elseif (array_key_exists($word, $camelCaseWords)) {
                $words[$key] = $camelCaseWords[$word];
            } elseif ($key == 0 or !in_array($word, $lowerCaseWords)) {
                $words[$key] = ucwords($word);
            }
        }

        /* Join the words back into a string */
        $newtitle = implode(' ', $words);

        return $newtitle;
    }

    /**
     * Load an uploaded CSV file into an associative array
     *
     * @param string $field Field name holding the file name
     * @param boolean $firstRowLabels (Optional) Default `TRUE`
     *
     * @return string[][]|boolean A two-dimensional array of string values, if the
     *        `$field` contains a CSV file, `FALSE` if there is no file
     **/
    public static function loadCsvToArray($field, $firstRowLabels = true)
    {
        $result = false;
        if (!empty($_FILES[$field]['tmp_name'])) {
            /* open the file for reading */
            $csv = fopen($_FILES[$field]['tmp_name'], 'r');
            $result = array();

            /* treat the first row as column labels */
            if ($firstRowLabels) {
                $fields = fgetcsv($csv);
            }

            /* walk through the file, storing each row in the array */
            while ($csvRow = fgetcsv($csv)) {
                $row = array();

                /* if we have column labels, use them */
                if ($firstRowLabels) {
                    foreach ($fields as $i => $field) {
                        if (isset($csvRow[$i])) {
                            $row[$field] = $csvRow[$i];
                        }
                    }
                } else {
                    $row = $csvRow;
                }

                /* append the row to the array */
                $result[] = $row;
            }
            fclose($csv);
        }
        return $result;
    }

    /**
     * What portion of string `$a` and `$b` overlaps?
     *
     * For example if `$a` is `'abcdefg`` and `$b` is `'fgjkli'`, the overlapping
     * portion would be `'fg'`.
     *
     * @param string $a
     * @param string $b
     * @param boolean $swap Attempt to swap `$a` and `$b` to find overlap. (default: `true`)
     * @return string Overlapping portion of `$a` and `$b`, `''` if no overlap
     */
    public static function overlap($a, $b, $swap = true)
    {
        if (!is_string($a) || !is_string($b)) {
            return '';
        }

        for ($i = 0; $i < strlen($a); $i++) {
            $overlap = true;
            for ($j = 0; $j < strlen($b) && $i + $j < strlen($a); $j++) {
                if ($a[$i+$j] !== $b[$j]) {
                    $overlap = false;
                    break;
                }
            }
            if ($overlap) {
                return substr($a, $i, $j);
            }
        }
        if ($swap) {
            return static::overlap($b, $a, false);
        }
        return '';
    }

    /**
     * Generate a URL from a path
     *
     * @param string $path A valid file path
     * @param string[] $vars Optional (default: `$_SERVER` unless run from CLI,
     *                       in which case the method fails without this
     *                       parameter). Array must have keys `HTTPS,
     *                       SERVER_NAME, CONTEXT_PREFIX,
     *                       CONTEXT_DOCUMENT_ROOT`.
     * @param string|false $basePath The base path from which to start when
     *                               processing a relative path.
     * @return false|string The URL to that path, or `false` if the URl cannot
     *     be computed (e.g. if run from CLI)
     */
    public static function URLfromPath($path, array $vars = null, $basePath = false)
    {
        if ($vars === null) {
            if (php_sapi_name() != 'cli') {
                $vars = $_SERVER;
            } else {
                return false;
            }
        }

        if ($basePath !== false && substr($path, 0, 1) !== '/') {
            $basePath = rtrim($basePath, '/');
            $path = realpath("$basePath/$path");
        }

        if (realpath($path)) {
            return (!isset($vars['HTTPS']) || $vars['HTTPS'] != 'on' ?
                        'http://' :
                        'https://'
                ) .
                $vars['SERVER_NAME'] .
                $vars['CONTEXT_PREFIX'] .
                str_replace(
                    $vars['CONTEXT_DOCUMENT_ROOT'],
                    '',
                    $path
                );
        } else {
            return false;
        }
    }

    /**
     * Does a URL "exist"?
     *
     * We're going to treat any response code < 400 as existing.
     *
     * @link http://php.net/manual/en/function.get-headers.php#112652 Comment
     *     get_headers() documentation
     * @param string $url
     */
    public static function URLexists($url)
    {
        $headers = get_headers($url);
        return intval(substr($headers[0], 9, 3)) < 400;
    }
}
