<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static PENDING()
 * @method static static PROCESSING()
 * @method static static COMPLETED()
 * @method static static FAILED()
 * @method static static SKIPPED()
 */
final class StoryStatus extends Enum
{
    const PENDING    = 0;
    const PROCESSING = 1;
    const COMPLETED  = 2;
    const FAILED     = 3;
    const SKIPPED    = 4;
}
