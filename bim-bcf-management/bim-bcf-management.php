<?php
/*
Plugin Name: BIM BCF Management
Plugin URI:
Description: Adds BCF 2.0 issue management to WordPress, upload zip archives with issues or add them through a form and keep track of your issues and their details.
Version: 1.0
Author: Bastiaan Grutters
Author URI: http://www.bastiaangrutters.nl
Usage:
Using shortcodes:
[showIssues]
[showMyIssues]
[showIssue]
[showAddZipForm]
[showAddIssueForm]

Or using php functions in templates:
<?php
if( class_exists( 'BIMBCFManagement' ) ) {
	// Show list of issues for this user
	BIMBCFManagement::showMyIssues();
	// Show list of issues accessible through Bimsie projects
	BIMBCFManagement::showIssues();
	// Show details of the supplied issue's id if accessible for the current user
	BIMBCFManagement::showIssue( $issueId );
	// Display a form with which a zip archive of issues can be imported
	BIMBCFManagement::showAddZipForm();
	// Display a form with which an issue can be added
	BIMBCFManagement::showAddIssueForm();
}
?>
*/

include( 'BIMsie.php' );

class BIMBCFManagement {
	private $options;

	public function __construct() {
		$this->options = get_option( 'bim_bcf_management_options', Array() );
		// Check default options and set if missing
		if( !isset( $this->options[ 'bcf_issue_post_type' ] ) ) {
			$this->options[ 'bcf_issue_post_type' ] = 'bcf_issue';
		}
		if( !isset( $this->options[ 'bcf_project_post_type' ] ) ) {
			$this->options[ 'bcf_project_post_type' ] = 'bcf_project';
		}

		// --- Action hooks ---
		// Add options menu page to menu
		add_action( 'admin_menu', Array( 'BIMBCFManagement', 'addOptionsMenu' ) );
		// Add post types etc at the WordPress init action
		add_action( 'init', Array( 'BIMBCFManagement', 'wordPressInit' ) );
		// Add script files
		add_action( 'wp_enqueue_scripts', Array( 'BIMBCFManagement', 'enqueueScripts' ) );
		// Keep track of new comments to place them in the right data format
		add_action( 'preprocess_comment' , Array( 'BIMBCFManagement', 'preprocessCommentHandler' ) );

		// --- Add shortcodes ---
		add_shortcode( 'showIssues', Array( 'BIMBCFManagement', 'showIssues' ) );
		add_shortcode( 'showMyIssues', Array( 'BIMBCFManagement', 'showMyIssues' ) );
		add_shortcode( 'showIssue', Array( 'BIMBCFManagement', 'showIssue' ) );
		add_shortcode( 'showAddZipForm', Array( 'BIMBCFManagement', 'showAddZipForm' ) );
		add_shortcode( 'showAddIssueForm', Array( 'BIMBCFManagement', 'showAddIssueForm' ) );
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
		add_image_size( 'issue-list-thumb', 90, 60 );
		add_image_size( 'issue-detail-thumb', 400, 300 );
	}

	public static function addOptionsMenu() {
		add_options_page( 'BCF Management Options', 'BCF Management',
			'activate_plugins', 'bim_bcf_management_options',
			Array( 'BIMBCFManagement', 'showOptionsPage' ) );
	}

	public static function showOptionsPage() {
		include( plugin_dir_path( __FILE__ ) . 'bim-bcf-management-options.php' );
	}

	public static function showIssues() {
		if( is_user_logged_in() ) {
			if( ( isset( $_GET[ 'bimsie' ] ) && $_GET[ 'bimsie' ] != '' ) || ( isset( $_POST[ 'bimsie' ] ) && $_POST[ 'bimsie' ] != '' ) ) {
				// Show issues belonging to projects on this server
				if( isset( $_GET[ 'bimsie' ] ) && $_GET[ 'bimsie' ] != '' ) {
					$server = htmlentities( $_GET[ 'bimsie' ], ENT_QUOTES, get_bloginfo( 'charset' ) );
					$projects = BIMsie::getProjects( $_GET[ 'bimsie' ] );
				} else {
					// Add new bimsie server, store credentials or just use server with temporary credentials
					$server = htmlentities( $_POST[ 'bimsie' ], ENT_QUOTES, get_bloginfo( 'charset' ) );
					$projects = BIMsie::getProjects( $_POST[ 'bimsie' ] );
				}
			}
			
			if( isset( $projects ) && $projects !== false ) {
				$options = BIMBCFManagement::getOptions();
				$projects = BIMsie::getHierarchicalProjects( $projects );
?>
				<h3><?php _e( 'Bimsie server', 'bim-bcf-management' ); ?>: <?php print( $server ); ?></h3>
<?php
				foreach( $projects as $project ) {
					
					$myIssues = get_posts( Array(
							'posts_per_page' => -1,
							'post_type' => $options[ 'bcf_issue_post_type' ],
							'post_status' => 'publish',
							'orderby' => 'date',
							'order' => 'DESC',
							'meta_query' => Array(
									Array(
											'key' => 'import_status',
											'value' => 'complete'
									),
									Array(
											'key' => 'poid',
											'value' => $project->oid 
									)
							)
					) );
?>
					<h4><?php print( $project->name ); ?></h4>
<?php
					if( count( $myIssues ) > 0 ) { 
?>					
					<table class="issue-table">
						<tr>
							<th>&nbsp;</th>
							<th><?php _e( 'Issue', 'bim-bcf-management' ); ?></th>
							<th><?php _e( 'Date', 'bim-bcf-management' ); ?></th>
							<th><?php _e( 'Revision', 'bim-bcf-management' ); ?></th>
						</tr>
<?php
						$index = 0;
						foreach( $myIssues as $issue ) {
							$roids = get_post_meta( $issue->ID, 'roid' );
							$poids = get_post_meta( $issue->ID, 'poid' );
							$revisionInformation = '';
							foreach( $poids as $key => $poid ) {
								if( $poid == $project->oid ) {
									if( isset( $roids[$key] ) ) {
										$revision = BIMsie::getRevision( $server, $project->oid , $roids[$key] );
										if( $revision ) {
											$revisionInformation = $revision->name;
										} else {
											$revisionInformation = '-';
										}
									}
									break;
								}
							}
							$timestamp = strtotime( $issue->post_date );
?>
						<tr class="<?php print( $index % 2 == 0 ? 'even' : 'odd' ); ?>">
							<td><?php print( get_the_post_thumbnail( $issue->ID, 'issue-list-thumb' ) ); ?></td>
							<td><a href="<?php print( get_bloginfo( 'wpurl' ) . $options[ 'issue_details_uri' ] . '?id=' . $issue->ID );  ?>"><?php print( $issue->post_title ); ?></a></<td>
							<td><?php print( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ); ?></<td>
							<td><?php print( $revisionInformation ); ?></td>
						</tr>
<?php
							$index ++;
						}
?>
					</table>
<?php
					} else {
?>
					<p><?php _e( 'No issues for this project', 'bim-bcf-management' ); ?></p>					
<?php
					}
				}
			} else {
				// Show stored bimsie servers
				$bimsieServers = BIMsie::getServers();
				foreach( $bimsieServers as $key => $bimsieServer ) {
?>
			<div class="bimsie-server-link">
				<form method="<?php print( ( !isset( $bimsieServer[ 'remember' ] ) || $bimsieServer[ 'remember' ] == 0 ) ? 'post' : 'get' ); ?>" action="">
					<h3><?php print( ( isset( $bimsieServer[ 'username' ] ) ? ( $bimsieServer[ 'username' ] . ' - ' ) : '' ) . $bimsieServer[ 'uri' ] ); ?></h3>
					<input type="hidden" name="bimsie" value="<?php print( $bimsieServer[ 'uri' ] ); ?>" />
<?php
					if( !isset( $bimsieServer[ 'remember' ] ) || $bimsieServer[ 'remember' ] == 0 ) {
?>
					<input type="checkbox" name="remember" value="1" id="remember-<?php print( $key ); ?>" /> <label for="remember-<?php print( $key ); ?>"><?php _e( 'Remember me', 'bim-bcf-management' ); ?></label><br />
					<label for="username-<?php print( $key ); ?>"><?php _e( 'Username', 'bim-bcf-management' ); ?></label> <input type="text" id="username-<?php print( $key ); ?>" name="username" placeholder="<?php _e( 'Username', 'bim-bcf-management' ); ?>" value="" /><br />
					<label for="password-<?php print( $key ); ?>"><?php _e( 'Password', 'bim-bcf-management' ); ?></label> <input type="password" id="password-<?php print( $key ); ?>" name="password" placeholder="<?php _e( 'Password', 'bim-bcf-management' ); ?>" value="" /><br />
<?php
					}
?>
					<input type="submit" value="<?php _e( 'View', 'bim-bcf-management' ); ?>" />
				</form>
				<a href="?bimsie=<?php print( urlencode( $bimsieServer[ 'uri' ] ) ); ?>"></a><br />
			</div>
<?php
				}
				// TODO: add new server form
			}
		} else {
?>
		<p><?php _e( 'Please log in to access this page', 'bim-bcf-management' ); ?></p>
<?php
		}
	}

	public static function showMyIssues() {
		if( is_user_logged_in() ) {
			//print( "showIssues()<br />" );
			global $current_user;
			get_currentuserinfo();
			$options = BIMBCFManagement::getOptions();
			$shownIssues = Array();
			$assignedIssues = get_posts( Array(
					'posts_per_page' => -1,
					'post_type' => $options[ 'bcf_issue_post_type' ],
					'post_status' => 'publish',
					'orderby' => 'date',
					'order' => 'DESC',
					'meta_query' => Array(
							Array(
									'key' => 'import_status',
									'value' => 'complete'
							),
							Array(
									'key' => 'assigned_to',
									'value' => $current_user->user_email
							)
					)
			) );
?>
		<h3><?php _e( 'Assigned to me', 'bim-bcf-management' ); ?></h3>
<?php
			if( count( $assignedIssues ) > 0 ) {
				$index = 0;
?>
		<table class="issue-table">
			<tr>
				<th>&nbsp;</th>
				<th><?php _e( 'Issue', 'bim-bcf-management' ); ?></th>
				<th><?php _e( 'Date', 'bim-bcf-management' ); ?></th>
				<th><?php _e( 'Bimsie server', 'bim-bcf-management' ); ?></th>
				<th><?php _e( 'Project/Revision', 'bim-bcf-management' ); ?></th>
			</tr>
<?php
				foreach( $assignedIssues as $issue ) {
					$shownIssues[] = $issue->ID;
					$bimsieServer = get_post_meta( $issue->ID, '_bimsie_uri', true );
					$poids = get_post_meta( $issue->ID, 'poid' );
					$roids = get_post_meta( $issue->ID, 'roid' );
					$projects = Array();
					foreach( $poids as $key => $poid ) {
						$project = BIMsie::getProject( $bimsieServer, $poid );
						$roid = isset( $roids[$key] ) ? $roids[$key] : '';
						if( $project && $roid != '' ) {
							$revision = BIMsie::getRevision( $bimsieServer, $poid, $roid );
							$projects[] = Array( $project->name, $revision? $revision->name : $roid );
						} elseif( $project ) {
							$projects[] = Array( $project->name, '' );
						} else {
							$projects[] = Array( $poid, $roid );
						}
					}					
					$timestamp = strtotime( $issue->post_date );
?>
			<tr class="<?php print( $index % 2 == 0 ? 'even' : 'odd' ); ?>">
				<td><?php print( get_the_post_thumbnail( $issue->ID, 'issue-list-thumb' ) ); ?></td>
				<td><a href="<?php print( get_bloginfo( 'wpurl' ) . $options[ 'issue_details_uri' ] . '?id=' . $issue->ID );  ?>"><?php print( $issue->post_title ); ?></a></<td>
				<td><?php print( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ); ?></<td>
				<td><?php print( $bimsieServer == '' ? '-' : $bimsieServer ); ?></<td>
				<td>
<?php 
					foreach( $projects as $project ) {
						print( $project[0] . ': ' . $project[1] . '<br />' );
					}
?>
				</<td>
			</tr>
<?php
					$index ++;
				}
?>
		</table>
<?php
			} else {
?>
		<p><?php _e( 'No issues assigned to you', 'bim-bcf-management' ); ?></p>
<?php
			}						
			$myUnfilteredIssues = get_posts( Array(
					'posts_per_page' => -1,
					'post_type' => $options[ 'bcf_issue_post_type' ],
					'post_status' => 'publish',
					'author' => get_current_user_id(),
					'orderby' => 'date',
					'order' => 'DESC',
					'meta_query' => Array(
							Array(
									'key' => 'import_status',
									'value' => 'complete'
							)
					)
			) );
			// TODO: maybe better to show double issues here?
			$myIssues = Array();
			foreach( $myUnfilteredIssues as $issue ) {
				if( !in_array( $issue->ID, $shownIssues ) ) {
					$myIssues[] = $issue;
				}
			}
			
?>
		<h3><?php _e( 'Added by me', 'bim-bcf-management' ); ?></h3>
<?php
			if( count( $myIssues ) > 0 ) {
				$index = 0;
?>
			<table class="issue-table">
				<tr>
					<th>&nbsp;</th>
					<th><?php _e( 'Issue', 'bim-bcf-management' ); ?></th>
					<th><?php _e( 'Date', 'bim-bcf-management' ); ?></th>
					<th><?php _e( 'Bimsie server', 'bim-bcf-management' ); ?></th>
					<th><?php _e( 'Project/Revision', 'bim-bcf-management' ); ?></th>
				</tr>
<?php
				foreach( $myIssues as $issue ) {
					$bimsieServer = get_post_meta( $issue->ID, '_bimsie_uri', true );
					$poids = get_post_meta( $issue->ID, 'poid' );
					$roids = get_post_meta( $issue->ID, 'roid' );
					$projects = Array();
					foreach( $poids as $key => $poid ) {
						$project = BIMsie::getProject( $bimsieServer, $poid );
						$roid = isset( $roids[$key] ) ? $roids[$key] : '';
						if( $project && $roid != '' ) {
							$revision = BIMsie::getRevision( $bimsieServer, $poid, $roid );
							$projects[] = Array( $project->name, $revision? $revision->name : $roid );
						} elseif( $project ) {
							$projects[] = Array( $project->name, '' );
						} else {
							$projects[] = Array( $poid, $roid );
						}
					}					
					$timestamp = strtotime( $issue->post_date );
?>
				<tr class="<?php print( $index % 2 == 0 ? 'even' : 'odd' ); ?>">
					<td><?php print( get_the_post_thumbnail( $issue->ID, 'issue-list-thumb' ) ); ?></td>
					<td><a href="<?php print( get_bloginfo( 'wpurl' ) . $options[ 'issue_details_uri' ] . '?id=' . $issue->ID );  ?>"><?php print( $issue->post_title ); ?></a></<td>
					<td><?php print( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ); ?></<td>
					<td><?php print( $bimsieServer == '' ? '-' : $bimsieServer ); ?></<td>
					<td>
<?php 
					foreach( $projects as $project ) {
						print( $project[0] . ': ' . $project[1] . '<br />' );
					}
?>
					</<td>
				</tr>
<?php
					$index ++;
				}
?>
			</table>
<?php
			} else {
?>
			<p><?php _e( 'No BCF issues imported yet', 'bim-bcf-management' ); ?></p>
<?php
			}
		} else {
?>
		<p><?php _e( 'Please log in to access this page', 'bim-bcf-management' ); ?></p>
<?php
		}
	}

	public static function showIssue() {
		if( is_user_logged_in() ) {
			global $post, $current_user;
			get_currentuserinfo();
			$issueId = ( isset( $_GET[ 'id' ] ) && ctype_digit( $_GET[ 'id' ] ) ) ? $_GET[ 'id' ] : -1;
			if( $issueId == -1 && isset( $post ) && isset( $post->ID ) ) {
				// if no id is supplied we assume the current post is the issue we want to display
				$issueId = $post->ID;
			}
			if( $issueId != -1 ) {
				$options = BIMBCFManagement::getOptions();
				$topicStatuses = explode( ',', $options[ 'topic_statuses' ] );
				$topicStatusesOptions = '';
				foreach( $topicStatuses as $topicStatus ) {
					$topicStatusesOptions .= '<option value="' . $topicStatus . '">' . $topicStatus . '</option>';
				}
				$priorities = explode( ',', $options[ 'priorities' ] );
				$prioritiesOptions = '';
				foreach( $priorities as $priority ) {
					$prioritiesOptions .= '<option value="' . $priority . '">' . $priority . '</option>';
				}
				$extraFieldsHtml = '<p class="comment-extra-field"><label for="Status">' . __( 'Status', 'bim-bcf-management' ) . '</label><input type="text" id="Status" name="Status" /></p>';
				$extraFieldsHtml .= '<p class="comment-extra-field"><label for="VerbalStatus">' . __( 'VerbalStatus', 'bim-bcf-management' ) . '</label><select id="VerbalStatus" name="VerbalStatus">' . $topicStatusesOptions . '</select></p>';
				$extraFieldsHtml .= '<p class="comment-extra-field"><label for="Priority">' . __( 'Priority', 'bim-bcf-management' ) . '</label><select id="Priority" name="Priority">' . $prioritiesOptions . '</select></p>';
				$currentUserId = get_current_user_id();
				$issue = get_post( $issueId );
				$assignedTo = get_post_meta( $issue->ID, 'assigned_to', true );
				$authorized = false;
				// check if user has access to this post
				if( $issue->post_author == $currentUserId || $current_user->user_email == $assignedTo ) {
					$authorized = true;
				}
				$uri = get_post_meta( $issue->ID, '_bimsie_uri', true );
				$poids = get_post_meta( $issue->ID, 'poid' );
				// We have not published this issue and it is not assigned to us
				// Check if we have access to at least one of its project(s)
				if( !$authorized ) {
					foreach( $poids as $poid ) {
						$project = BIMsie::getProject( $uri, $poid );
						if( $project ) {
							$authorized = true;
							break;
						}
					}
				}
				if( $issue->post_type == $options[ 'bcf_issue_post_type' ] && $authorized ) {
					$roids = get_post_meta( $issue->ID, 'roid' );
					$guid = get_post_meta( $issue->ID, 'guid', true );
					$markup = get_post_meta( $issue->ID, 'markup', true );
					$projects = Array();
					foreach( $poids as $key => $poid ) {
						$project = BIMsie::getProject( $uri, $poid );
						$roid = isset( $roids[$key] ) ? $roids[$key] : '';
						if( $project && $roid != '' ) {
							$revision = BIMsie::getRevision( $uri, $poid, $roid );
							$projects[] = Array( $project->name, $revision? $revision->name : $roid );
						} elseif( $project ) {
							$projects[] = Array( $project->name, $roid );
						} else {
							$projects[] = Array( $poid, $roid );
						}
					}
					$timestamp = strtotime( $issue->post_date );
?>
			<div class="issue-image"><?php print( get_the_post_thumbnail( $issueId, 'issue-detail-thumb' ) ); ?></div>
			<h3><?php print( $issue->post_title ); ?></h3>
			<p class="the-comment"><?php print( get_post_meta( $issue->ID, 'comment', true ) ); ?></p>
			<table class="issue-table">
				<tr>
					<td><?php _e( 'Date', 'bim-bcf-management' ); ?></td>
					<td><?php print( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ); ?></td>
				</tr>
				<tr>
					<td><?php _e( 'Guid', 'bim-bcf-management' ); ?></td>
					<td><?php print( $guid ); ?></td>
				</tr>				
				<tr>
					<td><?php _e( 'Assigned to', 'bim-bcf-management' ); ?></td>
					<td><?php print( $assignedTo ); ?></td>
				</tr>
<?php 
					if( isset( $markup[ 'Topic' ] ) ) {
?>
				<tr>
					<td><?php _e( 'Label', 'bim-bcf-management' ); ?></td>
					<td><?php print( isset( $markup[ 'Topic' ][ 'Label' ] ) ? $markup[ 'Topic' ][ 'Label' ] : '' ); ?></td>
				</tr>
				<!--tr>
					<td><?php _e( 'Creation Date', 'bim-bcf-management' ); ?></td>
					<td><?php print( isset( $markup[ 'Topic' ][ 'CreationDate' ] ) ? $markup[ 'Topic' ][ 'CreationDate' ] : '' ); ?></td>
				</tr>
				<tr>
					<td><?php _e( 'Modified Date', 'bim-bcf-management' ); ?></td>
					<td><?php print( isset( $markup[ 'Topic' ][ 'ModifiedDate' ] ) ? $markup[ 'Topic' ][ 'ModifiedDate' ] : '' ); ?></td>
				</tr-->
<?php
						if( isset( $markup[ 'Topic' ][ '@attributes' ] ) ) {
?>
				<tr>
					<td><?php _e( 'Topic Type', 'bim-bcf-management' ); ?></td>
					<td><?php print( isset( $markup[ 'Topic' ][ '@attributes' ][ 'TopicType' ] ) ? $markup[ 'Topic' ][ '@attributes' ][ 'TopicType' ] : '' ); ?></td>
				</tr>
				<tr>
					<td><?php _e( 'Topic Status', 'bim-bcf-management' ); ?></td>
					<td><?php print( isset( $markup[ 'Topic' ][ '@attributes' ][ 'TopicStatus' ] ) ? $markup[ 'Topic' ][ '@attributes' ][ 'TopicStatus' ] : '' ); ?></td>
				</tr>
<?php							
						}
					}
?>
			</table>
			<table class="issue-table">
				<tr>
					<th><?php _e( 'Project', 'bim-bcf-management' ); ?></th>
					<th><?php _e( 'Revision', 'bim-bcf-management' ); ?></th>
				</tr>
<?php 
					$count = 0;
					foreach( $projects as $project ) {
?>
				<tr class="<?php print( $count % 2 == 0 ? 'even' : 'odd' ); ?>">
					<td><?php print( $project[0] ); ?></td>
					<td><?php print( $project[1] ); ?></td>
				</tr>
<?php
						$count ++;
					}
?>				
			</table>
			<a name="comments"></a>
<?php
					$comments = get_comments( Array(
							'post_id' => $issue->ID
					) );
?>
			<ol class="comment-list">
<?php
					wp_list_comments( Array(), $comments );
?>
			</ol>
			<script type="text/javascript">
				jQuery( document ).ready( function() {
					jQuery( "#comments" ).remove();
					jQuery( "#commentform .form-submit" ).append( "<input type=\"hidden\" name=\"redirect_to\" value=\"<?php print( get_bloginfo( 'wpurl' ) . $options[ 'issue_details_uri' ] . '?id=' . $issue->ID ); ?>\" />" );
					jQuery( ".comment-list .reply" ).remove();
				} );
			</script>
<?php
					comment_form( Array( 'comment_notes_after' => $extraFieldsHtml ), $issue->ID );

				} else {
?>
			<p><?php _e( 'Issue not accessible', 'bim-bcf-management' ); ?></p>
<?php
				}
			} else {
?>
			<p><?php _e( 'No issue selected', 'bim-bcf-management' ); ?></p>
<?php
			}
		} else {
?>
		<p><?php _e( 'Please log in to access this page', 'bim-bcf-management' ); ?></p>
<?php
		}
	}

	public static function showAddZipForm() {
		if( is_user_logged_in() ) {
			if( isset( $_FILES[ 'bcf_zip_file' ] ) ) {
				if( isset( $_FILES[ 'bcf_zip_file' ][ 'error' ] ) && $_FILES[ 'bcf_zip_file' ][ 'error' ] != 0 ) {
					$errorMessage = __( 'Could not upload the file, contact a system administrator.', 'bim-bcf-management' ) . ' Error code: ' . $_FILES[ 'bcf_zip_file' ][ 'error' ];
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
									if( !BIMBCFManagement::addIssueFromZip( $files ) ) {
										$filesError = __( 'Error at import', 'bim-bcf-management' ) . ' (guid: ' . $guid . ', files: ';
										$firstFile = true;
										foreach( $files as $file ) {
											if( !$firstFile ) {
												$filesError .= ', ';
											} else {
												$firstFile = false;
											}
											$filesError .= $file[1];
										}
										$errorMessage .= $filesError;
									}
									$files = Array();
								}
								$guids[] = $guid;
							}
							$files[] = Array( $guid, $filename, $xml );
						}
						if( count( $files ) > 0 ) {
							$errorMessage .= BIMBCFManagement::addIssueFromZip( $files );
						}
						zip_close( $zip );
					} else {
						$errorMessage = __( 'Could not open the zip archive.', 'bim-bcf-management' ) . ' Error code: ' . $zip;
					}
				}

				if( isset( $errorMessage ) && $errorMessage != '' ) {
?>
				<p class="form-error-message"><?php print( $errorMessage ); ?></p>
<?php
				}
			}

			$options = BIMBCFManagement::getOptions();
			$unsetIssues = get_posts( Array(
					'post_type' => $options[ 'bcf_issue_post_type' ],
					'posts_per_page' => -1,
					'author' => get_current_user_id(),
					'meta_query' => Array(
							Array(
									'key' => 'import_status',
									'value' => 'pending'
							)
					)
			) );

			if( count( $unsetIssues ) > 0 ) {
				$projectIds = Array();
				foreach( $unsetIssues as $unsetIssue ) {
					$markups = get_post_meta( $unsetIssue->ID, 'markup' );
					foreach( $markups as $markup ) {
						if( isset( $markup[ 'Header' ][ 'File' ] ) ) {
							foreach( $markup[ 'Header' ][ 'File' ] as $file ) {
								if( isset( $file[ 'Filename' ] ) && $file[ 'Filename' ] != '' ) {
									if( !in_array( $file[ 'Filename' ], $projectIds ) ) {
										$projectIds[] = $file[ 'Filename' ];
									}
								}
							}
						}
					}
				}
				sort( $projectIds );
?>
				<h3><?php _e( 'Some issues are not linked to revisions and/or projects', 'bim-bcf-management' ); ?></h3>
				<table class="issue-table" id="update-issue-revision-table">
					<tr>
						<th>&nbsp;</th>
						<th><?php _e( 'Issue', 'bim-bcf-management' ); ?></th>
						<th><?php _e( 'Files', 'bim-bcf-management' ); ?></th>
						<th><?php _e( 'Date', 'bim-bcf-management' ); ?></th>
						<th><?php _e( 'Author', 'bim-bcf-management' ); ?></th>
						<th><?php _e( 'Projects', 'bim-bcf-management' ); ?></th>
						<th><?php _e( 'Revision', 'bim-bcf-management' ); ?></th>
					</tr>
<?php
				$index = 0;
				foreach( $unsetIssues as $unsetIssue ) {
					$author = get_post_meta( $unsetIssue->ID, 'Author', true );
					$timestamp = strtotime( $unsetIssue->post_date );
					$markups = get_post_meta( $unsetIssue->ID, 'markup' );
					$files = 0;
					foreach( $markups as $markup ) {
						if( isset( $markup[ 'Header' ][ 'File' ] ) && is_array( $markup[ 'Header' ][ 'File' ] ) ) {
							$files += count( $markup[ 'Header' ][ 'File' ] );
						}
					}
?>
					<tr class="issue-pending <?php print( $index % 2 == 0 ? 'even' : 'odd' ); ?>" id="issue-<?php print( $unsetIssue->ID ); ?>">
						<td><?php print( get_the_post_thumbnail( $unsetIssue->ID, 'issue-list-thumb' ) ); ?></td>
						<td><a href="<?php print( get_bloginfo( 'wpurl' ) . $options[ 'issue_details_uri' ] . '?id=' . $unsetIssue->ID );  ?>" target="_blank"><?php print( $unsetIssue->post_title ); ?></a></td>
						<td class="numeric"><?php print( $files ); ?></td>
						<td><?php print( date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ); ?></td>
						<td><?php print( $author == '' ? '-' : $author ); ?></td>
						<td class="project"></td>
						<td class="revision"></td>
					</tr>
<?php
					$index ++;
				}
				$bimsieServers = BIMsie::getServers();
?>
				</table>
				<script type="text/javascript">
					var bimBCFManagementSettings = {
						ajaxURI: "<?php print( plugins_url( 'ajax-handler.php' , __FILE__ ) ); ?>",
						loadingImage: "<img class=\"loading-image\" src=\"<?php bloginfo( 'wpurl' ); ?>/wp-admin/images/loading.gif\" alt=\"loading...\" />",
						bimsieServers: <?php print( json_encode( $bimsieServers ) ); ?>,
						ifcProjects: <?php print( json_encode( $projectIds ) ); ?>,
						text: {
							selectServerTitle: "<?php _e( 'Select a BIMsie server or enter a new one', 'bim-bcf-management' ); ?>",
							selectProjectTitle: "<?php _e( 'Select the project for each file', 'bim-bcf-management' ); ?>",
							newServerLabel: "<?php _e( 'Add BIMSie server URI', 'bim-bcf-management' ); ?>",
							submitServer: "<?php _e( 'Retrieve information', 'bim-bcf-management' ); ?>",
							selectServerLabel: "<?php _e( 'Select BIMSie server', 'bim-bcf-management' ); ?>",
							noServerOption: "--- <?php _e( 'New server', 'bim-bcf-management' ); ?> ---",
							rememberServerLabel: "<?php _e( 'Remember user', 'bim-bcf-management' ); ?>",
							serverUserLabel: "<?php _e( 'Username', 'bim-bcf-management' ); ?>",
							serverPasswordLabel: "<?php _e( 'Password', 'bim-bcf-management' ); ?>",
							serverSubmitError: "<?php _e( 'Supply a BIMSie server URI, username and password or select one from your list.', 'bim-bcf-management' ); ?>",
							noProjectsFoundMessage: "<?php _e( 'No projects could be found on this BIMSie server for this user.', 'bim-bcf-management' ); ?>",
							revision: "<?php _e( 'Revision', 'bim-bcf-management' ); ?>"
						}
					};
				</script>
<?php
			} else {
?>
			<form method="post" action="" enctype="multipart/form-data">
				<label for="bcf-zip-file"><?php _e( 'Select a BCF zip archive', 'bim-bcf-management' ); ?></label><br />
				<input type="file" id="bcf-zip-file" name="bcf_zip_file" /><br />
				<br />
				<input type="submit" value="<?php _e( 'Add', 'bim-bcf-management' ); ?>" />
			</form>
<?php
			}
		} else {
?>
		<p><?php _e( 'Please log in to access this page', 'bim-bcf-management' ); ?></p>
<?php
		}
	}


	private static function addIssueFromZip( $files ) {
		$guid = '';
		$markup = Array();
		$project = false;
		$visualizationInfo = Array();
		$snapshots = Array();

		foreach( $files as $file ) {
			if( $guid == '' ) {
				// Remember the guid, is the same for all files
				$guid = $file[0];
			}

			if( $file[1] == 'markup.bcf' ) {
				// extract the XML from the markup
				$xml = simplexml_load_string( $file[2] );
				$markup = BIMBCFManagement::convertSimpleXML2Array( $xml );
			} elseif( substr( $file[1], -5 ) == '.bcfv' ) {
				// extract the XML from viewpoint
				$xml = simplexml_load_string( $file[2] );
				$visualizationInfo[] = BIMBCFManagement::convertSimpleXML2Array( $xml );
			} elseif( $file[1] == 'project.bcfp' ) {
				// extract the XML from the project file
				$xml = simplexml_load_string( $file[2] );
				$project = BIMBCFManagement::convertSimpleXML2Array( $xml );
			} else {
				// This should be a screenshot file
				// store this to be added after we created the issue post
				$snapshots[] = Array( $file[1], $file[2] );
			}
		}
		// Create a post with information from the XML issue
		$options = BIMBCFManagement::getOptions();
		$currentUserId = get_current_user_id();

		$postData = Array(
				'post_title' => wp_strip_all_tags( $guid ),
				'post_content' => '',
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'post_status' => 'publish',
				'post_author' => $currentUserId
			);
		if( isset( $markup[ 'Comment' ] ) && isset( $markup[ 'Comment' ][ 'Date' ] ) ) {
			$date = strtotime( $markup[ 'Comment' ][ 'Date' ] );
			$postData[ 'post_date' ] = date( 'Y-m-d H:i:s', $date );
		}
		$postId = wp_insert_post( $postData );
		if( $postId > 0 ) {
			// Set post meta so we know this issue has yet to be attached to a project/revision
			add_post_meta( $postId, 'import_status', 'pending', true );

			// Add snapshots as attachments (first one is the thumbnail!)
			$snapshotIds = Array();
			$first = true;
			foreach( $snapshots as $snapshot ) {
				$id = BIMBCFManagement::writeSnapshot( $postId, $snapshot, $first );
				if( $id !== false ) {
					$snapshotIds[] = Array( 'id' => $id, 'file' => $snapshot[0] );
				}
				if( $first ) {
					$first = false;
				}
			}

			// TODO: some data processing to set things right!
			if( isset( $markup[ 'Viewpoints' ] ) && is_array( $markup[ 'Viewpoints' ] ) ) {
				foreach( $markup[ 'Viewpoints' ] as &$viewpoint ) {
					foreach( $snapshotIds as $snapshot ) {
						if( $snapshot[ 'file'] == $viewpoint[ 'Snapshot' ] ) { // TODO: might need some more validation, not sure how Snapshot string will be filled
							$viewpoint[ 'Snapshot' ] = wp_get_attachment_url( $snapshot[ 'id' ] );
							break 1;
						}
					}
				}
			}

			// Store XML stuff in post meta
			add_post_meta( $postId, 'markup', $markup, true );
			
			if( isset( $markup[ 'Topic' ] ) && isset( $markup[ 'Topic' ][ 'AssignedTo' ] ) ) {
				add_post_meta( $postId, 'assigned_to', $markup[ 'Topic' ][ 'AssignedTo' ] );
			}

			// TODO: this only works for BCF 1.0 
			if( isset( $markup[ 'Comment' ] ) ) {
				if( isset( $markup[ 'Comment' ][ 'VerbalStatus' ] ) ) {
					add_post_meta( $postId, 'VerbalStatus', $markup[ 'Comment' ][ 'VerbalStatus' ], true );
				}
				if( isset( $markup[ 'Comment' ][ 'Status' ] ) ) {
					add_post_meta( $postId, 'Status', $markup[ 'Comment' ][ 'Status' ], true );
				}
				if( isset( $markup[ 'Comment' ][ 'Author' ] ) ) {
					add_post_meta( $postId, 'Author', $markup[ 'Comment' ][ 'Author' ], true );
				}
				if( isset( $markup[ 'Comment' ][ 'Comment' ] ) ) {
					add_post_meta( $postId, 'Comment', $markup[ 'Comment' ][ 'Comment' ], true );
				}
			}
			add_post_meta( $postId, 'visualizationinfo', $visualizationInfo );

			// TODO: Could set some more values to filter on for this issue

			// If a project is set, add it as post meta
			if( $project !== false ) {
				add_post_meta( $postId, 'projectextension', $project );
			}
			return true;
		} else {
			return false;
		}
	}

	// Import issue from json data and set all special fields
	public static function addIssue( $jsonIssue, $userId = -1 ) {
		$options = BIMBCFManagement::getOptions();
		$userId = $userId == -1 ? get_current_user_id() : $userId;

		if( isset( $jsonIssue ) && isset( $jsonIssue[ 'markup' ] ) && isset( $jsonIssue[ 'visualizationinfo' ] ) && isset( $jsonIssue[ 'markup' ][ 'Topic' ] ) ) {
			// TODO: place information in special fields for faster access
			if( !isset( $jsonIssue[ 'markup' ][ 'Topic' ][ '@attributes' ] ) || !isset( $jsonIssue[ 'markup' ][ 'Topic' ][ '@attributes' ][ 'Guid' ] ) ) {
				$guid = BIMBCFManagement::getRandomGuid();
				$jsonIssue[ 'markup' ][ 'Topic' ][ '@attributes' ][ 'Guid' ] = $guid;
			}
			if( !isset( $jsonIssue[ 'markup' ][ 'Topic' ][ 'CreationDate' ] ) || $jsonIssue[ 'markup' ][ 'Topic' ][ 'CreationDate' ] == '' ) {
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'CreationDate' ] = date( 'Y-m-d\TH:i:sP' );
			}
			if( !isset( $jsonIssue[ 'markup' ][ 'Topic' ][ 'ModifiedDate' ] ) || $jsonIssue[ 'markup' ][ 'Topic' ][ 'ModifiedDate' ] == '' ) {
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'ModifiedDate' ] = date( 'Y-m-d\TH:i:sP' );
			}
			$postData = Array(
				'post_title' => isset( $jsonIssue[ 'markup' ][ 'Topic' ][ 'Title' ] ) ? $jsonIssue[ 'markup' ][ 'Topic' ][ 'Title' ] : $jsonIssue[ 'markup' ][ 'Topic' ][ '@attributes' ][ 'Guid' ],
				'post_content' => '',
				'post_status' => 'publish',
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'post_author' => $userId
			);
			$postId = wp_insert_post( $postData );
			if( $postId > 0 ) {
				add_post_meta( $postId, 'guid', $jsonIssue[ 'markup' ][ 'Topic' ][ '@attributes' ][ 'Guid' ] );
				add_post_meta( $postId, 'import_status', 'complete', true );
				add_post_meta( $postId, 'markup', $jsonIssue[ 'markup' ], false );
				add_post_meta( $postId, 'visualizationinfo', $jsonIssue[ 'visualizationinfo' ], false );
				if( isset( $jsonIssue[ 'markup' ][ 'Topic' ][ 'AssignedTo' ] ) ) {
					add_post_meta( $postId, 'assigned_to', $jsonIssue[ 'markup' ][ 'Topic' ][ 'AssignedTo' ] );
				}
				if( isset( $jsonIssue[ 'markup' ][ 'Header' ] ) && isset( $jsonIssue[ 'markup' ][ 'Header' ][ 'File' ] ) && is_array( $jsonIssue[ 'markup' ][ 'Header' ][ 'File' ] ) ) {
					foreach( $jsonIssue[ 'markup' ][ 'Header' ][ 'File' ] as $key => $file ) {
						if( isset( $file[ 'project' ] ) ) {
							add_post_meta( $postId, 'poid', $file[ 'project' ] );
						}
						if( isset( $file[ 'revision' ] ) ) {
							add_post_meta( $postId, 'roid', $file[ 'revision' ] );
						}
						if( !isset( $file[ 'Date' ] ) || $file[ 'Date' ] == '' ) {
							// If no file date is set, we fill in the current date
							$jsonIssue[ 'markup' ][ 'Header' ][ 'File' ][$key] = date( 'Y-m-d\TH:i:sP' );
						}
					}
				}
				if( isset( $jsonIssue[ 'projectextension' ] ) ) {
					add_post_meta( $postId, 'projectextension', $jsonIssue[ 'projectextension' ], false );
				}
				return $postId;
			} else {
				return false;
			}
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
				return $attachId;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	public static function convertSimpleXML2Array( $simpleXMLObject ) {
		// We expect there to not be any elements with the name attributes
		$xmlArray = Array();
		$attributes = Array();
		foreach( $simpleXMLObject->attributes() as $attributeName => $attributeValue ) {
			$attributes[$attributeName] = '' . $attributeValue;
		}
		if( count( $attributes ) > 0 ) {
			$xmlArray[ '@attributes' ] = $attributes;
		}
		$simpleXMLObject = ( Array ) $simpleXMLObject;
		foreach( $simpleXMLObject as $key => $value ) {
			if( $key != '@attributes' ) {
				if( is_string( $value ) ) {
					$xmlArray[$key] = $value;
				} elseif( is_array( $value ) ) {
					$values = Array();
					foreach( $value as $subKey => $subValue ) {
						$values[] = BIMBCFManagement::convertSimpleXML2Array( $subValue );
					}
					$xmlArray[$key] = $values;
				} elseif( get_class( $value ) == 'SimpleXMLElement' ) {
					$xmlArray[$key] = BIMBCFManagement::convertSimpleXML2Array( $value );
				} else {
					// Could happen if a boolean or integer is found, but not sure if SimpleXML casts those to none string type
					//print( "Unexpected value in XML object: $key => $value<br />\n" );
					$xmlArray[$key] = $value;
				}
			}
		}
		return $xmlArray;
	}

	public static function getUserIdTypes() {
		global $wpdb;
		$users = $wpdb->get_results( "SELECT user_email
			FROM {$wpdb->users}
			ORDER BY user_email ASC" );
		$emails = Array();
		foreach( $users as $user ) {
			$emails[] = $user->user_email;
		}
		return $emails;
	}

	public static function showAddIssueForm() {
		global $wpdb;
		if( is_user_logged_in() ) {
			$options = BIMBCFManagement::getOptions();
			$bimsieServers = BIMsie::getServers();
			$topicStatuses = explode( ',', $options[ 'topic_statuses' ] );
			$topicTypes = explode( ',', $options[ 'topic_types' ] );
			$topicLabels = explode( ',', $options[ 'topic_labels' ] );
			$snippetTypes = explode( ',', $options[ 'snippet_types' ] );
			$priorities = explode( ',', $options[ 'priorities' ] );
			$userIdTypes = BIMBCFManagement::getUserIdTypes();
			if( isset( $_POST[ 'submit' ] ) ) {
				$jsonIssue = Array(
						'markup' => Array(
								'Header' => Array( 'File' => Array() ),
								'Topic' => Array(),
								'Comment' => Array(),
								'Viewpoints' => Array()
						),
						'visualizationinfo' => Array(
								'Components' => Array(),
								'OrthogonalCamera' => Array(),
								'PerspectiveCamera' => Array(),
								'Lines' => Array(),
								'ClippingPlanes' => Array(),
								'Bitmap' => Array()
						)
				);
				$server = isset( $_POST[ 'bimsie_server_uri' ] ) ? $_POST[ 'bimsie_server_uri' ] : '';
				if( is_numeric( $server ) ) {
					if( isset( $bimsieServers[$server] ) ) {
						$server = $bimsieServers[$server][ 'uri' ];
					} else {
						$server = '';
					}
				}				
				if( is_array( $_POST[ 'file_project' ] ) ) {
					foreach( $_POST[ 'file_project' ] as $key => $project ) {
						if( $project != '' ) {
							$jsonIssue[ 'markup' ][ 'Header' ][ 'File' ][] = Array(
									'bimsie_url' => $server,
									'project' => $project,
									'revision' => isset( $_POST[ 'file_revision' ][$key] ) ? $_POST[ 'file_revision' ][$key] : '',
									'Filename' => isset( $_POST[ 'file_reference' ][$key] ) ? $_POST[ 'file_reference' ][$key] : '',
									'Date' => isset( $_POST[ 'file_date' ][$key] ) ? $_POST[ 'file_date' ][$key] : date( 'Y-m-d\TH:i:sP' ),
									'Reference' => isset( $_POST[ 'file_reference' ][$key] ) ? $_POST[ 'file_reference' ][$key] : '',
									'@attributes' => Array(
											'isExternal' => true,
											'IfcProject' => isset( $_POST[ 'file_ifcproject' ][$key] ) ? $_POST[ 'file_ifcproject' ][$key] : '',
											'IfcSpatialStructureElement' => isset( $_POST[ 'file_spatial' ][$key] ) ? $_POST[ 'file_spatial' ][$key] : '',
									)
							);
						}
					}
				}
				$jsonIssue[ 'markup' ][ 'Topic' ][ '@attributes' ] = Array( 'Guid' => $_POST[ 'topic_guid' ], 'TopicType' => $_POST[ 'topic_type' ], 'TopicStatus' => $_POST[ 'topic_status' ] );
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'ReferenceLink' ] = $_POST[ 'topic_referencelink' ];
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'Title' ] = $_POST[ 'topic_title' ];
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'Index' ] = $_POST[ 'topic_index' ];
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'Label' ] = $_POST[ 'topic_label' ];
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'CreationDate' ] = date( 'Y-m-d\TH:i:sP' );
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'ModifiedDate' ] = date( 'Y-m-d\TH:i:sP' );
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'AssignedTo' ] = $_POST[ 'assigned_to' ];
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'BimSnippet' ][ 'Reference' ] = $_POST[ 'bim_snippet_reference' ];
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'BimSnippet' ][ 'ReferenceSchema' ] = $_POST[ 'bim_snippet_reference_schema' ];
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'BimSnippet' ][ '@atributes' ] = Array( 'SnippetType' => $_POST[ 'bim_snippet_type' ], 'isExternal' => ( isset( $_POST[ 'bim_snippet_isexternal' ] ) && $_POST[ 'bim_snippet_isexternal' ] == 'true' ) ? true : false );
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'DocumentReference' ] = Array();
				if( is_array( $_POST[ 'referenced_document' ] ) ) {
					foreach( $_POST[ 'referenced_document' ] as $key => $referencedDocument ) {
						if( $referencedDocument != '' ) {
							$jsonIssue[ 'markup' ][ 'Topic' ][ 'DocumentReference' ][] = Array(
									'ReferencedDocument' => $referencedDocument,
									'Description' => isset( $_POST[ 'document_reference_description' ][$key] ) ? $_POST[ 'document_reference_description' ][$key] : '',
									'@attributes' => Array(
											'isExternal' => ( isset( $_POST[ 'document_reference_isexternal' ][$key] ) && $_POST[ 'document_reference_isexternal' ][$key] == 'true' ) ? true : false,
											'Guid' => isset( $_POST[ 'document_reference_guid' ][$key] ) ? $_POST[ 'document_reference_guid' ][$key] : ''
									)
							);
						}
					}
				}
				$jsonIssue[ 'markup' ][ 'Topic' ][ 'RelatedTopics' ] = Array();
				if( is_array( $_POST[ 'related_topic' ] ) ) {
					foreach( $_POST[ 'related_topic' ] as $key => $relatedTopic ) {
						if( $relatedTopic != '' ) {
							$jsonIssue[ 'markup' ][ 'Topic' ][ 'RelatedTopics' ][] = Array(
									'@attributes' => Array(
											'Guid' => $relatedTopic,
									)
							);
						}
					}
				}

				$uploadInfo = wp_upload_dir();
				$attachments = Array();

				if( is_array( $_POST[ 'viewpoint' ] ) ) {
					foreach( $_POST[ 'viewpoint' ] as $key => $viewpoint ) {
						if( $viewpoint != '' ) {
							$snapshot = '';
							if( isset( $_FILES[ 'snapshot' ] ) && is_array( $_FILES[ 'snapshot' ][ 'tmp_name' ] ) && isset( $_FILES[ 'snapshot' ][ 'tmp_name' ][$key] ) && $_FILES[ 'snapshot' ][ 'tmp_name' ][$key] != '' ) {
								if( !isset( $_FILES[ 'snapshot' ][ 'error' ][$key] ) || $_FILES[ 'snapshot' ][ 'error' ][$key] == 0 ) {
									$index = 1;
									$basename = substr( $_FILES[ 'snapshot' ][ 'name' ][$key], 0, strrpos( $_FILES[ 'snapshot' ][ 'name' ][$key], '.' ) );
									$extension = substr( $_FILES[ 'snapshot' ][ 'name' ][$key], strrpos( $_FILES[ 'snapshot' ][ 'name' ][$key], '.' ) + 1 );
									$filename = $_FILES[ 'snapshot' ][ 'name' ][$key];
									// Find the first available filename
									while ( file_exists( $uploadInfo[ 'path' ] . '/' . $filename ) ) {
										$index ++;
										$filename = $basename . '-' . $index . '.' . $extension;
									}
									if( move_uploaded_file( $_FILES[ 'snapshot' ][ 'tmp_name' ][$key], $uploadInfo[ 'path' ] . '/' . $filename ) !== false ) {
										// Add the snapshot as an attachment to the project
										$wpFiletype = wp_check_filetype( basename( $filename ), null );
										$attachment = Array(
												'guid' => $uploadInfo[ 'url' ] . '/' . basename( $filename ),
												'post_mime_type' => $wpFiletype[ 'type' ],
												'post_title' => preg_replace('/\.[^.]+$/', '', basename( $filename ) ),
												'post_content' => '',
												'post_status' => 'inherit'
										);
										$attachId = wp_insert_attachment( $attachment, $uploadInfo[ 'path' ] . '/' . $filename );
										// you must first include the image.php file
										// for the function wp_generate_attachment_metadata() to work
										require_once( ABSPATH . 'wp-admin/includes/image.php' );
										$attachData = wp_generate_attachment_metadata( $attachId, $uploadInfo[ 'path' ] . '/' . $filename );
										wp_update_attachment_metadata( $attachId, $attachData );
										$attachments[] = $attachId;
										$snapshot = wp_get_attachment_url( $attachId );
									}
								}
							}

							$viewpointObject = Array(
									'Viewpoint' => $viewpoint,
									'Snapshot' => $snapshot,
									'Comments' => Array(),
									'@attributes' => Array(
											'Guid' => isset( $_POST[ 'viewpoint_guid' ][$key] ) ? $_POST[ 'viewpoint_guid' ][$key] : ''
									)
							);
							if( is_array( $_POST[ 'viewpoint_comment' ] ) && is_array( $_POST[ 'viewpoint_comment' ][$key] ) ) {
								foreach( $_POST[ 'viewpoint_comment' ][$key] as $viewpointComment ) {
									if( $viewpointComment != '' ) {
										$viewpointObject[ 'Comments' ][] = $viewpointComment;
									}
								}
							}
							$jsonIssue[ 'markup' ][ 'Viewpoints' ][] = $viewpointObject;
						}
					}
				}

				if( is_array( $_POST[ 'component_orginatingsystem' ] ) ) {
					foreach( $_POST[ 'component_orginatingsystem' ] as $key => $componentOrginatingSystem ) {
						if( $componentOrginatingSystem != '' ) {
							$jsonIssue[ 'visualizationinfo' ][ 'Components' ][] = Array(
									'OriginatingSystem' => $componentOrginatingSystem,
									'AuthoringToolId' => isset( $_POST[ 'component_authoring_tool_id' ][$key] ) ? $_POST[ 'component_authoring_tool_id' ][$key] : '',
									'@attributes' => Array(
											'IfcGuid' => isset( $_POST[ 'component_ifcguid' ][$key] )? $_POST[ 'component_ifcguid' ][$key] : '',
											'Selected' => ( isset( $_POST[ 'component_selected' ][$key] ) && $_POST[ 'component_selected' ][$key] == 'true' ) ? true : false,
											'Visible' => ( isset( $_POST[ 'component_visible' ][$key] ) && $_POST[ 'component_visible' ][$key] == 'true' ) ? true : false,
											'Color' => isset( $_POST[ 'component_colour' ][$key] ) ? $_POST[ 'component_colour' ][$key] : ''
									)
							);
						}
					}
				}

				if( is_array( $_POST[ 'view_to_world_scale' ] ) ) {
					foreach( $_POST[ 'view_to_world_scale' ] as $key => $viewToWorldScale ) {
						if( $viewToWorldScale != '' ) {
							$jsonIssue[ 'visualizationinfo' ][ 'OrthogonalCamera' ][] = Array(
									'CameraViewPoint' => Array(
											'X' => isset( $_POST[ 'camera_viewpoint_x' ][$key] ) ? $_POST[ 'camera_viewpoint_x' ][$key] : '',
											'Y' => isset( $_POST[ 'camera_viewpoint_y' ][$key] ) ? $_POST[ 'camera_viewpoint_y' ][$key] : '',
											'Z' => isset( $_POST[ 'camera_viewpoint_z' ][$key] ) ? $_POST[ 'camera_viewpoint_z' ][$key] : '',
									),
									'CameraDirection' => Array(
											'X' => isset( $_POST[ 'camera_direction_x' ][$key] ) ? $_POST[ 'camera_direction_x' ][$key] : '',
											'Y' => isset( $_POST[ 'camera_direction_y' ][$key] ) ? $_POST[ 'camera_direction_y' ][$key] : '',
											'Z' => isset( $_POST[ 'camera_direction_z' ][$key] ) ? $_POST[ 'camera_direction_z' ][$key] : '',
									),
									'CameraUpVector' => Array(
											'X' => isset( $_POST[ 'camera_vector_up_x' ][$key] ) ? $_POST[ 'camera_vector_up_x' ][$key] : '',
											'Y' => isset( $_POST[ 'camera_vector_up_y' ][$key] ) ? $_POST[ 'camera_vector_up_y' ][$key] : '',
											'Z' => isset( $_POST[ 'camera_vector_up_z' ][$key] ) ? $_POST[ 'camera_vector_up_z' ][$key] : '',
									),
									'ViewToWorldScale' => $viewToWorldScale
							);
						}
					}
				}
				if( is_array( $_POST[ 'field_of_view' ] ) ) {
					foreach( $_POST[ 'field_of_view' ] as $key => $fieldOfView ) {
						if( $fieldOfView != '' ) {
							$jsonIssue[ 'visualizationinfo' ][ 'PerspectiveCamera' ][] = Array(
									'CameraViewPoint' => Array(
											'X' => isset( $_POST[ 'perspective_camera_viewpoint_x' ][$key] ) ? $_POST[ 'perspective_camera_viewpoint_x' ][$key] : '',
											'Y' => isset( $_POST[ 'perspective_camera_viewpoint_y' ][$key] ) ? $_POST[ 'perspective_camera_viewpoint_y' ][$key] : '',
											'Z' => isset( $_POST[ 'perspective_camera_viewpoint_z' ][$key] ) ? $_POST[ 'perspective_camera_viewpoint_z' ][$key] : '',
									),
									'CameraDirection' => Array(
											'X' => isset( $_POST[ 'perspective_camera_direction_x' ][$key] ) ? $_POST[ 'perspective_camera_direction_x' ][$key] : '',
											'Y' => isset( $_POST[ 'perspective_camera_direction_y' ][$key] ) ? $_POST[ 'perspective_camera_direction_y' ][$key] : '',
											'Z' => isset( $_POST[ 'perspective_camera_direction_z' ][$key] ) ? $_POST[ 'perspective_camera_direction_z' ][$key] : '',
									),
									'CameraUpVector' => Array(
											'X' => isset( $_POST[ 'perspective_camera_vector_up_x' ][$key] ) ? $_POST[ 'perspective_camera_vector_up_x' ][$key] : '',
											'Y' => isset( $_POST[ 'perspective_camera_vector_up_y' ][$key] ) ? $_POST[ 'perspective_camera_vector_up_y' ][$key] : '',
											'Z' => isset( $_POST[ 'perspective_camera_vector_up_z' ][$key] ) ? $_POST[ 'perspective_camera_vector_up_z' ][$key] : '',
									),
									'FieldOfView' => $fieldOfView
							);
						}
					}
				}
				if( is_array( $_POST[ 'line_start_x' ] ) ) {
					foreach( $_POST[ 'line_start_x' ] as $key => $lineStartX ) {
						if( $lineStartX != '' ) {
							$jsonIssue[ 'visualizationinfo' ][ 'Lines' ][] = Array(
									'StartPoint' => Array(
											'X' => $lineStartX,
											'Y' => isset( $_POST[ 'line_start_y' ][$key] ) ? $_POST[ 'line_start_y' ][$key] : '',
											'Z' => isset( $_POST[ 'line_start_z' ][$key] ) ? $_POST[ 'line_start_z' ][$key] : '',
									),
									'EndPoint' => Array(
											'X' => isset( $_POST[ 'line_end_x' ][$key] ) ? $_POST[ 'line_end_x' ][$key] : '',
											'Y' => isset( $_POST[ 'line_end_y' ][$key] ) ? $_POST[ 'line_end_y' ][$key] : '',
											'Z' => isset( $_POST[ 'line_end_z' ][$key] ) ? $_POST[ 'line_end_z' ][$key] : '',
									)
							);
						}
					}
				}
				if( is_array( $_POST[ 'clipping_plane_location_x' ] ) ) {
					foreach( $_POST[ 'clipping_plane_location_x' ] as $key => $clippingPlaneLocationX ) {
						if( $clippingPlaneLocationX != '' ) {
							$jsonIssue[ 'visualizationinfo' ][ 'ClippingPlanes' ][] = Array(
									'Location' => Array(
											'X' => $clippingPlaneLocationX,
											'Y' => isset( $_POST[ 'clipping_plane_location_y' ][$key] ) ? $_POST[ 'clipping_plane_location_y' ][$key] : '',
											'Z' => isset( $_POST[ 'clipping_plane_location_z' ][$key] ) ? $_POST[ 'clipping_plane_location_z' ][$key] : '',
									),
									'Direction' => Array(
											'X' => isset( $_POST[ 'clipping_plane_direction_x' ][$key] ) ? $_POST[ 'clipping_plane_direction_x' ][$key] : '',
											'Y' => isset( $_POST[ 'clipping_plane_direction_y' ][$key] ) ? $_POST[ 'clipping_plane_direction_y' ][$key] : '',
											'Z' => isset( $_POST[ 'clipping_plane_direction_z' ][$key] ) ? $_POST[ 'clipping_plane_direction_z' ][$key] : '',
									)
							);
						}
					}
				}
				if( is_array( $_POST[ 'bitmap' ] ) ) {
					foreach( $_POST[ 'bitmap' ] as $key => $bitstamp ) {
						if( $bitstamp != '' ) {
							$jsonIssue[ 'visualizationinfo' ][ 'Bitmap' ][] = Array(
									'Bitmap' => $bitmap,
									'Reference' => isset( $_POST[ 'reference' ][$key] ) ? $_POST[ 'reference' ][$key] : '',
									'Location' => Array(
											'X' => isset( $_POST[ 'bitmap_location_x' ][$key] ) ? $_POST[ 'bitmap_location_x' ][$key] : '',
											'Y' => isset( $_POST[ 'bitmap_location_y' ][$key] ) ? $_POST[ 'bitmap_location_y' ][$key] : '',
											'Z' => isset( $_POST[ 'bitmap_location_z' ][$key] ) ? $_POST[ 'bitmap_location_z' ][$key] : '',
									),
									'Normal' => Array(
											'X' => isset( $_POST[ 'bitmap_normal_x' ][$key] ) ? $_POST[ 'bitmap_normal_x' ][$key] : '',
											'Y' => isset( $_POST[ 'bitmap_normal_y' ][$key] ) ? $_POST[ 'bitmap_normal_y' ][$key] : '',
											'Z' => isset( $_POST[ 'bitmap_normal_z' ][$key] ) ? $_POST[ 'bitmap_normal_z' ][$key] : '',
									),
									'Up' => Array(
											'X' => isset( $_POST[ 'bitmap_up_x' ][$key] ) ? $_POST[ 'bitmap_up_x' ][$key] : '',
											'Y' => isset( $_POST[ 'bitmap_up_y' ][$key] ) ? $_POST[ 'bitmap_up_y' ][$key] : '',
											'Z' => isset( $_POST[ 'bitmap_up_z' ][$key] ) ? $_POST[ 'bitmap_up_z' ][$key] : '',
									),
									'Height' => isset( $_POST[ 'height' ][$key] ) ? $_POST[ 'height' ][$key] : '',
							);
						}
					}
				}
				
				$postId = BIMBCFManagement::addIssue( $jsonIssue );
				if( $postId !== false ) {
					if( $server != '' ) {
						add_post_meta( $postId, '_bimsie_uri', $server );
					} else {
						update_post_meta( $postId, 'import_status', 'pending' );
					}

					// add images to this post and set first as featured
					$first = true;
					foreach( $attachments as $attachmentId ) {
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts}
							SET post_parent = %d
							WHERE ID = %d", $postId, $attachmentId ) );
						if( $first ) {
							set_post_thumbnail( $postId, $attachmentId );
							$first = false;
						}
					}
?>
					<h3><?php _e( 'Issue added', 'bim-bcf-management' ); ?></h3>
					<a href="<?php print( get_bloginfo( 'wpurl' ) . $options[ 'issue_details_uri' ] . '?id=' . $postId );  ?>"><?php _e( 'view issue', 'bim-bcf-management' ); ?></a><br />
					<?php _e( 'or', 'bim-bcf-management' ); ?><br />
					<a href="<?php the_permalink(); ?>"><?php _e( 'add new issue', 'bim-bcf-management' ); ?></a><br />
<?php
					return '';
				} else {
					// TODO: issue could not be imported tell the user!
				}
			}
?>
			<form method="post" action="" id="add-issue-form" enctype="multipart/form-data">
				<h3><?php _e( 'Bimsie server', 'bim-bcf-management' ); ?></h3>
				<div id="bimsie-server-selection">
					<div class="status"></div>
<?php
			if( count( $bimsieServers ) > 0 ) {
?>
					<label for="server-selection"><?php _e( 'Select BIMSie server', 'bim-bcf-management' ); ?></label> <select id="server-selection" onchange="BIMBCFManagement.frontEndServerSelected();">
						<option value=""><?php _e( 'Add BIMSie server URI', 'bim-bcf-management' ); ?></option>
<?php
				foreach( $bimsieServers as $key => $server ) {
?>
						<option value="<?php print( $key ); ?>"><?php print( $server[ 'uri' ] . ( isset( $server[ 'username' ] ) ? ( ' - ' . $server[ 'username' ] ) : '' ) ); ?></option>
<?php
				}
?>
					</select><br />
					<div class="new-server-container">
						<label for="new-bimsie-server"><?php _e( 'Add BIMSie server URI', 'bim-bcf-management' ); ?></label>
						<input id="new-bimsie-server" type="text" />
					</div><br />
					<div class="toggle-server-info hidden">
						<input type="checkbox" id="server-remember-user" /> <label for="server-remember-user"><?php _e( 'Remember user', 'bim-bcf-management' ); ?></label>
						<div class="clear"></div><br />
						<label for="bimsie-username"><?php _e( 'Username', 'bim-bcf-management' ); ?></label> <input id="bimsie-username" type="text" /><br />
						<label for="bimsie-password"><?php _e( 'Password', 'bim-bcf-management' ); ?></label> <input id="bimsie-password" type="password" /><br />
					</div>
					<input type="button" value="<?php _e( 'Connect', 'bim-bcf-management' ); ?>" onclick="BIMBCFManagement.frontEndSubmitServerSelection();" />
				</div>
				<input type="hidden" id="bimsie-server-uri" name="bimsie_server_uri" />
<?php
			}
?>
				<h3><?php _e( 'Markup', 'bim-bcf-management' ); ?></h3>
				<h4><?php _e( 'Header', 'bim-bcf-management' ); ?></h4>
				<h5><?php _e( 'File', 'bim-bcf-management' ); ?></h5>
				<div class="file sub-element">
					<label for="file-filename-0"><?php _e( 'Filename', 'bim-bcf-management' ); ?></label>
					<input type="text" id="filename-0" name="file_filename[]" /><br />
					<label for="file-date-0"><?php _e( 'Date', 'bim-bcf-management' ); ?></label>
					<input type="date" id="file-date-0" name="file_date[]" /><br />
					<label for="file-reference-0"><?php _e( 'Reference', 'bim-bcf-management' ); ?></label>
					<input type="text" id="file-reference-0" name="file_reference[]" /><br />
					<label for="file-ifcproject-0"><?php _e( 'Ifc Project', 'bim-bcf-management' ); ?></label>
					<input type="text" id="file-ifcproject-0" name="file_ifcproject[]" /><br />
					<label for="file-ifcspatial-0"><?php _e( 'Ifc Spatial Structure Element', 'bim-bcf-management' ); ?></label>
					<input type="text" id="file-ifcspatial-0" name="file_spatial[]" /><br />
					<label for="file-project-0" class="project-place-holder"><?php _e( 'Bimsie project', 'bim-bcf-management' ); ?></label>
					<span class="select-project"><?php _e( 'Connect to a Bimsie server to get a project list', 'bim-bcf-management' ); ?></span>
					<br />
					<label for="file-revision-0" class="revision-place-holder"><?php _e( 'Bimsie revision', 'bim-bcf-management' ); ?></label>
					<br />
				</div>
				<a href="#" class="more-items" id="more-file"><?php _e( 'Add file', 'bim-bcf-management' ); ?></a><br />
				<h4><?php _e( 'Topic', 'bim-bcf-management' ); ?></h4>
				<div class="topic sub-element">
					<label for="referencelink"><?php _e( 'Referencelink', 'bim-bcf-management' ); ?></label>
					<input type="text" id="referencelink" name="topic_referencelink" /><br />
					<label for="topic-title"><?php _e( 'Title', 'bim-bcf-management' ); ?></label>
					<input type="text" id="topic-title" name="topic_title" /><br />
					<label for="topic-index"><?php _e( 'Index', 'bim-bcf-management' ); ?></label>
					<input type="text" id="topic-index" name="topic_index" /><br />
					<label for="topic-label"><?php _e( 'Label', 'bim-bcf-management' ); ?></label>
					<select id="topic-label" name="topic_label">
<?php
				foreach( $topicLabels as $topicLabel ) {
?>
						<option value="<?php print( trim( $topicLabel ) ); ?>"><?php print( trim( $topicLabel ) ); ?></option>
<?php
				}
?>
					</select><br />
					<label for="topic-type"><?php _e( 'Type', 'bim-bcf-management' ); ?></label>
					<select id="topic-type" name="topic_type">
<?php
				foreach( $topicTypes as $topicType ) {
?>
						<option value="<?php print( trim( $topicType ) ); ?>"><?php print( trim( $topicType ) ); ?></option>
<?php
				}
?>
					</select><br />
					<label for="topic-status"><?php _e( 'Status', 'bim-bcf-management' ); ?></label>
					<select id="topic-status" name="topic_status">
<?php
				foreach( $topicStatuses as $topicStatus ) {
?>
						<option value="<?php print( trim( $topicStatus ) ); ?>"><?php print( trim( $topicStatus ) ); ?></option>
<?php
				}
?>
					</select><br />
					<label for="topic-guid"><?php _e( 'Guid', 'bim-bcf-management' ); ?></label>
					<input type="text" id="topic-guid" name="topic_guid" /><br />
					<!-- label for="topic-creation-date"><?php _e( 'Creation date', 'bim-bcf-management' ); ?></label>
					<input type="date" id="topic-creation-date" name="topic_creation_date" /><br />
					<label for="topic-creation-time"><?php _e( 'Creation time', 'bim-bcf-management' ); ?></label>
					<input type="time" id="topic-creation-time" name="topic_creation_time" /><br />
					<label for="topic-modified-date"><?php _e( 'Creation date', 'bim-bcf-management' ); ?></label>
					<input type="date" id="topic-modified-date" name="topic_modified_date" /><br />
					<label for="topic-modified-time"><?php _e( 'Modified time', 'bim-bcf-management' ); ?></label>
					<input type="time" id="topic-modified-time" name="topic_modified_time" /><br /-->
					<label for="assigned-to"><?php _e( 'Assigned to', 'bim-bcf-management' ); ?></label>
					<select id="assigned-to" name="assigned_to">
<?php
				foreach( $userIdTypes as $userIdType ) {
?>
						<option value="<?php print( trim( $userIdType ) ); ?>"><?php print( trim( $userIdType ) ); ?></option>
<?php
				}
?>
					</select><br />
					<h5><?php _e( 'Bim Snippet', 'bim-bcf-management' ); ?></h5>
					<label for="bim-snippet-reference"><?php _e( 'Reference', 'bim-bcf-management' ); ?></label>
					<input type="text" id="bim-snippet-reference" name="bim_snippet_reference" /><br />
					<label for="bim-snippet-reference-schema"><?php _e( 'Reference schema', 'bim-bcf-management' ); ?></label>
					<input type="text" id="bim-snippet-reference-schema" name="bim_snippet_reference_schema" /><br />
					<label for="bim-snippet-type"><?php _e( 'Bim Snippet Type', 'bim-bcf-management' ); ?></label>
					<select id="bim-snippet-type" name="bim_snippet_type">
<?php
				foreach( $snippetTypes as $snippetType ) {
?>
						<option value="<?php print( trim( $snippetType ) ); ?>"><?php print( trim( $snippetType ) ); ?></option>
<?php
				}
?>
					</select><br />
					<input type="checkbox" id="bim-snippet-isexternal" name="bim_snippet_isexternal" value="true" />
					<label for="bim-snippet-isexternal"><?php _e( 'Is external', 'bim-bcf-management' ); ?></label><br />
					<h5><?php _e( 'Document Reference', 'bim-bcf-management' ); ?></h5>
					<div class="sub-element document-reference">
						<label for="referenced-document-0"><?php _e( 'Referenced document', 'bim-bcf-management' ); ?></label>
						<input type="text" id="referenced-document-0" name="referenced_document[]" /><br />
						<label for="document-reference-description-0"><?php _e( 'Description', 'bim-bcf-management' ); ?></label>
						<input type="text" id="document-reference-description-0" name="document_reference_description[]" /><br />
						<label for="document-reference-guid-0"><?php _e( 'Document reference guid', 'bim-bcf-management' ); ?></label>
						<input type="text" id="document-reference-guid-0" name="document_reference_guid[]" /><br />
						<input type="checkbox" id="document-reference-isexternal-0" name="document_reference_isexternal[]" value="true" />
						<label for="document-reference-isexternal-0"><?php _e( 'Is external', 'bim-bcf-management' ); ?></label><br />
					</div>
					<a href="#" class="more-items" id="more-document-reference"><?php _e( 'Add document reference', 'bim-bcf-management' ); ?></a><br />
					<h5><?php _e( 'Related topics', 'bim-bcf-management' ); ?></h5>
					<div class="sub-element related-topics">
						<label for="related-topic-0"><?php _e( 'Related topic', 'bim-bcf-management' ); ?></label>
						<input type="text" id="related-topic-0" name="related_topic[]" /><br />
					</div>
					<a href="#" class="more-items" id="more-related-topics"><?php _e( 'Add related topic', 'bim-bcf-management' ); ?></a><br />
				</div>
				<h4><?php _e( 'Viewpoint', 'bim-bcf-management' ); ?></h4>
				<div class="viewpoint sub-element">
					<label for="viewpoint-0"><?php _e( 'Viewpoint', 'bim-bcf-management' ); ?></label>
					<input type="text" id="viewpoint-0" name="viewpoint[]" /><br />
					<label for="viewpoint-guid-0"><?php _e( 'Guid', 'bim-bcf-management' ); ?></label>
					<input type="text" id="viewpoint-guid-0" name="viewpoint_guid[]" /><br />
					<label for="snapshot-0"><?php _e( 'Snapshot', 'bim-bcf-management' ); ?></label>
					<input type="file" id="snapshot-0" name="snapshot[]" /><br />
					<div class="viewpoint-comments-0 sub-sub-element">
						<label for="viewpoint-comments-0-0"><?php _e( 'Comment', 'bim-bcf-management' ); ?></label>
						<input type="text" id="viewpoint-comments-0-0" name="viewpoint_comment[0][]" /><br />
					</div>
					<a href="#" class="more-items" id="more-viewpoint-comments-0"><?php _e( 'Add viewpoint comment', 'bim-bcf-management' ); ?></a><br />
				</div>
				<a href="#" class="more-items" id="more-viewpoint"><?php _e( 'Add viewpoint', 'bim-bcf-management' ); ?></a><br />
				<h3><?php _e( 'Vizualization information', 'bim-bcf-management' ); ?></h3>
				<h4><?php _e( 'Components', 'bim-bcf-management' ); ?></h4>
				<div class="component sub-element">
					<label for="component-ifcguid-0"><?php _e( 'Ifc Guid', 'bim-bcf-management' ); ?></label>
					<input type="text" id="component-ifcguid-0" name="component_ifcguid[]" /><br />
					<input type="checkbox" id="component-selected-0" name="component_selected[]" value="true" />
					<label for="component-selected-0"><?php _e( 'Selected', 'bim-bcf-management' ); ?></label><br />
					<input type="checkbox" id="component-visible-0" name="component_visible[]" value="true" />
					<label for="component-visible-0"><?php _e( 'Visible', 'bim-bcf-management' ); ?></label><br />
					<label for="component-colour-0"><?php _e( 'Colour', 'bim-bcf-management' ); ?></label>
					<input type="text" id="component-colour-0" name="component_colour[]" /><br />
					<label for="component-orginatingsystem-0"><?php _e( 'Orginating system', 'bim-bcf-management' ); ?></label>
					<input type="text" id="component-orginatingsystem-0" name="component_orginatingsystem[]" /><br />
					<label for="component-authoring-tool-id-0"><?php _e( 'Authoring tool id', 'bim-bcf-management' ); ?></label>
					<input type="text" id="component-authoring-tool-id-0" name="component_authoring_tool_id[]" /><br />
				</div>
				<a href="#" class="more-items" id="more-component"><?php _e( 'Add component', 'bim-bcf-management' ); ?></a><br />
				<h4><?php _e( 'Orthogonal Camera', 'bim-bcf-management' ); ?></h4>
				<div class="camera-direction sub-element">
					<h5><?php _e( 'Camera View Point', 'bim-bcf-management' ); ?></h5>
					<label for="camera-viewpoint-x-0"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
					<input type="text" id="camera-viewpoint-x-0" name="camera_viewpoint_x[]" /><br />
					<label for="camera-viewpoint-y-0"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
					<input type="text" id="camera-viewpoint-y-0" name="camera_viewpoint_y[]" /><br />
					<label for="camera-viewpoint-z-0"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
					<input type="text" id="camera-viewpoint-z-0" name="camera_viewpoint_z[]" /><br />
					<h5><?php _e( 'Camera Direction', 'bim-bcf-management' ); ?></h5>
					<label for="camera-direction-x-0"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
					<input type="text" id="camera-direction-x-0" name="camera_direction_x[]" /><br />
					<label for="camera-direction-y-0"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
					<input type="text" id="camera-direction-y-0" name="camera_direction_y[]" /><br />
					<label for="camera-direction-z-0"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
					<input type="text" id="camera-direction-z-0" name="camera_direction_z[]" /><br />
					<h5><?php _e( 'Camera Up Vector', 'bim-bcf-management' ); ?></h5>
					<label for="camera-up-vector-x-0"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
					<input type="text" id="camera-up-vector-x-0" name="camera_up_vector_x[]" /><br />
					<label for="camera-up-vector-y-0"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
					<input type="text" id="camera-up-vector-y-0" name="camera_up_vector_y[]" /><br />
					<label for="camera-up-vector-z-0"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
					<input type="text" id="camera-up-vector-z-0" name="camera_up_vector_z[]" /><br />
					<label for="view-to-world-scale-0"><?php _e( 'View to world scale', 'bim-bcf-management' ); ?></label>
					<input type="text" id="view-to-world-scale-0" name="view_to_world_scale[]" /><br />
				</div>
				<a href="#" class="more-items" id="more-camera-direction"><?php _e( 'Add camera direction', 'bim-bcf-management' ); ?></a><br />
				<h4><?php _e( 'Perspective Camera', 'bim-bcf-management' ); ?></h4>
				<div class="camera-perspective sub-element">
					<h5><?php _e( 'Camera View Point', 'bim-bcf-management' ); ?></h5>
					<label for="perspective-camera-viewpoint-x-0"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
					<input type="text" id="perspective-camera-viewpoint-x-0" name="perspective_camera_viewpoint_x[]" /><br />
					<label for="perspective-camera-viewpoint-y-0"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
					<input type="text" id="perspective-camera-viewpoint-y-0" name="perspective_camera_viewpoint_y[]" /><br />
					<label for="perspective-camera-viewpoint-z-0"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
					<input type="text" id="perspective-camera-viewpoint-z-0" name="perspective_camera_viewpoint_z[]" /><br />
					<h5><?php _e( 'Camera Direction', 'bim-bcf-management' ); ?></h5>
					<label for="perspective-camera-direction-x-0"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
					<input type="text" id="perspective-camera-direction-x-0" name="perspective_camera_direction_x[]" /><br />
					<label for="perspective-camera-direction-y-0"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
					<input type="text" id="perspective-camera-direction-y-0" name="perspective_camera_direction_y[]" /><br />
					<label for="perspective-camera-direction-z-0"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
					<input type="text" id="perspective-camera-direction-z-0" name="perspective_camera_direction_z[]" /><br />
					<h5><?php _e( 'Camera Up Vector', 'bim-bcf-management' ); ?></h5>
					<label for="perspective-camera-up-vector-x-0"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
					<input type="text" id="perspective-camera-up-vector-x-0" name="perspective_camera_up_vector_x[]" /><br />
					<label for="perspective-camera-up-vector-y-0"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
					<input type="text" id="perspective-camera-up-vector-y-0" name="perspective_camera_up_vector_y[]" /><br />
					<label for="perspective-camera-up-vector-z-0"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
					<input type="text" id="perspective-camera-up-vector-z-0" name="perspective_camera_up_vector_z[]" /><br />
					<label for="field-of-view-0"><?php _e( 'Field of view', 'bim-bcf-management' ); ?></label>
					<input type="text" id="field-of-view-0" name="field_of_view[]" /><br />
				</div>
				<a href="#" class="more-items" id="more-camera-perspective"><?php _e( 'Add camera perspective', 'bim-bcf-management' ); ?></a><br />
				<h4><?php _e( 'Lines', 'bim-bcf-management' ); ?></h4>
				<div class="line sub-element">
					<h5><?php _e( 'Startpoint', 'bim-bcf-management' ); ?></h5>
					<label for="line-start-x-0"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
					<input type="text" id="line-start-x-0" name="line_start_x[]" /><br />
					<label for="line-start-y-0"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
					<input type="text" id="line-start-y-0" name="line_start_y[]" /><br />
					<label for="line-start-z-0"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
					<input type="text" id="line-start-z-0" name="line_start_z[]" /><br />
					<h5><?php _e( 'Endpoint', 'bim-bcf-management' ); ?></h5>
					<label for="line-end-x-0"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
					<input type="text" id="line-end-x-0" name="line_end_x[]" /><br />
					<label for="line-end-y-0"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
					<input type="text" id="line-end-y-0" name="line_end_y[]" /><br />
					<label for="line-end-z-0"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
					<input type="text" id="line-end-z-0" name="line_end_z[]" /><br />
				</div>
				<a href="#" class="more-items" id="more-line"><?php _e( 'Add line', 'bim-bcf-management' ); ?></a><br />
				<h4><?php _e( 'Clipping planes', 'bim-bcf-management' ); ?></h4>
				<div class="clipping-plane sub-element">
					<h5><?php _e( 'Location', 'bim-bcf-management' ); ?></h5>
					<label for="clipping-plane-location-x-0"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
					<input type="text" id="clipping-plane-location-x-0" name="clipping_plane_location_x[]" /><br />
					<label for="clipping-plane-location-y-0"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
					<input type="text" id="clipping-plane-location-y-0" name="clipping_plane_location_y[]" /><br />
					<label for="clipping-plane-location-z-0"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
					<input type="text" id="clipping-plane-location-z-0" name="clipping_plane_location_z[]" /><br />
					<h5><?php _e( 'Direction', 'bim-bcf-management' ); ?></h5>
					<label for="clipping-plane-direction-x-0"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
					<input type="text" id="clipping-plane-direction-x-0" name="clipping_plane_direction_x[]" /><br />
					<label for="clipping-plane-direction-y-0"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
					<input type="text" id="clipping-plane-direction-y-0" name="clipping_plane_direction_y[]" /><br />
					<label for="clipping-plane-direction-z-0"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
					<input type="text" id="clipping-plane-direction-z-0" name="clipping_plane_direction_z[]" /><br />
				</div>
				<a href="#" class="more-items" id="more-clipping-plane"><?php _e( 'Add clipping plane', 'bim-bcf-management' ); ?></a><br />
				<h4><?php _e( 'Bitmap', 'bim-bcf-management' ); ?></h4>
				<label for="bitmap"><?php _e( 'Bitmap', 'bim-bcf-management' ); ?></label>
				<input type="text" id="bitmap" name="bitmap" /><br />
				<label for="reference"><?php _e( 'Reference', 'bim-bcf-management' ); ?></label>
				<input type="text" id="reference" name="reference" /><br />
				<label for="height"><?php _e( 'Height', 'bim-bcf-management' ); ?></label>
				<input type="text" id="height" name="height" /><br />
				<h5><?php _e( 'Location', 'bim-bcf-management' ); ?></h5>
				<label for="bitmap-location-x"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
				<input type="text" id="bitmap-location-x" name="bitmap_location_x" /><br />
				<label for="bitmap-location-y"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
				<input type="text" id="bitmap-location-y" name="bitmap_location_y" /><br />
				<label for="bitmap-location-z"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
				<input type="text" id="bitmap-location-z" name="bitmap_location_z" /><br />
				<h5><?php _e( 'Normal', 'bim-bcf-management' ); ?></h5>
				<label for="bitmap-normal-x"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
				<input type="text" id="bitmap-normal-x" name="bitmap_normal_x" /><br />
				<label for="bitmap-normal-y"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
				<input type="text" id="bitmap-normal-y" name="bitmap_normal_y" /><br />
				<label for="bitmap-normal-z"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
				<input type="text" id="bitmap-normal-z" name="bitmap_normal_z" /><br />
				<h5><?php _e( 'Up', 'bim-bcf-management' ); ?></h5>
				<label for="bitmap-up-x"><?php _e( 'X', 'bim-bcf-management' ); ?></label>
				<input type="text" id="bitmap-up-x" name="bitmap_up_x" /><br />
				<label for="bitmap-up-y"><?php _e( 'Y', 'bim-bcf-management' ); ?></label>
				<input type="text" id="bitmap-up-y" name="bitmap_up_y" /><br />
				<label for="bitmap-up-z"><?php _e( 'Z', 'bim-bcf-management' ); ?></label>
				<input type="text" id="bitmap-up-z" name="bitmap_up_z" /><br />
				<div class="submit-container">
					<input type="submit" name="submit" value="<?php _e( 'Submit', 'bim-bcf-management' ); ?>" />
				</div>
			</form>
			<script type="text/javascript">
				var bimBCFManagementSettings = {
					ajaxURI: "<?php print( plugins_url( 'ajax-handler.php' , __FILE__ ) ); ?>",
					loadingImage: "<img class=\"loading-image\" src=\"<?php bloginfo( 'wpurl' ); ?>/wp-admin/images/loading.gif\" alt=\"loading...\" />",
					bimsieServers: <?php print( json_encode( $bimsieServers ) ); ?>,
					text: {
						serverSubmitError: "<?php _e( 'Supply a BIMSie server URI, username and password or select one from your list.', 'bim-bcf-management' ); ?>",
						noKnownRevisions: "<?php _e( 'There are no revisions for this project.', 'bim-bcf-management' ); ?>",
						serverSelected: "<?php _e( 'Connected to Bimsie Server', 'bim-bcf-management' ); ?>",

						selectServerTitle: "<?php _e( 'Select a BIMsie server or enter a new one', 'bim-bcf-management' ); ?>",
						selectProjectTitle: "<?php _e( 'Select the project for each file', 'bim-bcf-management' ); ?>",
						newServerLabel: "<?php _e( 'Add BIMSie server URI', 'bim-bcf-management' ); ?>",
						submitServer: "<?php _e( 'Retrieve information', 'bim-bcf-management' ); ?>",
						selectServerLabel: "<?php _e( 'Select BIMSie server', 'bim-bcf-management' ); ?>",
						noServerOption: "--- <?php _e( 'New server', 'bim-bcf-management' ); ?> ---",
						rememberServerLabel: "<?php _e( 'Remember user', 'bim-bcf-management' ); ?>",
						serverUserLabel: "<?php _e( 'Username', 'bim-bcf-management' ); ?>",
						serverPasswordLabel: "<?php _e( 'Password', 'bim-bcf-management' ); ?>",
						noProjectsFoundMessage: "<?php _e( 'No projects could be found on this BIMSie server for this user.', 'bim-bcf-management' ); ?>",
						revision: "<?php _e( 'Revision', 'bim-bcf-management' ); ?>"
					}
				};
			</script>
<?php
		} else {
?>
			<p><?php _e( 'Please log in to access this page', 'bim-bcf-management' ); ?></p>
<?php
		}
	}

	public static function getOptions( $forceReload = false ) {
		global $bimBCFManagement;
		if( $forceReload ) {
			$bimBCFManagement->options = get_option( 'bim_bcf_management_options', Array() );
		}
		return $bimBCFManagement->options;
	}

	public static function enqueueScripts() {
		wp_enqueue_style( 'bim-bcf-management', plugins_url( '/bim-bcf-management.css' , __FILE__ ) );
		// TODO: maybe I should only add this on certain pages and not everywhere? Think about it!
		wp_enqueue_script( 'bim-bcf-management', plugins_url( '/bim-bcf-management.js' , __FILE__ ), Array( 'jquery' ), '1.0.0', true );
	}

	public static function setProjectForPendingIssues( $projects, $projectNames = Array(), $revisions = Array() ) {
		$options = BIMBCFManagement::getOptions();
		$allDone = false;
		$unsetIssues = get_posts( Array(
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'posts_per_page' => -1,
				'author' => get_current_user_id(),
				'meta_query' => Array(
						Array(
								'key' => 'import_status',
								'value' => 'pending'
						)
				)
		) );

		$projectsMissingRevisions = Array();

		if( count( $unsetIssues ) > 0 ) {
			$projectIdsCheck = Array();
			foreach( $unsetIssues as $unsetIssue ) {
				$markup = get_post_meta( $unsetIssue->ID, 'markup', true );
				if( isset( $markup[ 'Header' ][ 'File' ] ) ) {
					foreach( $markup[ 'Header' ][ 'File' ] as $file ) {
						if( isset( $file[ 'Filename' ] ) && $file[ 'Filename' ] != '' ) {
							if( !in_array( $file[ 'Filename' ], $projectIdsCheck ) ) {
								$projectIdsCheck[] = $file[ 'Filename' ];
							}
						}
					}
				}
			}
			sort( $projectIdsCheck );
			// We fill this array with the projects where we have no revision yet
			if( count( $projectIdsCheck ) == count( $projects ) ) { // This should match... how can it not?
				$allDone = true;
				foreach( $unsetIssues as $unsetIssue ) {
					$markup = get_post_meta( $unsetIssue->ID, 'markup', true );
					delete_post_meta( $unsetIssue->ID, 'project' );
					delete_post_meta( $unsetIssue->ID, 'poid' );
					$poids = Array();
					delete_post_meta( $unsetIssue->ID, 'roid' );
					$roids = Array();
					$issueDone = true;
					if( isset( $markup[ 'Header' ][ 'File' ] ) ) {
						foreach( $markup[ 'Header' ][ 'File' ] as &$file ) {
							if( isset( $file[ 'Filename' ] ) && $file[ 'Filename' ] != '' ) {
								foreach( $projectIdsCheck as $key => $value ) {
									if( $value == $file[ 'Filename' ] ) {
										// We found the right project file
										// store the project oid for this issue
										// For lookup performance we place this also as poid and roid
										if( isset( $projects[$key] ) && $projects[$key] != '' && !in_array( $projects[$key], $poids ) ) {
											add_post_meta( $unsetIssue->ID, 'poid', $projects[$key] );
											$poids[] = $projects[$key];
											$file[ 'poid' ] = $projects[$key];
										}
										if( isset( $revisions[$key] ) && $revisions[$key] != '' && $revisions[$key] != -1 && !in_array( $revisions[$key], $roids ) ) {
											add_post_meta( $unsetIssue->ID, 'roid', $revisions[$key] );
											$roids[] = $revisions[$key];
											$file[ 'roid' ] = $revisions[$key];
										}
										if( !isset( $projectsMissingRevisions[$key] ) ) {
											$projectsMissingRevisions[$key] = Array( 'ifcProject' => $value, 'file' => $file[ 'Filename' ], 'oid' => $projects[$key], 'name' => ( isset( $projectNames[$key] ) ? $projectNames[$key] : '' ), 'revision' => ( ( isset( $revisions[$key] ) && $revisions[$key] != '' ) ? $revisions[$key] : -1 ) );
										}
										if( !isset( $revisions[$key] ) || $revisions[$key] == '' || $revisions[$key] == -1 ) {
											$issueDone = false;
											$allDone = false;
										}
										// TODO: replace filename with url
										//$file[ 'Filename' ] = Bimsie url
										if( !isset( $file[ '@attributes' ] ) ) {
											$file[ '@attributes' ] = Array();
										}
										$file[ '@attributes' ][ 'isExternal' ] = true;
										break 1;
									}
								}
							}
						}
					}
					if( $issueDone ) {
						update_post_meta( $unsetIssue->ID, 'import_status', 'complete' );
						update_post_meta( $unsetIssue->ID, 'markup', $markup );
					}
				}
				ksort( $projectsMissingRevisions );
			}
		}
		if( $allDone ) {
			$projectsMissingRevisions = Array();
		}
		return $projectsMissingRevisions;
	}

	public static function setBimsieUriForPendingIssues( $uri ) {
		$options = BIMBCFManagement::getOptions();
		$unsetIssues = get_posts( Array(
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'posts_per_page' => -1,
				'author' => get_current_user_id(),
				'meta_query' => Array(
						Array(
								'key' => 'import_status',
								'value' => 'pending'
						)
				)
		) );
		$uri = BIMBCFManagement::removeProtocol( $uri );
		foreach( $unsetIssues as $issue ) {
			update_post_meta( $issue->ID, '_bimsie_uri', $uri );
		}
	}

	public static function getIssuesByProjectRevision( $userId, $bimsieUrl, $poid, $roid ) {
		$options = BIMBCFManagement::getOptions();
		$bimsieUrl = BIMBCFManagement::removeProtocol( $bimsieUrl );
		$issues = get_posts( Array(
				'post_type' => $options[ 'bcf_issue_post_type' ],
				'posts_per_page' => -1,
				'author' => $userId,
				'meta_query' => Array(
						'relation' => 'AND',
						Array(
								'key' => 'import_status',
								'value' => 'complete'
						),
						Array(
								'key' => '_bimsie_uri',
								'value' => $bimsieUrl
						),
						Array(
								'key' => 'poid',
								'value' => $poid
						),
						Array(
								'key' => 'roid',
								'value' => $roid
						)
				)
		) );
		return $issues;
	}

	public static function getJSONFromIssue( $issue ) {
		$jsonIssue = Array();
		$jsonIssue[ 'markup' ] = get_post_meta( $issue->ID, 'markup', true );
		$jsonIssue[ 'visualizationinfo' ] = get_post_meta( $issue->ID, 'visualizationinfo' );
		$jsonIssue[ 'projectextension' ] = get_post_meta( $issue->ID, 'projectextension', true );
		return $jsonIssue;
	}

	public static function getRandomGuid() {
		$guid = uniqid() . uniqid() . uniqid();
		return substr( $guid, 0, 8 ) . '-' . substr( $guid, 8, 4 ) . '-' . substr( $guid, 12, 4 ) . '-' . substr( $guid, 16, 4 )  . '-' . substr( $guid, 20, 12 );
	}

	public static function preprocessCommentHandler( $commentData ) {
		$options = BIMBCFManagement::getOptions();
		$post = get_post( $commentData[ 'comment_post_ID' ] );
		if( $post->post_type == $options[ 'bcf_issue_post_type' ] ) {
			$user = wp_get_current_user();
			$topicStatuses = explode( ',', $options[ 'topic_statuses' ] );
			$priorities = explode( ',', $options[ 'priorities' ] );
			$verbalStatus = isset( $_POST[ 'VerbalStatus' ] ) ? $_POST[ 'VerbalStatus' ] : '';
			$status = isset( $_POST[ 'Status' ] ) ? $_POST[ 'Status' ] : 'Unknown';
			$priority = isset( $_POST[ 'Priority' ] ) ? $_POST[ 'Priority' ] : '';
			if( !in_array( $verbalStatus, $topicStatuses ) ) {
				$verbalStatus = isset( $topicStatuses[0] ) ? $topicStatuses[0] : '';
			}
			if( !in_array( $priority, $priorities ) ) {
				$priority = isset( $priorities[0] ) ? $priorities[0] : '';
			}
			$comment = Array(
				'VerbalStatus' => trim( $verbalStatus ),
				'Status' => $status,
				'Date' => date( 'Y-m-d\TH:i:sP' ),
				'Author' => $user->user_email,
				'Comment' => $commentData[ 'comment_content' ],
				'Topic' => Array(),
				'ModifiedDate' => date( 'Y-m-d\TH:i:sP' ),
				'Priority' => trim( $priority ),
				'@attributes' => Array( 'guid' => BIMBCFManagement::getRandomGuid() )
			);
			$markup = get_post_meta( $post->ID, 'markup', true );
			$markup[ 'Comment' ][] = $comment;
			update_post_meta( $post->ID, 'markup', $markup );
			$commentData[ 'comment_content' ] = $commentData[ 'comment_content' ] . "\n" . __( 'Status', 'bim-bcf-management' ) . ': ' . $status;
			$commentData[ 'comment_content' ] = $commentData[ 'comment_content' ] . "\n" . __( 'VerbalStatus', 'bim-bcf-management' ) . ': ' . $verbalStatus;
			$commentData[ 'comment_content' ] = $commentData[ 'comment_content' ] . "\n" . __( 'Priority', 'bim-bcf-management' ) . ': ' . $priority;
		}
		return $commentData;
	}
	
	public static function removeProtocol( $uri ) {
		$parts = explode( '://', $uri );
		return isset( $parts[1] ) ? $parts[1] : $uri;
	}
}

$bimBCFManagement = new BIMBCFManagement();
