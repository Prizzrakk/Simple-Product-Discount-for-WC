<?php
/**
 * Plugin Name: Simple Product Discounts
 * Plugin URI: 
 * Description: Product price based on the products quantity for Woocommerce.
 * Version: 1.0.0
 * Author: Prizzrak
 * Author URI: https://github.com/Prizzrakk
 * Donate URI: https://github.com/Prizzrakk
 * Text Domain: simple-product-discounts
 * Domain Path: /languages/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Requires PHP: 7.2
 * WC tested: 5.7.1
 *
 * Copyright 2021 Prizzrak
 *
 * This program is free software; you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation; either version 2 of the License, or
 *     (at your option) any later version.
 *
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 *
 *     You should have received a copy of the GNU General Public License
 *     along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
 
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'SIMPLE_PD', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'SIMPLE_PD_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'SIMPLE_PD_VER', '1.0' );
define( 'SIMPLE_PD_BASENAME', plugin_basename( __FILE__ ) );

if ( ! class_exists( 'Simple_Product_Discounts' ) ) {

class Simple_Product_Discounts {

    /* Hold the class instance. */
    private static $instance = null;

    /* Array of all options */
    private $options;

	/* Maximum additional fields */
	private $max_fields_count = 10;
	
	/* Start up */
    public function __construct() {
        $this->options = $this->get_options();
		$this->init_();
	}

    public static function get_instance() {
        if ( self::$instance == null ) self::$instance = new Simple_Product_Discounts();
        return self::$instance;
    }

    public function init_() {
		// Set up localisation
		add_action( 'init', array( $this, 'load_plugin_textdomain' ), 0 );
		// Add menu in admin panel under "Settings"
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		//Load admin css and js
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		// Check if WooCommerce is active
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			if ($this->options['active']) {
				add_action( 'admin_init', array( $this, 'adm_init' ) );
				$this->usr_init();
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_user_scripts' ) );
			}
		}
		// Add Support link at plugin description in Plugins page
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		// Add Settings link at plugin description in Plugins page
		add_filter( 'plugin_action_links', array( $this, 'plugin_action_links' ), 10, 2 );
    }

	/* Loads the plugin text domain for translation */
	public function load_plugin_textdomain() {
		load_textdomain( 'simple-product-discounts', SIMPLE_PD_DIR . 'languages/simple-product-discounts-' . determine_locale() . '.mo' );
		load_plugin_textdomain( 'simple-product-discounts', false, dirname( SIMPLE_PD_BASENAME ) . '/languages/' );
	}

    /* Get options data */
    function get_options() {
		$simple_pdq = get_option('simple_pdq');
		if (empty($simple_pdq['max_fields_count'])) $simple_pdq['max_fields_count'] = $this->max_fields_count;
		if (empty($simple_pdq['location'])) $simple_pdq['location'] = 'custom';
		if (!isset($simple_pdq['active'])) $simple_pdq['active'] = false;
		if (!isset($simple_pdq['display_total'])) $simple_pdq['display_total'] = false;
		if (!isset($simple_pdq['display_cart'])) $simple_pdq['display_cart'] = false;
		if (!isset($simple_pdq['display_checkout'])) $simple_pdq['display_checkout'] = false;
        return $simple_pdq;
    }

    /* Admin init actions, ajax calls */
    public function adm_init() {
		add_action( 'woocommerce_product_options_pricing', array( $this, 'pdq_woo_add_custom_fields') );
		add_action( 'woocommerce_process_product_meta', array( $this, 'pdq_woo_custom_fields_save'), 15 );
		add_action( 'wp_ajax_pdq_ajax_add_field', array( $this, 'pdq_ajax_add_field' ) );
		add_action( 'wp_ajax_nopriv_pdq_ajax_add_field', array( $this, 'pdq_ajax_add_field' ) );
		add_action( 'wp_ajax_pdq_ajax_upd_fields', array( $this, 'pdq_ajax_upd_fields' ) );
		add_action( 'wp_ajax_nopriv_pdq_ajax_upd_fields', array( $this, 'pdq_ajax_upd_fields' ) );
		// get current time
		$current_time = time();
		// set activation date
		$activation_date = get_option( 'simple_pdq__activation' );
		if ( $activation_date === false ) {
			update_option( 'simple_pdq__activation', $current_time, false );
			$activation_date = $current_time;
		}
		// get date when notice was closed
		$notice_date = get_option( 'simple_pdq__notice' );
		if ( $notice_date === false ) { $notice_date = $activation_date; }
		// display notice first time after 7 days, remind every day after close
		if ( $activation_date + 608400 <= $current_time && $notice_date + 86400 <= $current_time ) {
			$this->activation_date = $activation_date;
			add_action( 'admin_notices', array( $this, 'admin_notice__info' ) );
			/* Close/remove info notice with ajax */
			add_action( 'wp_ajax_simple_pdq_remove_notification', array( $this, 'simple_pdq_remove_notification' ) );
			add_action( 'wp_ajax_nopriv_simple_pdq_remove_notification', array( $this, 'simple_pdq_remove_notification' ) );
		}
	}

    /* Enqueue Admin Scripts */
    public function enqueue_admin_scripts() {
        global $pagenow;
        if ( $pagenow == 'post.php' || get_current_screen()->id == 'settings_page_simple_pdq' ) {
            wp_enqueue_style( 'product_dq_settings', SIMPLE_PD . 'assets/css/admin.css', array('wp-color-picker'), SIMPLE_PD_VER );
            wp_enqueue_script( 'product_dq_settings', SIMPLE_PD . 'assets/js/admin.js', array( 'jquery' ), SIMPLE_PD_VER, true );
        }
    }

    /* Add options page */
    public function add_plugin_page() {
        // This page will be under "Settings"
		//add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
        add_options_page(
            esc_html__( 'Simple Product Discounts based on Quantity', 'simple-product-discounts' ),
            esc_html__( 'Simple Product Discounts', 'simple-product-discounts' ),
            'manage_options',
			'simple_pdq',
            array( $this, 'print_settings_page' )
        );
    }

    /* Display admin notice */
	public function admin_notice__info() {
		$time_passed = round( ( time() - $this->activation_date ) / 86400, 0 );
		echo '<div class="simple_pdq-notice notice notice-info is-dismissible"><p>',
				sprintf( __( 'Thank you for using Simple Product Discounts plugin for %1$s days already. It would be great if you <a href="%2$s">support</a> the author!', 'simple-product-discounts' ), $time_passed, 'https://github.com/Prizzrakk' ),
			 '</p></div>';		
	}

	/* Close/remove info notice with ajax */
	public function simple_pdq_remove_notification() {
		update_option( 'simple_pdq__notice', time(), false );
	}

	/* Clear all data - delete all made discounts for all products */
	public function simple_pdq_clear_data() {
		global $wpdb;    
		$result = $wpdb->get_results( "DELETE FROM wp_postmeta WHERE meta_key LIKE '_price_field_%' OR meta_key LIKE '_count_field_%' OR meta_key='_fields_count'");
		$result = $wpdb->get_results( "DELETE FROM wp_options WHERE option_name LIKE 'simple_pdq%'");
        $this->options = $this->get_options();
		echo '<div id="message" class="updated fade"><p><strong>'.__('Plugin settings and all plugin data deleted!', 'simple-product-discounts').'</strong></p></div>';
	}

    /* Options page callback */
    public function print_settings_page() {
		if(isset($_POST['clear_spd'])) $this->simple_pdq_clear_data();
		if(isset($_POST['simple_pdq'])) {
			update_option('simple_pdq', $_POST['simple_pdq'], false);
			echo '<div id="message" class="updated fade"><p><strong>'.__('Settings saved!', 'simple-product-discounts').'</strong></p></div>';
			$simple_pdq = $_POST['simple_pdq'];
		} else {
			$simple_pdq = $this->options;
		}
?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<hr/>
<?php
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) :
 ?>
            <form method="post" action="" class="simple_profuct_discount_form">
				<h3><?php echo _e('Main settings', 'simple-product-discounts'); ?></h3>
				<p class="clearfix">
					<label>
						<input type="checkbox" id="active_pdq" class="" name="simple_pdq[active]" value="true" <?php echo (isset($simple_pdq['active']) && $simple_pdq['active']) ? 'checked="checked"' : ''; ?> />
						<?php echo __('Activate Product Discounts', 'simple-product-discounts'); ?>
					</label>
				</p>
				<p class="clearfix">
					<label><?php echo __('Max number of discount fields (default 10):', 'simple-product-discounts'); ?></label>
					<input type="number" id="max_fields_count" class="" name="simple_pdq[max_fields_count]" value="<?php echo (isset($simple_pdq['max_fields_count'])) ? stripslashes($simple_pdq['max_fields_count']) : '' ; ?>" placeholder="<?php echo __( 'Max number of discount fields', 'simple-product-discounts' ); ?>" />
				</p>
				<hr/>
				<h3><?php echo __('Product page settings', 'simple-product-discounts'); ?></h3>
				<div class="clearfix">
					<p><?php echo __('Location of discounts list:', 'simple-product-discounts'); ?></p>
					<?php $this->print_location_field($simple_pdq['location']); ?>
				</div>
				<p class="clearfix">
					<label>
						<input type="checkbox" id="display_total" class="" name="simple_pdq[display_total]" value="true" <?php echo (isset($simple_pdq['display_total']) && $simple_pdq['display_total']) ? 'checked="checked"' : ''; ?> />
						<?php echo __('Calculate and display total price after add to cart quantity', 'simple-product-discounts'); ?>
					</label>
				</p>
				<hr/>
				<h3><?php echo __('Cart/checkout page settings', 'simple-product-discounts'); ?></h3>
				<p class="clearfix">
					<label>
						<input type="checkbox" id="display_cart" class="" name="simple_pdq[display_cart]" value="true" <?php echo (isset($simple_pdq['display_cart']) && $simple_pdq['display_cart']) ? 'checked="checked"' : ''; ?> />
						<?php echo __('Use default output of price and total price for each product in cart and checkout', 'simple-product-discounts'); ?>
					</label>
				</p>
				<hr/>
				<input type="submit" value="<?php echo __('Save', 'simple-product-discounts'); ?>" class="button-primary" />
            </form>
            <form method="post" action="" class="simple_profuct_discount_clear_form">
				<hr/>
				<p class="clearfix">
					<?php echo __('Clear all data - will delete plugin settings and all made discounts for all products!', 'simple-product-discounts'); ?>
				</p>
				<input type="submit" value="<?php echo __('! Clear all data !', 'simple-product-discounts'); ?>" name="clear_spd" class="button-primary" />
            </form>
<?php
		else :
			echo '<div id="notice" class="error"><p><strong>'.__('Woocommerce not found! Install Woocommerce to use plugin.', 'simple-product-discounts').'</strong></p></div>';
		endif;
 ?>
        </div>
        <?php
    }
	
    /* Print Locations radio buttons */
    public function print_location_field( $args ) {

        printf(
            '<p><label><input type="radio" id="simple_pdq_location_price" name="simple_pdq[location]" value="%s" %s/> %s</label></p>',
            'price',
            checked( $args, 'price', false ),
            __( 'After price', 'simple-product-discounts' )
        );
        printf(
            '<p><label><input type="radio" id="simple_pdq_location_bform" name="simple_pdq[location]" value="%s" %s/> %s</label></p>',
            'b_form',
            checked( $args, 'b_form', false ),
            __( 'Before add to cart form', 'simple-product-discounts' )
        );
        printf(
            '<p><label><input type="radio" id="simple_pdq_location_button" name="simple_pdq[location]" value="%s" %s/> %s</label></p>',
            'button',
            checked( $args, 'button', false ),
            __( 'After add to cart button', 'simple-product-discounts' )
        );
        printf(
            '<p><label><input type="radio" id="simple_pdq_location_aform" name="simple_pdq[location]" value="%s" %s/> %s</label></p>',
            'a_form',
            checked( $args, 'a_form', false ),
            __( 'After add to cart form', 'simple-product-discounts' )
        );
        printf(
            '<p><label><input type="radio" id="simple_pdq_location_custom" name="simple_pdq[location]" value="%s" %s/> %s</label></p>',
            'custom',
            checked( $args, 'custom', false ),
            __( 'Custom (template tag) use:', 'simple-product-discounts' ) . ' <code> do_action(\'simple_pdq\'); </code>'
        );

    }

	/* Add field in admin panel by ajax call */
	public function pdq_ajax_add_field() {
		$fields_count = $_POST["_fields_count"];
		if ($fields_count+1 <= $this->options['max_fields_count']) {
			$fields_count++;
			$f = $fields_count;
			$insert = '<div class="discount_field">
						<p class="form-field _count_field_'.$f.'_field ">
							<label for="_count_field_'.$f.'">'.__( 'Amount, from', 'simple-product-discounts' ).'</label>
							<input type="number" class="short" name="_count_field_'.$f.'" id="_count_field_'.$f.'" value="" placeholder="'.__( 'Quantity of goods', 'simple-product-discounts' ).'"> </p>
						<p class="form-field _price_field_'.$f.'_field ">
							<label for="_price_field_'.$f.'">'.__( 'Price', 'simple-product-discounts' ).'</label>
							<input type="number" class="short" name="_price_field_'.$f.'" id="_price_field_'.$f.'" value="" placeholder="'.__( 'Discounted price', 'simple-product-discounts' ).'"> </p>
					   </div>';
		} else {
			$insert = '';
		}		
			$returnResponse = array("code" => "success", "count" => $fields_count, "insert" => $insert);
			echo json_encode($returnResponse);
            die();
	}
	
	/* Update fields in admin panel by ajax call */
	/* Save only filled fields, delete empty */
	public function pdq_ajax_upd_fields() {
		$post_id = $_POST["post_ID"];
		for ($f = 1; $f <= $this->options['max_fields_count']; $f++) {
			if ( isset( $_POST['_count_field_'.$f] ) && isset( $_POST['_price_field_'.$f] ) &&
				!empty( $_POST['_count_field_'.$f] ) && !empty( $_POST['_price_field_'.$f] ) ) {
				update_post_meta( $post_id, '_count_field_'.$f, sanitize_text_field(wp_unslash( $_POST['_count_field_'.$f] )) );
				update_post_meta( $post_id, '_price_field_'.$f, sanitize_text_field(wp_unslash( $_POST['_price_field_'.$f] )) );
				$fields_count = $f;
			} else {
				delete_post_meta( $post_id, '_count_field_'.$f );
				delete_post_meta( $post_id, '_price_field_'.$f );
			}
		}
		if ($fields_count == '') $fields_count = 0;
		update_post_meta( $post_id, '_fields_count', $fields_count );
			$returnResponse = array("code" => "success", "count" => $fields_count);
			echo json_encode($returnResponse);
            die();
	}
	
	/* Display buttons (Add and Unpade) and fields (if have) in admin panel product page after price */
	public function pdq_woo_add_custom_fields() {
		echo '<hr /><div class="discount_group"><p><strong>'.__( 'Price based on the quantity', 'simple-product-discounts' ).'</strong></p>';
			$fields_count = get_post_meta( get_the_ID(), '_fields_count', true );
			if ($fields_count == '') $fields_count = 0;
			woocommerce_wp_hidden_input([
				'id'    => '_fields_count',
				'value' => $fields_count,
			]);
		if ($fields_count > 0) {
			for ($f = 1; $f <= $fields_count; $f++) {
				echo '<div class="discount_field">'; 
					woocommerce_wp_text_input( array(
					   'id'                => '_count_field_'.$f,
					   'label'             => __( 'Amount, from', 'simple-product-discounts' ),
					   'placeholder'       => __( 'Quantity of goods', 'simple-product-discounts' ),
					   'type'              => 'number',
					) );
					woocommerce_wp_text_input( array(
					   'id'                => '_price_field_'.$f,
					   'label'             => __( 'Price', 'simple-product-discounts' ),
					   'placeholder'       => __( 'Discounted price', 'simple-product-discounts' ),
					   'type'              => 'number',
					) );

				echo '</div>';
			}
		}
			echo '<div class="discount_field_buttons">
					<button type="submit" name="discount_add_field_button" class="discount_add_field_button button">'.__('Add field','simple-product-discounts').'</button>
					<button type="submit" name="discount_upd_field_button" class="discount_upd_field_button button">'.__('Update fields','simple-product-discounts').'</button>
					<span class="loaderimage"></span>
				</div>';
		echo '</div>';
	}
	/* Update/Save fields in admin panel by Save/Update global button */
	/* Save only filled fields, delete empty */
	public function pdq_woo_custom_fields_save( $post_id ) {
		for ($f = 1; $f <= $this->options['max_fields_count']; $f++) {
			if ( isset( $_POST['_count_field_'.$f] ) && isset( $_POST['_price_field_'.$f] ) &&
				!empty( $_POST['_count_field_'.$f] ) && !empty( $_POST['_price_field_'.$f] ) ) {
				update_post_meta( $post_id, '_count_field_'.$f, sanitize_text_field(wp_unslash( $_POST['_count_field_'.$f] )) );
				update_post_meta( $post_id, '_price_field_'.$f, sanitize_text_field(wp_unslash( $_POST['_price_field_'.$f] )) );
				$fields_count = $f;
			} else {
				delete_post_meta( $post_id, '_count_field_'.$f );
				delete_post_meta( $post_id, '_price_field_'.$f );
			}
		}
		if ($fields_count == '') $fields_count = 0;
		update_post_meta( $post_id, '_fields_count', $fields_count );
	}
/********************************** Frontend part ********************************************/	
    /* User init actions at frontend */
    public function usr_init() {
        switch ( $this->options['location'] ) { //set location of discounts
        case 'price':  // after price
			add_action( 'woocommerce_single_product_summary', array( $this, 'display_simple_product_discounts' ), 15 );
            break;
        case 'b_form': // after price but before add to cart form
			add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'display_simple_product_discounts' ), 15 );
            break;
        case 'button': // after add to cart button
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'display_simple_product_discounts' ), 15 );
            break;
        case 'a_form': // after add to cart form
			add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'display_simple_product_discounts' ), 15 );
            break;
        default: // use code
			add_action( 'simple_pdq', array( $this, 'display_simple_product_discounts' ) );
            break;
        }
		// Display or not Total summ in product page
		if ($this->options['display_total']) add_action( 'woocommerce_after_add_to_cart_quantity', array( $this, 'display_total_summ' ), 15 );
		// Calculate and set new price for each product in cart / checkout
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'new_product_dq_price' ), 10 );
		if (!$this->options['display_cart']) {
			// Show each product regular and sale price in cart
			add_filter( 'woocommerce_cart_item_price', array( $this, 'my_woocommerce_cart_item_price' ), 10, 3 );
			// Show Total regular and sale price in cart/checkout
			add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'my_woocommerce_cart_item_subtotal' ), 10, 3 );
		}
    }
    /* Enqueue User Scripts */
    public function enqueue_user_scripts() {
		if ( is_product() ) {
            wp_enqueue_style( 'simple-product-discounts_styles', SIMPLE_PD . 'assets/css/simple-product-discounts.css', false, SIMPLE_PD_VER );
        }
		if ( is_product() && $this->options['display_total'] ) { // no Total summ - no need jQuery
            wp_enqueue_script( 'simple-product-discounts_settings', SIMPLE_PD . 'assets/js/simple-product-discounts.js', array( 'jquery' ), SIMPLE_PD_VER, true );
        }
    }

    /* Print total summ under product quantity - needs jQuery to update */
	public function display_total_summ() {
		$curr_position = $this->curr_position();
		echo '<div class="clearfix"></div>
			<div class="discount_price_">
				<span class="price_title">'.__('Price', 'simple-product-discounts').': </span>'.
				$curr_position['before'].'<span class="price_price">'.get_post_meta( get_the_ID(), '_price', true).'</span>'.$curr_position['after'].'
			</div>
			<div class="discount_total_">
				<span class="price_title">'.__('Total', 'simple-product-discounts').': </span>'.
				$curr_position['before'].'<span class="total_price">'.get_post_meta( get_the_ID(), '_price', true).'</span>'.$curr_position['after'].'
			</div>';
	}
	
    /* Print avaible product discounts */
	public function display_simple_product_discounts() {
		$curr_position = $this->curr_position();
		$content = '';
		$before = '<div class="discount_price_group clearfix">';
			$before_inner = '<div class="discount_price_field">';
				$before_inner_qty = '<span class="discount_price_qty">';
					$before_qty_text = __('From ', 'simple-product-discounts');
					$after_qty_text = __(' pcs.', 'simple-product-discounts');
				$after_inner_qty = '</span>';
				$before_inner_prc = '<span class="discount_price_prc">';
					$before_prc_text = $curr_position['before'];
					$after_prc_text = $curr_position['after'];
				$after_inner_prc = '</span>';
			$after_inner = '</div>';
		$after = '</div>';
		$fields_count = get_post_meta( get_the_ID(), '_fields_count', true );
		if ($fields_count == '') $fields_count = 0;
		for ($f = 1; $f <= $fields_count; $f++) {
			$cf = get_post_meta( get_the_ID(), '_count_field_'.$f, true );
			$pf = get_post_meta( get_the_ID(), '_price_field_'.$f, true );
			if ( !empty($cf) && !empty($pf) ) {
				$content .= $before_inner. $before_inner_qty.$before_qty_text .$cf. $after_qty_text.$after_inner_qty . $before_inner_prc.$before_prc_text . $pf . $after_prc_text.$after_inner_prc .$after_inner;
			}
		}
		if (!empty($content)) {
			$content = 	'<input type="hidden" class="discount_fields_count" value="'.$fields_count.'">'.
						$before_inner. $before_inner_qty.$before_qty_text .'1'. $after_qty_text.$after_inner_qty 
						. $before_inner_prc.$before_prc_text . get_post_meta( get_the_ID(), '_price', true) . $after_prc_text.$after_inner_prc .$after_inner
						.$content;
			$content = '<div class="clearfix"></div>'.$before.$content.$after.'<div class="clearfix"></div>';
		}
		echo $content;
	}
	// Get currency position and simbol
	private function curr_position() {
		switch ( get_option( 'woocommerce_currency_pos' ) ) {
			case 'right':
				$before_price = '';
				$after_price = get_woocommerce_currency_symbol();
				break;
			case 'right_space':
				$before_price = '';
				$after_price = '&nbsp;' . get_woocommerce_currency_symbol();
				break;
			case 'left':
				$before_price = get_woocommerce_currency_symbol();
				$after_price = '';
				break;
			case 'left_space':
			default:
				$before_price = get_woocommerce_currency_symbol() . '&nbsp;';
				$after_price = '';
				break;
		}
		return array('before' => $before_price, 'after' => $after_price );
	}
	// Calculate and set new price for each product in cart / checkout
	public function new_product_dq_price() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$id = $cart_item['product_id'];
			$fc = get_post_meta( $id, '_fields_count', true );
			if ($fc == '') continue;
			for ($f = 1; $f <= $fc; $f++) {
				$cf = get_post_meta( $id, '_count_field_'.$f, true );
				$pf = get_post_meta( $id, '_price_field_'.$f, true );
				if ( ( !empty($cf) && !empty($pf) ) && $cart_item['quantity'] >= $cf ) $cart_item['data']->set_price( $pf );
			}
		}
	}
	// Show each product regular and sale price in cart
	public function my_woocommerce_cart_item_price( $old_display, $cart_item, $cart_item_key ) {
		$product = $cart_item['data'];
		if ( $product && $cart_item['data']->regular_price != $cart_item['data']->get_price() )
			return sprintf( '<del>%s</del> <ins>%s</ins>',  wc_price($cart_item['data']->regular_price), wc_price($cart_item['data']->get_price()) );
		return $old_display;
	}
	// Show Total regular and sale price in cart / checkout
	public function my_woocommerce_cart_item_subtotal( $old_display, $cart_item, $cart_item_key ) {
		$product = $cart_item['data'];
		if ( $product && $cart_item['data']->regular_price != $cart_item['data']->get_price() )
			return sprintf( '<del>%s</del> <ins>%s</ins>',  wc_price($cart_item['data']->regular_price * $cart_item['quantity']), wc_price($cart_item['data']->get_price() * $cart_item['quantity']) );
		return $old_display;
	}


		/* Add links to plugin support link. */
		public function plugin_row_meta( $links, $file ) {
			if ( ! current_user_can( 'install_plugins' ) ) return $links;
			if ( $file == SIMPLE_PD_BASENAME )
				return array_merge(	$links,	array (	sprintf( '<a href="https://github.com/Prizzrakk" target="_blank">%s</a>', __( 'Support', 'simple-product-discounts' ) )	) );
			return $links;
		}

		/* Add link to settings page. */
		public function plugin_action_links( $links, $file ) {
			if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) return $links;
			if ( $file == SIMPLE_PD_BASENAME ) {
				$settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php' ) . '?page=simple_pdq', __( 'Settings', 'simple-product-discounts' ) );
				array_unshift( $links, $settings_link );
			}
			return $links;
		}

} // end class

} // end class check


/* GO! */
Simple_Product_Discounts::get_instance();

?>