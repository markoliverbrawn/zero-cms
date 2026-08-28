<?php

declare(strict_types=1);

/**
 * File: src/Core/App.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 *
 * This class is composed of focused traits under src/Core/Concerns/, one per concern (access
 * control, bootstrap, request handling, module/theme/block/model registries, sidebar, current
 * tenant context, rendering, and small utility helpers). Every method keeps living on App and is
 * called exactly as before (App::render(), App::getCurrentSite(), etc.) -- the split only moves
 * where the code physically lives, so it carries no behavior change and no call-site updates.
 */

namespace Zero\Core;

use Zero\Core\Concerns\BootstrapsApp;
use Zero\Core\Concerns\EnforcesAccessControl;
use Zero\Core\Concerns\HandlesRequests;
use Zero\Core\Concerns\HasUtilityHelpers;
use Zero\Core\Concerns\ManagesAdminSidebar;
use Zero\Core\Concerns\ManagesBlocksAndModels;
use Zero\Core\Concerns\ManagesCurrentContext;
use Zero\Core\Concerns\ManagesFormFields;
use Zero\Core\Concerns\ManagesModelColumnRenderers;
use Zero\Core\Concerns\ManagesModelListActions;
use Zero\Core\Concerns\ManagesModelRowActions;
use Zero\Core\Concerns\ManagesModules;
use Zero\Core\Concerns\ManagesModuleSettings;
use Zero\Core\Concerns\ManagesThemes;
use Zero\Core\Concerns\RendersViews;
use Zero\Core\Concerns\ResolvesTenantContext;

/**
 * Class App
 *
 * Single static facade for the whole engine, assembled entirely from the Concerns traits it
 * composes: access control, bootstrap, request handling, the module/theme/block/model registries,
 * the admin sidebar, tenant context, view rendering, and utility helpers. It declares no members
 * of its own, so every App::* call resolves into one of those traits.
 */
class App
{
    use EnforcesAccessControl;
    use BootstrapsApp;
    use ResolvesTenantContext;
    use HandlesRequests;
    use ManagesModules;
    use ManagesThemes;
    use ManagesAdminSidebar;
    use ManagesBlocksAndModels;
    use ManagesFormFields;
    use ManagesModelColumnRenderers;
    use ManagesModelListActions;
    use ManagesModelRowActions;
    use ManagesModuleSettings;
    use ManagesCurrentContext;
    use RendersViews;
    use HasUtilityHelpers;
}
