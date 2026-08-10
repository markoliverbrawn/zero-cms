<?php
/**
 * Zero CMS Complete DocBlock Auditor
 */

$srcDir = '/data/misc/zero/src';
$files = getPhpFiles($srcDir);

$missingFileHeaders = [];
$missingClassDocs = [];
$missingMethodDocs = [];

foreach ($files as $file) {
    if (strpos($file, '/Views/') !== false || strpos($file, '/templates/') !== false) {
        continue;
    }

    $relative = str_replace('/data/misc/zero/', '', $file);
    analyzeFile($file, $relative, $missingFileHeaders, $missingClassDocs, $missingMethodDocs);
}

echo "=== COMPLETE DOCBLOCK AUDIT ===\n\n";

echo "--- MISSING FILE HEADERS (" . count($missingFileHeaders) . ") ---\n";
foreach ($missingFileHeaders as $f) {
    echo "  - $f\n";
}
echo "\n";

echo "--- MISSING CLASS/INTERFACE/TRAIT DOCS (" . count($missingClassDocs) . ") ---\n";
foreach ($missingClassDocs as $c) {
    echo "  - {$c['file']}:${c['line']} : {$c['name']}\n";
}
echo "\n";

echo "--- MISSING METHOD DOCS (" . count($missingMethodDocs) . ") ---\n";
echo "Total missing method docblocks: " . count($missingMethodDocs) . "\n";
$byFile = [];
foreach ($missingMethodDocs as $m) {
    $byFile[$m['file']][] = $m['method'];
}
foreach ($byFile as $f => $methods) {
    echo "  - $f : " . implode(', ', $methods) . "\n";
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

function analyzeFile($filePath, $relative, &$missingFileHeaders, &$missingClassDocs, &$missingMethodDocs) {
    $tokens = token_get_all(file_get_contents($filePath));
    
    // 1. Check for file-level header (must be before T_NAMESPACE or T_CLASS etc.)
    $firstDocFound = false;
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] === T_DOC_COMMENT) {
                // To be a file header, it must be the very first doc comment and must be before any namespace/class
                $firstDocFound = true;
                break;
            }
            if ($token[0] === T_NAMESPACE || $token[0] === T_CLASS || $token[0] === T_INTERFACE || $token[0] === T_TRAIT) {
                break;
            }
        }
    }
    // Let's also check if there is a separate class/interface/trait doc block.
    // If the only doc comment is right before class/interface/trait, it serves as the class block, meaning file header is missing.
    // So we need a doc comment before namespace or at the very top for a file header.
    $hasFileHeader = false;
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] === T_DOC_COMMENT) {
                $hasFileHeader = true;
                break;
            }
            if ($token[0] === T_NAMESPACE) {
                // If we hit namespace before any doc comment, file header is definitely missing
                break;
            }
            if ($token[0] === T_CLASS || $token[0] === T_INTERFACE || $token[0] === T_TRAIT) {
                break;
            }
        }
    }
    if (!$hasFileHeader) {
        $missingFileHeaders[] = $relative;
    }

    // 2. Parse classes, traits, interfaces and methods
    $currentClass = null;
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }

        list($id, $text, $line) = $token;

        if ($id === T_CLASS || $id === T_INTERFACE || $id === T_TRAIT) {
            // Check if it is a real class declaration or just ::class
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

            $className = '';
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j])) {
                    if ($tokens[$j][0] === T_STRING) {
                        $className = $tokens[$j][1];
                        break;
                    }
                    if ($tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }
                }
                break;
            }
            $currentClass = $className;

            $hasDoc = false;
            for ($j = $i - 1; $j >= 0; $j--) {
                if (!is_array($tokens[$j])) {
                    if ($tokens[$j] === '{' || $tokens[$j] === '}' || $tokens[$j] === ';') {
                        break;
                    }
                    continue;
                }
                if ($tokens[$j][0] === T_DOC_COMMENT) {
                    $hasDoc = true;
                    break;
                }
                if ($tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                if ($tokens[$j][0] === T_FINAL || $tokens[$j][0] === T_ABSTRACT) {
                    continue;
                }
                break;
            }
            if (!$hasDoc) {
                $missingClassDocs[] = [
                    'file' => $relative,
                    'line' => $line,
                    'name' => $className
                ];
            }
        }

        if ($id === T_FUNCTION) {
            $funcName = '';
            $isAnonymous = true;
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j])) {
                    if ($tokens[$j][0] === T_STRING) {
                        $funcName = $tokens[$j][1];
                        $isAnonymous = false;
                        break;
                    }
                    if ($tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }
                }
                break;
            }

            if ($isAnonymous) {
                continue;
            }

            $hasDoc = false;
            for ($j = $i - 1; $j >= 0; $j--) {
                if (!is_array($tokens[$j])) {
                    if ($tokens[$j] === '{' || $tokens[$j] === '}' || $tokens[$j] === ';') {
                        break;
                    }
                    continue;
                }
                if ($tokens[$j][0] === T_DOC_COMMENT) {
                    $hasDoc = true;
                    break;
                }
                if ($tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                if (in_array($tokens[$j][0], [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT])) {
                    continue;
                }
                break;
            }
            if (!$hasDoc) {
                $missingMethodDocs[] = [
                    'file' => $relative,
                    'line' => $line,
                    'class' => $currentClass ?? '[Global]',
                    'method' => $funcName
                ];
            }
        }
    }
}
