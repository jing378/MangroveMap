<?php

namespace App\Support;

/**
 * The mangrove genera users pick when mapping, with the local name and a short plain description,
 * so residents and experts read the dashboard's genus figures the same way.
 */
class MangroveGenera
{
    /** @var array<string, array{local: string, about: string, color: string}> */
    public const INFO = [
        'Rhizophora' => [
            'local' => 'Bakawan',
            'about' => 'Stilt roots that arch out from the trunk. The most common mangrove for planting.',
            'color' => '#1e9e62',
        ],
        'Avicennia' => [
            'local' => 'Bungalon',
            'about' => 'Pencil-like roots poking up from the mud around the tree. Often on the edges of a stand.',
            'color' => '#2b8fb8',
        ],
        'Sonneratia' => [
            'local' => 'Pagatpat',
            'about' => 'Cone-shaped breathing roots and large fluffy flowers. Usually on the seaward side.',
            'color' => '#c07818',
        ],
        'Bruguiera' => [
            'local' => 'Miapi',
            'about' => 'Knee-shaped roots. Grows a little further inland, behind the stilt-root trees.',
            'color' => '#8e5bb5',
        ],
        'Ceriops' => [
            'local' => 'Tungog',
            'about' => 'Small trees or shrubs with short knee roots, on higher, drier ground.',
            'color' => '#d04030',
        ],
        'Xylocarpus' => [
            'local' => 'Tabigi',
            'about' => 'Large round fruits like cannonballs and flat, snaking roots. On the landward side.',
            'color' => '#6b7d2a',
        ],
        'Nypa' => [
            'local' => 'Nipa/Sasa',
            'about' => 'A palm without a trunk along rivers and creeks. Its leaves are used for roofing.',
            'color' => '#e6a23c',
        ],
    ];

    /** Colour for genera that are not in the list above (typed in as "Other"). */
    public const OTHER_COLOR = '#909399';

    /** @return array{local: ?string, about: ?string, color: string} */
    public static function info(string $genus): array
    {
        return self::INFO[$genus] ?? ['local' => null, 'about' => null, 'color' => self::OTHER_COLOR];
    }
}
