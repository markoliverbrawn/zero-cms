<?php
// src/Integration/Tests/ModuleLangDictionaryTest.php
// Guards the module-owned language dictionary mandate (Rule 2 / Rule 6): modules own their strings,
// core owns none of theirs, every module ships all four languages, and no two modules silently
// claim the same key. Module dictionaries all array_merge into one flat bucket inside I18n, so a
// duplicate key between two modules resolves by scandir order -- a collision is a real bug, and
// this suite is the only thing that makes it loud.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;

echo "=== Module Language Dictionary Tests ===\n";

$languages = ['en', 'es', 'hr', 'mi'];

/**
 * Keys that are derived from a model field name at render time rather than written literally at a
 * call site: AbstractFormField::resolveHelperText() builds "{field}_help" / "{field}_desc" from the
 * field itself (Rule 6), so these physically cannot carry a module-id prefix. They are exempt from
 * the prefix convention -- but NOT from the collision check below, which is exactly where a clash
 * between two modules owning a same-named field (two "price" fields, say) would surface.
 */
$derivedSuffixes = ['_help', '_desc'];

/**
 * Unprefixed keys that predate the prefix convention. Grandfathered so the convention can be
 * enforced on everything new without a rename sweep through existing call sites. Do not add to
 * this list -- prefix the new key with its module id instead.
 */
$legacyUnprefixed = [];

/**
 * Core keys that happen to begin with a string doubling as a module id, and so trip the
 * "core names no module" check below without actually belonging to that module. 'admin_dashboard'
 * is the sidebar label core itself registers in ManagesAdminSidebar -- the Admin module ships no
 * dictionary of its own. Add here only for a key core genuinely owns.
 */
$coreOwnedPrefixMatches = ['admin_dashboard'];

// Map each module directory on disk to its authoritative module id. getId() is the source of truth,
// not the folder name -- Search's folder is "Search" but its id is "site-search".
$moduleIds = [];
foreach (App::getModules() as $module) {
    $ref = new ReflectionClass($module);
    $moduleIds[dirname($ref->getFileName())] = $module->getId();
}

assert_critical(!empty($moduleIds), "Module registry resolved at least one module");

// Collect every module dictionary, keyed by language then module id.
$dictionaries = [];
foreach ($moduleIds as $moduleDir => $moduleId) {
    foreach ($languages as $code) {
        $file = $moduleDir . '/Lang/' . $code . '.php';
        if (!file_exists($file)) {
            continue;
        }
        $loaded = require $file;
        assert_test(is_array($loaded), "{$moduleId}/Lang/{$code}.php returns an array");
        if (is_array($loaded)) {
            $dictionaries[$code][$moduleId] = $loaded;
        }
    }
}

// ---------------------------------------------------------------------------------------------
// 1. Every module that ships any dictionary must ship all four languages, with matching key sets.
//    There is no per-key fallback to English, so a key absent from the active language renders as
//    the raw key string in the admin UI.
// ---------------------------------------------------------------------------------------------
echo "Testing per-module language completeness...\n";

$modulesWithLang = [];
foreach ($dictionaries as $code => $byModule) {
    $modulesWithLang = array_merge($modulesWithLang, array_keys($byModule));
}
$modulesWithLang = array_values(array_unique($modulesWithLang));
if (empty($modulesWithLang)) {
    // No module currently ships a dictionary. Sections 1, 2 and 4 below iterate the (empty) set
    // and are inert; section 3 still guards core against absorbing module strings.
    echo "  (no module ships a Lang dictionary -- per-module completeness checks skipped)\n";
}

foreach ($modulesWithLang as $moduleId) {
    $reference = array_keys($dictionaries['en'][$moduleId] ?? []);
    assert_test(!empty($reference), "Module '{$moduleId}' ships an en.php dictionary");

    foreach ($languages as $code) {
        $present = isset($dictionaries[$code][$moduleId]);
        assert_test($present, "Module '{$moduleId}' ships a {$code}.php dictionary");
        if (!$present) {
            continue;
        }
        $keys = array_keys($dictionaries[$code][$moduleId]);
        $missing = array_diff($reference, $keys);
        $extra = array_diff($keys, $reference);
        assert_test(
            empty($missing),
            "Module '{$moduleId}' {$code}.php covers every en.php key"
                . (empty($missing) ? '' : ' -- missing: ' . implode(', ', $missing))
        );
        assert_test(
            empty($extra),
            "Module '{$moduleId}' {$code}.php declares no key absent from en.php"
                . (empty($extra) ? '' : ' -- extra: ' . implode(', ', $extra))
        );
    }
}

// ---------------------------------------------------------------------------------------------
// 2. No two modules may claim the same key. I18n merges every module dictionary into one flat
//    array, so the winner is whichever module scandir reaches last -- silent and order-dependent.
//    Identical values are still a collision worth naming: the duplicate belongs in core, or one of
//    the two keys needs a module-id prefix.
// ---------------------------------------------------------------------------------------------
echo "Testing cross-module key collisions...\n";

foreach ($languages as $code) {
    $owners = [];
    foreach ($dictionaries[$code] ?? [] as $moduleId => $translations) {
        foreach (array_keys($translations) as $key) {
            $owners[$key][] = $moduleId;
        }
    }

    $collisions = [];
    foreach ($owners as $key => $claimants) {
        if (count($claimants) > 1) {
            $collisions[] = $key . ' (' . implode(' + ', $claimants) . ')';
        }
    }

    assert_test(
        empty($collisions),
        "No key in {$code} is claimed by more than one module"
            . (empty($collisions) ? '' : ' -- collisions: ' . implode('; ', $collisions))
    );
}

// ---------------------------------------------------------------------------------------------
// 3. Core must not carry module strings, and a module must not redeclare a core key. translate()
//    resolves core before the module bucket, so a key in both silently uses core's value and the
//    module file becomes dead weight.
// ---------------------------------------------------------------------------------------------
echo "Testing core dictionary separation...\n";

foreach ($languages as $code) {
    $coreFile = APPLICATION_ROOT . '/src/Lang/' . $code . '.php';
    assert_critical(file_exists($coreFile), "Core dictionary src/Lang/{$code}.php exists");
    $core = require $coreFile;

    foreach ($dictionaries[$code] ?? [] as $moduleId => $translations) {
        $shadowed = array_intersect(array_keys($translations), array_keys($core));
        assert_test(
            empty($shadowed),
            "Module '{$moduleId}' declares no key already in core {$code}.php"
                . (empty($shadowed) ? '' : ' -- shadowed by core: ' . implode(', ', $shadowed))
        );
    }

    // Core must never name a module. Catches the specific regression this mandate was introduced
    // to fix: module strings drifting back into the core dictionaries. The match is by module-id
    // prefix, so a core key that legitimately opens with a word doubling as a module id has to be
    // exempted by name -- $coreOwnedPrefixMatches below, not a loosening of the rule.
    $moduleNamed = [];
    foreach (array_keys($core) as $key) {
        if (in_array($key, $coreOwnedPrefixMatches, true)) {
            continue;
        }
        foreach ($moduleIds as $moduleId) {
            if (strpos($key, $moduleId . '_') === 0) {
                $moduleNamed[] = $key;
            }
        }
    }
    assert_test(
        empty($moduleNamed),
        "Core {$code}.php carries no module-prefixed key"
            . (empty($moduleNamed) ? '' : ' -- found: ' . implode(', ', $moduleNamed))
    );
}

// ---------------------------------------------------------------------------------------------
// 4. Prefix convention: any module key that is not field-derived and not grandfathered must start
//    with its own module id, which is what keeps the shared flat namespace collision-free.
// ---------------------------------------------------------------------------------------------
echo "Testing module-id prefix convention...\n";

foreach ($dictionaries['en'] ?? [] as $moduleId => $translations) {
    $exempt = $legacyUnprefixed[$moduleId] ?? [];
    $offenders = [];

    foreach (array_keys($translations) as $key) {
        if (in_array($key, $exempt, true)) {
            continue;
        }
        $isDerived = false;
        foreach ($derivedSuffixes as $suffix) {
            if (substr($key, -strlen($suffix)) === $suffix) {
                $isDerived = true;
                break;
            }
        }
        if ($isDerived) {
            continue;
        }
        if (strpos($key, $moduleId . '_') !== 0) {
            $offenders[] = $key;
        }
    }

    assert_test(
        empty($offenders),
        "Module '{$moduleId}' prefixes every new non-derived key with '{$moduleId}_'"
            . (empty($offenders) ? '' : ' -- unprefixed: ' . implode(', ', $offenders))
    );
}

echo "=== Module Language Dictionary Tests Complete ===\n";
