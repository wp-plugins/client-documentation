<?php
/*
Plugin Name: Simple Documentation
Plugin URI: http://mathieuhays.co.uk/simple-documentation/
Description: This plugin helps webmasters/developers to provide documentation through the wordpress dashboard.
Version: 1.1.8
Author: Mathieu Hays
Author URI: http://mathieuhays.co.uk
License: GPL2
*/

/*  Copyright 2013  Mathieu Hays  (email : mathieu@mathieuhays.com)

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

if ( ! defined( 'ABSPATH' ) ) exit;

class clientDocumentation {

  public $allowed_html = array(
    'iframe' => array(
      'src' => array(),
      'style' => array(),
      'height' => array(),
      'width' => array()
    ),
    'a' => array(
      'href' => array(),
      'title' => array(),
      'target' => array(),
      'style' => array()
    ),
    'p' => array('align' => array(), 'style' => array()),
    'br' => array('style' => array())
  );

  public $allowed_note = array(
    'a' => array(
      'href' => array(),
      'title' => array(),
      'target' => array()
    ),
    'p' => array('style' => array(),'align'=>array()),
    'strong' => array('style' => array()),
    'b' => array('style' => array()),
    'em' => array('style'=>array()),
    'i' => array('style'=>array()),
    'br' => array('style'=>array()),
    'code' => array('style' => array())
  );

  /**
	 * __construct function.
	 *
	 * Add actions and filters
	 */
	public function __construct() {
		global $wpdb;

		/* Table Version */
		define( 'SMPLDCMTNTBL', '1.0' );
    define( 'SMPLDCMTNVRS', '1.1.8' );


    if(!get_site_option('clientDocumentation_table'))
		  add_site_option( 'clientDocumentation_table', $wpdb->prefix . 'clientDocumentation' );

    $wpdb->clientDocumentation = get_site_option('clientDocumentation_table');

		//Activation
		register_activation_hook( __FILE__, array( $this, 'install_tables' ), 10 );
		register_activation_hook( __FILE__, array( $this, 'set_default_values' ), 10 );

		//Uninstall
		register_uninstall_hook( __FILE__, array( 'clientDocumentation', 'uninstall') );

		//Actions
		add_action( 'admin_init' , array( $this , 'load_plugin_textdomain' ) );
		add_action( 'admin_init' , array( $this , 'add_admin_styles' ) );
		add_action( 'admin_init' , array( $this , 'add_admin_scripts' ) );
		add_action( 'wp_ajax_cd_ajax' , array( $this , 'ajaxhandle' ) );
		add_action( 'wp_dashboard_setup', array($this, 'add_dashboard') );

		// Filters
		add_filter( 'admin_body_class' , array( $this, 'admin_body_class') );

    // MU support
    if(is_multisite()){
      add_action( 'network_admin_menu', array( $this, 'register_page' ));
      add_action( 'wp_network_dashboard_setup', array($this, 'add_dashboard') );
    }else{
      add_action( 'admin_menu' , array( $this , 'register_page' ) );
    }
	}

	/**
	 * Add admin stylesheet.
	 *
	 * Icon webfont : Font Awesome
	 * http://fortawesome.github.io/Font-Awesome/
	 */
	public function add_admin_styles() {
		global $wp_styles;

		wp_enqueue_style('clientDocumentation_Stylesheet', plugins_url('css/clientDocumentation.css', __FILE__),array(), SMPLDCMTNVRS );
		wp_enqueue_style('font-awesome', plugins_url('css/font-awesome.min.css', __FILE__) );
		wp_enqueue_style('font-awesome-ie7', plugins_url( '/css/font-awesome-ie7.min.css' ), __FILE__ );
  		$wp_styles->add_data( 'font-awesome-ie7', 'conditional', 'lte IE 7' );
	}

	/**
	 * Add admin scripts.
	 */
	public function add_admin_scripts() {
		global $pagenow;

		// Localization
		$local = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'fields_missings' => __( 'Following fields are missing :', 'clientDocumentation' ),
			'is_missing' => __( 'is missing !', 'clientDocumentation' )
		);

        if($pagenow == 'index.php' || ($pagenow == 'admin.php' && $_GET['page'] == 'clientDocumentation' )){
	        wp_enqueue_script( 'clientDocumentation_js', plugins_url( '/js/clientDocumentation.js' , __FILE__ ), array( 'jquery' ), SMPLDCMTNVRS);
	        wp_localize_script( 'clientDocumentation_js', 'ajax_object', $local );
	        wp_enqueue_media();
        }
    }

    /**
     * Add Widget on dashboard.
     */
    public function add_dashboard(){

    	// Get options saved by the administrator
    	$widget_dn = (get_option('clientDocumentation_widgetTitle')) ? stripslashes(get_option( 'clientDocumentation_widgetTitle' )) : __('Resources', 'clientDocumentation');
		  $role = (get_option('clientDocumentation_clientRole')) ? get_option('clientDocumentation_clientRole') : 'Editor';

		// Filter by role and apply custom title
		if($this->check_user_role($role) || $this->check_user_role('administrator'))
	    	wp_add_dashboard_widget( 'clientDocumentation' , $widget_dn , array( $this, 'dashboard_widget') );
    }

    /**
     * Add body class on documentation page.
     */
    public function admin_body_class(){
	    global $pagenow,$classes;

	    if(is_admin() && ($pagenow == 'admin.php' && $_GET['page'] == 'clientDocumentation' ))
	    	return $classes .= 'clientDocumentation';
    }

    /**
     * Delete table and options on uninstall
     */
    public function uninstall(){
	    global $wpdb;

	    $wpdb->query("DROP TABLE $wpdb->clientDocumentation");

	    delete_option( 'clientDocumentation_clientRole' );
      delete_option( 'clientDocumentation_dbVersion' );
	    delete_option( 'clientDocumentation_widgetTitle' );
	    delete_option( 'clientDocumentation_itemNumber' );
	    delete_option( 'clientDocumentation_welcomeMessage' );
      delete_option( 'clientDocumentation_welcomeTitle' );
      delete_option( 'clientDocumentation_allitems' );
      delete_option( 'clientDocumentation_pinned' );
      delete_site_option( 'clientDocumentation_table' );

    }

    /**
     * Thanks to App themes.
     * http://docs.appthemes.com/tutorials/wordpress-check-user-role-function/
     *
     * @param string $role Role name
     * @param int $user_id (optionnal) The ID of a user. Defaults to the current user.
     * @return bool
     */
    public function check_user_role($role, $user_id = null){

	    if(is_numeric($user_id))
	    	$user = get_userdata( $user_id );
	    else
	    	$user = wp_get_current_user();

	    if( empty($user) )
	    	return false;

	    return in_array( $role, (array) $user->roles );

    }

	/**
	 * Localisation
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'clientDocumentation', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Install plugin table on activation
	 */
	public function install_tables() {
		global $wpdb;

		$wpdb->hide_errors();

	    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	    $cdoc_tables = "
	    	CREATE TABLE {$wpdb->clientDocumentation} (
			ID bigint(20) NOT NULL auto_increment,
			type varchar(200) NOT NULL default 'note',
			title varchar(255) NOT NULL default 'New document',
			content text NOT NULL,
			etoile_b tinyint(1) NOT NULL default 0,
			etoile_t datetime,
			PRIMARY KEY  (ID) )";
	    dbDelta( $cdoc_tables );
	}

	/**
	 * Add initial data if there's no record yet in the plugin's table.
	 */
	public function set_default_values(){
		global $wpdb;

		update_option('clientDocumentation_clientRole','editor');
		update_option('clientDocumentation_dbVersion','1.0');

		$data = array(
			'type' => '',
			'title' => __( 'How to create your first post', 'clientDocumentation' ),
			'content' => __( 'Example of content' , 'clientDocumentation')
		);
		$count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->clientDocumentation");

		if($count == 0)
			$initial_entries = $wpdb->insert($wpdb->clientDocumentation, $data);
	}

	/**
	 * Register page function. Only available to Administrators
	 */
	public function register_page(){
	  $former_icon = plugins_url( 'client-documentation/img/icon.png' );
    $icon = 'div';

    add_menu_page(
	    	__( 'Simple Documentation' , 'clientDocumentation' ),
	    	__( 'Documentation' , 'clientDocumentation' ),
	    	'manage_options',
	    	'clientDocumentation',
	    	array( $this, 'page_content' ) ,
        $icon
	    );

	    add_action( 'admin_print_styles-clientDocumentation' , array( $this, 'add_admin_styles' ) );
	    add_action( 'admin_print_scripts-clientDocumentation' , array( $this, 'add_admin_scripts' ) );
	}

	/**
	 * Define icon depends on the item type
	 */
	public function icon($type) {

		switch($type){

			case 'note':
				return 'align-left';
			break;
			case 'link':
				return 'link';
			break;
			case 'video':
				return 'youtube-play';
			break;
			case 'image':
				return 'picture';
			break;
			default:
				return 'file';
			break;

		}
	}

	/**
	 * Documentation page content
	 */
	public function page_content(){
		global $wpdb;

		echo '<div class="wrap">';

			if($this->check_user_role('administrator')){

				echo '<div id="icon-edit-pages" class="icon32"></div>';
				echo '<h2>'.__('Simple Documentation','clientDocumentation').'</h2>';

				$entries = $wpdb->get_results("SELECT * FROM $wpdb->clientDocumentation ORDER BY ID DESC");

				?>
				<div class="clearfix">

					<div class="first-part">

						<h2><?php _e( 'Documentation list', 'clientDocumentation' ); ?></h2>
						<ul class="cd-list">

						<?php

							if(!empty($entries)){

								foreach($entries as $data){

							?>

								<li class="cd-list-el" id="cd_list_<?php echo $data->ID; ?>">
								<div class="cd_title">
									<i class="icon-<?php echo $this->icon($data->type); ?>"></i>
									<span class="cd_list_title"><?php echo stripslashes($data->title); ?></span>
									<span class="cd_field_action">
										<a href="#TB_inline?width=350&height=550&inlineId=cd_edit_field" class="thickbox edit_field" data-itemid="<?php echo $data->ID; ?>" data-itemtype="<?php echo $data->type; ?>" title="Edit: <?php echo esc_html(stripslashes($data->title)); ?>"><i class="icon-pencil"></i></a>
										<i class="icon-remove remove_field" data-itemid="<?php echo $data->ID; ?>" title="Remove: <?php echo esc_html(stripslashes($data->title)); ?>"></i>
									</span>
								</div>
								<div class="cd_expand"><?php echo stripslashes($data->content); ?></div>
								</li>

							<?php

								}
							}

						?>

						</ul>
					</div><!-- .first-part -->

					<div class="second-part">

						<div class="cd_info">

							<h2><?php _e( 'Information' , 'clientDocumentation' ); ?></h2>

							<p><?php _e( 'Hit the add button to share advice and great links to your client directly on its interface.', 'clientDocumentation' ); ?><br />
								<?php _e('You can customize titles, content, user roles in','clientDocumentation'); ?> &quot;<?php _e('Edit settings','clientDocumentation'); ?>&quot;.<br />
								<?php _e('Run multiple websites ? Use the Import/Export feature to quickly and easily add content.','clientDocumentation'); ?>
							</p>

							<?php $this->modal(); ?>

						</div>

						<div class="cd_credits">
							<?php _e( 'This plugin was created by', 'clientDocumentation' ); ?> <a href="http://mathieuhays.co.uk">Mathieu HAYS</a> - <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=862ML9VW2NVQU"><?php _e('Donate','clientDocumentation'); ?></a> - <a href="http://twitter.com/mathieuhays">@mathieuhays</a>
						</div>

					</div><!-- .second-part -->

				</div><!-- .clearfix -->

<?php
			} // If is administrator

		echo '</div>';
	}

	/**
	 * Add 'selected' on select <option> which match
	 */
	public function checked($role){

		$data = get_option('clientDocumentation_clientRole');
		if(!empty($data) && $data == $role) return ' selected';

	}

	/**
	 * Add modal on documentation page
	 * > Add content Modal
	 * > Edit settings Modal
	 * > Edit content Modal
	 */
	public function modal(){
		global $wp_roles,$wpdb;
		add_thickbox();

		?>
		<a href="#TB_inline?width=600&height=550&inlineId=cd_add_content" class="thickbox cd_open_modal button-primary first" tabindex="1">
			<i class="icon-plus-sign-alt"></i><?php _e( 'Add new content' , 'clientDocumentation' ); ?>
		</a>

		<a href="#TB_inline?width=350&height=550&inlineId=cd_edit_settings" class="thickbox button-secondary cd_open_modal" tabindex="2">
			<i class="icon-gear"></i><?php _e( 'Edit settings' , 'clientDocumentation' ); ?>
		</a>

    <a href="#TB_inline?width=350&height=550&inlineId=cd_import_export" class="thickbox button-secondary cd_open_modal" tabindex="2">
			<i class="icon-gear"></i><?php _e( 'Import / Export' , 'clientDocumentation' ); ?>
		</a>

		<!--
			[MODAL] Add content modal
		-->
		<div class="cd_modal" id="cd_add_content" style="display:none">

			<!--
				HEADER - Content type switch
			-->
			<div class="cd_tick_header">

				<ul class="clearfix">

					<li class="cd_tick_video" id="cd_tick_video" tabindex="3">
						<i class="icon-youtube-play"></i>
						<?php _e( 'Video' , 'clientDocumentation' ); ?>
					</li>

					<li class="cd_tick_note" id="cd_tick_note" tabindex="7">
						<i class="icon-comment-alt"></i>
						<?php _e( 'Quick note' , 'clientDocumentation' ); ?>
					</li>

					<li class="cd_tick_link" id="cd_tick_link" tabindex="11">
						<i class="icon-link"></i>
						<?php _e( 'Link' , 'clientDocumentation' ); ?>
					</li>

					<li class="cd_tick_file" id="cd_tick_file" tabindex="16">
						<i class="icon-copy"></i>
						<?php _e( 'Upload a file' , 'clientDocumentation' ); ?>
					</li>

				</ul>

			</div><!-- end .cd_tick_header -->

			<!--
				Body - Fields
			-->
			<div class="cd_tick_body">
				<form name="cd_add_content_form" id="cd_add_content_form">

					<!--
						[MODAL] DEFAULT
					-->
					<fieldset class="cd_default">
						<?php _e( 'Choose a type of document !', 'clientDocumentation' ); ?>
					</fieldset><!-- end .cd_default -->

					<!--
						[MODAL] ADD VIDEO
					-->
					<fieldset id="cd_video" style="display:none;">
						<p><input type="text" id="cd_title_video" name="cd_title" placeholder="<?php _e( 'Your title here ...', 'clientDocumentation' ); ?>" tabindex="4"/></p>
						<p>
							<textarea id="cd_content_video" name="cd_content" placeholder="<?php _e( '<embed code here/>', 'clientDocumentation' ); ?>" tabindex="5"></textarea>
						</p>
						<p>
							<?php _e( 'Support &lt;iframe&gt; and &lt;a&gt; that is to say youtube, vimeo, screenr ...', 'clientDocumentation' ); ?>
						</p>
						<div class="button-primary submit_button" id="cd_submit_video" tabindex="6"><?php _e( 'Add a video' , 'clientDocumentation' ); ?></div>
					</fieldset><!-- end #cd_video -->

					<!--
						[MODAL] ADD NOTE
					-->
					<fieldset id="cd_note" style="display:none;">
						<p><input type="text" id="cd_title_note" name="cd_note" placeholder="<?php _e( 'Your title here ...', 'clientDocumentation' ); ?>" tabindex="8"/></p>
						<p>
							<textarea id="cd_content_note" name="cd_content" placeholder="<?php _e( 'Put here a quick note that will help your customer.', 'clientDocumentation' ); ?>" tabindex="9" ></textarea>
						</p>
						<div class="button-primary submit_button" id="cd_submit_note" tabindex="10"><?php _e( 'Add a note' , 'clientDocumentation' ); ?></div>
					</fieldset><!-- end #cd_note -->

					<!--
						[MODAL] ADD LINK
					-->
					<fieldset id="cd_link" style="display:none;">
						<p>
							<input type="text" id="cd_title_link" name="cd_title" placeholder="<?php _e( 'Your title here ...', 'clientDocumentation' ); ?>" tabindex="12" />
						</p>
						<p>
							<input type="url" id="cd_content_link" name="cd_content" placeholder="<?php _e( 'http://agencysite.com/tutorial/part-1' , 'clientDocumentation' ); ?>" tabindex="13"/>
						</p>
						<div class="button-primary submit_button" id="cd_submit_link" tabindex="14"><?php _e( 'Add a link' , 'clientDocumentation' ); ?></div>
					</fieldset><!-- end #cd_link -->

					<!--
						[MODAL] ADD FILE
					-->
					<fieldset id="cd_file" style="display:none;">
						<p><input type="text" id="cd_title_file" name="cd_title" placeholder="<?php _e( 'Your title here ...', 'clientDocumentation' ); ?>" tabindex="16"/></p>
						<p>
							<input type="url" id="cd_content_file" name="cd_content" class="cd_text_upload" placeholder="<?php _e( 'File path / url' , 'clientDocumentation' ); ?>" tabindex="17"/>
							<input type="button" id="cd_button_file" tabindex="18" class="button-secondary cd_button_upload" value="<?php _e( 'Upload a file' , 'clientDocumentation' ); ?>"/>
						</p>
						<div class="button-primary submit_button" tabindex="19" id="cd_submit_file"><?php _e( 'Add a file' , 'clientDocumentation' ); ?></div>
					</fieldset><!-- end #cd_file -->

				</form><!-- end #cd_add_content_form -->
			</div><!-- end .cd_tick_body -->

		</div><!-- #cd_add_content .cd_modal -->

		<!--
			[MODAL] Settings Modal
		-->
		<?php

			$documentation = (get_option('clientDocumentation_widgetTitle')) ? stripslashes(get_option('clientDocumentation_widgetTitle')) : __('Resources', 'clientDocumentation');
			$itemNumber = (get_option('clientDocumentation_itemNumber')) ? get_option('clientDocumentation_itemNumber') : 10;
      $welcomeTitle = (get_option('clientDocumentation_welcomeTitle')) ? stripslashes(get_option('clientDocumentation_welcomeTitle')) : __('Welcome','clientDocumentation');
			$welcomeMessage = (get_option('clientDocumentation_welcomeMessage')) ? stripslashes(esc_html(get_option('clientDocumentation_welcomeMessage'))) : __('Need Help ? All you need is here !','clientDocumentation');
      $allitems = (get_option('clientDocumentation_allitems')) ? stripslashes(get_option('clientDocumentation_allitems')) : __('All items','clientDocumentation');
      $pinned = (get_option('clientDocumentation_pinned')) ? stripslashes(get_option('clientDocumentation_pinned')) : __('Pinned','clientDocumentation');

		?>
		<div class="cd_modal" id="cd_edit_settings" style="display:none">

			<div class="cd_edit_settings">
				<h2><?php _e( 'Settings' , 'clientDocumentation' ); ?></h2>
				<form method="post" name="clientDocumentation_form" action="">

					<input type="hidden" name="clientDocumentation_sbtcr" value="CDMH"/>
					<div class="cd_edition_div2 clearfix">
            <p class="cd_one">
              <label for="clientDocumentation_clientRole"><?php _e( 'Define the client user role' , 'clientDocumentation' ); ?></label><br />
              <select name="clientDocumentation_clientRole" id="clientDocumentation_clientRole">
                <?php
                  $roles = $wp_roles->roles;
                  foreach($roles as $srole => $vrole){
                    echo '<option value="'.$srole.'" '. $this->checked( $srole ) . '>'.__($vrole['name'],'clientDocumentation').'</option>';
                  }
                ?>
              </select>
					  </p>
            <p class="cd_two">
              <label for="clientDocumentation_items_number"><?php _e( 'Number of items displayed per page', 'clientDocumentation' ); ?></label><br />
              <input type="text" name="clientDocumentation_items_number" id="clientDocumentation_items_number" value="<?php echo $itemNumber; ?>" />
            </p>
          </div>
					<div class="cd_edition_div2 clearfix">
            <p class="cd_one">
						  <label for="clientDocumentation_widget_title"><?php _e( 'Widget title', 'clientDocumentation' ); ?></label><br />
						  <input type="text" name="clientDocumentation_widget_title" id="clientDocumentation_widget_title" value="<?php echo $documentation; ?>"/>
					  </p>
            <p class="cd_two">
              <label for="clientDocumentation_welcomeTitle"><?php _e( '"Welcome" title', 'clientDocumentation' ); ?></label><br />
              <input type="text" name="clientDocumentation_welcomeTitle" id="clientDocumentation_welcomeTitle" value="<?php echo $welcomeTitle; ?>" />
            </p>
          </div>
					<p>
						<label for="clientDocumentation_welcomeMessage"><?php _e('Welcome Message','clientDocumentation'); ?></label><br />
						<input type="text" name="clientDocumentation_welcomeMessage" id="clientDocumentation_welcomeMessage" value="<?php echo $welcomeMessage; ?>"/>
					</p>
					<div class="cd_edition_div2 clearfix">
						<p class="cd_one">
                <label for="clientDocumentation_pinned"><?php _e('"Pinned" title','clientDocumentation'); ?></label><br />
                <input type="text" id="clientDocumentation_pinned" name="clientDocumentation_pinned" value="<?php echo $pinned; ?>" />
            </p>
            <p class="cd_two">
                <label for="clientDocumentation_allitems"><?php _e('"All items" title','clientDocumentation'); ?></label><br />
                <input type="text" id="clientDocumentation_allitems" name="clientDocumentation_allitems" value="<?php echo $allitems; ?>" />
            </p>
					</div>
					<div class="button-primary setting_submit" id="cd_setting_submit"><?php _e( 'Save' , 'clientDocumentation' ); ?></div>

				</form>
			</div>

		</div><!-- end #cd_edit_settings .cd_modal -->

		<!--
			[MODAL] Edit field
		-->
		<div class="cd_modal" id="cd_edit_field" style="display:none">

			<div class="cd_field_edition">

				<h2><?php _e( 'Edition', 'clientDocumentation' ); ?></h2>

				<form method="post" name="clientDocumentation_form" action="">

					<p>
						<label for="cd_edit_title"><?php _e('Title', 'clientDocumentation'); ?></label><br />
						<input type="text" id="cd_edit_title" name="cd_edit_title" value="" />
					</p>

					<p id="cd_edit_text" style="display:none">
						<label for="cd_edit_content"><?php _e('Content', 'clientDocumentation'); ?></label><br />
						<input type="text" id="cd_edit_content_text" name="cd_edit_content" value="" />
					</p>
					<p id="cd_edit_textarea" style="display:none">
						<label for="cd_edit_content_textarea"><?php _e('Content','clientDocumentation'); ?></label><br />
						<textarea id="cd_edit_content_textarea" name="cd_edit_content_textarea"></textarea>
					</p>
					<p id="cd_edit_file" style="display:none">
						<label for="cd_edit_content_file"><?php _e('Content', 'clientDocumentation'); ?></label><br />
						<input type="url" id="cd_edit_content_file" name="cd_edit_content_file" class="cd_text_upload" placeholder="<?php _e( 'File path / url' , 'clientDocumentation' ); ?>" value=""/>
						<input type="button" id="cd_button_file" class="button-secondary cd_button_upload" value="<?php _e( 'Upload a file' , 'clientDocumentation' ); ?>"/>
					</p>

					<div class="button-primary edition_submit" id="cd_edition_submit" data-itemid="" data-itemtype=""><?php _e( 'Edit' , 'clientDocumentation' ); ?></div>

				</form>

			</div>

		</div>

    <div class="cd_modal" id="cd_import_export" style="display:none">

			<div class="cd_import_export">
				<div class="cd_import">
          <h2><?php _e('Import','clientDocumentation'); ?></h2>
          <textarea id="cd_import_content"></textarea><br />
          <div class="button-secondary cd_import_button"><?php _e('Import','clientDocumentation'); ?></div>
        </div><!-- .cd_import -->
        <div class="cd_export">
          <h2><?php _e('Export','clientDocumentation'); ?></h2>
          <textarea id="cd_export_action"></textarea>
          <div class="button-secondary cd_export_button"><?php _e('Generate','clientDocumentation'); ?></div>
          <select id="cd_export_options">
            <option value="options" selected><?php _e('Include options','clientDocumentation'); ?></option>
            <option value="no-options"><?php _e('Exclude options','clientDocumentation'); ?></option>
          </select>
          <small><?php _e('if included, options will be overwritten','clientDocumentation'); ?></small>
        </div><!-- .cd_export -->
			</div>

		</div><!-- end #cd_edit_settings .cd_modal -->

		<?php
	}

	/**
	 * Handle all ajax request of this plugin
	 * Return a response in JSON format
	 */
	public function ajaxhandle(){

		global $wpdb;
		$empty_fields = array();

		if( $_POST['action'] == 'cd_ajax' ):

			if( $_POST['cd_action'] == 'add_content'){

				/*
					Add content request
				*/

				if( empty( $_POST['cd_type'] ) ) $empty_fields[] = 'type';
				if( empty( $_POST['cd_title'] ) ) $empty_fields[] = 'title';
				if( empty( $_POST['cd_content'] ) ) $empty_fields[] = 'content';

				if(!empty($empty_fields)){

					$response = array(
						'issue' => 'missing-fields',
						'data' => $empty_fields
					);
					echo json_encode($response);
					die();

				}

				$type = sanitize_text_field( $_POST[ 'cd_type' ] );
				$title = sanitize_text_field( $_POST[ 'cd_title' ] );
				if(in_array($type, array( 'link', 'file' ) ) ) $content = esc_url_raw( $_POST[ 'cd_content' ] );
        elseif($type == 'note') $content = $this->support_code($_POST['cd_content']);
				elseif($type == 'video') $content = wp_kses(nl2br($_POST['cd_content']), $this->allowed_html);
				else $content = sanitize_text_field( $_POST[ 'cd_content' ] );

				$table_name = $wpdb->clientDocumentation;
				$data = array(
					'type' => $type,
					'title' => $title,
					'content' => $content
				);
				if($wpdb->insert($table_name, $data)){

					$response = array(
						'issue' => 'success',
						'data' => array(
							'ID' => $wpdb->insert_id,
							'type' => $type,
							'title' => $title,
							'content' => $content
						)
					);
					echo json_encode($response);
					die();

				}else{

					$response = array(
						'issue' => 'error-insert',
						'data' => __( 'Error while adding your new document, please try again later.' , 'clientDocumentation' )
					);
					echo json_encode($response);
					die();

				}
			}elseif( $_POST['cd_action'] == 'remove' ){

				/*
					Remove item request
				*/

				if( is_numeric($_POST['cd_id']) ){


					$data = array(
						'ID' => $_POST['cd_id']
					);

					$table_name = $wpdb->clientDocumentation;

					if( $wpdb->delete($table_name, $data, array('%d') ) ){

						$response = array(
							'issue' => 'success-remove',
							'data' => array(
								'ID' => $_POST['cd_id']
							)
						);
						echo json_encode($response);
						die();

					}else{

						$response = array(
							'issue' => 'error-delete',
							'data' => __( 'Error while deleting the entry. Please try again later.' , 'clientDocumentation' )
						);
						echo json_encode($response);
						die();
					}


				}else{
					$response = array(
						'issue' => 'error-nan',
						'data' => __( 'Error on process', 'clientDocumentation' )
					);
					echo json_encode($response);
					die();

				}

			}elseif( $_POST['cd_action'] == 'manage_settings' ){

				/*
					Edit settings request
				*/
				if(isset($_POST['cd_clientRole'], $_POST['cd_widgetTitle'], $_POST['cd_itemNumber'], $_POST['cd_welcomeMessage'], $_POST['cd_welcomeTitle'], $_POST['cd_allitems'])){

					if(is_numeric($_POST['cd_itemNumber']))
					update_option( 'clientDocumentation_itemNumber', $_POST['cd_itemNumber']);
					update_option( 'clientDocumentation_clientRole', $_POST['cd_clientRole']);
					update_option( 'clientDocumentation_widgetTitle', sanitize_text_field($_POST['cd_widgetTitle']));
          update_option( 'clientDocumentation_welcomeTitle', sanitize_text_field($_POST['cd_welcomeTitle']));
					update_option( 'clientDocumentation_welcomeMessage', $_POST['cd_welcomeMessage']);
          update_option( 'clientDocumentation_allitems', sanitize_text_field($_POST['cd_allitems']));
          update_option( 'clientDocumentation_pinned' , sanitize_text_field($_POST['cd_pinned']));

					$response = array(
						'issue' => 'success',
						'data' => __( 'Options updated' , 'clientDocumentation' )
					);

				}else{

					$response = array(
						'issue' => 'error',
						'data' => __( 'One or more fields are missing !' , 'clientDocumentation' )
					);

				}

				echo json_encode($response);
				die();

			}elseif( $_POST['cd_action'] == 'manage_stars' ){

				/*
					Manage Star on widget. (When user pin an item)
				*/

				if(isset($_POST['cd_itemid']) && is_numeric($_POST['cd_itemid'])){

					$etoile_b = $wpdb->get_var( $wpdb->prepare(
						"SELECT etoile_b FROM $wpdb->clientDocumentation WHERE ID = %d ",
						$_POST['cd_itemid']
						)
					);

					if($etoile_b)
						$values = array( 'etoile_b' => 0, 'etoile_t' => 0 );
					else
						$values = array( 'etoile_b' => 1, 'etoile_t' => date("Y-m-d H:i:s") );


					$where = array( 'ID' => $_POST['cd_itemid'] );
					$formatv = array( '%d', '%d' );
					$formatw = array( '%d' );

					if( $wpdb->update( $wpdb->clientDocumentation , $values , $where , $formatv , $formatw ) ){

						$response = array(
							'issue' => 'success',
							'data' => array(
								'etoile_b' => $values['etoile_b'],
								'etoile_t' => $values['etoile_t'],
								'ID' => $_POST['cd_itemid']
							)
						);

					}else{

						$response = array(
							'issue' => 'error',
							'data' => __( 'Error !', 'clientDocumentation' )
						);

					}

					echo json_encode($response);
					die();
				}
			}elseif( $_POST['cd_action'] == 'edit_field' ){

				/*
					Item edition request
				*/

				if(is_numeric($_POST['cd_itemid'])){

					if(isset($_POST['cd_title'],$_POST['cd_content'],$_POST['cd_type'])){

						$title = sanitize_text_field($_POST['cd_title']);
						$type = $_POST['cd_type'];



						if(in_array($type, array('file','link'))) $content = esc_url_raw( $_POST['cd_content'] );
            elseif($type == 'note') $content = $this->support_code( $_POST['cd_content'] );
						elseif($type == 'video') $content = wp_kses( nl2br($_POST['cd_content']), $this->allowed_html );
						else $content = sanitize_text_field($_POST['cd_content']);

            $dbval = $wpdb->get_row("SELECT title,content FROM $wpdb->clientDocumentation WHERE ID='".mysql_real_escape_string($_POST['cd_itemid'])."' LIMIT 0,1");

            if($dbval->title == $title && $dbval->content == $content){
              $response = array(
                'issue' => 'success',
                'data' => array(
                  'ID' => $_POST['cd_itemid'],
                  'title' => $title,
                  'content' => $content,
                  'type' => $type
                )
              );
              echo json_encode($response);
              die();
            }

						$values = array(
							'title' => $title,
							'content' => $content
						);
						$where = array( 'ID' => $_POST['cd_itemid'] );
						$formatv = array( '%s','%s' );
						$formatw = array( '%d' );

						if( $wpdb->update( $wpdb->clientDocumentation , $values , $where , $formatv , $formatw ) ){

							$response = array(
								'issue' => 'success',
								'data' => array(
									'ID' => $_POST['cd_itemid'],
									'title' => $title,
									'content' => $content,
									'type' => $type
								)
							);

						}else{

							$response = array(
								'issue' => 'error',
								'data' => __( 'Update error, please try again later.', 'clientDocumentation' )
							);

						}

					}else{

						$response = array(
							'issue' => 'error',
							'data' => __( 'Some fields are empty', 'clientDocumentation' )
						);

					}

				}else{

					$response = array(
						'issue' => 'error',
						'data' => __( 'Error while processing, Please try again later.', 'clientDocumentation' )
					);

				}

				echo json_encode($response);
				die();

    }elseif($_POST['cd_action'] == 'export'){

        if(isset($_POST['cd_spec']) && $_POST['cd_spec'] == 'no-options'){ $options = false; }
        else{

          $options = array();
          $options_q = array( 'itemNumber','clientRole','widgetTitle','welcomeTitle','welcomeMessage','allitems','pinned' );

          foreach($options_q as $opt){
            $options[$opt] = (get_option('clientDocumentation_' . $opt)) ? get_option('clientDocumentation_' . $opt) : false;
          }
        }

        $items = array();
        $item_r = array('type','title','content','etoile_b','etoile_t');

        $query = $wpdb->get_results("SELECT * FROM $wpdb->clientDocumentation ORDER BY ID ASC");
        foreach($query as $q){
          $data = array();

          foreach($item_r as $r){
            if(in_array($r,array('title','content')))
               $data[$r] = stripslashes($q->$r);
            else
               $data[$r] = $q->$r;
          }

          $items[$q->ID] = $data;
        }



        echo json_encode( array( 'options' => $options, 'items' => $items ) );
        die();

    }elseif($_POST['cd_action'] == 'import'){

        if(empty($_POST['cd_content'])){
          $response = array(
            'issue' => 'error',
            'data' => __( 'One or more fields are missing !' , 'clientDocumentation' )
          );
          echo json_encode($response);
          die();
        }

        $table_name = $wpdb->clientDocumentation;
        $data = json_decode(stripslashes($_POST['cd_content']));
        $error = 0;

        if($data->options != false){
          $opts = array('itemNumber','clientRole','widgetTitle','welcomeTitle','welcomeMessage','allitems','pinned');
          foreach($opts as $opt){
            if($data->options->$opt != false)
              update_option('clientDocumentation_' . $opt, $data->options->$opt);
          }
        }

        foreach($data->items as $item){


          $data = array(
            'type' => $item->type,
            'title' => $item->title,
            'content' => $item->content,
            'etoile_b' => $item->etoile_b,
            'etoile_t' => $item->etoile_t
          );
          if(!$wpdb->insert($table_name, $data))
            $error++;

        }

        if($error!=0) $response = array( 'issue' => 'error', 'data' => __('Import error','clientDocumentation') );
        else $response = array( 'issue' => 'success', 'data' => __('Import completed','clientDocumentation') );
        echo json_encode($response);
        die();

		}else{

			$response = array(
				'issue' => 'error-action',
				'data' => __( 'Error, please contact the administrator.' , 'clientDocumentation' )
			);
			echo json_encode($response);
			die();
		}
		endif;
	}

  public function support_code($content){

    $clean='';
    $code = explode('<code>',$content);
    $clean = wp_kses(nl2br($code[0]), $this->allowed_note);
    for($i=1;$i<count($code);$i++){
      $clean .= '<code>';
      $htmlctt = explode('</code>',$code[$i]);
      $clean .= preg_replace( '/<br\W*?\/>/' ,'', nl2br( htmlentities( $htmlctt[0] ) ), 1);
      $clean .= '</code>';
      $clean .= wp_kses(nl2br($htmlctt[1]), $this->allowed_note);
    }

    return $clean;

  }

	/**
	 * Dashboard widget content
	 */
	public function dashboard_widget(){
		global $wpdb;

		$count_item = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->clientDocumentation ORDER BY ID DESC");
		$nmb_setting = (get_option('clientDocumentation_itemNumber')) ? get_option('clientDocumentation_itemNumber') : 10;
		$welcomeTitle = (get_option('clientDocumentation_welcomeTitle')) ? stripslashes(get_option('clientDocumentation_welcomeTitle')) : __('Welcome','clientDocumentation');
    $welcomeMessage = (get_option('clientDocumentation_welcomeMessage')) ? stripslashes(get_option('clientDocumentation_welcomeMessage')) : __('Need Help ? All you need is here !','clientDocumentation');
    $allitems = (get_option('clientDocumentation_allitems')) ? stripslashes(get_option('clientDocumentation_allitems')) : __('All items','clientDocumentation');
    $pinned = (get_option('clientDocumentation_pinned')) ? stripslashes(get_option('clientDocumentation_pinned')) : __('Pinned','clientDocumentation');

		if(isset($_GET['cdp']) && is_numeric($_GET['cdp'])) $currentpage = $_GET['cdp'];
		else $currentpage = 1;

		$nombredepages = ceil($count_item / $nmb_setting);
		$limitmin = ( $currentpage * $nmb_setting ) - $nmb_setting;
		$limit = $limitmin.', '.$nmb_setting;

		echo '<h4 class="cd_wdg_title first">' . $welcomeTitle . '</h4>';
		echo '<p>' . $welcomeMessage . '</p>';

		/* Pinned items section - Max: 3 */
		$star_item_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->clientDocumentation WHERE etoile_b='1' ORDER BY ID DESC");

		if($star_item_count != 0){

			$item_s = $wpdb->get_results("SELECT * FROM $wpdb->clientDocumentation WHERE etoile_b='1' ORDER BY etoile_t DESC LIMIT 0,3");
			echo '<h4 class="cd_wdg_title">' . $pinned . ' :</h4>';
			echo '<ul class="cd_client_star">';

			foreach($item_s as $s){

				echo '<li>';

				if( in_array($s->type, array('link','file')) ){
				echo '<h5><i class="icon-star cd_star cdpin" data-itemid="' . $s->ID . '"></i>';
				echo '<i class="icon-' . $this->icon($s->type) . '"></i> <a href="' . $s->content . '" target="_blank">' . stripslashes($s->title) . '</a>';
				echo '<span class="cd_expand_hover">' . __( 'Open in a new tab', 'clientDocumentation' ) . '</span></h5>';
			}
			else{
				echo '<h5 class="cd_widget_title"><i class="icon-star cd_star cdpin" data-itemid="' . $s->ID . '"></i>';
				echo '<i class="icon-' . $this->icon($s->type) . '"></i> ' . stripslashes($s->title) . ' <span class="cd_expand_hover">' . __( 'Click to view', 'clientDocumentation' ) . '</span></h5>';
				echo '<div class="cd_widget_content"><p>' . stripslashes($s->content) . '</p></div>';
			}

				echo '</li>';

			}

			echo '</ul>';

		}

		/* Main section - All items */

		// Handle pagination
		$count_item = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->clientDocumentation ORDER BY ID DESC");
		$nmb_setting = (get_option('clientDocumentation_itemNumber')) ? get_option('clientDocumentation_itemNumber') : 10;

		if(isset($_GET['cdp']) && is_numeric($_GET['cdp'])) $currentpage = $_GET['cdp'];
		else $currentpage = 1;

		$nombredepages = ceil($count_item / $nmb_setting);
		$limitmin = ( $currentpage * $nmb_setting ) - $nmb_setting;
		$limit = $limitmin.', '.$nmb_setting;

		$entries = $wpdb->get_results("SELECT * FROM $wpdb->clientDocumentation ORDER BY ID DESC LIMIT ".$limit);

		echo '<h4 class="cd_wdg_title">'. $allitems .'</h4>';
		echo '<ul class="cd_client_list">';
		foreach($entries as $data){

			echo '<li>';
			$clstar = ($data->etoile_b == 0) ? '-empty' : '';
			if( in_array($data->type, array('link','file')) ){
				echo '<h5><i class="icon-star'.$clstar.' cd_star cdpin" data-itemid="'. $data->ID .'"></i>';
				echo '<i class="icon-' . $this->icon($data->type) . '"></i> <a href="' . $data->content . '" target="_blank">' . stripslashes($data->title) . '</a>';
				echo '<span class="cd_expand_hover">' . __( 'Open in a new tab', 'clientDocumentation' ) . '</span></h5>';
			}
			else{
				echo '<h5 class="cd_widget_title"><i class="icon-star'.$clstar.' cd_star cdpin" data-itemid="' . $data->ID . '"></i>';
				echo '<i class="icon-' . $this->icon($data->type) . '"></i> ' . stripslashes($data->title) . ' <span class="cd_expand_hover">' . __( 'Click to view', 'clientDocumentation' ) . '</span></h5>';
				echo '<div class="cd_widget_content"><p>' . stripslashes($data->content) . '</p></div>';
			}
			echo '</li>';

		}
		echo '</ul>';

		/* Pagination */
		if($nombredepages != 1){
			echo "<ul class='cd_page'>";
			echo "<li>".__('Pages','clientDocumentation').":</li>";
			for($i=1;$i<=$nombredepages;$i++){
				echo '<li><a href="?cdp='.$i.'"';
				if($currentpage == $i) echo ' class="cd_current"';
				echo '>'.$i.'</a></li>';
			}
			echo '</ul>';
		}
	}
}
if(is_admin()) new clientDocumentation();
?>