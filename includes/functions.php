<?php
/*
 * License:

 Copyright 2016 - Stranger Studios, LLC

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License, version 2, as
 published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// This file has miscellaneous functions to help things run smoothly.

function pmprommpu_is_loaded() { // If you can run this, we're loaded.
	return true;
}

function pmprommpu_plugin_dir() {
	return PMPROMMPU_DIR;
}

// Called as a filter by pmpro_pages_custom_template_path to add our path to the search path for user pages.
function pmprommpu_override_user_pages($templates, $page_name, $type, $where, $ext) {
	if(file_exists(PMPROMMPU_DIR . "/pages/{$page_name}.{$ext}")) {
		// We add our path as the second in the array - after core, but before user locations.
		// The array is reversed later, so this means user templates come first, then us, then core.
		array_splice($templates, 1, 0, PMPROMMPU_DIR . "/pages/{$page_name}.{$ext}");
	}
	return $templates;
}

// This function returns an array of the successfully-purchased levels from the most recent checkout
// for the user specified by user_id. If user_id isn't specified, the current user will be used.
// If there are no successful levels, or no checkout, will return an empty array.
function pmprommpu_get_levels_from_latest_checkout($user_id = NULL, $statuses_to_check = 'success', $checkout_id = -1) {
	global $wpdb, $current_user;

	if(empty($user_id))
	{
		$user_id = $current_user->ID;
	}

	if(empty($user_id))
	{
		return [];
	}

	//make sure user id is int for security
	$user_id = intval($user_id);

	$retval = array();
	$all_levels = pmpro_getAllLevels(true, true);

	$checkoutid = intval($checkout_id);
	if($checkoutid<1) {
		$checkoutid = $wpdb->get_var("SELECT MAX(checkout_id) FROM $wpdb->pmpro_membership_orders WHERE user_id=$user_id");
		if(empty($checkoutid) || intval($checkoutid)<1) { return $retval; }
	}

	$querySql = "SELECT membership_id FROM $wpdb->pmpro_membership_orders WHERE checkout_id = " . esc_sql( $checkoutid ) . " AND ( gateway = 'free' OR ";
	if(!empty($statuses_to_check) && is_array($statuses_to_check)) {
		$querySql .= "status IN('" . implode("','", $statuses_to_check) . "') ";
	} elseif(!empty($statuses_to_check)) {
		$querySql .= "status = '" . esc_sql($statuses_to_check) . "' ";
	} else {
		$querySql .= "status = 'success'";
	}
	$querySql .= " )";

	$levelids = $wpdb->get_col($querySql);
	foreach($levelids as $thelevel) {
		if(array_key_exists($thelevel, $all_levels)) {
			$retval[] = $all_levels[$thelevel];
		}
	}
	return $retval;
}

function pmprommpu_join_with_and($inarray) {
	$outstring = "";

	if(!is_array($inarray) || count($inarray)<1) { return $outstring; }

	$lastone = array_pop($inarray);
	if(count($inarray)>0) {
		$outstring .= implode(', ', $inarray);
		if(count($inarray)>1) { $outstring .= ', '; } else { $outstring .= " "; }
		$outstring .= "and ";
	}
	$outstring .= "$lastone";
	return $outstring;
}