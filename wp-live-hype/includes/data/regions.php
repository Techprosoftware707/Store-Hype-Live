<?php
/**
 * Default region weighting per country (0 = off, 1 = low, 2 = normal, 3 = high),
 * roughly following population so the default rotation feels realistic.
 * Regions not listed default to Normal. Administrators can override every
 * value on the Weighting tab.
 *
 * `exclude` lists WooCommerce "regions" that are not real places to mention
 * (e.g. US military mail codes) — they are never used.
 *
 * @package AlwaysFinal\LiveHype
 */

defined( 'ABSPATH' ) || exit;

return array(
	'exclude'  => array(
		'US' => array( 'AA', 'AE', 'AP' ),
	),
	'defaults' => array(
		'CA' => array(
			'ON' => 3,
			'QC' => 3,
			'BC' => 3,
			'AB' => 3,
			'MB' => 2,
			'SK' => 2,
			'NS' => 2,
			'NB' => 1,
			'NL' => 1,
			'PE' => 1,
			'YT' => 1,
			'NT' => 1,
			'NU' => 1,
		),
		'US' => array(
			'CA' => 3,
			'TX' => 3,
			'FL' => 3,
			'NY' => 3,
			'PA' => 3,
			'IL' => 3,
			'OH' => 3,
			'GA' => 3,
			'NC' => 3,
			'MI' => 3,
			'AK' => 1,
			'WY' => 1,
			'VT' => 1,
			'ND' => 1,
			'SD' => 1,
			'DE' => 1,
			'RI' => 1,
			'MT' => 1,
			'DC' => 1,
		),
		'AU' => array(
			'NSW' => 3,
			'VIC' => 3,
			'QLD' => 3,
			'WA'  => 2,
			'SA'  => 2,
			'TAS' => 1,
			'ACT' => 1,
			'NT'  => 1,
		),
		'NZ' => array(
			'AUK' => 3,
			'CAN' => 3,
			'WGN' => 3,
			'WKO' => 2,
			'BOP' => 2,
			'WTC' => 1,
			'GIS' => 1,
			'TAS' => 1,
			'MBH' => 1,
		),
		'DE' => array(
			'DE-NW' => 3,
			'DE-BY' => 3,
			'DE-BW' => 3,
			'DE-NI' => 2,
			'DE-HE' => 2,
			'DE-BE' => 3,
			'DE-HB' => 1,
			'DE-SL' => 1,
		),
	),
	// City defaults (slug => level); unlisted cities default to Normal.
	'cities'   => array(
		'CA' => array(
			'toronto'       => 3,
			'montreal'      => 3,
			'vancouver'     => 3,
			'calgary'       => 3,
			'edmonton'      => 3,
			'ottawa'        => 3,
			'iqaluit'       => 1,
			'yellowknife'   => 1,
			'whitehorse'    => 1,
			'brandon'       => 1,
			'kamloops'      => 1,
			'nanaimo'       => 1,
			'red-deer'      => 1,
			'lethbridge'    => 1,
			'charlottetown' => 1,
			'fredericton'   => 1,
		),
		'US' => array(
			'new-york'     => 3,
			'los-angeles'  => 3,
			'chicago'      => 3,
			'houston'      => 3,
			'phoenix'      => 3,
			'philadelphia' => 3,
		),
		'GB' => array(
			'london'     => 3,
			'birmingham' => 3,
			'manchester' => 3,
		),
		'AU' => array(
			'sydney'    => 3,
			'melbourne' => 3,
			'brisbane'  => 3,
		),
		'NZ' => array(
			'auckland' => 3,
		),
	),
);
