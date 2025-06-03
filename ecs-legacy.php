<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoEmptyReturnFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->sets([__DIR__.'/vendor/contao/contao/vendor-bin/ecs/config/legacy.php']);

    $ecsConfig->skip([
        HeaderCommentFixer::class,
        PhpdocNoEmptyReturnFixer::class,
    ]);
};
