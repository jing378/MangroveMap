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
     * `species` lists the Philippine species of the genus. Each has a tree photo (`image` + `note`) and, when there is
     * a photo, `leaves` and `roots` ({note, image, credit, source}); the genus `roots` is shown for species without
     * their own. Photos are Wikimedia Commons (own `credit` + `source`) under public/images/genera/species/ (240 px
     * copies in thumbs/), or, where Commons had none, the field guide's (SPECIES_CREDIT).
     *
     * @var array<string, array{local: string, about: string, color: string, image: string, credit: string, source: string, species: list<array{name: string, local: string, note: string, image: string, credit?: string, source?: string, leaves?: array<string, string>, roots?: array<string, string>}>, roots?: array<string, string>}>
     */
    public const INFO = [
        'Rhizophora' => [
            'local' => 'Bakawan',
            'about' => 'Stilt roots that arch out from the trunk. The most common mangrove for planting.',
            'color' => '#1e9e62',
            'image' => 'images/genera/rhizophora.jpg',
            'credit' => 'Photo: Tauʻolunga, CC BY-SA 3.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Rhizophora_mangle.jpg',
            'roots' => ['note' => 'Stilt roots arch out from the trunk and low branches into the mud.', 'image' => 'images/genera/species/rhizophora-apiculata-roots.jpg', 'credit' => 'Photo: Foo Chuan Wei, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Rhizophora_apiculata_-_Brunei_-_511451148.jpg'],
            'species' => [
                [
                    'name' => 'Rhizophora apiculata', 'local' => 'bakhaw lalaki',
                    'note' => 'Tall tree in dense stands, along creeks and the middle of the forest.',
                    'image' => 'images/genera/species/rhizophora-apiculata-tree.jpg', 'credit' => 'Photo: Bernard DUPONT from FRANCE, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Tall-stilt_Mangroves_(Rhizophora_apiculata)_(15851577945).jpg',
                    'leaves' => ['note' => 'Leaves end in a sharp point; leaf stalks and midribs are often reddish.', 'image' => 'images/genera/species/rhizophora-apiculata-leaves.jpg', 'credit' => 'Photo: Dinesh Valke from Thane, India, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Rhizophora_apiculata_Blume_(53231938109).jpg'],
                    'roots' => ['image' => 'images/genera/species/rhizophora-apiculata-roots.jpg', 'credit' => 'Photo: Foo Chuan Wei, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Rhizophora_apiculata_-_Brunei_-_511451148.jpg'],
                ],
                [
                    'name' => 'Rhizophora mucronata', 'local' => 'bakhaw babae',
                    'note' => 'Large tree on soft, deep mud; the longest, warty seedlings.',
                    'image' => 'images/genera/species/rhizophora-mucronata-tree.jpg', 'credit' => 'Photo: Bernard DUPONT from FRANCE, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Tall-stilted_Mangrove_(Rhizophora_mucronata)_(9734173658).jpg',
                    'leaves' => ['note' => 'Broad leaves ending in a long, thin spiny tip.', 'image' => 'images/genera/species/rhizophora-mucronata-leaves.jpg', 'credit' => 'Photo: CC0 (public domain)', 'source' => 'https://commons.wikimedia.org/wiki/File:Indo-West_Pacific_Stilt_Mangrove_imported_from_iNaturalist_photo_256753741_on_27_February_2024.jpg'],
                    'roots' => ['image' => 'images/genera/species/rhizophora-mucronata-roots.jpg', 'credit' => 'Photo: CC0 (public domain)', 'source' => 'https://commons.wikimedia.org/wiki/File:Indo-West_Pacific_Stilt_Mangrove_imported_from_iNaturalist_photo_256753680_on_27_February_2024.jpg'],
                ],
                [
                    'name' => 'Rhizophora stylosa', 'local' => 'bakhaw bato',
                    'note' => 'Small to medium tree, often on sandy or rocky shores.',
                    'image' => 'images/genera/species/rhizophora-stylosa-tree.jpg', 'credit' => 'Photo: Jennifer M. Real, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Rhizopora_stylosa_at_Bongalonan_Marine_Sanctuary.jpg',
                    'leaves' => ['note' => 'Leaves with a short spiny tip; flowers have a long style.', 'image' => 'images/genera/species/rhizophora-stylosa-leaves.jpg', 'credit' => 'Photo: John Robert McPherson, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Rhizophora_stylosa,_Nudgee_Creek,_Nudgee_Beach_IMGP0920.jpg'],
                    'roots' => ['image' => 'images/genera/species/rhizophora-stylosa-roots.jpg', 'credit' => 'Photo: Jonathan Wilkins, CC BY-SA 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Detail_of_mangrove_roots.jpg'],
                ],
            ],
        ],
        'Avicennia' => [
            'local' => 'Bungalon',
            'about' => 'Pencil-like roots poking up from the mud around the tree. Often on the edges of a stand.',
            'color' => '#2b8fb8',
            'image' => 'images/genera/avicennia.jpg',
            'credit' => 'Photo: public domain',
            'source' => 'https://commons.wikimedia.org/wiki/File:Avicennia_germinans.jpg',
            'roots' => ['note' => 'Pencil-like breathing roots (pneumatophores) poke up from the mud around the tree.', 'image' => 'images/genera/species/avicennia-marina-roots.jpg', 'credit' => 'Photo: Peripitus, CC BY-SA 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Pneumatophore_overkill_-_grey_mangrove.JPG'],
            'species' => [
                [
                    'name' => 'Avicennia alba', 'local' => 'bungalon, apiapi, miapi',
                    'note' => 'Spreading tree at the seaward edge, on soft mud.',
                    'image' => 'images/genera/species/avicennia-alba-tree.jpg', 'credit' => 'Photo: Wie146, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Avic_alba_20210420-105242_ppk.jpg',
                    'leaves' => ['note' => 'Narrow, pointed leaves, silvery-white underneath; small orange flowers.', 'image' => 'images/genera/species/avicennia-alba-leaves.jpg', 'credit' => 'Photo: Wie146, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Avic_alba_20210420-114257_ppk.jpg'],
                ],
                [
                    'name' => 'Avicennia marina', 'local' => 'bungalon, apiapi, miapi',
                    'note' => 'Shrub or tree with smooth, pale grey bark that flakes in patches.',
                    'image' => 'images/genera/species/avicennia-marina-tree.jpg', 'credit' => 'Photo: Colin Trainor, CC BY-SA 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Mature_mangrove_tree_(Avicennia_marina)_at_edge_of_Lake_Be_Malae.jpg',
                    'leaves' => ['note' => 'Leaves are greyish-white underneath.', 'image' => 'images/genera/species/avicennia-marina-leaves.jpg', 'credit' => 'Photo: Peripitus, CC BY-SA 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Grey_Mangrove_leaf_underside.jpg'],
                    'roots' => ['image' => 'images/genera/species/avicennia-marina-roots.jpg', 'credit' => 'Photo: Peripitus, CC BY-SA 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Pneumatophore_overkill_-_grey_mangrove.JPG'],
                ],
                [
                    'name' => 'Avicennia officinalis', 'local' => 'bungalon, apiapi, miapi',
                    'note' => 'Tree with a dense crown, more on the landward side.',
                    'image' => 'images/genera/species/avicennia-officinalis-tree.jpg', 'credit' => 'Photo: Dinesh Valke from Thane, India, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Avicennia_officinalis_(2445950610).jpg',
                    'leaves' => ['note' => 'Broad leaves, rounded at the tip; larger yellow-orange flowers.', 'image' => 'images/genera/species/avicennia-officinalis-leaves.jpg', 'credit' => 'Photo: Dinesh Valke from Thane, India, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Avicennia_officinalis_(2682502984).jpg'],
                ],
                [
                    'name' => 'Avicennia rumphiana', 'local' => 'bungalon, apiapi, miapi',
                    'note' => 'Hairy, brownish leaf undersurface and hairy fruit.',
                    'image' => 'images/genera/species/avicennia-rumphiana.jpg',
                ],
            ],
        ],
        'Sonneratia' => [
            'local' => 'Pagatpat',
            'about' => 'Cone-shaped breathing roots and large fluffy flowers. Usually on the seaward side.',
            'color' => '#c07818',
            'image' => 'images/genera/sonneratia.jpg',
            'credit' => 'Photo: Ton Rulkens, CC BY-SA 2.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Sonneratia_alba_-_fruit_(8349980264).jpg',
            'roots' => ['note' => 'Thick, cone-shaped breathing roots, taller and sturdier than Avicennia\'s.', 'image' => 'images/genera/species/sonneratia-alba-roots.jpg', 'credit' => 'Photo: Dinesh Valke from Thane, India, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Sonneratia_alba_Sm._(52264549541).jpg'],
            'species' => [
                [
                    'name' => 'Sonneratia alba', 'local' => 'pagatpat',
                    'note' => 'Tree on the seaward edge, in the open sea front.',
                    'image' => 'images/genera/species/sonneratia-alba-tree.jpg', 'credit' => 'Photo: Ariefrahman, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Sonneratia_alba_-_Manado_(1).JPG',
                    'leaves' => ['note' => 'Thick, rounded leaves; the fruit sits in a star-shaped cup.', 'image' => 'images/genera/species/sonneratia-alba-leaves.jpg', 'credit' => 'Photo: Ton Rulkens from Mozambique, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Sonneratia_alba_-_fruit_(8349980264).jpg'],
                    'roots' => ['image' => 'images/genera/species/sonneratia-alba-roots.jpg', 'credit' => 'Photo: Dinesh Valke from Thane, India, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Sonneratia_alba_Sm._(52264549541).jpg'],
                ],
                [
                    'name' => 'Sonneratia caseolaris', 'local' => 'pedada',
                    'note' => 'Tree along rivers and brackish water, with drooping branches.',
                    'image' => 'images/genera/species/sonneratia-caseolaris-tree.jpg', 'credit' => 'Photo: Judgefloro, Public domain', 'source' => 'https://commons.wikimedia.org/wiki/File:08734JfMasantolSanIsidroAnacfvf_08.JPG',
                    'leaves' => ['note' => 'Narrower, pointed leaves; flowers with pink-red stamens.', 'image' => 'images/genera/species/sonneratia-caseolaris-leaves.jpg', 'credit' => 'Photo: Renjusplace, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Sonneratia_caseolaris,_Apple_Mangrove._%E0%B4%86%E0%B4%AA%E0%B5%8D%E0%B4%AA%E0%B4%BF%E0%B5%BE_%E0%B4%95%E0%B4%A3%E0%B5%8D%E0%B4%9F%E0%B5%BD_flower.jpg'],
                    'roots' => ['image' => 'images/genera/species/sonneratia-caseolaris-roots.jpg', 'credit' => 'Photo: Shagil Kannur, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Mangroves_at_Muzhappilangad,_Kannur,_Kerala_00_(27).jpg'],
                ],
                [
                    'name' => 'Sonneratia ovata', 'local' => 'pedada',
                    'note' => 'Small tree of less salty, inner areas, with very round, broad leaves.',
                    'image' => 'images/genera/species/sonneratia-ovata-tree.jpg', 'credit' => 'Photo: Abu Hamas, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Sonneratia_ovata.jpg',
                ],
            ],
        ],
        'Bruguiera' => [
            'local' => 'Pototan',
            'about' => 'Knee-shaped roots. Grows a little further inland, behind the stilt-root trees.',
            'color' => '#8e5bb5',
            'image' => 'images/genera/bruguiera.jpg',
            'credit' => 'Photo: Ton Rulkens, CC BY-SA 2.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Bruguiera_gymnorrhiza_-_flowers_and_fruit_(8349980084).jpg',
            'roots' => ['note' => 'Knee roots loop up out of the mud; the trunk base has small buttresses.', 'image' => 'images/genera/species/bruguiera-gymnorrhiza-roots.jpg', 'credit' => 'Photo: Anonymous Powered, CC BY-SA 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Bruguiera_gymnorhiza_roots.jpg'],
            'species' => [
                [
                    'name' => 'Bruguiera cylindrica', 'local' => 'pototan, busain',
                    'note' => 'Small to medium tree, often in stands on firm mud.',
                    'image' => 'images/genera/species/bruguiera-cylindrica-tree.jpg', 'credit' => 'Photo: Dinesh Valke from Thane, India, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Bruguiera_cylindrica_(L.)_Blume_(48149770441).jpg',
                    'leaves' => ['note' => 'Pointed, glossy leaves; small greenish-white flowers.', 'image' => 'images/genera/species/bruguiera-cylindrica-leaves.jpg', 'credit' => 'Photo: Dinesh Valke from Thane, India, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Bruguiera_cylindrica_(L.)_Blume_(53232091300).jpg'],
                ],
                [
                    'name' => 'Bruguiera gymnorrhiza', 'local' => 'pototan, busain',
                    'note' => 'Large tree with a dense, dark crown.',
                    'image' => 'images/genera/species/bruguiera-gymnorrhiza-tree.jpg', 'credit' => 'Photo: John Robert McPherson, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Bruguiera_gymnorrhiza,_between_dune_and_Avicennia_marina_shrubland,_Nudgee_Creek_01.jpg',
                    'leaves' => ['note' => 'Large leathery leaves; big red flowers.', 'image' => 'images/genera/species/bruguiera-gymnorrhiza-leaves.jpg', 'credit' => 'Photo: MaedaAkihiko, CC0', 'source' => 'https://commons.wikimedia.org/wiki/File:Bruguiera-gymnorhiza_Flower.jpg'],
                    'roots' => ['image' => 'images/genera/species/bruguiera-gymnorrhiza-roots.jpg', 'credit' => 'Photo: Anonymous Powered, CC BY-SA 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Bruguiera_gymnorhiza_roots.jpg'],
                ],
                [
                    'name' => 'Bruguiera parviflora', 'local' => 'langarai',
                    'note' => 'Slender tree with a narrow crown.',
                    'image' => 'images/genera/species/bruguiera-parviflora-tree.jpg', 'credit' => 'Photo: Wibowo Djatmiko (Wie146), CC BY-SA 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Brugu_parvi_111021-18703_H_kbu.jpg',
                    'leaves' => ['note' => 'Yellowish-green leaves, tiny flowers and long, very thin seedlings.', 'image' => 'images/genera/species/bruguiera-parviflora-leaves.jpg', 'credit' => 'Photo: Wibowo Djatmiko (Wie146), CC BY-SA 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Brugu_parvi_111021-18857_Fr_kbu.jpg'],
                    'roots' => ['image' => 'images/genera/species/bruguiera-parviflora-roots.jpg', 'credit' => 'Photo: Wibowo Djatmiko (Wie146), CC BY-SA 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Brugu_parvi_111020-18576_R_kbu.jpg'],
                ],
                [
                    'name' => 'Bruguiera sexangula', 'local' => 'pototan',
                    'note' => 'Medium tree, often along creeks.',
                    'image' => 'images/genera/species/bruguiera-sexangula-tree.jpg', 'credit' => 'Photo: Krzysztof Ziarnek, Kenraiz, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Bruguiera_sexangula_kz05.jpg',
                    'leaves' => ['note' => 'Glossy leaves; yellow to orange flowers.', 'image' => 'images/genera/species/bruguiera-sexangula-leaves.jpg', 'credit' => 'Photo: Krzysztof Ziarnek, Kenraiz, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Bruguiera_sexangula_kz01.jpg'],
                ],
            ],
        ],
        'Ceriops' => [
            'local' => 'Tungog',
            'about' => 'Small trees or shrubs with short knee roots, on higher, drier ground.',
            'color' => '#d04030',
            'image' => 'images/genera/ceriops.jpg',
            'credit' => 'Photo: Ton Rulkens, CC BY-SA 2.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Ceriops_tagal_-_flowers_(8349980250).jpg',
            'roots' => ['note' => 'Small buttresses at the trunk base and short knee roots.'],
            'species' => [
                [
                    'name' => 'Ceriops decandra', 'local' => 'baras-baras',
                    'note' => 'Bushy shrub or small tree with rounded leaves; short seedlings.',
                    'image' => 'images/genera/species/ceriops-decandra.jpg',
                ],
                [
                    'name' => 'Ceriops tagal', 'local' => 'tungog, tangal',
                    'note' => 'Small tree, often in dense thickets on higher ground.',
                    'image' => 'images/genera/species/ceriops-tagal-tree.jpg', 'credit' => 'Photo: Abu Hamas, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Ceriops_tagal_50590387.jpg',
                    'leaves' => ['note' => 'Rounded leaves clustered at the branch tips; long, ridged seedlings.', 'image' => 'images/genera/species/ceriops-tagal-leaves.jpg', 'credit' => 'Photo: Dinesh Valke from Thane, India, CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Ceriops_tagal_(Perr.)_C.B.Rob._(48149859877).jpg'],
                ],
            ],
        ],
        'Xylocarpus' => [
            'local' => 'Tabigi',
            'about' => 'Large round fruits like cannonballs and flat, snaking roots. On the landward side.',
            'color' => '#6b7d2a',
            'image' => 'images/genera/xylocarpus.jpg',
            'credit' => 'Photo: Toby Hudson, CC BY-SA 3.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Xylocarpus_granatum.jpg',
            'roots' => ['note' => 'Roots spread over the ground from the trunk base.'],
            'species' => [
                [
                    'name' => 'Xylocarpus granatum', 'local' => 'tabigi',
                    'note' => 'Tree with smooth, peeling bark and big round fruits.',
                    'image' => 'images/genera/species/xylocarpus-granatum-tree.jpg', 'credit' => 'Photo: juggadery, CC BY 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Dhundul_tree_(23617916672).jpg',
                    'leaves' => ['note' => 'Compound leaves of 2–3 pairs of rounded leaflets.', 'image' => 'images/genera/species/xylocarpus-granatum-leaves.jpg', 'credit' => 'Photo: Tony van Kampen, CC BY 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Xylocarpus_granatum_195278393.jpg'],
                    'roots' => ['note' => 'Flat, snake-like plank roots wind across the ground.'],
                ],
                [
                    'name' => 'Xylocarpus moluccensis', 'local' => 'piag-ao',
                    'note' => 'Tree with rough, fissured bark; leaves turn red before falling.',
                    'image' => 'images/genera/species/xylocarpus-moluccensis-tree.jpg', 'credit' => 'Photo: Steve Fitzgerald, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Xylocarpus-moluccensis-SF24190-01.jpg',
                    'leaves' => ['note' => 'Compound leaves with pointed leaflets.', 'image' => 'images/genera/species/xylocarpus-moluccensis-leaves.jpg', 'credit' => 'Photo: Steve Fitzgerald, CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Xylocarpus-moluccensis-SF23319-01.jpg'],
                    'roots' => ['note' => 'Cone-shaped breathing roots around the tree.'],
                ],
            ],
        ],
        'Nypa' => [
            'local' => 'Nipa/Sasa',
            'about' => 'A palm without a trunk along rivers and creeks. Its leaves are used for roofing.',
            'color' => '#e6a23c',
            'image' => 'images/genera/nypa.jpg',
            'credit' => 'Photo: Qaalvin, CC BY-SA 3.0',
            'source' => 'https://commons.wikimedia.org/wiki/File:Nipa_palms.jpg',
            'roots' => ['note' => 'No trunk: a thick stem creeps under the mud and the leaves rise straight from it.'],
            'species' => [
                [
                    'name' => 'Nypa fruticans', 'local' => 'nipa, sasa',
                    'note' => 'Trunkless palm in clumps along rivers and creeks.',
                    'image' => 'images/genera/species/nypa-fruticans-tree.jpg', 'credit' => 'Photo: luluchouette, CC BY 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Nypa_fruticans_258785735.jpg',
                    'leaves' => ['note' => 'Long, feather-like fronds rising from the ground; a ball-shaped fruit cluster.', 'image' => 'images/genera/species/nypa-fruticans-leaves.jpg', 'credit' => 'Photo: luluchouette, CC BY 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Nypa_fruticans_282440316.jpg'],
                    'roots' => ['image' => 'images/genera/species/nypa-fruticans-roots.jpg', 'credit' => 'Photo: Tim Hirsch, CC BY 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Nypa_fruticans_244938790.jpg'],
                ],
            ],
        ],
    ];

    /**
     * Where the species and local names come from, and the credit for the species photos taken from that guide
     * (those without their own `credit`). Keep it shown wherever those photos are.
     */
    public const SPECIES_CREDIT = 'Species and local names: J. H. Primavera, Field Guide to Philippine Mangroves (PTFCF / ZSL-Philippines). Photo from the guide: Primavera et al. 2004, Handbook of Mangroves in the Philippines – Panay (SEAFDEC/AQD, UNESCO).';

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
     * @return array<string, array{local: string, about: string, color: string, image: string, credit: string, source: string, species: list<array{name: string, local: string, note: string, image: string, credit?: string, source?: string, leaves?: array<string, string>, roots?: array<string, string>}>, roots?: array<string, string>}>
     */
    public static function forClient(): array
    {
        return array_map(fn (array $info) => [
            'image' => self::photoUrl($info['image']),
            'species' => array_map(fn (array $species) => [
                'image' => self::photoUrl($species['image']),
                'thumb' => self::photoUrl(self::thumbPath($species['image'])),
                'parts' => self::speciesParts($species, $info),
            ] + $species, $info['species'] ?? []),
        ] + $info, self::INFO);
    }

    /**
     * The photos the viewer offers for one species, one at a time: the tree, then its leaves and roots when there
     * is a photo. A species without its own roots photo shows the genus's (`genusTypical`), since roots are mostly
     * the same across a genus.
     *
     * @return list<array{key: string, label: string, image: string, note: ?string, credit: ?string, source: ?string, genusTypical: bool}>
     */
    private static function speciesParts(array $species, array $genus): array
    {
        $part = fn (string $key, string $label, array $photo, bool $genusTypical = false) => [
            'key' => $key,
            'label' => $label,
            'image' => self::photoUrl($photo['image']),
            'note' => $photo['note'] ?? null,
            'credit' => $photo['credit'] ?? null,
            'source' => $photo['source'] ?? null,
            'genusTypical' => $genusTypical,
        ];

        $parts = [$part('tree', 'Tree', $species)];
        if (isset($species['leaves']['image'])) {
            $parts[] = $part('leaves', 'Leaves', $species['leaves']);
        }
        if (isset($species['roots']['image'])) {
            $parts[] = $part('roots', 'Roots', ['note' => $species['roots']['note'] ?? $genus['roots']['note'] ?? null] + $species['roots']);
        } elseif (isset($genus['roots']['image'])) {
            $parts[] = $part('roots', 'Roots', ['note' => $species['roots']['note'] ?? $genus['roots']['note'] ?? null] + $genus['roots'], true);
        }

        return $parts;
    }

    /** Full URL with the file's modified time, so a replaced photo isn't served from a browser or Cloudflare cache. */
    /** The 240 px copy of a species photo (public/images/genera/species/thumbs), else the photo itself. */
    private static function thumbPath(string $path): string
    {
        $thumb = dirname($path).'/thumbs/'.basename($path);

        return is_file(public_path($thumb)) ? $thumb : $path;
    }

    private static function photoUrl(string $path): string
    {
        $modified = @filemtime(public_path($path));

        return asset($path).($modified ? '?v='.$modified : '');
    }
}
