<?php
/*
Plugin Name: TNO BCF Management
Plugin URI: http://www.tno.nl
Description: Adds BCF 2.0 issue management to WordPress, upload zip archives with issues or add them through a form and keep track of your issues and their details.
Version: 1.0
Author: Bastiaan Grutters
Author URI: http://www.bastiaangrutters.nl
Usage: 
<?php
if( class_exists( 'TNOBCFManagement' ) ) {
	// Show list of issues for this user
	TNOBCFManagement::showIssues();
	// Show details of the supplied issue's id if accessible for the current user
	TNOBCFManagement::showIssue( $issueId );
	// Display a form with which a zip archive of issues can be imported
	TNOBCFManagement::showAddZipForm();
	// Display a form with which an issue can be added
	TNOBCFManagement::showAddIssueForm();
}
?>
 */

class TNOBCFManagement {
	private $options;
	
	public function __construct() {
		$this->options = get_option( 'tno_bcf_management_options', Array() );
		// Check default options and set if missing
		if( !isset( $this->options[ 'bcf_issue_post_type' ] ) ) {
			$this->options[ 'bcf_issue_post_type' ] = 'bcf_issue';
		}
		if( !isset( $this->options[ 'bcf_project_post_type' ] ) ) {
			$this->options[ 'bcf_project_post_type' ] = 'bcf_project';
		}
		
		// --- Action hooks ---
		// Add options menu page to menu
		add_action( 'admin_menu', Array( 'TNOBCFManagement', 'addOptionsMenu' ) );
		// Add post types etc at the WordPress init action
		add_action( 'init', Array( 'TNOBCFManagement', 'wordPressInit' ) );
	}
	
	public static function wordPressInit() {
		$postTypeArguments = Array(
				'labels' => Array(
						'name' => _x( 'BCF Issues', 'post type general name' ),
						'singular_name' => _x( 'BCF Issue', 'post type singular name'),
						'add_new' => __( 'Add New' ),
						'add_new_item' => __( 'Add New BCF Issue' ),
						'edit_item' => __( 'Edit BCF Issue' ),
						'new_item' => __( 'New BCF Issue' ),
						'all_items' => __( 'All BCF Issues' ),
						'view_item' => __( 'View BCF Issue' ),
						'search_items' => __( 'Search BCF Issues' ),
						'not_found' =>  __( 'No BCF issue found' ),
						'not_found_in_trash' => __( 'No BCF Issues found in Trash' ),
						'parent_item_colon' => '',
						'menu_name' => 'BCF Issues' ),
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => true,
				'show_in_menu' => true,
				'query_var' => true,
				'rewrite' => true,
				'has_archive' => true,
				'hierarchical' => false,
				'menu_position' => null,
				'supports' => Array( 'title', 'editor', 'thumbnail', 'author', 'custom-fields' )
		);
		register_post_type( 'bcf_issue', $postTypeArguments );
		
		$postTypeArguments = Array(
				'labels' => Array(
						'name' => _x( 'BCF Projects', 'post type general name' ),
						'singular_name' => _x( 'BCF Project', 'post type singular name'),
						'add_new' => __( 'Add New' ),
						'add_new_item' => __( 'Add New BCF Project' ),
						'edit_item' => __( 'Edit BCF Project' ),
						'new_item' => __( 'New BCF Project' ),
						'all_items' => __( 'All BCF Projects' ),
						'view_item' => __( 'View BCF Project' ),
						'search_items' => __( 'Search BCF Projects' ),
						'not_found' =>  __( 'No BCF project found' ),
						'not_found_in_trash' => __( 'No BCF Projects found in Trash' ),
						'parent_item_colon' => '',
						'menu_name' => 'BCF Projects' ),
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => false,
				'show_in_menu' => false,
				'query_var' => true,
				'rewrite' => true,
				'map_meta_cap' => false,
				'has_archive' => false,
				'hierarchical' => false,
				'menu_position' => null,
				'supports' => Array( 'title', 'editor', 'thumbnail', 'author', 'custom-fields' )
		);
		register_post_type( 'bcf_project', $postTypeArguments );
		
		// Add image sizes for the issue list and details pages
		add_theme_support( 'post-thumbnails' );
		add_image_size( 'issue-list-thumb', 120, 80 ); 
		add_image_size( 'issue-detail-thumb', 400, 300 );
	}
	
	public static function addOptionsMenu() {
		add_options_page( 'TNO BCF Management Options', 'TNO BCF Management', 'activate_plugins', 'tno_bcf_management_options', Array( 'TNOBCFManagement', 'showOptionsPage' ) );
	}
	
	public static function showOptionsPage() {
		include( plugin_dir_path( __FILE__ ) . 'tno-bcf-management-options.php' );
	}
	
	public static function showIssues() {
		//print( "showIssues()<br />" );
		$options = TNOBCFManagement::getOptions();
		$myIssues = get_posts( Array(
				'posts_per_page' => -1,
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'post_status' => 'publish',
				'orderby' => 'date',
				'order' => 'DESC',
		) );
		if( count( $myIssues ) > 0 ) {
			$index = 0;
?>
			<table>
				<tr>
					<th>&nbsp;</th>
					<th>Issue</th>
					<th>Revisie</th>
					<th>Project</th>
				</tr>
<?php 
			foreach( $myIssues as $issue ) {
				$revision = get_post_meta( $issue->ID, 'revision', true );
				$project = get_post_meta( $issue->ID, 'project', true );
?>
				<tr class="<?php print( $index % 2 == 0 ? 'even' : 'odd' ); ?>">
					<td><?php print( get_the_post_thumbnail( $issue->ID, 'issue-list-thumb' ) ); ?></td>
					<td><a href="<?php print( get_bloginfo( 'wpurl' ) . $options[ 'issue_details_uri' ] . '?id=' . $issue->ID );  ?>"><?php print( $issue->post_title ); ?></a></<td>
					<td><?php print( $revision == '' ? '-' : $revision ); ?></<td>
					<td><?php print( $project == '' ? '-' : $project ); ?></<td>
				</tr>
<?php
				$index ++;
			}
?>				
			</table>
<?php
		} else {
?>
			<p>Je hebt nog geen BCF issues.</p>
<?php
		}
	}
	
	public static function showIssue() {
		//print( "showIssue()<br />" );
		$issueId = ( isset( $_GET[ 'id' ] ) && ctype_digit( $_GET[ 'id' ] ) ) ? $_GET[ 'id' ] : -1;
		if( $issueId != -1 ) {
			$currentUserId = get_current_user_id();
			$issue = get_post( $issueId );
			if( $issue->post_author == $currentUserId ) {
				
?>
			<div class="issue-image"><?php print( get_the_post_thumbnail( $issueId, 'issue-detail-thumb' ) ); ?></div>
			<h3><?php print( $issue->post_title ); ?></h3>
			<table>
<?php 
?>

<?php
?>			
			</table>
<?php
			} else {
?>
			<p>Je kunt enkel issues bekijken die je zelf hebt ingesteld</p>
<?php
			}
		} else {
?>
			<p>Geen issue gekozen</p>
<?php
		}
	}

	public static function showAddZipForm() {
		if( isset( $_FILES[ 'bcf_zip_file' ] ) ) {
			if( isset( $_FILES[ 'bcf_zip_file' ][ 'error' ] ) && $_FILES[ 'bcf_zip_file' ][ 'error' ] != 0 ) {
				$errorMessage = 'Could not upload the file, contact a system administrator. Error code: ' . $_FILES[ 'bcf_zip_file' ][ 'error' ];
			}
			if( !isset( $errorMessage ) ) {
				$errorMessage = '';
				$zip = zip_open( $_FILES[ 'bcf_zip_file' ][ 'tmp_name' ] );
				if( is_resource( $zip ) ) {
					$files = Array();
					$guids = Array();
					while( ( $entry = zip_read( $zip ) ) !== false ) {
						// Every line should be a file from an issue
						$xml = '';
						$entryName = explode( '/', zip_entry_name( $entry ) );
						if( count( $entryName ) > 1 ) {
							$guid = $entryName[count( $entryName ) - 2];
							$filename = $entryName[count( $entryName ) - 1];
							if( zip_entry_open( $zip, $entry ) ) {
								while( ( $subEntry = zip_entry_read( $entry ) ) !== false && $subEntry != '' ) {
									$xml .= $subEntry;
								}
								zip_entry_close( $entry );
							}
						}
						if( !in_array( $guid, $guids ) ) {
							if( count( $files ) > 1 ) {
								// Import this XML
								$errorMessage .= TNOBCFManagement::addIssueFromZip( $files );
								$files = Array();
							}
							$guids[] = $guid;
						}
						$files[] = Array( $guid, $filename, $xml );
					}
					if( count( $files ) > 0 ) {
						$errorMessage .= TNOBCFManagement::addIssueFromZip( $files );
					}
					zip_close( $zip );
				} else {
					$errorMessage = 'Could not open the zip archive. Error code: ' . $zip;
				}
			}
			
			if( isset( $errorMessage ) && $errorMessage != '' ) {
?>
				<p class="form-error-message"><?php print( $errorMessage ); ?></p>
<?php
			}
		}
?>
			<form method="post" action="" enctype="multipart/form-data">
				<label for="bcf-zip-file">Select a BCF zip archive</label><br />
				<input type="file" id="bcf-zip-file" name="bcf_zip_file" /><br />
				<br />
				<input type="submit" value="toevoegen" />
			</form>
<?php
	}
	
	private static function addIssueFromZip( $files ) {
		$guid = '';
		$markup = Array();
		$project = false;
		$viewpoints = Array();
		$snapshots = Array();
			
		foreach( $files as $file ) {
			if( $guid == '' ) {
				// Remember the guid, is the same for all files
				$guid = $file[0];
			}
			
			if( $file[1] == 'markup.bcf' ) {
				// extract the XML from the markup
				$xml = simplexml_load_string( $file[2] );
				$markup = TNOBCFManagement::convertSimpleXML2Array( $xml );
			} elseif( $file[1] == 'viewpoint.bcfv' || substr( $file[1], strlen( $file[1] ) - 5 ) == '.bcfv' ) {
				//print( "viewpoint found: {$file[1]}<br />" );
				// extract the XML from viewpoint
				$xml = simplexml_load_string( $file[2] );
				$viewpoints[] = TNOBCFManagement::convertSimpleXML2Array( $xml );
			} elseif( $file[1] == 'project.bcfp' ) {
				//print( "project file found: {$file[1]}<br />" );
				// extract the XML from the project file
				$xml = simplexml_load_string( $file[2] );
				$project = TNOBCFManagement::convertSimpleXML2Array( $xml );
			} else {
				//print( "snapshot found: {$file[1]}<br />" );
				// This should be a screenshot file
				// store this to be added after we created the issue post
				$snapshots[] = Array( $file[1], $file[2] );
			}
		}
		//var_dump( $guid, $markup, $project, $viewpoints );
		// Create a post with information from the XML issue
		$options = TNOBCFManagement::getOptions();
		$currentUserId = get_current_user_id();
		
		$postData = Array(
				'post_title' => wp_strip_all_tags( $guid ),
				'post_content' => '',
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'post_status' => 'publish',
				'post_author' => $currentUserId
			);
		$postId = wp_insert_post( $postData );
		if( $postId > 0 ) {
			// Store XML stuff in post meta
			add_post_meta( $postId, 'markup', $markup, true );
			foreach( $viewpoints as $viewpoint ) {
				add_post_meta( $postId, 'viewpoint', $viewpoint, false );
			}
			
			// TODO: Could set some values to filter on for this issue
			
			// TODO: If a project is set, we check if it exists, then link it otherwise we
			 
			// Add snapshots as attachments (first one is the thumbnail!)
			$first = true;
			foreach( $snapshots as $snapshot ) {
				TNOBCFManagement::writeSnapshot( $postId, $snapshot, $first );
				if( $first ) {
					$first = false;
				}
			}
			return true;
		} else {
			return false;
		}
	}
	
	private static function writeSnapshot( $postId, $snapshot, $first = false ) {
		// We make sure the filename has a dot and extension
		if( strpos( $snapshot[0], '.' ) !== false ) {
			$uploadInfo = wp_upload_dir();
			$index = 1;
			$basename = substr( $snapshot[0], 0, strrpos( $snapshot[0], '.' ) );
			$extension = substr( $snapshot[0], strrpos( $snapshot[0], '.' ) + 1 );
			$filename = $snapshot[0];
			// Find the first available filename
			// TODO: can this get slow? If that is the case we could store the index for the current year/month and read it from there
			while ( file_exists( $uploadInfo[ 'path' ] . '/' . $filename ) ) {
				$index ++;
				$filename = $basename . '-' . $index . '.' . $extension;
			}
			$file = fopen( $uploadInfo[ 'path' ] . '/' . $filename, 'w' );
			if( $file !== false ) {
				fwrite( $file, $snapshot[1] );
				fclose( $file );
				// Add the snapshot as an attachment to the project
				$wpFiletype = wp_check_filetype( basename( $filename ), null );
				$attachment = Array(
						'guid' => $uploadInfo[ 'url' ] . '/' . basename( $filename ),
						'post_mime_type' => $wpFiletype[ 'type' ],
						'post_title' => preg_replace('/\.[^.]+$/', '', basename( $filename ) ),
						'post_content' => '',
						'post_status' => 'inherit'
				);
				$attachId = wp_insert_attachment( $attachment, $uploadInfo[ 'path' ] . '/' . $filename, $postId );
				// you must first include the image.php file
				// for the function wp_generate_attachment_metadata() to work
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				$attachData = wp_generate_attachment_metadata( $attachId, $uploadInfo[ 'path' ] . '/' . $filename );
				wp_update_attachment_metadata( $attachId, $attachData );
				if( $first ) {
					set_post_thumbnail( $postId, $attachId );
				}
			} else {
				return false;
			}
		}
	}
	
	public static function convertSimpleXML2Array( $simpleXMLObject ) {
		// We expect there to not be any elements with the name attributes
		$xmlArray = Array( '@attributes' => Array() );
		foreach( $simpleXMLObject->attributes() as $attributeName => $attributeValue ) {
			$xmlArray[ '@attributes' ][$attributeName] = '' . $attributeValue;
		}
		$simpleXMLObject = ( Array ) $simpleXMLObject;
		foreach( $simpleXMLObject as $key => $value ) {
			if( $key != '@attributes' ) {
				if( is_string( $value ) ) {
					$xmlArray[$key] = $value;
				} elseif( is_array( $value ) ) {
					$values = Array();
					foreach( $value as $subKey => $subValue ) {
						$values[] = TNOBCFManagement::convertSimpleXML2Array( $subValue );
					}
					$xmlArray[$key] = $values;
				} elseif( get_class( $value ) == 'SimpleXMLElement' ) {
					$xmlArray[$key] = TNOBCFManagement::convertSimpleXML2Array( $value );
				} else {
					// Could happen if a boolean or integer is found, but not sure if SimpleXML casts those to none string type
					print( "Unexpected value in XML object: $key => $value<br />\n" );
					$xmlArray[$key] = $value;
				}
			}
		}
		return $xmlArray;
	}

	public static function showAddIssueForm() {
		print( "showAddIssueForm()<br />" );
	}
	
	public static function getOptions( $forceReload = false ) {
		global $tnoBCFManagement;
		if( $forceReload ) {
			$tnoBCFManagement->options = get_option( 'tno_bcf_management_options', Array() );
		}
		return $tnoBCFManagement->options;
	}
}

$tnoBCFManagement = new TNOBCFManagement();
