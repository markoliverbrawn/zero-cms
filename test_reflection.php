<?php
/**
 * Test autoloading and Reflection class inspection
 */

require_once '/data/misc/zero/tests/bootstrap.php';

$srcDir = '/data/misc/zero/src';
$files = getPhpFiles($srcDir);

$loaded = 0;
$failed = [];

foreach ($files as $file) {
    if (strpos($file, '/Views/') !== false || strpos($file, '/templates/') !== false) {
        continue;
    }

    $relative = str_replace('/data/misc/zero/', '', $file);
    $fqn = getFqnFromFile($file);
    if (!$fqn) {
        continue;
    }

    try {
        require_once $file;
        $reflector = new ReflectionClass($fqn);
        $loaded++;
    } catch (Throwable $e) {
        $failed[] = [
            'file' => $relative,
            'fqn' => $fqn,
            'error' => $e->getMessage()
        ];
    }
}

echo "Successfully loaded classes: $loaded\n";
echo "Failed classes: " . count($failed) . "\n";
foreach ($failed as $f) {
    echo "  - {$f['file']} (FQN: {$f['fqn']}) : {$f['error']}\n";
}

function getPhpFiles($dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $files = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

function getFqnFromFile($file) {
    $content = file_get_contents($file);
    $tokens = token_get_all($content);
    $namespace = '';
    $class = '';
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (is_array($tokens[$i])) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j])) {
                        if ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NAME_QUALIFIED) {
                            $namespace .= $tokens[$j][1];
                        } elseif ($tokens[$j][0] === T_WHITESPACE) {
                            continue;
                        }
                    } else {
                        if ($tokens[$j] === ';') {
                            break;
                        }
                    }
                }
            }
            if ($tokens[$i][0] === T_CLASS || $tokens[$i][0] === T_INTERFACE || $tokens[$i][0] === T_TRAIT) {
                // Check for ::class
                $isClassResolution = false;
                for ($j = $i - 1; $j >= 0; $j--) {
                    if (is_array($tokens[$j])) {
                        if ($tokens[$j][0] === T_WHITESPACE) {
                            continue;
                        }
                        if ($tokens[$j][0] === T_DOUBLE_COLON) {
                            $isClassResolution = true;
                        }
                    }
                    break;
                }
                if ($isClassResolution) {
                    continue;
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j])) {
                        if ($tokens[$j][0] === T_STRING) {
                            $class = $tokens[$j][1];
                            break;
                        }
                    }
                }
                break;
            }
        }
    }

    if (!$class) {
        return null;
    }
    return $namespace ? $namespace . '\\' . $class : $class;
}
