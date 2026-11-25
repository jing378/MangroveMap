<?php

/*
| Official mangrove figures the dashboards compare MangroveMap with ("Compared with official figures",
| App\Services\MangroveComparison). Each has the place, hectares, year and a citable source. Update them here
| when a newer release comes out; the satellite figures per municipality come from the Global Mangrove Watch
| file (config map.mangrove_history.path) and need no entry.
|
| 'province' names the entry for this system's province: the comparison table's "% mapped" uses the satellite
| figures per municipality, and this provincial total is shown beside the satellite's own total.
*/

return [
    'figures' => [
        [
            'key' => 'philippines',
            'place' => 'Philippines',
            'hectares' => 311216,
            'year' => 2020,
            'source' => 'Philippine Statistics Authority (PSA)',
            'url' => 'https://psa.gov.ph/content/philippines-mangrove-extent-reaches-311216-hectares-2020',
            'note' => 'Up 2.8% from 302,811 ha in 2015; 1.1% of the country\'s land area.',
        ],
        [
            'key' => 'region',
            'place' => 'Eastern Visayas (Region VIII)',
            'hectares' => 34679,
            'year' => 2020,
            'source' => 'Philippine Statistics Authority (PSA)',
            'url' => 'https://psa.gov.ph/content/philippines-mangrove-extent-reaches-311216-hectares-2020',
            'note' => 'Third-largest region, 11.2% of the national total.',
        ],
        [
            'key' => 'province',
            'place' => 'Southern Leyte',
            'hectares' => 942.77,
            'year' => 2021,
            'source' => 'Provincial Government of Southern Leyte (PENRMO)',
            'url' => 'https://southernleyte.gov.ph/marine-resources/',
            'note' => 'Provincial environment office inventory.',
        ],
    ],

    'province' => 'province',

    // The satellite year shown beside the official figures (theirs are 2020); the table also shows the latest year.
    'satellite_year' => 2020,
];
