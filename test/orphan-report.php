<?php
/**
 * orphan-report.php  -  SLP Dealer Guard
 *
 * READ ONLY. Writes nothing, deletes nothing, redirects nothing.
 *
 * Finds store_page posts with no owning wp_store_locator row and proposes a
 * disposition for each. This is the query the reconcile pass structurally
 * cannot run: that pass walks departing locations, and an orphan has no
 * location to depart.
 *
 *   wp eval-file orphan-report.php
 *   wp eval-file orphan-report.php csv > orphans.csv
 *
 * Disposition logic, in order:
 *   REDIRECT   a live sibling exists in the same slug family and the family
 *              still has feed rows -> 301 the orphan to the sibling
 *   REMOVE     no live sibling and no feed rows -> departed dealer
 *   REVIEW     anything else; a human decides
 *
 * "Live sibling" = a store_page in the same base-slug family that IS owned
 * by a row. Slug family is the slug with any trailing -<n> stripped.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;

// WP-CLI's eval-file passes POSITIONAL arguments in $args. A leading "--"
// is consumed by WP-CLI as an associative parameter and never reaches this
// file, so a run with a leading double dash errors out before this file
// is reached. Invoke as `wp eval-file orphan-report.php csv`. Both
// spellings are accepted so a mistyped run still does the right thing
// rather than silently printing the wrong format.
$argv = (array) ( $args ?? array() );
$csv  = in_array( 'csv', $argv, true ) || in_array( '--csv', $argv, true );

$table = $wpdb->prefix . 'store_locator';

// ---------------------------------------------------------------- orphans
$orphans = $wpdb->get_results(
	"SELECT p.ID, p.post_name, p.post_title, p.post_status, p.post_author,
	        p.post_date, p.post_modified
	   FROM {$wpdb->posts} p
	   LEFT JOIN {$table} s ON s.sl_linked_postid = p.ID
	  WHERE p.post_type = 'store_page'
	    AND p.post_status = 'publish'
	    AND s.sl_id IS NULL
	  ORDER BY p.post_date"
);

// ------------------------------------------------- every linked store_page
$linked = $wpdb->get_results(
	"SELECT p.ID, p.post_name, p.post_modified,
	        s.sl_id, s.sl_store, s.sl_address, s.sl_city, s.sl_state
	   FROM {$wpdb->posts} p
	   JOIN {$table} s ON s.sl_linked_postid = p.ID
	  WHERE p.post_type = 'store_page'"
);

$family = function ( $slug ) {
	return preg_replace( '/-\d+$/', '', (string) $slug );
};

/**
 * Normalise a street address for comparison.
 *
 * Same rules as rev25 s0.121: fold case, strip punctuation, collapse
 * whitespace, then normalise street suffixes and directionals. That section
 * exists because a case-sensitive key reported 306 dealers where 303 stood.
 */
$norm = function ( $s ) {
	$s = strtolower( trim( (string) $s ) );
	$s = preg_replace( '/[^a-z0-9 ]/', ' ', $s );
	$s = preg_replace( '/\s+/', ' ', $s );
	$map = array(
		'street' => 'st', 'avenue' => 'ave', 'road' => 'rd', 'drive' => 'dr',
		'parkway' => 'pkwy', 'boulevard' => 'blvd', 'highway' => 'hwy',
		'lane' => 'ln', 'court' => 'ct', 'circle' => 'cir', 'place' => 'pl',
		'north' => 'n', 'south' => 's', 'east' => 'e', 'west' => 'w',
	);
	foreach ( $map as $long => $short ) {
		$s = preg_replace( '/\b' . $long . '\b/', $short, $s );
	}
	return trim( $s );
};

$linked_by_family = array();
$linked_by_addr   = array();
foreach ( $linked as $row ) {
	$linked_by_family[ $family( $row->post_name ) ][] = $row;
	$key = $norm( $row->sl_address ) . '|' . $norm( $row->sl_city );
	$linked_by_addr[ $key ] = $row;
}

// ----------------------------------------------------------------- report
$rows = array();
foreach ( $orphans as $o ) {

	// THE ADDRESS IS THE FACT; THE SLUG IS A PROXY.
	//
	// SLP writes the full location into postmeta at page creation, and that
	// postmeta SURVIVES orphaning. Seven of the twelve orphans on Aura DEV
	// carry it. Matching on it gives an exact target instead of a guess.
	//
	// The first cut of this file resolved by slug family and picked the
	// most recently modified sibling. On a set rewritten nightly that is
	// arbitrary ordering, not evidence, and it was wrong twice out of two
	// where it mattered: premier-boating-centers-6 is Jasper TX and was
	// sent to Aransas Pass, several hundred miles away; ashley-marine-llc-3
	// is a DEPARTED location in Opelika AL and was offered a redirect at
	// all. Both were caught by hand. This resolves them by measurement.
	$meta_addr = get_post_meta( $o->ID, 'slp_location_address', true );
	$meta_city = get_post_meta( $o->ID, 'slp_location_city', true );
	$has_meta  = ( $meta_addr !== '' && $meta_city !== '' );

	$fam      = $family( $o->post_name );
	$siblings = isset( $linked_by_family[ $fam ] ) ? $linked_by_family[ $fam ] : array();
	usort( $siblings, function ( $a, $b ) {
		return strcmp( $b->post_modified, $a->post_modified );
	} );

	$target  = null;
	$basis   = '';
	$verdict = 'REVIEW';
	$reason  = '';

	if ( $has_meta ) {
		$key = $norm( $meta_addr ) . '|' . $norm( $meta_city );
		if ( isset( $linked_by_addr[ $key ] ) ) {
			$target  = $linked_by_addr[ $key ];
			$basis   = 'postmeta address';
			$verdict = 'REDIRECT';
			$reason  = sprintf(
				'%s, %s still in the location table as sl_id %d -> %d /%s/',
				$meta_addr, $meta_city, $target->sl_id, $target->ID, $target->post_name
			);
		} else {
			$verdict = 'DEPARTED';
			$reason  = sprintf(
				'%s, %s has NO row in the location table. The dealer left; '
				. 'this is not a surplus page for a surviving one. 410, or a '
				. 'redirect to the nearest survivor, is a business decision.',
				$meta_addr, $meta_city
			);
		}
	} elseif ( count( $siblings ) === 1 ) {
		// No snapshot, but one live page in the family forces the target.
		$target  = $siblings[0];
		$basis   = 'slug family, single sibling';
		$verdict = 'REDIRECT';
		$reason  = sprintf(
			'no slp_location_* postmeta; exactly one live page in the family '
			. 'forces the target -> %d /%s/ owned by sl_id %d',
			$target->ID, $target->post_name, $target->sl_id
		);
	} elseif ( count( $siblings ) === 0 ) {
		$verdict = 'REMOVE';
		$reason  = 'no postmeta and no linked page in the slug family; '
		         . 'dealer appears departed';
	} else {
		$verdict = 'REVIEW';
		$reason  = sprintf(
			'no slp_location_* postmeta and %d live pages in the family. '
			. '"Most recently modified" is not evidence for a multi-location '
			. 'dealer - resolve by hand.',
			count( $siblings )
		);
	}

	// A hand-edited page may hold content the feed cannot rebuild.
	if ( $o->post_modified !== $o->post_date && $verdict === 'REDIRECT' ) {
		$reason = 'HAND EDITED (post_modified != post_date) - read it before '
		        . 'discarding. ' . $reason;
	}

	// Elementor content on an orphan is unrecoverable once the post goes.
	$has_el = ! empty( get_post_meta( $o->ID, '_elementor_data', true ) );
	if ( $has_el && $verdict !== 'REVIEW' ) {
		$verdict = 'REVIEW';
		$reason  = 'carries _elementor_data. ' . $reason;
	}

	$rows[] = array(
		'id'          => (int) $o->ID,
		'slug'        => $o->post_name,
		'title'       => $o->post_title,
		'meta_addr'   => $has_meta ? $meta_addr . ', ' . $meta_city : '',
		'basis'       => $basis,
		'created'     => $o->post_date,
		'modified'    => $o->post_modified,
		'elementor'   => $has_el ? 'Y' : 'N',
		'siblings'    => count( $siblings ),
		'target_id'   => $target ? (int) $target->ID : 0,
		'target_slug' => $target ? $target->post_name : '',
		'verdict'     => $verdict,
		'reason'      => $reason,
	);
}

// ----------------------------------------------------------------- output
if ( $csv ) {
	$out = fopen( 'php://output', 'w' );
	if ( count( $rows ) ) { fputcsv( $out, array_keys( $rows[0] ) ); }
	foreach ( $rows as $r ) { fputcsv( $out, $r ); }
	fclose( $out );
	return;
}

$total_pages = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->posts}
	  WHERE post_type='store_page' AND post_status='publish'" );
$total_rows  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

printf( "location rows        %d\n", $total_rows );
printf( "store_page publish   %d\n", $total_pages );
printf( "linked               %d\n", count( $linked ) );
printf( "orphaned             %d\n\n", count( $rows ) );

// Arithmetic self-check. If these do not reconcile, a page is linked to a
// row that no longer exists, or linked twice, and the report is unsafe.
$expect = $total_pages - count( $linked );
if ( $expect !== count( $rows ) ) {
	printf( "ARITHMETIC FAIL  pages - linked = %d but %d orphans found\n",
		$expect, count( $rows ) );
	printf( "Do not act on this report.\n" );
	return;
}
printf( "arithmetic ok        %d - %d = %d\n\n",
	$total_pages, count( $linked ), count( $rows ) );

$tally = array();
foreach ( $rows as $r ) {
	$tally[ $r['verdict'] ] = ( $tally[ $r['verdict'] ] ?? 0 ) + 1;
	printf( "%-9s %6d  /%s/\n", $r['verdict'], $r['id'], $r['slug'] );
	printf( "          created %s   siblings %d   elementor %s\n",
		$r['created'], $r['siblings'], $r['elementor'] );
	if ( $r['meta_addr'] !== '' ) {
		printf( "          postmeta %s   basis: %s\n",
			$r['meta_addr'], $r['basis'] !== '' ? $r['basis'] : 'none' );
	}
	printf( "          %s\n\n", $r['reason'] );
}

printf( "--\n" );
foreach ( $tally as $k => $v ) { printf( "%-9s %d\n", $k, $v ); }
printf( "\nNothing was changed. Re-run with the csv argument to capture the table.\n" );
