<?php

/**
 * Funkcja robiąca rekursywnego normalnego merga przekazanych tablic.
 * Funkcja znaleziona na php.net w komentarzach userów.
 *
 * @return array
 */
function &array_merge_recursive_distinct()
{
    $aArrays = func_get_args();
    $aMerged = $aArrays[0];
    for($i = 1; $i < count($aArrays); $i++) {
        if (is_array($aArrays[$i])) {
            foreach ($aArrays[$i] as $key => $val) {
                if (is_array($aArrays[$i][$key])) {
                    if (isset($aMerged[$key])) {
                        $aMerged[$key] = is_array($aMerged[$key]) ? array_merge_recursive_distinct($aMerged[$key], $aArrays[$i][$key]) : $aArrays[$i][$key];
                    } else {
                        $aMerged[$key] = $aArrays[$i][$key];
                    }
                } else {
                    $aMerged[$key] = $val;
                }
            }
        }
    }
    return $aMerged;
}

/**
 * Wywołuje na każdym elemencie tablicy przekazaną funkcję i zwraca jej wynik do
 * tablicy wynikowej.
 *
 * @example
 * <code>
 *      $prices = array_walk_closure($someArray, function($value, $key) {
 *          return $value->getPrice();
 *      });
 * </code>
 *
 * @param ArrayAccess $array
 * @param Closure $closure
 * @return array
 */
function array_walk_closure($array, Closure $closure) // XXX nie można wymusić ArrayAccess, bo nie przechodzą zwykłe tablice :/
{
    $result = array();
    foreach ($array as $k => $v) {
        $result[] = $closure($v, $k);
    }
    return $result;
}

// json_decode_nice('{json:1, x: {"aaa": "A\B\C"}}'
function json_decode_nice($json, $assoc = true) {
    $json = str_replace(["\n", "\r", '\\'], ['', '', '\\\\'], $json);
    $json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/','$1"$3":', $json);
    dbg($json);
    return json_decode($json,$assoc);
}

/**
 * Serializuje obiekt do wartości skalarnej.
 * @param object $var
 * @return scalar
 */
function object_to_scalar($var)
{
    if (! is_object($var))
        trigger_error('Value is not an object', E_USER_ERROR);

    if (method_exists($var, 'toScalar'))        $scalar = $var->toScalar();
    elseif (method_exists($var, '__toString'))  $scalar = $var->__toString();
    elseif (method_exists($var, 'toString'))    $scalar = $var->toString();
    elseif (method_exists($var, 'toValue'))     $scalar = $var->toValue();
    else trigger_error(sprintf('Impossible convert object class %s to scalar value', get_class($var)));

    if (! is_scalar($scalar))
        trigger_error(sprintf('An error occured during serialize object class %s', get_class($var)));

    return $scalar;
}

function mb_ucfirst($string, $encoding = "UTF-8")
{
    $strlen = mb_strlen($string, $encoding);
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $then = mb_substr($string, 1, $strlen - 1, $encoding);
    return mb_strtoupper($firstChar, $encoding) . $then;
}

function mb_ucwords($str) {
    return mb_convert_case($str, MB_CASE_TITLE, "UTF-8");
}


class FatalErrorException extends Exception {}
function brs_error_handler($errno, $errstr, $errfile, $errline) {
    if($errno == E_DEPRECATED) return false;
    // ini_set('display_errors', 0); // XXX sam nie wiem - jak jest 1, to w cli mode wyjątki pokazują się dwa razy... a jak to jest, to nie wali fatalami na ekran wcale...
    //ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    throw new FatalErrorException(sprintf('%s in file %s line %s', $errstr, $errfile, $errline));
}
function convert_errors_to_exceptions() {
    set_error_handler('brs_error_handler');
}