<?php

namespace App\Support;

/**
 * The mangrove genera users pick when mapping, with the local name and a short plain description,
 * so residents and experts read the dashboard's genus figures the same way.
 */
class MangroveGenera
{
    /**
     * `image` is a sample photo under public/ (Wikimedia Commons; `credit` + `source` give the attribution
     * its licence asks for).
     *
     * @var array<string, array{local: string, about: string, color: string, image: string, credit: string, source: string}>
     */
    public const INFO = [
        'Rhizophora' => [
            'local' => 'Bakawan',
            'about' => 'Stilt roots that arch out from the trunk. The most common mangrove for planting.',
            'color' => '#1e9e62',
            'image' => 'images/genera/rhizophora.jpg',
            'credit' => 'Photo: Tauʻolunga, CC BY-SA 3.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Rhizophora_mangle.jpg',
        ],
        'Avicennia' => [
            'local' => 'Bungalon',
            'about' => 'Pencil-like roots poking up from the mud around the tree. Often on the edges of a stand.',
            'color' => '#2b8fb8',
            'image' => 'images/genera/avicennia.jpg',
            'credit' => 'Photo: public domain',
            'source' => 'https://commons.wikimedia.org/wiki/File:Avicennia_germinans.jpg',
        ],
        'Sonneratia' => [
            'local' => 'Pagatpat',
            'about' => 'Cone-shaped breathing roots and large fluffy flowers. Usually on the seaward side.',
            'color' => '#c07818',
            'image' => 'images/genera/sonneratia.jpg',
            'credit' => 'Photo: Ton Rulkens, CC BY-SA 2.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Sonneratia_alba_-_fruit_(8349980264).jpg',
        ],
        'Bruguiera' => [
            'local' => 'Miapi',
            'about' => 'Knee-shaped roots. Grows a little further inland, behind the stilt-root trees.',
            'color' => '#8e5bb5',
            'image' => 'images/genera/bruguiera.jpg',
            'credit' => 'Photo: Ton Rulkens, CC BY-SA 2.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Bruguiera_gymnorrhiza_-_flowers_and_fruit_(8349980084).jpg',
        ],
        'Ceriops' => [
            'local' => 'Tungog',
            'about' => 'Small trees or shrubs with short knee roots, on higher, drier ground.',
            'color' => '#d04030',
            'image' => 'images/genera/ceriops.jpg',
            'credit' => 'Photo: Ton Rulkens, CC BY-SA 2.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Ceriops_tagal_-_flowers_(8349980250).jpg',
        ],
        'Xylocarpus' => [
            'local' => 'Tabigi',
            'about' => 'Large round fruits like cannonballs and flat, snaking roots. On the landward side.',
            'color' => '#6b7d2a',
            'image' => 'images/genera/xylocarpus.jpg',
            'credit' => 'Photo: Toby Hudson, CC BY-SA 3.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Xylocarpus_granatum.jpg',
        ],
        'Nypa' => [
            'local' => 'Nipa/Sasa',
            'about' => 'A palm without a trunk along rivers and creeks. Its leaves are used for roofing.',
            'color' => '#e6a23c',
            'image' => 'images/genera/nypa.jpg',
            'credit' => 'Photo: Qaalvin, CC BY-SA 3.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Nipa_palms.jpg',
        ],
    ];

    /** Colour for genera that are not in the list above (typed in as "Other"). */
    public const OTHER_COLOR = '#909399';

    /** @return array{local: ?string, about: ?string, color: string, image?: string, credit?: string, source?: string} */
    public static function info(string $genus): array
    {
        return self::INFO[$genus] ?? ['local' => null, 'about' => null, 'color' => self::OTHER_COLOR];
    }

    /**
     * The list for pages' JavaScript, with each sample photo as a full URL.
     *
     * @return array<string, array{local: string, about: string, color: string, image: string, credit: string, source: string}>
     */
    public static function forClient(): array
    {
        return array_map(fn (array $info) => ['image' => asset($info['image'])] + $info, self::INFO);
    }
}
