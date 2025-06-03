<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    // prod requirement but not installed in dev mode
    ->ignoreErrorsOnPackage('contao/core-bundle', [ErrorType::UNUSED_DEPENDENCY])

    // dev requirement to access legacy ECS config
    ->ignoreErrorsOnPackage('contao/contao', [ErrorType::DEV_DEPENDENCY_IN_PROD])
;
