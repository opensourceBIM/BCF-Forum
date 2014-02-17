<?php
global $bimBCFManagement, $wpdb, $wp_roles;

if( isset( $_POST['action'] ) ) {
	foreach( $_POST[ 'bim_bcf_management_options' ] AS $key => $newOption ) {
		$options[$key] = $newOption;
	}

	if( isset( $options[ 'issue_page' ] ) && $options[ 'issue_page' ] != -1 ) {
		$permalink = get_permalink( $options[ 'issue_page' ] );
		$wpurl = get_bloginfo( 'wpurl' );
		$options[ 'issue_details_uri' ] = str_replace( $wpurl, '', $permalink );
	}

	update_option( 'bim_bcf_management_options', $options );
	$bimBCFManagementOptions = BIMBCFManagement::getOptions( true );

	// Here we update our extensions.xsd file
	$file = fopen( plugin_dir_path( __FILE__ ) . 'xsd/extensions.xsd', 'w' );
	if( $file !== false ) {
		$redefines = '';
		fwrite( $file, '<?xml version="1.0" encoding="UTF-8"?>' . "\n" );
		fwrite( $file, '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">' . "\n" );
		fwrite( $file, '	<xs:redefine schemaLocation="markup.xsd">' . "\n" );
		if( isset( $options[ 'topic_types' ] ) && $options[ 'topic_types' ] != '' ) {
			$topicTypes = explode( ',', $options[ 'topic_types' ] );
			fwrite( $file, '		<xs:simpleType name="TopicType">' . "\n" );
			fwrite( $file, '			<xs:restriction base="TopicType">' . "\n" );
			foreach( $topicTypes as $topicType ) {
				fwrite( $file, '				<xs:enumeration value="' . htmlentities( trim( stripslashes( $topicType ) ), defined( 'ENT_XML1' ) ? ENT_XML1 : ENT_QUOTES, 'UTF-8' ) . '" />' . "\n" );
			}
			fwrite( $file, '			</xs:restriction>' . "\n" );
			fwrite( $file, '		</xs:simpleType>' . "\n" );
			$redefines .= '	<xs:element name="ExtendedTopicType" type="TopicType" />' . "\n";
		}
		if( isset( $options[ 'topic_statuses' ] ) && $options[ 'topic_statuses' ] != '' ) {
			$topicStatuses = explode( ',', $options[ 'topic_statuses' ] );
			fwrite( $file, '		<xs:simpleType name="TopicStatus">' . "\n" );
			fwrite( $file, '			<xs:restriction base="TopicStatus">' . "\n" );
			foreach( $topicStatuses as $topicStatus ) {
				fwrite( $file, '				<xs:enumeration value="' . htmlentities( trim( stripslashes( $topicStatus ) ), defined( 'ENT_XML1' ) ? ENT_XML1 : ENT_QUOTES, 'UTF-8' ) . '" />' . "\n" );
			}
			fwrite( $file, '			</xs:restriction>' . "\n" );
			fwrite( $file, '		</xs:simpleType>' . "\n" );
			$redefines .= '	<xs:element name="ExtendedTopicStatus" type="TopicStatus" />' . "\n";
		}
		if( isset( $options[ 'topic_labels' ] ) && $options[ 'topic_labels' ] != '' ) {
			$topicLabels = explode( ',', $options[ 'topic_labels' ] );
			fwrite( $file, '		<xs:simpleType name="TopicLabel">' . "\n" );
			fwrite( $file, '			<xs:restriction base="TopicLabel">' . "\n" );
			foreach( $topicLabels as $topicLabel ) {
				fwrite( $file, '				<xs:enumeration value="' . htmlentities( trim( stripslashes( $topicLabel ) ), defined( 'ENT_XML1' ) ? ENT_XML1 : ENT_QUOTES, 'UTF-8' ) . '" />' . "\n" );
			}
			fwrite( $file, '			</xs:restriction>' . "\n" );
			fwrite( $file, '		</xs:simpleType>' . "\n" );
			$redefines .= '	<xs:element name="ExtendedTopicLabel" type="TopicLabel" />' . "\n";
		}
		if( isset( $options[ 'snippet_types' ] ) && $options[ 'snippet_types' ] != '' ) {
			$snippetTypes = explode( ',', $options[ 'snippet_types' ] );
			fwrite( $file, '		<xs:simpleType name="SnippetType">' . "\n" );
			fwrite( $file, '			<xs:restriction base="SnippetType">' . "\n" );
			foreach( $snippetTypes as $snippetType ) {
				fwrite( $file, '				<xs:enumeration value="' . htmlentities( trim( stripslashes( $snippetType ) ), defined( 'ENT_XML1' ) ? ENT_XML1 : ENT_QUOTES, 'UTF-8' ) . '" />' . "\n" );
			}
			fwrite( $file, '			</xs:restriction>' . "\n" );
			fwrite( $file, '		</xs:simpleType>' . "\n" );
			$redefines .= '	<xs:element name="ExtendedSnippetType" type="SnippetType" /> . "\n"';
		}
		if( isset( $options[ 'priorities' ] ) && $options[ 'priorities' ] != '' ) {
			$priorities = explode( ',', $options[ 'priorities' ] );
			fwrite( $file, '		<xs:simpleType name="Priority">' . "\n" );
			fwrite( $file, '			<xs:restriction base="Priority">' . "\n" );
			foreach( $priorities as $priority ) {
				fwrite( $file, '				<xs:enumeration value="' . htmlentities( trim( stripslashes( $priority ) ), defined( 'ENT_XML1' ) ? ENT_XML1 : ENT_QUOTES, 'UTF-8' ) . '" />' . "\n" );
			}
			fwrite( $file, '			</xs:restriction>' . "\n" );
			fwrite( $file, '		</xs:simpleType>' . "\n" );
			$redefines .= '	<xs:element name="ExtendedPriority" type="Priority" />' . "\n";
		}
		if( isset( $options[ 'user_id_types' ] ) && $options[ 'user_id_types' ] != '' ) {
			$userIdTypes = explode( ',', $options[ 'user_id_types' ] );
			fwrite( $file, '		<xs:simpleType name="UserIdType">' . "\n" );
			fwrite( $file, '			<xs:restriction base="UserIdType">' . "\n" );
			foreach( $userIdTypes as $userIdType ) {
				fwrite( $file, '				<xs:enumeration value="' . htmlentities( trim( stripslashes( $userIdType ) ), defined( 'ENT_XML1' ) ? ENT_XML1 : ENT_QUOTES, 'UTF-8' ) . '" />' . "\n" );
			}
			fwrite( $file, '			</xs:restriction>' . "\n" );
			fwrite( $file, '		</xs:simpleType>' . "\n" );
			$redefines .= '	<xs:element name="ExtendedUserIdType" type="UserIdType" />' . "\n";
		}
		fwrite( $file, '	</xs:redefine>' . "\n" );
		fwrite( $file, $redefines );
		fwrite( $file, '</xs:schema>' . "\n" );
		fclose( $file );
	} else {
?>
		<p>
			<?php _e( 'Could not write extensions.xsd, make sure the file is writable.', 'bim-bcf-management' ); ?><br />
			<?php print( plugin_dir_path( __FILE__ ) . 'xsd/extensions.xsd' ); ?>
		</p>
<?php
	}
} else {
	$bimBCFManagementOptions = BIMBCFManagement::getOptions();
}

$postTypes = get_post_types( Array(), 'objects' );
$pages = get_posts( Array(
		'post_type' => 'page',
		'posts_per_page' => -1
) );
?>
<div class="wrap">
	<div class="icon32" id="icon-options-general"></div>
	<h2>BIM BCF Management Options</h2>
	<form method="post" enctype="multipart/form-data">
		<table class="form-table">
			<tr valign="top">
				<td><label for="bcf-issue-post-type">BCF Issue Post Type</label></td>
				<td>
<?php
	if( is_array( $postTypes ) ) {
?>
					<select name="bim_bcf_management_options[bcf_issue_post_type]" id="bcf-issue-post-type">
<?php
		foreach( $postTypes AS $key => $postType ) {
?>
						<option value="<?php print( $key ); ?>" <?php print( ( $key == $bimBCFManagementOptions[ 'bcf_issue_post_type' ] ? ' selected="selected"' : '' ) ); ?>>
							<?php print( $postType->labels->name ); ?>
						</option>
<?php
   }
?>
				</select>
<?php
}
?>
					<p class="description"><?php _e( 'The post type in which we place BCF issues', 'bim-bcf-management' ); ?></p>
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
					<select name="bim_bcf_management_options[bcf_project_post_type]" id="bcf-project-post-type">
<?php
						foreach( $postTypes AS $key => $postType ) {
?>
						<option value="<?php print( $key ); ?>" <?php print( ( $key == $bimBCFManagementOptions[ 'bcf_project_post_type' ] ? ' selected="selected"' : '' ) ); ?>>
							<?php print( $postType->labels->name ); ?>
						</option>
<?php
   }
?>
				</select>
<?php
}
?>
					<p class="description"><?php _e( 'The post type in which we place BCF projects, issues can be linked to these projects', 'bim-bcf-management' ); ?></p></td>
			</tr>
			<tr valign="top">
				<td><label for="issue-page">Issue page</label>
				</td>
				<td>
					<select name="bim_bcf_management_options[issue_page]" id="issue-page">
						<option value="-1"><?php _e( 'Custom URI', 'bim-bcf-management' ); ?></option>
<?php
	foreach( $pages as $page ) {
?>
						<option value="<?php print( $page->ID ); ?>"<?php print( ( isset( $bimBCFManagementOptions[ 'issue_page' ] ) && $bimBCFManagementOptions[ 'issue_page' ] == $page->ID ? ' selected' : '' ) ); ?>>
							<?php print( $page->post_title ); ?>
                    	</option>


<?php
   }
?>
				   </select>
				   <p class="description"><?php _e( 'Page which displays single issues', 'bim-bcf-management' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<td><label for="issue-details-uri">Issue detail URI</label></td>
				<td>
					<input type="text" id="issue-details-uri" name="bim_bcf_management_options[issue_details_uri]" value="<?php print( isset( $bimBCFManagementOptions[ 'issue_details_uri' ] ) ? $bimBCFManagementOptions[ 'issue_details_uri' ] : '' ); ?>" />
					<p class="description"><?php _e( 'Set the relative URI here if you do not select a page in the option above. This should point to the page which displays single issues', 'bim-bcf-management' ); ?></p>
				</td>
			</tr>
			<!--tr valign="top">
				<td><label for="extensions">Extensions</label></td>
				<td>
					<textarea rows="10" cols="40" id="extensions" name="bim_bcf_management_options[extensions]"><?php print( isset( $bimBCFManagementOptions[ 'extensions' ] ) ? stripslashes( $bimBCFManagementOptions[ 'extensions' ] ) : '' ); ?></textarea>
					<p class="description"><?php _e( 'Put the XML for the extension schema here', 'bim-bcf-management' ); ?></p>
				</td>
			</tr-->
			<tr valign="top">
				<td><label for="topic-types">Topic types</label></td>
				<td>
					<input type="text" id="issue-details-uri" name="bim_bcf_management_options[topic_types]" value="<?php print( isset( $bimBCFManagementOptions[ 'topic_types' ] ) ? stripslashes( $bimBCFManagementOptions[ 'topic_types' ] ) : '' ); ?>" />
					<p class="description"><?php _e( 'Comma seperated list of topic types, used in the site and to generate extensions.xsd', 'bim-bcf-management' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<td><label for="topic-statuses">Topic statuses</label></td>
				<td>
					<input type="text" id="topic-statuses" name="bim_bcf_management_options[topic_statuses]" value="<?php print( isset( $bimBCFManagementOptions[ 'topic_statuses' ] ) ? stripslashes( $bimBCFManagementOptions[ 'topic_statuses' ] ) : '' ); ?>" />
					<p class="description"><?php _e( 'Comma seperated list of topic statuses, used in the site and to generate extensions.xsd', 'bim-bcf-management' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<td><label for="topic-labels">Topic labels</label></td>
				<td>
					<input type="text" id="topic-labels" name="bim_bcf_management_options[topic_labels]" value="<?php print( isset( $bimBCFManagementOptions[ 'topic_labels' ] ) ? stripslashes( $bimBCFManagementOptions[ 'topic_labels' ] ) : '' ); ?>" />
					<p class="description"><?php _e( 'Comma seperated list of topic labels, used in the site and to generate extensions.xsd', 'bim-bcf-management' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<td><label for="snippet-types">Snippet types</label></td>
				<td>
					<input type="text" id="snippet-types" name="bim_bcf_management_options[snippet_types]" value="<?php print( isset( $bimBCFManagementOptions[ 'snippet_types' ] ) ? stripslashes( $bimBCFManagementOptions[ 'snippet_types' ] ) : '' ); ?>" />
					<p class="description"><?php _e( 'Comma seperated list of snippet labels, used in the site and to generate extensions.xsd', 'bim-bcf-management' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<td><label for="priorities">Priorities</label></td>
				<td>
					<input type="text" id="priorities" name="bim_bcf_management_options[priorities]" value="<?php print( isset( $bimBCFManagementOptions[ 'priorities' ] ) ? stripslashes( $bimBCFManagementOptions[ 'priorities' ] ) : '' ); ?>" />
					<p class="description"><?php _e( 'Comma seperated list of priorities, used in the site and to generate extensions.xsd', 'bim-bcf-management' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<td><label for="user-id-types">User ID types</label></td>
				<td>
					<input type="text" id="user-id-types" name="bim_bcf_management_options[user_id_types]" value="<?php print( isset( $bimBCFManagementOptions[ 'user_id_types' ] ) ? stripslashes( $bimBCFManagementOptions[ 'user_id_types' ] ) : '' ); ?>" />
					<p class="description"><?php _e( 'Comma seperated list of user id types, used in the site and to generate extensions.xsd', 'bim-bcf-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<p class="submit">
						<input class="button-primary" type="submit" name="action" value="update" />
					</p>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<p class="description">The API URI: <a href="<?php print( plugins_url( 'api.php', __FILE__ ) ); ?>" target="_blank"><?php print( plugins_url( 'api.php', __FILE__ ) ); ?></a></p>
					<p class="description">extensions.xsd: <a href="<?php print( plugins_url( 'xsd/extensions.xsd', __FILE__ ) ); ?>" target="_blank"><?php print( plugins_url( 'xsd/extensions.xsd', __FILE__ ) ); ?></a></p>
				</td>
			</tr>
		</table>
	</form>
</div>
