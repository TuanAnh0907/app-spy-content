<?php declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static TRANSLATED()
 * @method static static CONVERTED()
 * @method static static ORIGINAL()
 */
final class DtruyenStoryType extends Enum
{
    const TRANSLATED = 'translated';
    const CONVERTED = 'converted';
    const ORIGINAL = 'original';
}
