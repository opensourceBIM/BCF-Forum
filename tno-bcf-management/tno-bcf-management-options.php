<?php
global $tnoBCFManagement, $wpdb, $wp_roles;

if( isset( $_POST['action'] ) ) {
   foreach( $_POST[ 'tno_bcf_management_options' ] AS $key => $newOption ) {
      $options[$key] = $newOption;
   }

   /*if( !isset( $_POST[ 'tno_bcf_management_options' ][ 'enabled_post_types' ] ) ) {
      $options[ 'enabled_post_types' ] = Array();
   }

   if( isset( $_FILES[ 'import_selfscan_csv' ] ) && isset( $_FILES[ 'import_selfscan_csv' ][ 'tmp_name' ] ) && $_FILES[ 'import_selfscan_csv' ][ 'tmp_name' ] != '' ) {
      $file = fopen( $_FILES[ 'import_selfscan_csv' ][ 'tmp_name' ], 'r' );
      $firstLine = Array();
      while( ( $line = fgets( $file ) ) !== false ) {
      	if( count( $firstLine ) == 0 ) {
      		$firstLine = explode( '","', $line );
      	  foreach( $firstLine as $key => $value ) {
      	  	$firstLine[$key] = trim( $value, "\x22" );
      	  }
      	} else {
      		$data = explode( '","', $line );
      	  foreach( $data as $key => $value ) {
      	  	$data[$key] = trim( $value, "\x22" );
      	  }
      		$tnoBCFManagement->importSelfscan( $data, $firstLine );
      	}
      }
      fclose( $file );
   }
   if( isset( $_FILES[ 'import_advisors_csv' ] ) && isset( $_FILES[ 'import_advisors_csv' ][ 'tmp_name' ] ) && $_FILES[ 'import_advisors_csv' ][ 'tmp_name' ] != '' ) {
      $file = fopen( $_FILES[ 'import_advisors_csv' ][ 'tmp_name' ], 'r' );
      while( ( $line = fgets( $file ) ) !== false ) {
      	$data = explode( '","', $line );
      	foreach( $data as $key => $value ) {
      		$data[$key] = trim( $value, "\x22 \r\n" );
      	}
      	if( count( $data ) >= 2 ) {
					$email = sanitize_user( $data[2] );
					// Check if this user already exists else we need to add it
					$advisorId = username_exists( $email );
					if( !isset( $advisorId ) ) {
						$randomPassword = wp_generate_password( 8, false );
						$advisorId = wp_create_user( $email, $randomPassword, $email );
						$displayName = $data[1];
						$user = get_user_by( 'id', $advisorId );
						if( isset( $user ) && $user !== false ) {
							$user->set_role( $tnoBCFManagement->options[ 'adviser_role' ] );
							$userData = Array( 'ID' => $advisorId, 'display_name' => $displayName, 'first_name' => $displayName );
							wp_update_user( $userData );
							add_user_meta( $advisorId, 'external_advisor_id', $data[0] );
						}
					}
				}
      }
      fclose( $file );
   }
   if( isset( $_FILES[ 'import_quickscan_csv' ] ) && isset( $_FILES[ 'import_quickscan_csv' ][ 'tmp_name' ] ) && $_FILES[ 'import_quickscan_csv' ][ 'tmp_name' ] != '' ) {
      $file = fopen( $_FILES[ 'import_quickscan_csv' ][ 'tmp_name' ], 'r' );
      $firstLine = Array();
      while( ( $line = fgets( $file ) ) !== false ) {
      	if( count( $firstLine ) == 0 ) {
      		$firstLine = explode( '","', $line );
      	  foreach( $firstLine as $key => $value ) {
      	  	$firstLine[$key] = trim( $value, "\x22" );
      	  }
      	} else {
      		$data = explode( '","', $line );
      	  foreach( $data as $key => $value ) {
      	  	$data[$key] = trim( $value, "\x22" );
      	  }
      		$tnoBCFManagement->importQuickscan( $data, $firstLine );
      	}
      }
      fclose( $file );
   }*/

   update_option( 'tno_bcf_management_options', $options );
   $tnoBCFManagementOptions = TNOBCFManagement::getOptions( true );
} else {
	$tnoBCFManagementOptions = TNOBCFManagement::getOptions();	
}


$postTypes = get_post_types( Array(), 'objects' );
/*$taxonomies = get_taxonomies( Array( 'public' => true, '_builtin' => false ), 'objects' );
$roles = $wp_roles->roles;
$terms = get_terms( $tnoBCFManagementOptions[ 'taxonomy_topic' ], Array( 'hide_empty' => false ) );
$aspects = get_terms( $tnoBCFManagementOptions[ 'taxonomy_category' ], Array( 'hide_empty' => false ) );
$pages = get_posts( Array(
		'post_type' => 'page',
		'posts_per_page' => -1
		) );*/
?>
<div class="wrap">
	<div class="icon32" id="icon-options-general"></div>
	<h2>TNO BCF Management Options</h2>
	<form method="post" enctype="multipart/form-data">
		<table class="form-table">
			<!--tr>
				<td><label for="company-role">Company role</label></td>
				<td>
<?php
if( is_array( $wp_roles->roles ) ) {
?>
                  <select name="tno_bcf_management_options[company_role]" id="company-role">
<?php
   foreach( $wp_roles->roles AS $key => $role ) {
?>
                     <option value="<?php print( $key ); ?>" <?php print( ( $key == $tnoBCFManagementOptions[ 'company_role' ] ? ' selected="selected"' : '' ) ); ?>>
						<?php print( $role[ 'name' ] ); ?>
                     </option>
<?php
   }
?>
				   </select>
<?php
}
?>
				</td>
				<td class="description">The role users get for whom a BIM Quickscan has been submitted</td>
			</tr>
			<tr>
				<td><label for="adviser-role">Adviser role</label></td>
				<td>
<?php
if( is_array( $wp_roles->roles ) ) {
?>
                  <select name="tno_bcf_management_options[adviser_role]" id="adviser-role">
<?php
   foreach( $wp_roles->roles AS $key => $role ) {
?>
                     <option value="<?php print( $key ); ?>" <?php print( ( $key == $tnoBCFManagementOptions[ 'adviser_role' ] ? ' selected="selected"' : '' ) ); ?>>
						<?php print( $role[ 'name' ] ); ?>
                     </option>
<?php
   }
?>
				   </select>
<?php
}
?>
				</td>
				<td class="description">The role users get who submit BIM Quickscans for other companies</td>
			</tr-->
			<tr valign="top">
				<td><label for="bcf-issue-post-type">BCF Issue Post Type</label></td>
				<td>
<?php
if( is_array( $postTypes ) ) {
?>
                  <select name="tno_bcf_management_options[bcf_issue_post_type]" id="bcf-issue-post-type">
<?php
   foreach( $postTypes AS $key => $postType ) {
?>
                     <option value="<?php print( $key ); ?>" <?php print( ( $key == $tnoBCFManagementOptions[ 'bcf_issue_post_type' ] ? ' selected="selected"' : '' ) ); ?>>
						<?php print( $postType->labels->name ); ?>
                     </option>
<?php
   }
?>
				   </select>
<?php
}
?>
					<p class="description">The post type in which we place BCF issues</p>
				</td>
			</tr>
			<tr valign="top">
				<td><label for="bcf-project-post-type">BCF Project Post Type</label></td>
				<td>
<?php
if( is_array( $postTypes ) ) {
?>
                  <select name="tno_bcf_management_options[bcf_project_post_type]" id="bcf-project-post-type">
<?php
   foreach( $postTypes AS $key => $postType ) {
?>
                     <option value="<?php print( $key ); ?>" <?php print( ( $key == $tnoBCFManagementOptions[ 'bcf_project_post_type' ] ? ' selected="selected"' : '' ) ); ?>>
						<?php print( $postType->labels->name ); ?>
                     </option>
<?php
   }
?>
				   </select>
<?php
}
?>
					<p class="description">The post type in which we place BCF projects, issues can be linked to these projects</p>
				</td>
			</tr>
			<tr valign="top">
				<td><label for="issue-details-uri">Issue detail URI</label></td>
				<td>
					<input type="text" id="issue-details-uri" name="tno_bcf_management_options[issue_details_uri]" value="<?php print( isset( $tnoBCFManagementOptions[ 'issue_details_uri' ] ) ? $tnoBCFManagementOptions[ 'issue_details_uri' ] : '' ); ?>" />
					<p class="description">The URI of the page which uses the issue detail template</p>
				</td>
				
			</tr>			
			<!--tr>
				<td><label for="topic-taxonomy">The taxonomy for topics</label></td>
				<td>
<?php
if( is_array( $taxonomies ) ) {
?>
                  <select name="tno_bcf_management_options[taxonomy_topic]" id="topic-taxonomy">
<?php
   foreach( $taxonomies AS $key => $taxonomy ) {
?>
                     <option value="<?php print( $key ); ?>" <?php print( ( $key == $tnoBCFManagementOptions[ 'taxonomy_topic' ] ? ' selected="selected"' : '' ) ); ?>>
						<?php print( $taxonomy->labels->name ); ?>
                     </option>
<?php
   }
?>
				   </select>
<?php
}
?>
				</td>
				<td class="description">The taxonomy topics are stored in</td>
			</tr>
			<tr>
				<td><label for="exclude-topic">Exclude topic from chart</label></td>
				<td>
<?php
if( is_array( $terms ) ) {
?>
                  <select name="tno_bcf_management_options[exclude_topic]" id="exclude-topic">
                  	<option value="">None</option>
<?php
   foreach( $terms AS $key => $term ) {
?>
                     <option value="<?php print( $term->term_id ); ?>" <?php print( ( $term->term_id == $tnoBCFManagementOptions[ 'exclude_topic' ] ? ' selected="selected"' : '' ) ); ?>>
						<?php print( $term->name ); ?>
                     </option>
<?php
   }
?>
				   </select>
<?php
}
?>
				</td>
				<td class="description">A topic to exclude from the report bar chart</td>
			</tr>
			<tr>
				<td><label for="category-taxonomy">The taxonomy for aspects</label></td>
				<td>
<?php
if( is_array( $taxonomies ) ) {
?>
                  <select name="tno_bcf_management_options[taxonomy_category]" id="category-taxonomy">
<?php
   foreach( $taxonomies AS $key => $taxonomy ) {
?>
                     <option value="<?php print( $key ); ?>" <?php print( ( $key == $tnoBCFManagementOptions[ 'taxonomy_category' ] ? ' selected="selected"' : '' ) ); ?>>
						<?php print( $taxonomy->labels->name ); ?>
                     </option>
<?php
   }
?>
				   </select>
<?php
}
?>
				</td>
				<td class="description">The taxonomy aspects are stored in</td>
			</tr>
			<tr>
				<td><label for="reports-per-page">Reports per page</label></td>
				<td>
				   <input type="text" name="tno_bcf_management_options[reports_per_page]" value="<?php print( isset( $tnoBCFManagementOptions[ 'reports_per_page' ] ) ? $tnoBCFManagementOptions[ 'reports_per_page' ] : '20' ); ?>" id="reports-per-page" />
				</td>
				<td class="description">The number of reports per page in the frontend.</td>
			</tr>
<?php
if( count( $pages ) > 0 ) {
?>
			<tr>
				<td><label for="not-logged-in-page">Not logged in page</label></td>
				<td>
                  <select name="tno_bcf_management_options[not_logged_in_page]" id="not-logged-in-page">
<?php
   foreach( $pages as $page ) {
?>
                     <option value="<?php print( $page->ID ); ?>" <?php print( ( $page->ID == $tnoBCFManagementOptions[ 'not_logged_in_page' ] ? ' selected="selected"' : '' ) ); ?>>
												<?php print( $page->post_title ); ?>
                     </option>
<?php
   }
?>
				   </select>
				</td>
				<td class="description">The page to which visitors who are redirected when they are not logged in and trying to access a protected page</td>
			</tr>
<?php
}
if( count( $terms ) > 0 ) {
   foreach( $terms as $term ) {
   	if( $term->term_id != $tnoBCFManagementOptions[ 'exclude_topic' ] ) {
?>
			<tr>
				<td><label for="topic-cap-<?php print( $term->term_id ); ?>">Topic cap <?php print( $term->name ); ?></label></td>
				<td>
					<input type="text" id="topic-cap-<?php print( $term->term_id ); ?>" name="tno_bcf_management_options[topic_cap_<?php print( $term->term_id ); ?>]" value="<?php print( isset( $tnoBCFManagementOptions[ 'topic_cap_' . $term->term_id ] ) ? $tnoBCFManagementOptions[ 'topic_cap_' . $term->term_id ] : 5 ); ?>" />
				</td>
				<td class="description">The topic cap for the topic &quot;<?php print( $term->name ); ?>&quot;, results will be scored down to this value</td>
			</tr>
<?php
   	}
   }
}
if( count( $aspects ) > 0 ) {
   foreach( $aspects as $aspect ) {
?>
			<tr>
				<td><label for="aspect-short-name-<?php print( $aspect->term_id ); ?>">Short name for <?php print( $aspect->name ); ?></label></td>
				<td>
					<input type="text" id="aspect-short-name-<?php print( $aspect->term_id ); ?>" name="tno_bcf_management_options[aspect_short_name_<?php print( $aspect->term_id ); ?>]" value="<?php print( isset( $tnoBCFManagementOptions[ 'aspect_short_name_' . $aspect->term_id ] ) ? $tnoBCFManagementOptions[ 'aspect_short_name_' . $aspect->term_id ] : $aspect->name ); ?>" />
				</td>
				<td class="description">The short name used in the radar graph for the aspect &quot;<?php print( $aspect->name ); ?>&quot;</td>
			</tr>
<?php
   }
}

if( class_exists( 'RGForms' ) ) {
	$forms =  RGFormsModel::get_forms();
} else {
	$forms = Array();
}
if( count( $forms ) > 0 ) {
?>
			<tr>
				<td><label for="quickscan-gravityform">The Quickscan form</label></td>
				<td>
                  <select name="tno_bcf_management_options[quickscan_form]" id="quickscan-gravityform">
<?php
   foreach( $forms as $form ) {
?>
                     <option value="<?php print( $form->id ); ?>" <?php print( ( $form->id == $tnoBCFManagementOptions[ 'quickscan_form' ] ? ' selected="selected"' : '' ) ); ?>>
												<?php print( $form->title ); ?>
                     </option>
<?php
   }
?>
				   </select>
				</td>
				<td class="description">The GravityForms form which contains the Quickscan questions</td>
			</tr>
<?php
}
?>
			<tr>
				<td><label for="import-selfscan-csv">Import selfscan CSV</label></td>
				<td>
				   <input type="file" name="import_selfscan_csv" id="import-selfscan-csv" />
				</td>
				<td class="description">Upload a CSV file to be imported as selfscans.</td>
			</tr>
			<tr>
				<td><label for="import-quickscan-csv">Import quickscan CSV</label></td>
				<td>
				   <input type="file" name="import_quickscan_csv" id="import-quickscan-csv" />
				</td>
				<td class="description">Upload a CSV file to be imported as Quickscans with advisor.</td>
			</tr>
			<tr>
				<td><label for="import-quickscan-advisors">Import advisors CSV</label></td>
				<td>
				   <input type="file" name="import_advisors_csv" id="import-quickscan-advisors" />
				</td>
				<td class="description">Upload a CSV file containing the advisors and their id used to match them with Quickscans.</td>
			</tr>
			<tr>
				<td colspan="2">
					<input type="checkbox" name="add_roles" id="add-roles" value="true" />
					<label for="add-roles">Force add the roles required for Quickscans</label>
				<td>
				<td class="description">In case the advisor and company roles are not created on activating the plugin check this to force them to be added.</td>
			</tr-->
			<tr>
				<td colspan="2">
					<p class="submit">
						<input class="button-primary" type="submit" name="action"
							value="update" />
					</p>
				</td>
			</tr>
			</table>
	</form>
</div>
