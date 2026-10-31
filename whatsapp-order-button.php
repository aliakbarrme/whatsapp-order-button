<?php
/**
 * Plugin Name:       WhatsApp Order Button for WooCommerce
 * Plugin URI:        https://github.com/Aliakbar45480/whatsapp-order-button
 * Description:       Adds an "Order on WhatsApp" button to WooCommerce products. Customers send you a pre-filled message with the product, quantity, variation, price and link.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Ali Akbar (BD Webhut)
 * Author URI:        https://github.com/Aliakbar45480
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wa-order-button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WAOB_Plugin {

	const OPTION = 'waob_settings';

	public static function defaults() {
		return array(
			'phone'       => '',
			'label'       => 'Order on WhatsApp',
			'message'     => "Hi! I'd like to order:\n*{product}*{variation}\nQuantity: {qty}\nPrice: {price}\n{url}",
			'color'       => '#25D366',
			'on_single'   => 1,
			'on_loop'     => 0,
			'hide_cart'   => 0,
			'floating'    => 1,
			'float_text'  => 'Hi! I have a question about your products.',
		);
	}

	public static function get( $key ) {
		$opts = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'action_links' ) );

		if ( ! self::get( 'phone' ) ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'single_button' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'single_button_no_cart' ), 31 );
		add_action( 'woocommerce_after_shop_loop_item', array( __CLASS__, 'loop_button' ), 15 );
		add_action( 'wp_footer', array( __CLASS__, 'floating_button' ) );
		if ( self::get( 'hide_cart' ) ) {
			add_filter( 'woocommerce_is_purchasable', '__return_false' );
		}
	}

	/* ---------- Admin ---------- */

	public static function menu() {
		add_options_page( 'WhatsApp Order Button', 'WhatsApp Order', 'manage_options', 'waob', array( __CLASS__, 'settings_page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=waob' ) ) . '">' . esc_html__( 'Settings', 'wa-order-button' ) . '</a>' );
		return $links;
	}

	public static function register_settings() {
		register_setting( 'waob', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$d   = self::defaults();
		$out = array();
		$out['phone']      = preg_replace( '/\D+/', '', isset( $in['phone'] ) ? $in['phone'] : '' );
		$out['label']      = sanitize_text_field( isset( $in['label'] ) ? $in['label'] : $d['label'] );
		$out['message']    = sanitize_textarea_field( isset( $in['message'] ) ? $in['message'] : $d['message'] );
		$out['color']      = sanitize_hex_color( isset( $in['color'] ) ? $in['color'] : '' ) ? sanitize_hex_color( $in['color'] ) : $d['color'];
		$out['float_text'] = sanitize_text_field( isset( $in['float_text'] ) ? $in['float_text'] : $d['float_text'] );
		foreach ( array( 'on_single', 'on_loop', 'hide_cart', 'floating' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		return $out;
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		$n = self::OPTION;
		?>
		<div class="wrap">
			<h1>WhatsApp Order Button</h1>
			<?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
				<div class="notice notice-warning"><p>WooCommerce is not active. Product buttons need WooCommerce; the floating chat button works without it.</p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'waob' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="waob_phone">WhatsApp number</label></th>
						<td><input id="waob_phone" class="regular-text" name="<?php echo esc_attr( $n ); ?>[phone]" value="<?php echo esc_attr( $o['phone'] ); ?>" placeholder="8801XXXXXXXXX" />
						<p class="description">International format with country code, digits only (e.g. 8801712345678).</p></td></tr>
					<tr><th scope="row"><label for="waob_label">Button text</label></th>
						<td><input id="waob_label" class="regular-text" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $o['label'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="waob_msg">Message template</label></th>
						<td><textarea id="waob_msg" class="large-text" rows="6" name="<?php echo esc_attr( $n ); ?>[message]"><?php echo esc_textarea( $o['message'] ); ?></textarea>
						<p class="description">Placeholders: <code>{product}</code> <code>{variation}</code> <code>{qty}</code> <code>{price}</code> <code>{sku}</code> <code>{url}</code> <code>{site}</code></p></td></tr>
					<tr><th scope="row"><label for="waob_color">Button color</label></th>
						<td><input id="waob_color" type="color" name="<?php echo esc_attr( $n ); ?>[color]" value="<?php echo esc_attr( $o['color'] ); ?>" /></td></tr>
					<tr><th scope="row">Show button</th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[on_single]" value="1" <?php checked( $o['on_single'] ); ?> /> On single product pages</label><br />
						<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[on_loop]" value="1" <?php checked( $o['on_loop'] ); ?> /> On shop / category pages</label><br />
						<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[hide_cart]" value="1" <?php checked( $o['hide_cart'] ); ?> /> WhatsApp-only store (hide Add to cart)</label>
					</td></tr>
					<tr><th scope="row">Floating chat button</th><td>
						<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[floating]" value="1" <?php checked( $o['floating'] ); ?> /> Show a floating WhatsApp button on every page</label><br />
						<input class="regular-text" name="<?php echo esc_attr( $n ); ?>[float_text]" value="<?php echo esc_attr( $o['float_text'] ); ?>" placeholder="Pre-filled message" />
					</td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ---------- Front end ---------- */

	public static function assets() {
		$css = '.waob-btn{display:inline-flex;align-items:center;gap:8px;background:' . self::get( 'color' ) . ';color:#fff!important;border:0;border-radius:6px;padding:.75em 1.2em;font-weight:600;text-decoration:none!important;cursor:pointer;line-height:1.2;margin:8px 0}'
			. '.waob-btn:hover{filter:brightness(.92)}.waob-btn svg{width:1.2em;height:1.2em;fill:currentColor}'
			. 'form.cart .waob-btn{margin-left:8px}.waob-loop{margin-top:6px;font-size:.9em}'
			. '.waob-float{position:fixed;right:20px;bottom:20px;z-index:9999;width:56px;height:56px;border-radius:50%;background:#25D366;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(0,0,0,.2);transition:transform .2s}'
			. '.waob-float:hover{transform:scale(1.08)}.waob-float svg{width:30px;height:30px;fill:#fff}';
		wp_register_style( 'waob', false, array(), '1.0.0' );
		wp_enqueue_style( 'waob' );
		wp_add_inline_style( 'waob', $css );

		wp_register_script( 'waob', '', array(), '1.0.0', true );
		wp_enqueue_script( 'waob' );
		wp_add_inline_script(
			'waob',
			"document.addEventListener('click',function(e){var b=e.target.closest('.waob-btn[data-template]');if(!b)return;e.preventDefault();" .
			"var f=b.closest('form.cart'),q=1,v='';if(f){var qi=f.querySelector('input.qty');if(qi&&qi.value)q=qi.value;" .
			"f.querySelectorAll('select[name^=\"attribute_\"]').forEach(function(s){if(s.value){var l=s.closest('tr');var n=l&&l.querySelector('label')?l.querySelector('label').textContent.trim():s.name.replace('attribute_','');v+='\\n'+n+': '+s.options[s.selectedIndex].text;}});}" .
			"var p=b.dataset.price;var vp=f&&f.querySelector('.woocommerce-variation-price .amount');if(vp&&vp.textContent.trim())p=vp.textContent.trim();" .
			"var m=b.dataset.template.split('{qty}').join(q).split('{variation}').join(v).split('{price}').join(p);" .
			"window.open('https://wa.me/'+b.dataset.phone+'?text='+encodeURIComponent(m),'_blank','noopener');});"
		);
	}

	private static function icon() {
		return '<svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3C8.8 3 3 8.7 3 15.8c0 2.5.7 4.9 2 7L3 29l6.4-2c2 1.1 4.3 1.7 6.6 1.7 7.2 0 13-5.7 13-12.8S23.2 3 16 3zm0 23.4c-2.1 0-4.1-.6-5.9-1.7l-.4-.3-3.8 1.2 1.2-3.7-.3-.4a10.4 10.4 0 0 1-1.7-5.7C5.1 10 10 5.2 16 5.2S26.9 10 26.9 15.8 22 26.4 16 26.4zm6-7.8c-.3-.2-1.9-.9-2.2-1s-.5-.2-.7.2-.8 1-1 1.2-.4.2-.7.1a8.9 8.9 0 0 1-4.4-3.8c-.3-.6.3-.5 1-1.7.1-.2 0-.4 0-.6l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4s-1.2 1.1-1.2 2.7 1.2 3.2 1.4 3.4 2.4 3.6 5.8 5c2.2.9 3 1 4.1.8.7-.1 1.9-.8 2.2-1.5s.3-1.4.2-1.5-.3-.2-.6-.3z"/></svg>';
	}

	private static function template_for( $product ) {
		$price = wp_strip_all_tags( html_entity_decode( wc_price( wc_get_price_to_display( $product ) ), ENT_QUOTES, 'UTF-8' ) );
		$map   = array(
			'{product}' => $product->get_name(),
			'{sku}'     => $product->get_sku(),
			'{url}'     => get_permalink( $product->get_id() ),
			'{site}'    => get_bloginfo( 'name' ),
		);
		return array( strtr( self::get( 'message' ), $map ), $price );
	}

	private static function button( $product, $extra_class = '' ) {
		if ( ! $product || ! function_exists( 'wc_price' ) ) {
			return;
		}
		list( $tpl, $price ) = self::template_for( $product );
		printf(
			'<a href="#" class="waob-btn %1$s" data-phone="%2$s" data-price="%3$s" data-template="%4$s" rel="nofollow">%5$s<span>%6$s</span></a>',
			esc_attr( $extra_class ),
			esc_attr( self::get( 'phone' ) ),
			esc_attr( $price ),
			esc_attr( $tpl ),
			self::icon(), // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
			esc_html( self::get( 'label' ) )
		);
	}

	public static function single_button() {
		if ( self::get( 'on_single' ) ) {
			global $product;
			self::button( $product );
		}
	}

	/** When "WhatsApp-only" hides the cart form, render the button in the summary instead. */
	public static function single_button_no_cart() {
		if ( self::get( 'on_single' ) && self::get( 'hide_cart' ) ) {
			global $product;
			self::button( $product );
		}
	}

	public static function loop_button() {
		if ( self::get( 'on_loop' ) ) {
			global $product;
			self::button( $product, 'waob-loop' );
		}
	}

	public static function floating_button() {
		if ( ! self::get( 'floating' ) ) {
			return;
		}
		printf(
			'<a class="waob-float" href="%1$s" target="_blank" rel="noopener nofollow" aria-label="%2$s">%3$s</a>',
			esc_url( 'https://wa.me/' . self::get( 'phone' ) . '?text=' . rawurlencode( self::get( 'float_text' ) ) ),
			esc_attr__( 'Chat on WhatsApp', 'wa-order-button' ),
			self::icon() // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
		);
	}
}

add_action( 'plugins_loaded', array( 'WAOB_Plugin', 'init' ) );
