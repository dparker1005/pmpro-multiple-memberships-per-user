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

// This file is where we override the default PMPro functionality & pages as needed. (Which is a lot.)

// if a list of level ids is passed to checkout, pull out the first as the main level and save the rest
function pmprommpu_init_checkout_levels() {
	//update and save pmpro checkout levels
	if ( ! is_admin() && ! empty( $_REQUEST['level'] ) && $_REQUEST['level'] != 'all' ) {
		global $pmpro_checkout_level_ids, $pmpro_checkout_levels;

		//convert spaces back to +
		$_REQUEST['level'] = str_replace( array( ' ', '%20' ), '+', $_REQUEST['level'] );

		//get the ids
		$pmpro_checkout_level_ids = array_map( 'intval', explode( "+", preg_replace( "[^0-9\+]", "", $_REQUEST['level'] ) ) );

		//setup pmpro_checkout_levels global
		$pmpro_checkout_levels = array();
		foreach ( $pmpro_checkout_level_ids as $level_id ) {
			$pmpro_checkout_levels[] = pmpro_getLevelAtCheckout( $level_id );
		}

		//update default request vars to only point to one (main) level
		$_REQUEST['level'] = $pmpro_checkout_level_ids[0];
		$_GET['level']     = $_REQUEST['level'];
		$_POST['level']    = $_REQUEST['level'];
	}

	//update and save pmpro checkout deleted levels
	if ( ! is_admin() && ! empty( $_REQUEST['dellevels'] ) ) {
		global $pmpro_checkout_del_level_ids;

		//convert spaces back to +
		$_REQUEST['dellevels'] = str_replace( array( ' ', '%20' ), '+', $_REQUEST['dellevels'] );

		//get the ids
		$pmpro_checkout_del_level_ids = array();
		$pmpro_checkout_del_level_ids = array_map( 'intval', explode( "+", preg_replace( "[^0-9\+]", "", $_REQUEST['dellevels'] ) ) );
	}
}

add_action( 'init', 'pmprommpu_init_checkout_levels', 100 );

// trying to add a level that is already had? Sorry, Charlie.
function pmprommpu_template_redirect_dupe_level_check() {

	global $pmpro_checkout_level_ids;
	global $pmpro_pages;

	//on the checkout page?
	if ( ! empty( $pmpro_checkout_level_ids ) && ! is_admin() && ! empty( $_REQUEST['level'] ) && ! is_page( $pmpro_pages['cancel'] ) ) {

		$oktoproceed   = true;
		$currentlevels = pmpro_getMembershipLevelsForUser();

		foreach ( $currentlevels as $curcurlevel ) {
			if ( in_array( $curcurlevel->ID, $pmpro_checkout_level_ids ) ) {
				$oktoproceed = false;
			}
		}
		if ( ! $oktoproceed ) {
			wp_redirect( pmpro_url( "levels" ) );
			exit;
		}
	}
}

// the user pages - the actual function is in functions.php
add_filter( 'pmpro_pages_custom_template_path', 'pmprommpu_override_user_pages', 10, 5 );

function pmprommpu_frontend_scripts() {

	global $pmpro_pages;
	global $post;

	// Make sure PMPro is active
	if ( ! defined( 'PMPRO_VERSION' ) ) {
		return;
	}

	// Only load this on the checkout page
	if ( is_page( $pmpro_pages['checkout'] ) ) {

		global $pmpro_show_discount_code;

		wp_register_script( 'pmprommpu-checkout', plugins_url( '../js/pmprommpu-checkout.js', __FILE__ ), array( 'jquery' ), PMPROMMPU_VER, true );

		wp_localize_script( 'pmprommpu-checkout', 'pmprodc', array(
			'settings' => array(
				'ajaxurl'                 => admin_url( 'admin-ajax.php' ),
				'timeout'                 => apply_filters( "pmpro_ajax_timeout", 5000, "applydiscountcode" ),
				'processed_dc'            => ! empty( $_REQUEST['discount_code'] ),
				'show_main_discount_code' => $pmpro_show_discount_code,
			)
		) );

		wp_enqueue_script( 'pmprommpu-checkout' );
	}

	// If we're on a levels page, or the page contains the advanced levels page shortcode.
	if ( is_page( $pmpro_pages['levels'] ) || ( !empty( $post->post_content ) && false !== stripos( $post->post_content, '[pmpro_advanced_levels' ) ) ) {

		$incoming_levels  = pmpro_getMembershipLevelsForUser();
		$available_levels = pmpro_getAllLevels( false, true );

		$selected_levels = array();
		$level_elements  = array();
		$current_levels  = array();
		$all_levels      = array();

		if ( false !== $incoming_levels ) { // At this point, we're not disabling others in the group for initial selections, because if they're here, they probably want to change them.

			foreach ( $incoming_levels as $curlev ) {

				$selected_levels[]             = "level-{$curlev->id}";
				$level_elements[]              = "input#level{$curlev->id}";
				$current_levels[ $curlev->id ] = $curlev->name;
			}
		}

		if ( false !== $available_levels ) {

			foreach ( $available_levels as $lvl ) {
				$all_levels[ $lvl->id ] = $lvl->name;
			}
		}

		wp_register_script( 'pmprommpu-levels', plugins_url( '../js/pmprommpu-levels.js', __FILE__ ), array( 'jquery' ), PMPROMMPU_VER, true );
		wp_localize_script( 'pmprommpu-levels', 'pmprolvl',
			array(
				'settings'       => array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'timeout' => apply_filters( "pmpro_ajax_timeout", 5000, "applydiscountcode" ),
					'cancel_lnk' => esc_url_raw( pmpro_url( 'cancel', '') ),
					'checkout_lnk' => esc_url_raw( pmpro_url( 'checkout', '' ) ),
				),
				'lang'           => array(
					'selected_label' => __( 'Selected', 'pmpro-multiple-memberships-per-user' ),
					'current_levels' => _x( 'Current Levels', 'title for currently selected levels', 'pmpro-multiple-memberships-per-user' ),
					'added_levels'   => _x( 'Added Levels', 'title for added levels', 'pmpro-multiple-memberships-per-user' ),
					'removed_levels' => _x( 'Removed Levels', 'title for removed levels', 'pmpro-multiple-memberships-per-user' ),
					'none' => _x( 'None', 'value displayed when no levels selected', 'pmpro-multiple-memberships-per-user' ),
				),
				'alllevels'   => $all_levels,
				'selectedlevels' => $selected_levels,
				'levelelements'  => $level_elements,
				'currentlevels'  => $current_levels,
			)
		);
		wp_enqueue_script( 'pmprommpu-levels');
	}

}

add_action( 'wp_enqueue_scripts', 'pmprommpu_frontend_scripts' );

// Filter the text on the checkout page that tells the user what levels they're getting
function pmprommpu_checkout_level_text( $intext, $levelids_adding, $levelids_deleting ) {
	// if not level set in URL param, assume default level and we don't need to filter.
	if ( empty( $levelids_adding ) ) {
		return $intext;
	}

	$levelarr  = pmpro_getAllLevels( true, true );
	$outstring = '<p>' . _n( 'You have selected the following level', 'You have selected the following levels', count( $levelids_adding ), 'pmprommpu' ) . ':</p>';
	foreach ( $levelids_adding as $curlevelid ) {
		$outstring .= "<p class='levellist'><strong><span class='levelnametext'>" . $levelarr[ $curlevelid ]->name . "</span></strong>";
		if ( ! empty( $levelarr[ $curlevelid ]->description ) ) {
			$outstring .= "<br /><span class='leveldesctext'>" . stripslashes( $levelarr[ $curlevelid ]->description ) . "</span>";
		}
		$outstring .= "</p>";
	}
	if ( ! empty( $levelids_deleting ) && count( $levelids_deleting ) > 0 ) {
		$outstring .= '<p>' . _n( 'You are removing the following level', 'You are removing the following levels', count( $levelids_deleting ), 'pmprommpu' ) . ':</p>';
		foreach ( $levelids_deleting as $curlevelid ) {
			$outstring .= "<p class='levellist'><strong><span class='levelnametext'>" . $levelarr[ $curlevelid ]->name . "</span></strong>";
			if ( ! empty( $levelarr[ $curlevelid ]->description ) ) {
				$outstring .= "<br /><span class='leveldesctext'>" . stripslashes( $levelarr[ $curlevelid ]->description ) . "</span>";
			}
			$outstring .= "</p>";
		}
	}

	return $outstring;
}

add_filter( 'pmprommpu_checkout_level_text', 'pmprommpu_checkout_level_text', 10, 3 );

// Ensure than when a membership level is changed, it doesn't delete the old one or unsubscribe them at the gateway.
// We'll handle both later in the process.
function pmprommpu_pmpro_deactivate_old_levels( $deactivate ) {
	global $pmpro_pages;

	//don't deactivate other levels, unless we're on the cancel page and set to cancel all
	if ( ! is_page( $pmpro_pages['cancel'] ) || empty( $_REQUEST['levelstocancel'] ) || $_REQUEST['levelstocancel'] != 'all' ) {
		$deactivate = false;
	}

	return $deactivate;
}

add_filter( 'pmpro_deactivate_old_levels', 'pmprommpu_pmpro_deactivate_old_levels', 10, 1 );

function pmprommpu_pmpro_cancel_previous_subscriptions( $cancel ) {
	global $pmpro_pages;

	//don't cancel other subscriptions, unless we're on the cancel page and set to cancel all
	if ( ! is_page( $pmpro_pages['cancel'] ) || empty( $_REQUEST['levelstocancel'] ) || $_REQUEST['levelstocancel'] != 'all' ) {
		$cancel = false;
	}

	return $cancel;
}

add_filter( 'pmpro_cancel_previous_subscriptions', 'pmprommpu_pmpro_cancel_previous_subscriptions', 10, 1 );

// Called after the checkout process, we are going to do three things here:
// First, process any extra levels that need to be charged/subbed for
// Then, any unsubscriptions that the user opted for (whose level ids are in $_REQUEST['dellevels']) will be dropped.
// Then, any remaining conflicts will be dropped.
function pmprommpu_pmpro_after_checkout( $user_id, $checkout_statuses ) {
	global $wpdb, $current_user, $gateway, $discount_code, $discount_code_id, $pmpro_msg, $pmpro_msgt, $pmpro_level, $pmpro_checkout_levels, $pmpro_checkout_del_level_ids, $pmpro_checkout_id;

	//make sure we only call this once
	remove_action( 'pmpro_after_checkout', 'pmprommpu_pmpro_after_checkout', 99, 2 );

	//process extra checkouts
	if ( ! empty( $pmpro_checkout_levels ) ) {
		global $username, $password, $password2, $bfirstname, $blastname, $baddress1, $baddress2, $bcity, $bstate, $bzipcode, $bcountry, $bphone, $bemail, $bconfirmemail, $CardType, $AccountNumber, $ExpirationMonth, $ExpirationYear, $CVV;

		foreach ( $pmpro_checkout_levels as $level ) {
			//skip the "main" level we already processed
			if ( $level->id == $pmpro_level->id ) {
				continue;
			}

			//process payment unless free
			if ( ! pmpro_isLevelFree( $level ) ) {
				$morder                   = new MemberOrder();
				$morder->membership_id    = $level->id;
				$morder->membership_name  = $level->name;
				$morder->discount_code    = $discount_code;
				$morder->InitialPayment   = $level->initial_payment;
				$morder->PaymentAmount    = $level->billing_amount;
				$morder->ProfileStartDate = date( "Y-m-d", current_time( "timestamp" ) ) . "T0:0:0";
				$morder->BillingPeriod    = $level->cycle_period;
				$morder->BillingFrequency = $level->cycle_number;

				if ( $level->billing_limit ) {
					$morder->TotalBillingCycles = $level->billing_limit;
				}

				if ( pmpro_isLevelTrial( $level ) ) {
					$morder->TrialBillingPeriod    = $level->cycle_period;
					$morder->TrialBillingFrequency = $level->cycle_number;
					$morder->TrialBillingCycles    = $level->trial_limit;
					$morder->TrialAmount           = $level->trial_amount;
				}

				//credit card values
				$morder->cardtype              = $CardType;
				$morder->accountnumber         = $AccountNumber;
				$morder->expirationmonth       = $ExpirationMonth;
				$morder->expirationyear        = $ExpirationYear;
				$morder->ExpirationDate        = $ExpirationMonth . $ExpirationYear;
				$morder->ExpirationDate_YdashM = $ExpirationYear . "-" . $ExpirationMonth;
				$morder->CVV2                  = $CVV;

				//not saving email in order table, but the sites need it
				$morder->Email = $bemail;

				//sometimes we need these split up
				$morder->FirstName = $bfirstname;
				$morder->LastName  = $blastname;
				$morder->Address1  = $baddress1;
				$morder->Address2  = $baddress2;

				//other values
				$morder->billing          = new stdClass();
				$morder->billing->name    = $bfirstname . " " . $blastname;
				$morder->billing->street  = trim( $baddress1 . " " . $baddress2 );
				$morder->billing->city    = $bcity;
				$morder->billing->state   = $bstate;
				$morder->billing->country = $bcountry;
				$morder->billing->zip     = $bzipcode;
				$morder->billing->phone   = $bphone;

				//$gateway = pmpro_getOption("gateway");
				$morder->gateway = $gateway;
				$morder->setGateway();

				//setup level var
				$morder->getMembershipLevel();
				$morder->membership_level = apply_filters( "pmpro_checkout_level", $morder->membership_level );

				//tax
				$morder->subtotal = $morder->InitialPayment;
				$morder->getTax();

				//filter for order, since v1.8
				$morder = apply_filters( "pmpro_checkout_order", $morder );

				$pmpro_processed = $morder->process();

				if ( ! empty( $pmpro_processed ) ) {
					$pmpro_msg       = __( "Payment accepted.", "pmpro" );
					$pmpro_msgt      = "pmpro_success";
					$pmpro_confirmed = true;
				} else {
					//Payment failed. We need to backout this order and all previous orders.

					//find all orders for this checkout, refund and cancel them
					$checkout_orders = pmpro_getMemberOrdersByCheckoutID( $morder->checkout_id );
					foreach ( $checkout_orders as $checkout_order ) {
						if ( $checkout_order->status != 'error' ) {
							//try to refund
							if ( $checkout_order->gateway == "stripe" ) {
								//TODO: abstract this and implement refund for other gateways
								$refunded = $checkout_order->Gateway->refund( $checkout_order );
							}

							//cancel
							$checkout_order->cancel();
						}
					}

					//set the error message
					$pmpro_msg = __( "ERROR: This checkout included several payments. Some of them were processed successfully and some failed. We have attempted to refund any payments made. You should contact the site owner to resolve this issue.", "pmprommpu" );

					if ( ! empty( $morder->error ) ) {
						$pmpro_msg .= " " . __( "More information:", "pmprommpu" ) . " " . $morder->error;
					}
					$pmpro_msgt = "pmpro_error";

					//don't send an email
					add_filter( 'pmpro_send_checkout_emails', '__return_false' );

					//don't redirect
					add_filter( 'pmpro_confirmation_url', '__return_false' );

					//bail from this function
					return;
				}
			} else {
				//empty order for free levels
				$morder                 = new MemberOrder();
				$morder->InitialPayment = 0;
				$morder->Email          = $bemail;
				$morder->gateway        = "free";

				$morder = apply_filters( "pmpro_checkout_order_free", $morder );
			}

			//change level and save order
			do_action( 'pmpro_checkout_before_change_membership_level', $user_id, $morder );

			//start date is NOW() but filterable below
			$startdate = current_time( "mysql" );
			$startdate = apply_filters( "pmpro_checkout_start_date", $startdate, $user_id, $level );

			//calculate the end date
			if ( ! empty( $level->expiration_number ) ) {
				$enddate = date( "Y-m-d", strtotime( "+ " . $level->expiration_number . " " . $level->expiration_period, current_time( "timestamp" ) ) );
			} else {
				$enddate = "NULL";
			}

			$enddate = apply_filters( "pmpro_checkout_end_date", $enddate, $user_id, $level, $startdate );

			//check code before adding it to the order
			$code_check = pmpro_checkDiscountCode( $discount_code, $level->id, true );
			if ( $code_check[0] == false ) {
				//error
				$pmpro_msg  = $code_check[1];
				$pmpro_msgt = "pmpro_error";

				//don't use this code
				$use_discount_code = false;
			} else {
				//all okay
				$use_discount_code = false;
			}

			//update membership_user table.
			//(NOTE: we can avoid some DB calls by using the global $discount_code_id, but the core preheaders/checkout.php may have blanked it)
			if ( ! empty( $discount_code ) && ! empty( $use_discount_code ) ) {
				$discount_code_id = $wpdb->get_var( "SELECT id FROM $wpdb->pmpro_discount_codes WHERE code = '" . esc_sql( $discount_code ) . "' LIMIT 1" );
			} else {
				$discount_code_id = "";
			}

			$custom_level = array(
				'user_id'         => $user_id,
				'membership_id'   => $level->id,
				'code_id'         => $discount_code_id,
				'initial_payment' => $level->initial_payment,
				'billing_amount'  => $level->billing_amount,
				'cycle_number'    => $level->cycle_number,
				'cycle_period'    => $level->cycle_period,
				'billing_limit'   => $level->billing_limit,
				'trial_amount'    => $level->trial_amount,
				'trial_limit'     => $level->trial_limit,
				'startdate'       => $startdate,
				'enddate'         => $enddate
			);

			if ( pmpro_changeMembershipLevel( $custom_level, $user_id, 'changed' ) ) {
				//we're good

				//add an item to the history table, cancel old subscriptions
				if ( ! empty( $morder ) ) {
					$morder->user_id       = $user_id;
					$morder->membership_id = $level->id;
					$morder->saveOrder();
				}
			}
		}
	}

	pmprommpu_send_checkout_emails( $user_id, $pmpro_checkout_id );

	//remove levels to be removed
	if ( ! empty( $pmpro_checkout_del_level_ids ) ) {

		foreach ( $pmpro_checkout_del_level_ids as $idtodel ) {
			pmpro_cancelMembershipLevel( $idtodel, $user_id, 'cancelled' );
		}
	}

	// OK, levels are added, levels are removed. Let's check once more for any conflict, and resolve them - with extreme prejudice.
	$currentlevels   = pmpro_getMembershipLevelsForUser( $user_id );
	$currentlevelids = array();

	if ( is_array( $currentlevels ) ) {

		foreach ( $currentlevels as $curlevel ) {

			$currentlevelids[] = $curlevel->id;
		}
	}

	$levelsandgroups = pmprommpu_get_levels_and_groups_in_order();
	$allgroups       = pmprommpu_get_groups();

	$levelgroupstoprune = array();

	foreach ( $levelsandgroups as $curgp => $gplevels ) {

		if ( array_key_exists( $curgp, $allgroups ) && $allgroups[ $curgp ]->allow_multiple_selections == 0 ) { // we only care about groups that restrict to one level within it

			$conflictlevels = array();

			foreach ( $gplevels as $curlevel ) {

				if ( isset( $curlevel->id ) && in_array( $curlevel->id, $currentlevelids ) ) {
					$conflictlevels[] = $curlevel->id;
				}
			}

			if ( count( $conflictlevels ) > 1 ) {
				$levelgroupstoprune[] = $conflictlevels;
			}
		}
	}

	if ( count( $levelgroupstoprune ) > 0 ) { // we've got some resolutions to do.

		foreach ( $levelgroupstoprune as $curgroup ) {

			foreach ( $curgroup as $idtodel ) {

				pmpro_cancelMembershipLevel( $idtodel, $user_id, 'change' );
			}
		}
	}
}

add_action( 'pmpro_after_checkout', 'pmprommpu_pmpro_after_checkout', 99, 2 );

function pmprommpu_stop_default_checkout_emails( $inflag ) {
	return false;
}

add_filter( 'pmpro_send_checkout_emails', 'pmprommpu_stop_default_checkout_emails', 10, 1 );

function pmprommpu_set_checkout_id( $inorder ) {
	global $pmpro_checkout_id;

	if ( ! empty( $pmpro_checkout_id ) ) {
		$inorder->checkout_id = $pmpro_checkout_id;
	}

	return $inorder;
}

add_filter( 'pmpro_checkout_order', 'pmprommpu_set_checkout_id', 10, 1 );
add_filter( 'pmpro_checkout_order_free', 'pmprommpu_set_checkout_id', 10, 1 );


/**
 * Set pmpro_require_billing to true if any of the checkout levels are paid.
 */
function pmprommpu_pmpro_require_billing( $require_billing, $level ) {
	global $pmpro_checkout_levels;

	if ( ! empty( $pmpro_checkout_levels ) ) {
		foreach( $pmpro_checkout_levels as $checkout_level ) {
			if ( ! pmpro_isLevelFree( $checkout_level ) ) {
				$require_billing = true;
				break;
			}
		}
	}

	return $require_billing;
}
add_filter( 'pmpro_require_billing', 'pmprommpu_pmpro_require_billing', 10, 2);
