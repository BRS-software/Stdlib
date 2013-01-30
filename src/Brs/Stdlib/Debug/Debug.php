<?php
namespace Brs\Stdlib\Debug;

use ReflectionClass;

class Debug
{
    public static $maxOutputLength = 100000;
    protected static $sapi = null;

    public function registerFunctions()
    {
        require_once __DIR__ . '/../../../DebugFunctions.php';
    }

    public static function getSapi()
    {
        if (static::$sapi === null) {
            static::$sapi = PHP_SAPI;
        }
        return static::$sapi;
    }

    public static function dump($var = null, $label = null, $echo = true, $_callRewind = 0)
    {
        $output = self::_dump($var, $label);
        if (is_numeric($_callRewind)) {
            $output .= self::showCall($_callRewind);
        }
        if ($echo) {
            echo $output;
        }
        return $output;
    }

    public static function dumpObject($var = null, $label = null, $echo = true, $fullDump = false, $_callRewind = 0)
    {
        if (! is_object($var)) {
            return self::dump($var, $label, $echo, $_callRewind+1);
        }

        $output = self::_dump(self::reflectionClass($var, $fullDump), $label, false);
        $output .= self::_dump($var, '- instance', true);

        if (is_numeric($_callRewind)) {
            $output .= self::showCall($_callRewind);
        }
        if ($echo) {
            echo $output;
        }
        return $output;
    }

    public static function reflectionClass($className, $fullDump = false)
    {
        if (is_object($className)) {
            $className = get_class($className);
        }

        $reflection = new ReflectionClass($className);

        if ($fullDump) {
            $class = (string) $reflection;

        } else {
            $class = 'class ' . $className;
            // self::dump($reflection->getParentClass());

            if ($parent = $reflection->getParentClass()) {
                $class .= ' extends ' . $parent->name;
            }

            if ($interfaces = $reflection->getInterfaceNames()) {
                $class .= ' implements ' . join(', ', $interfaces);
            }

            $class .= PHP_EOL . '{' . PHP_EOL;

            $constans = [];
            foreach ($reflection->getConstants() as $name => $value) {
                $constans[] = sprintf("  const %s = %s", $name, var_export($value, true));
            }
            if ($constans) {
                $class .= join("\n", $constans);
                $class .= PHP_EOL . PHP_EOL;
            }

            $properties = [];
            foreach ($reflection->getProperties() as $prop) {
                $desc = [' '];

                if ($prop->isPublic()) {
                    $desc[] = 'public';
                } elseif ($prop->isProtected()) {
                    $desc[] = 'proteced';
                } elseif ($prop->isPrivate()) {
                    $desc[] = 'private';
                }

                $desc[] = 'property $' . $prop->name;

                // if ($prop->isDefault()) {
                //     $desc[] = self::getParamValue($prop->getValue());
                // }

                if ($className !== $prop->getDeclaringClass()->name) {
                    $desc[] = sprintf('[inherits %s]', $prop->getDeclaringClass()->name);
                }
                $properties[] = join(' ', $desc);
            }
            if ($properties) {
                $class .= join("\n", $properties);
                $class .= PHP_EOL . PHP_EOL;
            }

            $methods = [];
            $showArgs = function($method) {
                $params = [];
                array_walk($method->getParameters(), function($p) use(&$params) {
                    // $params[] = str_replace('Parameter ', '', $p->__tostring());
                    $desc[] = '#' . $p->getPosition();

                    if ($p->isArray()) {
                        $desc[] = 'array';
                    } elseif ($p->getClass()) {
                        $desc[] = $p->getClass()->name;
                    }

                    $desc[] = ($p->isPassedByReference() ? '&' : '') . '$' . $p->getName();

                    if ($p->isDefaultValueAvailable()) {
                        // $desc[] = sprintf('= %s', var_export($p->getDefaultValue(), true));
                        $desc[] = self::getParamValue($p->getDefaultValue());
                    }
                    $params[] = join(' ', $desc);
                });
                return join(', ', $params);
            };

            foreach ($reflection->getMethods() as $m) {
                // self::dump($m);
                $desc = [];
                $desc[] = " ";

                if ($m->isFinal()) {
                    $desc[] = 'final';
                } elseif ($m->isAbstract()) {
                    $desc[] = 'abstract';
                }

                if ($m->isPublic()) {
                    $desc[] = 'public';
                } elseif ($m->isProtected()) {
                    $desc[] = 'protected';
                } elseif ($m->isPrivate()) {
                    $desc[] = 'private';
                }

                if ($m->isStatic()) {
                    $desc[] = 'static';
                }

                $sortKey = join(' ', $desc) . $m->name;

                $desc[] =
                    'method '.
                    ($m->returnsReference() ? '&' : '')
                    .sprintf('%s(%s)', $m->name, $showArgs($m))
                ;

                try {
                    $desc[] = sprintf('[overwrites, prototype %s]', $m->getPrototype()->class);
                } catch (\ReflectionException $e) {
                    if ($m->class !== $className) {
                        $desc[] = sprintf('[inherits %s]', $m->class);
                    }
                }
                //print $m->__tostring();
                $methods[$sortKey] = join(' ', $desc);
                ksort($methods);
            }

            // self::dump($methods);
            $class .= join("\n", $methods);
            $class .= PHP_EOL . '}';

        }
        return $class;
    }

    public static function dumpAll($var/*[, $var2, $var3, ...]*/)
    {
        $output = '';
        foreach (func_get_args() as $i => $var) {
            $output .= self::_dump($var, 'var '.$i);
        }
        // $output = join('--', $output);
        $output .= self::showCall();
        echo $output;
        return $output;
    }

    public static function showCall($rewind = 0)
    {
        $trace = debug_backtrace()[$rewind+1];
        return self::htmlFormat(sprintf("^--Who called me: %s line %s\n\n", $trace['file'], $trace['line']));
    }

    public static function htmlFormat($text, $style = null)
    {
        if (static::getSapi() === 'cli') {
            return $text;
        }
        if ($style) {
            return sprintf('<pre style="%s">%s</pre>', $style, $text);
        } else {
            return sprintf('<pre>%s</pre>', $text);
        }
    }

    protected static function _dump($var = '', $label = null, $dumpVar = true)
    {
        if ($dumpVar) {
            ob_start();
            var_dump($var);
            $output = ob_get_clean();

            if (strlen($output) > self::$maxOutputLength) {
                $output = substr($output, 0, self::$maxOutputLength)
                    . sprintf(
                        "\n\n ... truncated afater %s characters (full size: %s) see %s::\$maxOutputLength parameter\n",
                        self::$maxOutputLength, strlen($output), get_class()
                    );
            }

        } else {
            $output = $var;
        }

        if (extension_loaded('xdebug')) {
            $output = preg_replace(["/\=\>\n(\s+)/m", "/\{\n(\s+)\.\.\.\n(\s+)\}/m"], [" => ", ""], $output);
        } else {
            $output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
        }

        if (static::getSapi() == 'cli') {
            $output = PHP_EOL . $label
                    . PHP_EOL . $output
                    . PHP_EOL;
        } else {
            $output = self::htmlFormat($label . $output);
        }
        return $output;
    }

    protected static function getParamValue($value)
    {
        return sprintf(
            '= %s',
            preg_replace(
                ["/\]\=\>\n(\s+)/m", "/\n/"],
                ["] => ", ""],
                var_export($value, true)
            )
        );
    }
}

// Debug::dumpAll('test', 1, ['a' => 1, 'b' => new \stdclass]);
// exit;


// function mpr($val, $isXml = false, $_traceRewind = 0) {
//     if($isXml) {
//         header("content-type: text/xml");
//         die($val);
//     }
//     if(!headers_sent()) {
//         header("content-type: text/plain");
//     }
//     if (is_array($val) || is_object($val)) {
//         print_r($val);

//         if(is_array($val))
//             reset($val);
//     } else {
//         var_dump($val);
//     }
//     $trace = debug_backtrace();
//     echo sprintf("^--Who called me: %s line %s\n\n", $trace[$_traceRewind]['file'], $trace[$_traceRewind]['line']);
// }
// function mprd($val, $isXml = false) {
//     mpr($val, $isXml, 1);
//     die("die!\n\n");
// }
