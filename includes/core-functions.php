<?php

/*
 * If running a version of PMPro without MMPU merged, declare the moved functions.
 */
function pmprommpu_include_core_functions() {
	if ( defined( 'PMPRO_VERSION' ) && version_compare( PMPRO_VERSION, '3', '>' ) ) {
		// Core PMPro will load these functions.
		return;
	}
	// From functions.php

	//set up wpdb for the tables we need
	function pmprommpu_setDBTables()
	{
		global $wpdb;
		$wpdb->hide_errors();
		$wpdb->pmpro_groups = $wpdb->prefix . 'pmpro_groups';
		$wpdb->pmpro_membership_levels_groups = $wpdb->prefix . 'pmpro_membership_levels_groups';
	}
	pmprommpu_setDBTables();

	// Return an array of all level groups, with the key being the level group id.
	// Groups have an id, name, displayorder, and flag for allow_multiple_selections
	function pmprommpu_get_groups() {
		global $wpdb;

		$allgroups = $wpdb->get_results("SELECT * FROM $wpdb->pmpro_groups ORDER BY id");
		$grouparr = array();
		foreach($allgroups as $curgroup) {
			$grouparr[$curgroup->id] = $curgroup;
		}

		return $grouparr;
	}

	// Given a name and a true/false flag about whether it allows multiple selections, create a level group.
	function pmprommpu_create_group($inname, $inallowmult = true) {
		global $wpdb;

		$allowmult = intval($inallowmult);
		$result = $wpdb->insert($wpdb->pmpro_groups, array('name' => $inname, 'allow_multiple_selections' => $allowmult), array('%s', '%d'));

		if($result) { return $wpdb->insert_id; } else { return false; }
	}

	// Set (or move) a membership level into a level group
	function pmprommpu_set_level_for_group($levelid, $groupid) {
		global $wpdb;

		$levelid = intval($levelid);
		$groupid = intval($groupid); // just to be safe

		// TODO: Error checking would be smart.
		$wpdb->delete( $wpdb->pmpro_membership_levels_groups, array( 'level' => $levelid ) );
		$wpdb->insert($wpdb->pmpro_membership_levels_groups, array('level' => $levelid, 'group' => $groupid), array('%d', '%d' ) );
	}

	// Return an array of the groups and levels in display order - keys are group ID, and values are their levels, in display order
	function pmprommpu_get_levels_and_groups_in_order($includehidden = false) {
		global $wpdb;

		$retarray = array();

		$pmpro_levels = pmpro_getAllLevels($includehidden, true);
		$pmpro_level_order = pmpro_getOption('level_order');
		$pmpro_levels = apply_filters('pmpro_levels_array', $pmpro_levels );

		$include = array();

		foreach( $pmpro_levels as $level ) {
			$include[] = $level->id;
		}

		$included = esc_sql( implode(',', $include) );

		$order = array();
		if(! empty($pmpro_level_order)) { $order = explode(',', $pmpro_level_order); }

		$grouplist = $wpdb->get_col("SELECT id FROM {$wpdb->pmpro_groups} ORDER BY displayorder, id ASC");
		if($grouplist) {
			foreach($grouplist as $curgroup) {

				$curgroup = intval($curgroup);

				$levelsingroup = $wpdb->get_col(
					$wpdb->prepare( "
						SELECT level 
						FROM {$wpdb->pmpro_membership_levels_groups} AS mlg 
						INNER JOIN {$wpdb->pmpro_membership_levels} AS ml ON ml.id = mlg.level AND ml.allow_signups LIKE %s
						WHERE mlg.group = %d 
						AND ml.id IN (" . $included ." )
						ORDER BY level ASC",
					($includehidden ? '%' : 1),
					$curgroup
					)
				);

				if(count($order)>0) {

					$mylevels = array();

					foreach($order as $level_id) {
						if(in_array($level_id, $levelsingroup)) { $mylevels[] = $level_id; }
					}

					$retarray[$curgroup] = $mylevels;

				} else {

					$retarray[$curgroup] = $levelsingroup;
				}
			}
		}

		return $retarray;
	}

	/**
	 * Checks if a user has any membership level within a certain group
	 */
	function pmprommpu_hasMembershipGroup($groups = NULL, $user_id = NULL) {
		global $current_user, $wpdb;

		//assume false
		$return = false;

		//default to current user
		if(empty($user_id)) {
			$user_id = $current_user->ID;
		}

		//get membership levels (or not) for given user
		if(!empty($user_id) && is_numeric($user_id))
			$membership_levels = pmpro_getMembershipLevelsForUser($user_id);
		else
			$membership_levels = NULL;

		//make an array out of a single element so we can use the same code
		if(!is_array($groups)) {
			$groups = array($groups);
		}

		//no levels, so no groups
		if(empty($membership_levels)) {
			$return = false;
		} else {
			//we have levels, so test against groups given
			foreach($groups as $group_id) {
				foreach($membership_levels as $level) {
					$levelgroup = pmprommpu_get_group_for_level($level->id);
					if($levelgroup == $group_id) {
						$return = true;	//found one!
						break 2;
					}
				}
			}
		}

		//filter just in case
		$return = apply_filters("pmprommpu_has_membership_group", $return, $user_id, $groups);
		return $return;
	}

	// Given a level ID, this function returns the group ID it belongs to.
	function pmprommpu_get_group_for_level($levelid) {
		global $wpdb;

		$levelid = intval($levelid); // just to be safe

		$groupid = $wpdb->get_var( $wpdb->prepare( "SELECT mlg.group FROM {$wpdb->pmpro_membership_levels_groups} mlg WHERE level = %d", $levelid ) );
		if($groupid) {
			$groupid = intval($groupid);
		} else {
			$groupid = -1;
		}
		return $groupid;
	}

	// Given a level ID and new group ID, this function sets the group ID for a level. Returns a success flag (true/false).
	function pmprommpu_set_group_for_level($levelid, $groupid) {
		global $wpdb;

		$levelid = intval($levelid); // just to be safe
		$groupid = intval($groupid); // just to be safe

		// TODO: Error checking would be smart.
		$wpdb->delete( $wpdb->pmpro_membership_levels_groups, array( 'level' => $levelid ) );

		$success = $wpdb->insert( $wpdb->pmpro_membership_levels_groups, array('group' => $groupid, 'level' => $levelid ) );

		if($success>0) {
			return true;
		} else {
			return false;
		}
	}

	// Called by AJAX to add a group from the admin-side Membership Levels and Groups page. Incoming parms are name and mult (can users sign up for multiple levels in this group - 0/1).
	function pmprommpu_add_group() {
		global $wpdb;

		$displaynum = $wpdb->get_var("SELECT MAX(displayorder) FROM {$wpdb->pmpro_groups}");
		if(! $displaynum || intval($displaynum)<1) { $displaynum = 1; } else { $displaynum = intval($displaynum); $displaynum++; }

		if(array_key_exists("name", $_REQUEST)) {
			$allowmult = 0;
			if(array_key_exists("mult", $_REQUEST) && intval($_REQUEST["mult"])>0) { $allowmult = 1; }
			$wpdb->insert($wpdb->pmpro_groups,
				array(	'name' => $_REQUEST["name"],
						'allow_multiple_selections' => $allowmult,
						'displayorder' => $displaynum),
				array(	'%s',
						'%d',
						'%d')
				);
		}

		wp_die();
	}

	// Called by AJAX to edit a group from the admin-side Membership Levels and Groups page. Incoming parms are group (the ID #), name and mult (can users sign up for multiple levels in this group - 0/1).
	function pmprommpu_edit_group() {
		global $wpdb;

		if(array_key_exists("name", $_REQUEST) && array_key_exists("group", $_REQUEST) && intval($_REQUEST["group"])>0) {
			$allowmult = 0;
			if(array_key_exists("mult", $_REQUEST) && intval($_REQUEST["mult"])>0) { $allowmult = 1; }
			$grouptoedit = intval($_REQUEST["group"]);

			// TODO: Error checking would be smart.
			$wpdb->update($wpdb->pmpro_groups,
				array(	'name' => $_REQUEST["name"],
						'allow_multiple_selections' => $allowmult
				), // SET
				array(	'id' => $grouptoedit), // WHERE
				array(	'%s',
						'%d',
						'%d'
				), // SET FORMAT
				array(	'%d' ) // WHERE format
			);
		}

		wp_die();
	}

	// Called by AJAX to delete an empty group from the admin-side Membership Levels and Groups page. Incoming parm is group (group ID #).
	function pmprommpu_del_group() {
		global $wpdb;

		if(array_key_exists("group", $_REQUEST) && intval($_REQUEST["group"])>0) {
			$groupid = intval($_REQUEST["group"]);

			// TODO: Error checking would be smart.
			$wpdb->delete( $wpdb->pmpro_membership_levels_groups, array('group' => $groupid ) );
			$wpdb->delete( $wpdb->pmpro_groups, array( 'id' => $groupid) );
		}

		wp_die();
	}

	// Called by AJAX from the admin-facing levels page when the rows are reordered. Incoming parm (neworder) is an ordered array of objects (with two parms, group (scalar ID) and levels (ordered array of scalar level IDs))
	function pmprommpu_update_level_and_group_order() {
		global $wpdb;

		$grouparr = array();
		$levelarr = array();

		if(array_key_exists("neworder", $_REQUEST) && is_array($_REQUEST["neworder"])) {
			foreach($_REQUEST["neworder"] as $curgroup) {
				$grouparr[] = $curgroup["group"];
				foreach($curgroup["levels"] as $curlevel) {
					$levelarr[] = $curlevel;
				}
			}
			$ctr = 1;

			// Inefficient for large groups/large numbers of groups
			foreach($grouparr as $orderedgroup) {

				// TODO: Error checking would be smart.
				$wpdb->update( $wpdb->pmpro_groups, array ( 'displayorder' => $ctr ), array( 'id' => $orderedgroup ) );
				$ctr++;
			}
			pmpro_setOption('level_order', $levelarr);
		}

		wp_die();
	}

	// Given a membership level (required) and a user ID (or current user, if empty), add them. If an admin wants to force
	// the addition even if it's illegal, they can set force_add to true.
	// Checks group constraints to make sure it's legal first, then uses pmpro_changeMembershipLevel from PMPro core
	// to do the heavy lifting. If the addition isn't legal, returns false. Returns true on success.
	function pmprommpu_addMembershipLevel($level = NULL, $user_id = NULL, $force_add = false) {
		global $current_user, $wpdb;

		//assume false
		$return = false;

		$level_id = -1;

		// Make sure we have a level object.
		if( is_array( $level ) && ( ! empty( $level['id'] ) || ! empty( $level['membership_id'] ) ) ) {
			$level_id = !empty($level['id']) ? $level['id'] : $level['membership_id'];
		} elseif(is_object($level) && ( !empty($level->id) || !empty($level->membership_id)) ) {
			$level_id = !empty($level->id) ? $level->id : $level->membership_id;
		} elseif(is_numeric($level) && intval($level)>0) {
			$level_id = intval($level);
		}
		
		if( $level_id < 1 ) {
			return $return;
		}

		//default to current user
		if(empty($user_id)) {
			$user_id = $current_user->ID;
		} else {
			$user_id = intval($user_id);
		}

		$allgroups = pmprommpu_get_groups();

		// OK, we have the user and the level. Let's check to see if adding it is legal. Is it in a group where they can have only one?
		if(!$force_add) {
			$groupid = pmprommpu_get_group_for_level($level_id);

			if(array_key_exists($groupid, $allgroups) && $allgroups[$groupid]->allow_multiple_selections<1) { // There can be only one.
				// Do they already have one in this group?
				$otherlevels = $wpdb->get_col( $wpdb->prepare( "SELECT mlg.level FROM {$wpdb->pmpro_membership_levels_groups} AS mlg WHERE mlg.group = %d AND mlg.level <>  %d", $groupid, $level_id ) );
				if ( false !== pmpro_hasMembershipLevel( $otherlevels, $user_id ) ) { return $return; }
			}
		}

		// OK, we're legal (or don't care). Let's add it. Set elsewhere by filter, changeMembershipLevel should not disable old levels.	
		if ( is_array( $level ) ) {
			// A custom level array was passed, so pass that along.		
			return pmpro_changeMembershipLevel($level, $user_id);
		} else {
			return pmpro_changeMembershipLevel($level_id, $user_id);
		}
		
	}

	// Start functions from overrides.php
	// let's make sure jQuery UI Dialog is present on the admin side.
	function pmprommpu_addin_jquery_dialog( $pagehook ) {

		if ( false === stripos( $pagehook, "pmpro-membership" ) ) { // only add the overhead on the membership levels page.
			return;
		}

		wp_enqueue_script( 'jquery-ui-dialog' );

		// Load JS module for overrides.php (make it debuggable).
		wp_register_script( 'pmprommpu-overrides',
			plugins_url( '../js/pmprommpu-overrides.js', __FILE__ ),
			array(
				'jquery',
				'jquery-ui-dialog',
				'jquery-migrate',
			),
			PMPROMMPU_VER
		);

		wp_localize_script( 'pmprommpu-overrides', 'pmprommpu', array(
				'lang'     => array(
					'confirm_delete' => __( 'Are you sure you want to delete this group? It cannot be undone.', 'pmprommpu' ),
				),
				'settings' => array(
					'level_page_url' => add_query_arg( 'page', 'pmpro-membershiplevels', admin_url( 'admin.php' ) ),
				)
			)
		);

		wp_enqueue_script( 'pmprommpu-overrides' );
	}

	add_action( 'admin_enqueue_scripts', 'pmprommpu_addin_jquery_dialog' );

	/**
	 * Change membership levels admin page to show groups.
	 */
	function pmprommpu_pmpro_membership_levels_table( $intablehtml, $inlevelarr ) {
		$groupsnlevels = pmprommpu_get_levels_and_groups_in_order( true );
		$allgroups     = pmprommpu_get_groups();
		$alllevels     = pmpro_getAllLevels( true, true );
		$gateway       = pmpro_getOption( "gateway" );

		ob_start();

		// Check for orphaned levels
		$orphaned_level_ids = array_combine( array_keys( $alllevels ), array_keys( $alllevels ) );
		foreach( $groupsnlevels as $group_id => $group ) {
			if ( ! isset( $first_group_id ) ) {
				$first_group_id = $group_id;
			}
			foreach( $group as $level_id ) {
				unset( $orphaned_level_ids[$level_id] );
			}
		}
		unset( $group_id );
		unset( $group );
		unset( $level_id );

		// We found some
		if ( ! empty( $orphaned_level_ids ) && ! empty( $first_group_id ) ) {
			foreach( $orphaned_level_ids as $orphaned_level_id ) {
				pmprommpu_set_group_for_level( $orphaned_level_id, $first_group_id );
				$groupsnlevels[$first_group_id][] = $orphaned_level_id;
			}
			?>
			<div id="message" class="inline error">
				<p><?php printf( __('The following levels were not yet in a group: %s. These levels have been added to the first group found.', 'pmpro-multiple-memberships-per-user' ), implode(', ', $orphaned_level_ids ) ); ?></p>
			</div>
			<?php
		}

		// Check if gateway is supported
		if ( $gateway == "paypalexpress" || $gateway == "paypalstandard" ) { // doing this manually for now; should do it via a setting in the gateway class.
			?>
			<div id="message" class="error">
				<p><?php echo __( "Multiple Memberships Per User (MMPU) does not work with PayPal Express or PayPal Standard. Please disable the MMPU plug-in or change gateways to continue.", "mmpu" ); ?></p>
			</div>
			<?php
			$rethtml = ob_get_clean();

			return $rethtml;
		}

		?>

		<script>
			/* jQuery(document).ready(function () {
				jQuery('#add-new-group').insertAfter("h2 .add-new-h2");
			}); */
		</script>
		<a id="add-new-group" class="add-new-h2" href="#"><?php _e( 'Add New Group', 'pmprommpu' ); ?></a>

		<table class="widefat mmpu-membership-levels membership-levels">
			<thead>
			<tr>
				<th width="20%"><?php _e( 'Group', 'pmpro' ); ?></th>
				<th><?php _e( 'ID', 'pmpro' ); ?></th>
				<th><?php _e( 'Name', 'pmpro' ); ?></th>
				<th><?php _e( 'Billing Details', 'pmpro' ); ?></th>
				<th><?php _e( 'Expiration', 'pmpro' ); ?></th>
				<th><?php _e( 'Allow Signups', 'pmpro' ); ?></th>
			</tr>
			</thead>
			<?php
			$count = 0;
			foreach ( $groupsnlevels as $curgroup => $itslevels ) {
				$onerowclass = "";
				if ( count( $itslevels ) == 0 ) {
					$onerowclass = "onerow";
				} else {
					$onerowclass = "toprow";
				}
				$groupname       = "Unnamed Group";
				$groupallowsmult = 0;
				if ( array_key_exists( $curgroup, $allgroups ) ) {
					$groupname       = $allgroups[ $curgroup ]->name;
					$groupallowsmult = $allgroups[ $curgroup ]->allow_multiple_selections;
				}
				?>
				<tbody data-groupid="<?php echo $curgroup; ?>" class="membership-level-groups">
				<tr class="grouprow <?php echo $onerowclass; ?>">
					<th rowspan="<?php echo max( count( $itslevels ) + 1, 2 ); ?>" scope="rowgroup" valign="top">
						<h2><?php echo $groupname; ?></h2>
						<input type="hidden" class="pmprommpu-allow-multi" name="allow_multi[]" value="<?php esc_attr_e( $groupallowsmult ); ?>">
						<?php if ( ! $groupallowsmult ) { ?>
							<p><em><?php _e( 'Users can only choose one level from this group.', 'pmprommpu' ); ?></em></p>
						<?php } ?>
						<p>
							<a data-groupid="<?php echo $curgroup; ?>" title="<?php _e( 'edit', 'pmpro' ); ?>" href="#"
							class="editgrpbutt button-primary"><?php _e( 'edit', 'pmpro' ); ?></a>
							<!--
							<a data-groupid="<?php echo $curgroup; ?>" title="<?php _e( 'edit', 'pmpro' ); ?>" href="admin.php?page=pmpro-membershiplevels&edit=<?php /* echo $level->id; */ ?>" class="editgrpbutt button-primary"><?php _e( 'edit', 'pmpro' ); ?></a>
	-->
							<?php if ( count( $itslevels ) == 0 ) { ?>
								<a title="<?php _e( 'delete', 'pmpro' ); ?>" data-groupid="<?php echo $curgroup; ?>"
								href="javascript: void(0);"
								class="delgroupbutt button-secondary"><?php _e( 'delete', 'pmpro' ); ?></a>
							<?php } ?>
						</p>
					</th>
				</tr>
				<?php if ( count( $itslevels ) > 0 ) { ?>
					<?php foreach ( $itslevels as $curlevelid ) {
						if ( array_key_exists( $curlevelid, $alllevels ) ) { // Just in case there's a level artifact in the groups table that wasn't removed - it won't show here.
							$level = $alllevels[ $curlevelid ];

							$page_link = add_query_arg( array(
								'page' => 'pmpro-membershiplevels',
								'edit' => $level->id
							), admin_url( 'admin.php' ) );
							?>
							<tr class="<?php if ( $count ++ % 2 == 1 ) { ?>alternate<?php } ?> levelrow <?php if ( ! $level->allow_signups ) { ?>pmpro_gray<?php } ?> <?php if ( ! pmpro_checkLevelForStripeCompatibility( $level ) || ! pmpro_checkLevelForBraintreeCompatibility( $level ) || ! pmpro_checkLevelForPayflowCompatibility( $level ) || ! pmpro_checkLevelForTwoCheckoutCompatibility( $level ) ) { ?>pmpro_error<?php } ?>">

								<td class="levelid"><?php echo $level->id ?></td>
								<td class="level_name">
									<a href="<?php echo $page_link; ?>"><strong><?php echo esc_attr( $level->name ); ?></strong></a>
									<div class="row-actions">
										<span><a title="<?php _e( 'Edit', 'pmpro' ); ?>" href="<?php echo $page_link; ?>"><?php _e( 'Edit', 'pmpro' ); ?></a> |</span>
										<span><a title="<?php _e( 'Copy', 'pmpro' ); ?>" href="<?php echo add_query_arg( array(
											'page' => 'pmpro-membershiplevels',
											'copy' => $level->id,
											'edit' => '-1'
										), admin_url( 'admin.php' ) ); ?>"><?php _e( 'Copy', 'pmpro' ); ?></a> |</span>
										<span><a title="<?php _e( 'Delete', 'pmpro' ); ?>"
										href="javascript:askfirst('<?php echo str_replace( "'", "\'", sprintf( __( "Are you sure you want to delete membership level %s? All subscriptions will be cancelled.", "pmpro" ), $level->name ) ); ?>', '<?php echo wp_nonce_url( add_query_arg( array(
											'page'     => 'pmpro-membershiplevels',
											'action'   => 'delete_membership_level',
											'deleteid' => $level->id
										), admin_url( 'admin.php' ) ), 'delete_membership_level', 'pmpro_membershiplevels_nonce' ); ?>'); void(0);"><?php _e( 'Delete', 'pmpro' ); ?></a></span>
									</div>
								</td>
								<td>
									<?php if ( pmpro_isLevelFree( $level ) ) { ?>
										<?php _e( 'FREE', 'pmpro' ); ?>
									<?php } else { ?>
										<?php echo str_replace( 'The price for membership is', '', pmpro_getLevelCost( $level ) ); ?>
									<?php } ?>
								</td>
								<td>
									<?php if ( ! pmpro_isLevelExpiring( $level ) ) { ?>
										--
									<?php } else { ?>
										<?php _e( 'After', 'pmpro' ); ?><?php echo $level->expiration_number ?><?php echo sornot( $level->expiration_period, $level->expiration_number ) ?>
									<?php } ?>
								</td>
								<td><?php if ( $level->allow_signups ) { ?><a
										href="<?php echo pmpro_url( "checkout", "?level=" . $level->id ); ?>"><?php _e( 'Yes', 'pmpro' ); ?></a><?php } else { ?><?php _e( 'No', 'pmpro' ); ?><?php } ?>
								</td>
							</tr>
							<?php
						}
					}
					?>
				<?php } else { ?>
					<tr class="levelrow">
						<td colspan="6"></td>
					</tr>
				<?php } ?>
				</tbody>
			<?php } ?>
			</tbody>
		</table>
		<div id="addeditgroupdialog" style="display:none;">
			<p>Name<input type="text" size="30" id="groupname"></p>
			<p>Can users choose more than one level in this group? <input type="checkbox" id="groupallowmult" value="1"></p>
		</div>
		
		<?php
		$rethtml = ob_get_clean();

		return $rethtml;
	}

	add_filter( 'pmpro_membership_levels_table', 'pmprommpu_pmpro_membership_levels_table', 10, 2 );

	/*
		Add options to edit level page
	*/
	//add options
	function pmprommpu_add_group_to_level_options() {
		$level     = $_REQUEST['edit'];
		$allgroups = pmprommpu_get_groups();
		$prevgroup = pmprommpu_get_group_for_level( $level );
		?>
		<h3 class="topborder"><?php _e( 'Group', 'mmpu' ); ?></h3>
		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row" valign="top"><label><?php _e( 'Group', 'mmpu' ); ?></label></th>
				<td><select name="groupid">
						<?php foreach ( $allgroups as $curgroup ) { ?>
							<option value="<?php echo $curgroup->id; ?>" <?php if ( $curgroup->id == $prevgroup ) {
								echo "selected";
							} ?>><?php echo $curgroup->name; ?></option>
						<?php } ?>
					</select></td>
			</tr>
			</tbody>
		</table>

		<?php
	}

	add_action( 'pmpro_membership_level_after_other_settings', 'pmprommpu_add_group_to_level_options' );

	//save options
	function pmprommpu_save_group_on_level_edit( $levelid ) {
		if ( array_key_exists( "groupid", $_REQUEST ) && intval( $_REQUEST["groupid"] ) > 0 ) {
			pmprommpu_set_group_for_level( $levelid, $_REQUEST["groupid"] );
		}
	}

	add_action( 'pmpro_save_membership_level', 'pmprommpu_save_group_on_level_edit' );

	/*
		Delete group data when a level is deleted
	*/
	function pmprommpu_on_del_level( $levelid ) {
		global $wpdb;
		$levelid = intval( $levelid );

		// TODO: Error checking would be smart.
		if ( false === $wpdb->delete( $wpdb->pmpro_membership_levels_groups, array( 'level' => $levelid ) ) ) {
			global $pmpro_msg;
			global $pmpro_msgt;

			$pmpro_msg = __( "Unable to delete the level from its group", "pmpro-multiple-memberships-per-user" );
			$pmpro_msgt = "pmpro_error";

		}
	}

	add_action( 'pmpro_delete_membership_level', 'pmprommpu_on_del_level' );

	// Actual functions are defined in functions.php.
	add_action( 'wp_ajax_pmprommpu_add_group', 'pmprommpu_add_group' );
	add_action( 'wp_ajax_pmprommpu_edit_group', 'pmprommpu_edit_group' );
	add_action( 'wp_ajax_pmprommpu_del_group', 'pmprommpu_del_group' );
	add_action( 'wp_ajax_pmprommpu_update_level_and_group_order', 'pmprommpu_update_level_and_group_order' );

	function pmprommpu_show_multiple_levels_in_memlist( $inuser ) {
		$allmylevels = pmpro_getMembershipLevelsForUser( $inuser->ID );
		$memlevels   = array();
		foreach ( $allmylevels as $curlevel ) {
			$memlevels[] = $curlevel->name;
		}

		$inuser->membership = implode( ', ', $memlevels );

		return $inuser;
	}

	add_filter( 'pmpro_members_list_user', 'pmprommpu_show_multiple_levels_in_memlist', 10, 1 );

	/*
	* Replaces the default "Level" and "Level ID" columns in Members List
	* with MMPU variants.
	*
	* @since 0.7
	*/
	function pmprommpu_memberslist_extra_cols( $columns ) {
		$new_columns = array();
		foreach ( $columns as $col_key => $col_name ) {
			if ( $col_key == 'membership' ) {
				$new_columns['mmpu_memberships'] = 'Levels';
			} elseif ( $col_key == 'membership_id' ) {
				$new_columns['mmpu_membership_ids'] = 'Level IDs';
			} else {
				$new_columns[$col_key] = $col_name;
			}
		}
		return $new_columns;
	}
	add_filter( 'pmpro_memberslist_extra_cols', 'pmprommpu_memberslist_extra_cols' );

	/*
	* Fills the MMPU-genereated columns in Members List.
	*
	* @since 0.7
	*/
	function pmprommpu_fill_memberslist_col_member_number( $colname, $user_id ) {
		if ( 'mmpu_memberships' === $colname ) {
			$user_levels = pmpro_getMembershipLevelsForUser( $user_id );
			$memlevels   = array();
			foreach ( $user_levels as $curlevel ) {
				$memlevels[] = $curlevel->name;
			}
			echo( implode( ', ', $memlevels ) );
		}
		if ( 'mmpu_membership_ids' === $colname ) {
			$user_levels = pmpro_getMembershipLevelsForUser( $user_id );
			$memlevels   = array();
			foreach ( $user_levels as $curlevel ) {
				$memlevels[] = $curlevel->id;
			}
			echo( implode( ', ', $memlevels ) );
		}
	}
	add_filter( 'pmpro_manage_memberslist_custom_column', 'pmprommpu_fill_memberslist_col_member_number', 10, 2 );

	// From profile.php (deleted)

	/**
	 * Removed default PMPro edit profile functionality and add our own.
	 *
	 * NOTE: Stripe "updates" are not compatible with MMPU
	 */
	function pmprommpu_init_profile_hooks() {
		//remove default pmpro hooks
		remove_action( 'show_user_profile', 'pmpro_membership_level_profile_fields' );
		remove_action( 'edit_user_profile', 'pmpro_membership_level_profile_fields' );
		remove_action( 'personal_options_update', 'pmpro_membership_level_profile_fields_update' );
		remove_action( 'edit_user_profile_update', 'pmpro_membership_level_profile_fields_update' );

		//add our own
		add_action( 'show_user_profile', 'pmprommpu_membership_level_profile_fields' );
		add_action( 'edit_user_profile', 'pmprommpu_membership_level_profile_fields' );
		add_action( 'personal_options_update', 'pmprommpu_membership_level_profile_fields_update' );
		add_action( 'edit_user_profile_update', 'pmprommpu_membership_level_profile_fields_update' );
	}
	add_action('init', 'pmprommpu_init_profile_hooks');

	/**
	 * Show the membership levels section
	 *  add_action( 'show_user_profile', 'pmprommpu_membership_level_profile_fields' );
	 *  add_action( 'edit_user_profile', 'pmprommpu_membership_level_profile_fields' );
	 */
	function pmprommpu_membership_level_profile_fields($user) {
	global $current_user;

		$membership_level_capability = apply_filters("pmpro_edit_member_capability", "manage_options");
		if(!current_user_can($membership_level_capability))
			return false;

		if ( ! function_exists( 'pmpro_getMembershipLevelForUser' ) ) {
			return false;
		}

		global $wpdb;
		$user->membership_level = pmpro_getMembershipLevelForUser($user->ID);

		$alllevels = $wpdb->get_results( "SELECT * FROM {$wpdb->pmpro_membership_levels}", OBJECT_K );

		if(!$alllevels)
			return "";
	?>
	<h3><?php _e("Membership Levels", "pmprommpu"); ?></h3>
	<?php
		$show_membership_level = true;
		$show_membership_level = apply_filters("pmpro_profile_show_membership_level", $show_membership_level, $user);
		if($show_membership_level)
		{
		?>
		<table class="wp-list-table widefat fixed pmprommpu_levels" width="100%" cellpadding="0" cellspacing="0" border="0">
		<thead>
			<tr>
				<th>Group</th>
				<th>Membership Level</th>
				<th>Expiration</th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>
		<?php
			//get levels and groups
			$currentlevels = pmpro_getMembershipLevelsForUser($user->ID);
			$levelsandgroups = pmprommpu_get_levels_and_groups_in_order(true);
			$allgroups = pmprommpu_get_groups();

			//some other vars
			$current_day = date("j", current_time('timestamp'));
			$current_month = date("M", current_time('timestamp'));
			$current_year = date("Y", current_time('timestamp'));

			ob_start();
			?>
			<tr id="new_levels_tr_template" class="new_levels_tr">
				<td>
					<select class="new_levels_group" name="new_levels_group[]">
						<option value="">-- <?php _e("Choose a Group", "pmpro");?> --</option>
						<?php foreach($allgroups as $group) { ?>
							<option value="<?php echo $group->id;?>"><?php echo $group->name;?></option>
						<?php } ?>
					</select>
				</td>
				<td>
					<em><?php _e('Choose a group first.', 'pmprommpu');?></em>
				</td>
				<td>
					<?php
						//default enddate values
						$end_date = false;
						$selected_expires_day = $current_day;
						$selected_expires_month = date("m");
						$selected_expires_year = (int)$current_year + 1;
					?>
					<select class="expires new_levels_expires" name="new_levels_expires[]">
						<option value="0" <?php if(!$end_date) { ?>selected="selected"<?php } ?>><?php _e("No", "pmpro");?></option>
						<option value="1" <?php if($end_date) { ?>selected="selected"<?php } ?>><?php _e("Yes", "pmpro");?></option>
					</select>
					<span class="expires_date new_levels_expires_date" <?php if(!$end_date) { ?>style="display: none;"<?php } ?>>
						on
						<select name="new_levels_expires_month[]">
							<?php
								for($i = 1; $i < 13; $i++)
								{
								?>
								<option value="<?php echo $i?>" <?php if($i == $selected_expires_month) { ?>selected="selected"<?php } ?>><?php echo date("M", strtotime($i . "/15/" . $current_year, current_time("timestamp")))?></option>
								<?php
								}
							?>
						</select>
						<input name="new_levels_expires_day[]" type="text" size="2" value="<?php echo $selected_expires_day?>" />
						<input name="new_levels_expires_year[]" type="text" size="4" value="<?php echo $selected_expires_year?>" />
					</span>
				</td>
				<td><a class="remove_level" href="javascript:void(0);"><?php _e('Remove', 'pmprommpu');?></a></td>
			</tr>
			<?php
			$new_level_template_html = preg_replace('/\n\t+/', '', ob_get_contents());
			ob_end_clean();

			//set group for each level
			for($i = 0; $i < count($currentlevels); $i++) {
				$currentlevels[$i]->group = pmprommpu_get_group_for_level($currentlevels[$i]->id);
			}

			//loop through all groups in order and show levels if the user has any currently
			foreach($levelsandgroups as $group_id => $levels) {
				if(pmprommpu_hasMembershipGroup($group_id, $user->ID)) {
					//user has at least one of these levels, so let's show them
					foreach($currentlevels as $level) {
						if($level->group == $group_id) {
						?>
						<tr>
							<td width="25%"><?php echo $allgroups[$group_id]->name;?></td>
							<td width="25%">
								<?php
									echo $level->name;
								?>
								<input class="membership_level_id" type="hidden" name="membership_levels[]" value="<?php echo esc_attr($level->id);?>" />
							</td>
							<td width="25%">
							<?php
								$show_expiration = true;
								$show_expiration = apply_filters("pmpro_profile_show_expiration", $show_expiration, $user);
								if($show_expiration)
								{
									//is there an end date?
									$end_date = !empty($level->enddate);

									//some vars for the dates
									if($end_date)
										$selected_expires_day = date("j", $level->enddate);
									else
										$selected_expires_day = $current_day;

									if($end_date)
										$selected_expires_month = date("m", $level->enddate);
									else
										$selected_expires_month = date("m");

									if($end_date)
										$selected_expires_year = date("Y", $level->enddate);
									else
										$selected_expires_year = (int)$current_year + 1;
								}
								?>
								<select class="expires" name="expires[]">
									<option value="0" <?php if(!$end_date) { ?>selected="selected"<?php } ?>><?php _e("No", "pmpro");?></option>
									<option value="1" <?php if($end_date) { ?>selected="selected"<?php } ?>><?php _e("Yes", "pmpro");?></option>
								</select>
								<span class="expires_date" <?php if(!$end_date) { ?>style="display: none;"<?php } ?>>
									on
									<select name="expires_month[]">
										<?php
											for($i = 1; $i < 13; $i++)
											{
											?>
											<option value="<?php echo $i?>" <?php if($i == $selected_expires_month) { ?>selected="selected"<?php } ?>><?php echo date("M", strtotime($i . "/15/" . $current_year, current_time("timestamp")))?></option>
											<?php
											}
										?>
									</select>
									<input name="expires_day[]" type="text" size="2" value="<?php echo $selected_expires_day?>" />
									<input name="expires_year[]" type="text" size="4" value="<?php echo $selected_expires_year?>" />
								</span>
							</td>
							<td width="25%"><a class="remove_level" href="javascript:void(0);"><?php _e('Remove', 'pmprommpu');?></a></td>
						</tr>
						<tr class="old_levels_delsettings_tr_template remove_level">
							<td></td>
							<td colspan="3">
								<label for="send_admin_change_email"><input value="1" id="send_admin_change_email" name="send_admin_change_email[]" type="checkbox"> Send the user an email about this change.</label><br>
								<label for="cancel_subscription"><input value="1" id="cancel_subscription" name="cancel_subscription[]" type="checkbox"> Cancel this user's subscription at the gateway.</label>
							</td>
						</tr>
						<?php
						}
					}
				}
			}
		?>
		<tr>
			<td colspan="4"><a href="javascript:void(0);" class="add_level">+ <?php _e('Add Level', 'pmprommpu');?></a></td>
		</tr>
		</tbody>
		</table>
		<script type="text/javascript">
			//vars with levels and groups
			var alllevels = <?php echo json_encode($alllevels);?>;
			var allgroups = <?php echo json_encode($allgroups);?>;
			var levelsandgroups = <?php echo json_encode($levelsandgroups);?>;
			var delsettingsrow = jQuery(".old_levels_delsettings_tr_template").first().detach();
			jQuery(".old_levels_delsettings_tr_template").detach();

			var new_level_template_html = '<?php echo $new_level_template_html; ?>';

			//update levels when a group dropdown changes
			function updateLevelSelect(e) {
				var groupselect = jQuery(e.target);
				var leveltd = groupselect.parent().next('td');
				var group_id = groupselect.val();

				leveltd.html('');

				//group chosen?
				if(group_id.length > 0) {
					//add level select
					var levelselect = jQuery('<select class="new_levels_level" name="new_levels_level[]"></select>').appendTo(leveltd);
					levelselect.append('<option value="">-- ' + <?php echo json_encode(__('Choose a Level', 'pmprommpu'));?> + ' --</option>');
					for(item in levelsandgroups[group_id]) {
						levelselect.append('<option value="'+alllevels[levelsandgroups[group_id][item]].id+'">'+alllevels[levelsandgroups[group_id][item]].name+'</option>');
					}
				} else {
					leveltd.html('<em>' + <?php echo json_encode(__('Choose a group first.', 'pmprommpu'));?> + '</em>');
				}
			}

			//remove level
			function removeLevel(e) {
				var removelink = jQuery(e.target);
				var removetr = removelink.closest('tr');

				if(removetr.hasClass('new_levels_tr')) {
					//new level? just delete the row
					removetr.remove();
				} else if(removetr.hasClass('remove_level')) {
					removetr.removeClass('remove_level');
					removelink.html(<?php echo json_encode(__('Remove', 'pmprommpu'));?>);
					removelink.next('input').remove();
					removetr.nextAll('.old_levels_delsettings_tr_template').first().remove();
				} else {
					//existing level? red it out and add to be removed
					removetr.addClass('remove_level');
					removelink.html(<?php echo json_encode(__('Undo', 'pmprommpu'));?>);
					var olevelid = removelink.closest('tr').find('input.membership_level_id').val();
					jQuery('<input type="hidden" name="remove_levels_id[]" value="'+olevelid+'">').insertAfter(removelink);
					removetr.after(delsettingsrow.clone());
				}
			}

			//bindings
			function pmprommpu_updateBindings() {
				//hide/show expiration dates
				jQuery('select.expires').unbind('change.pmprommpu');
				jQuery('select.expires').bind('change.pmprommpu', function() {
					if(jQuery(this).val() == 1)
						jQuery(this).next('span.expires_date').show();
					else
						jQuery(this).next('span.expires_date').hide();
				});

				//update level selects when groups are updated
				jQuery('select.new_levels_group').unbind('change.pmprommpu');
				jQuery('select.new_levels_group').bind('change.pmprommpu', updateLevelSelect);

				//remove buttons
				jQuery('a.remove_level').unbind('click.pmprommpu');
				jQuery('a.remove_level').bind('click.pmprommpu', removeLevel);

				//clone new level tr
				jQuery('a.add_level').unbind('click.pmprommpu');
				jQuery('a.add_level').bind('click.pmprommpu', function() {
					var newleveltr = jQuery('a.add_level').closest('tbody').append(new_level_template_html);
					pmprommpu_updateBindings();
				});
			}

			//on load
			jQuery(document).ready(function() {
				pmprommpu_updateBindings();
			});
		</script>
		<?php
		do_action("pmpro_after_membership_level_profile_fields", $user);
		}
	}

	/**
	 * Handle updates
	 *  add_action( 'personal_options_update', 'pmprommpu_membership_level_profile_fields_update' );
	 *  add_action( 'edit_user_profile_update', 'pmprommpu_membership_level_profile_fields_update' );
	*/
	function pmprommpu_membership_level_profile_fields_update() {
		//get the user id
		global $wpdb, $current_user;
		wp_get_current_user();

		if(!empty($_REQUEST['user_id'])) {
			$user_id = $_REQUEST['user_id'];
		} else {
			$user_id = $current_user->ID;
		}

		$membership_level_capability = apply_filters("pmpro_edit_member_capability", "manage_options");
		if(!current_user_can($membership_level_capability))
			return false;

		// OK. First, we're going to remove them from any levels that they should be dropped from - and keep an array of the levels we're dropping (so we don't adjust expiration later)
		$droppedlevels = array();
		$old_levels = pmpro_getMembershipLevelsForUser($user_id);
		if(array_key_exists('remove_levels_id', $_REQUEST)) {
			foreach($_REQUEST['remove_levels_id'] as $arraykey => $leveltodel) {
				pmpro_cancelMembershipLevel($leveltodel, $user_id, 'admin_cancelled');
				$droppedlevels[] = $leveltodel;
			}
		}

		// Next, let's update the expiration on any existing levels - as long as the level isn't in one of the ones we dropped them from.
		if(array_key_exists('expires', $_REQUEST)) {
			foreach($_REQUEST['expires'] as $expkey => $doesitexpire) {
				$thislevel = $_REQUEST['membership_levels'][$expkey];
				if(!in_array($thislevel, $droppedlevels)) { // we don't change expiry for a level we've dropped.
					if(!empty($doesitexpire)) { // we're going to expire.
						//update the expiration date
						$expiration_date = intval($_REQUEST['expires_year'][$expkey]) . "-" . str_pad(intval($_REQUEST['expires_month'][$expkey]), 2, "0", STR_PAD_LEFT) . "-" . str_pad(intval($_REQUEST['expires_day'][$expkey]), 2, "0", STR_PAD_LEFT);

						$wpdb->update(
							$wpdb->pmpro_memberships_users,
							array( 'enddate' => $expiration_date ),
							array(
								'status' => 'active',
								'membership_id' => $thislevel,
								'user_id' => $user_id ), // Where clause
							array( '%s' ),  // format for data
							array( '%s', '%d', '%d' ) // format for where clause
						);

						// $wpdb->query("UPDATE $wpdb->pmpro_memberships_users SET enddate = '" . $expiration_date . "' WHERE status = 'active' AND membership_id = '" . intval($thislevel) . "' AND user_id = '" . $user_id . "' LIMIT 1");
					} else { // No expiration for me!
						$wpdb->update(
							$wpdb->pmpro_memberships_users,
							array( 'enddate' => NULL ),
							array(
								'status' => 'active',
								'membership_id' => $thislevel,
								'user_id' => $user_id
							),
							array( NULL ),
							array( '%s', '%d', '%d' )
						);

						// $wpdb->query("UPDATE $wpdb->pmpro_memberships_users SET enddate = NULL WHERE status = 'active' AND membership_id = '" . intval($thislevel) . "' AND user_id = '" . $user_id . "' LIMIT 1");
					}
				}
			}
		}
		// Finally, we'll add any new levels requested. First, we'll try it without forcing, and then if need be, we'll force it (but then we'll know to give a warning about it.)
		if(array_key_exists('new_levels_level', $_REQUEST)) {
			$hadtoforce = false;
			$curlevels = pmpro_getMembershipLevelsForUser($user_id); // have to do it again, because we've made changes since above.
			$curlevids = array();
			foreach($curlevels as $thelev) { $curlevids[] = $thelev->ID; }
			foreach($_REQUEST['new_levels_level'] as $newkey => $leveltoadd) {
				if(! in_array($leveltoadd, $curlevids)) {
					$result = pmprommpu_addMembershipLevel($leveltoadd, $user_id, false);
					if(! $result) {
						pmprommpu_addMembershipLevel($leveltoadd, $user_id, true);
						$hadtoforce = true;
					}
					$doweexpire = $_REQUEST['new_levels_expires'][$newkey];
					if(!empty($doweexpire)) { // we're going to expire.
						//update the expiration date
						$expiration_date = intval($_REQUEST['new_levels_expires_year'][$newkey]) . "-" . str_pad(intval($_REQUEST['new_levels_expires_month'][$newkey]), 2, "0", STR_PAD_LEFT) . "-" . str_pad(intval($_REQUEST['new_levels_expires_day'][$newkey]), 2, "0", STR_PAD_LEFT);

						$wpdb->update(
							$wpdb->pmpro_memberships_users,
							array( 'enddate' => $expiration_date ),
							array(
								'status' => 'active',
								'membership_id' => $leveltoadd,
								'user_id' => $user_id ), // Where clause
							array( '%s' ),  // format for data
							array( '%s', '%d', '%d' ) // format for where clause
						);

						// $wpdb->query("UPDATE $wpdb->pmpro_memberships_users SET enddate = '" . $expiration_date . "' WHERE status = 'active' AND membership_id = '" . intval($leveltoadd) . "' AND user_id = '" . $user_id . "' LIMIT 1");
					} else { // No expiration for me!
						$wpdb->update(
							$wpdb->pmpro_memberships_users,
							array( 'enddate' => NULL ),
							array(
								'status' => 'active',
								'membership_id' => $leveltoadd,
								'user_id' => $user_id
							),
							array( NULL ),
							array( '%s', '%d', '%d' )
						);

						// $wpdb->query("UPDATE $wpdb->pmpro_memberships_users SET enddate = NULL WHERE status = 'active' AND membership_id = '" . intval($leveltoadd) . "' AND user_id = '" . $user_id . "' LIMIT 1");
					}
				}
			}
			if($hadtoforce) {
				// TODO: Should flag some kind of message to alert the admin that we had to force it (and the consequences of that).
			}
		}
		wp_cache_delete( 'user_' . $user_id . '_levels', 'pmpro' );
	}

	// From upgrades.php
	//	These functions are run on startup if user is an admin. They check for upgrades -
	//	and if it's a new install, everything is an upgrade!

	function pmprommpu_setup_and_upgrade() {
		global $wpdb;

		$installed_version = get_option("pmprommpu_version");

		//if we can't find the DB tables, reset version to 0
		$wpdb->hide_errors();
		$wpdb->pmpro_groups = $wpdb->prefix . 'pmpro_groups';
		$table_exists = $wpdb->query("SHOW TABLES LIKE '" . $wpdb->pmpro_groups . "'");
		if(!$table_exists || $installed_version < 1) {
			pmprommpu_setup_v1();
		}
	}

	function pmprommpu_db_delta() {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		global $wpdb;
		$wpdb->hide_errors();
		$wpdb->pmpro_groups = $wpdb->prefix . 'pmpro_groups';
		$wpdb->pmpro_membership_levels_groups = $wpdb->prefix . 'pmpro_membership_levels_groups';

		$sqlQuery = "CREATE TABLE `" . $wpdb->pmpro_groups . "` (
			`id` int unsigned NOT NULL AUTO_INCREMENT,
			`name` varchar(255) NOT NULL,
			`allow_multiple_selections` tinyint NOT NULL DEFAULT '1',
			`displayorder` int,
			PRIMARY KEY (`id`),
			KEY `name` (`name`)
		)";
		dbDelta($sqlQuery);

		$sqlQuery = "CREATE TABLE `" . $wpdb->pmpro_membership_levels_groups . "` (
			`id` int unsigned NOT NULL AUTO_INCREMENT,
			`level` int unsigned NOT NULL DEFAULT '0',
			`group` int unsigned NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`),
			KEY `level` (`level`),
			KEY `group` (`group`)
		)";
		dbDelta($sqlQuery);
	}

	function pmprommpu_setup_v1() {
		// Set any additional default options here.

		// Set up the database.
		pmprommpu_db_delta();

		// Save the current version number, and return it to stop later updates (or not, as the case may be).
		update_option("pmprommpu_version", "1");
		return 1;
	}

	if(is_admin()) {
		pmprommpu_setup_and_upgrade();
	}
}
add_action( 'init', 'pmprommpu_include_core_functions', 0 );