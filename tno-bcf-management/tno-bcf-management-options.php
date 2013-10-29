<?php
global $tnoBCFManagement, $wpdb, $wp_roles;

if( isset( $_POST['action'] ) ) {
	foreach( $_POST[ 'tno_bcf_management_options' ] AS $key => $newOption ) {
		$options[$key] = $newOption;
	}
	 
	if( isset( $options[ 'issue_page' ] ) && $options[ 'issue_page' ] != -1 ) {
		$permalink = get_permalink( $options[ 'issue_page' ] );
		$wpurl = get_bloginfo( 'wpurl' );
		$options[ 'issue_details_uri' ] = str_replace( $wpurl, '', $permalink );
	}

	update_option( 'tno_bcf_management_options', $options );
	$tnoBCFManagementOptions = TNOBCFManagement::getOptions( true );
} else {
	$tnoBCFManagementOptions = TNOBCFManagement::getOptions();
}


$postTypes = get_post_types( Array(), 'objects' );
$pages = get_posts( Array(
		'post_type' => 'page',
		'posts_per_page' => -1
) );
?>
<div class="wrap">
	<div class="icon32" id="icon-options-general"></div>
	<h2>TNO BCF Management Options</h2>
	<form method="post" enctype="multipart/form-data">
		<table class="form-table">
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
					<p class="description"><?php _e( 'The post type in which we place BCF issues' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<td>
					<label for="bcf-project-post-type">BCF Project Post Type</label>
				</td>
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
					<p class="description"><?php _e( 'The post type in which we place BCF projects, issues can be linked to these projects' ); ?></p></td>
			</tr>
			<tr valign="top">
				<td><label for="issue-page">Issue page</label>
				</td>
				<td>
					<select name="tno_bcf_management_options[issue_page]" id="issue-page">
						<option value="-1"><?php _e( 'Custom URI' ); ?></option>
<?php
	foreach( $pages as $page ) {
?>					
						<option value="<?php print( $page->ID ); ?>"<?php print( ( isset( $tnoBCFManagementOptions[ 'issue_page' ] ) && $tnoBCFManagementOptions[ 'issue_page' ] == $page->ID ? ' selected' : '' ) ); ?>>
							<?php print( $page->post_title ); ?>
                    	</option>
						
						
<?php
   }
?>
				   </select>
				   <p class="description"><?php _e( 'Page which displays single issues' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<td><label for="issue-details-uri">Issue detail URI</label></td>
				<td>
					<input type="text" id="issue-details-uri" name="tno_bcf_management_options[issue_details_uri]" value="<?php print( isset( $tnoBCFManagementOptions[ 'issue_details_uri' ] ) ? $tnoBCFManagementOptions[ 'issue_details_uri' ] : '' ); ?>" />
					<p class="description"><?php _e( 'Set the relative URI here if you do not select a page in the option above. This should point to the page which displays single issues' ); ?></p>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<p class="description">The API URI: <a href="<?php print( plugins_url( 'api.php', __FILE__ ) ); ?>" target="_blank"><?php print( plugins_url( 'api.php', __FILE__ ) ); ?></a></p>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<p class="submit">
						<input class="button-primary" type="submit" name="action" value="update" />
					</p>
				</td>
			</tr>
		</table>
	</form>
</div>
