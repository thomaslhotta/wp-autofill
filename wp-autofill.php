<?php
/**
 * @package   WP-Autofill
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @link      https://github.com/thomaslhotta/wp-autofill
 * @copyright 2016 Thomas Lhotta
 *
 * @wordpress-plugin
 * Plugin Name:	      WordPress Form auto-filler
 * Plugin URI:	      https://github.com/thomaslhotta/wp-gtm
 * Description:	      Auto fills forms on WordPress for testing purposes
 * Version:	      1.0
 * Author:	      Thomas Lhotta
 * Author URI:	      https://github.com/thomaslhotta
 * License:	      GPL-2.0+
 * GitHub Plugin URI: https://github.com/thomaslhotta/wp-autofill
 */


if ( ! defined( 'ABSPATH' ) ) {
	return;
}

class WP_Autofill
{
	/**
	 * @var WP_Autofill
	 */
	protected static $instance;

	/**
	 * Returns a singleton instance.
	 *
	 * @return WP_Autofill
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {
		add_action( 'bp_after_register_page', array( $this, 'add_script' ) );
		add_action( 'bp_after_profile_edit_content', array( $this, 'add_script' ) );

		add_filter( 'gform_get_form_filter', array( $this, 'add_script' ) );

		add_action( 'after_signup_user', array( $this, 'show_activation_key' ), 10, 3 );
	}

	/**
	 * Renders the activation key on the BuddyPress activation page
	 *
	 * @param $user
	 * @param $user_email
	 * @param $key
	 */
	public function show_activation_key( $user, $user_email, $key ) {
		$key = trailingslashit( bp_get_activation_page() ) . $key . '/';

		add_filter(
			'the_content',
			function ( $content ) use ( $key ) {
				return sprintf(
					'<a href="%s">%s</a>%s',
					esc_url( $key ),
					esc_html( $key ),
					$content
				);
			}
		);
	}

	/**
	 * Enqueues the filler script
	 *
	 * @param string $pass_through
	 *
	 * @return string
	 */
	function add_script( $pass_through = '' ) {
		add_action( 'wp_footer', array( $this, 'autofill_js' ), 999 );
		return $pass_through;
	}

	/**
	 * Renders the filling script
	 */
	public function autofill_js() {
		$name = $this->get_new_user_name();
		$email = $name . '@' . $this->get_domain();
		?>
			<script type="application/ecmascript">
				( function( $ ) {
					function randString( x ){
					    var s = '';
					    while( s.length< x && x > 0 ){
					        var r = Math.random();
					        s+= ( r< 0.1 ? Math.floor( r*100 ) : String.fromCharCode(
						        Math.floor(r*26) + ( r> 0.5 ? 97 : 65 ) )
					        );
					    }
					    return s;
					}

					function randomInt( min, max ) {
                        return Math.floor( Math.random() * ( max - min + 1 ) ) + min;
					}

					$( document ).ready( function() {
						var inputs = $( 'input, select, textarea' ).toArray(),
						    loop = function() {
							    var input = inputs.shift();
							    input = $( input );

							    // Skip conditions
							    if ( 'SELECT' === input.prop( 'tagName' ) && ! input.val() ) {
								    var options = input.find( 'option[value!=""]' ).filter( ':not([disabled])' ),
								        random  = Math.floor( options.length * ( Math.random() % 1 ) );

								    options.eq( random ).prop( 'selected', true );
							    } else if ( 'checkbox' === input.attr( 'type' ) ) {
								    if ( input.prop( 'required' ) || 1 === randomInt( 0, 1 ) ) {
										input.click();
									}
								} else if ( 'radio' === input.attr( 'type' ) ) {
								    var radios = $( '[name=' + input.attr( 'name' ) + '' );
								    radios.eq( randomInt( 0, radios.length - 1 ) ).click();
								    // Remove all other radio input with the same name from the list of inputs
								    inputs = $( inputs ).not( radios ).toArray();
						        } else if ( ! input.val() ) {
									if ( 'signup_email' === input.attr( 'name' ) ) {
										input.val( '<?php echo esc_js( $email ); ?>' );
									} else if ( 'password' === input.attr( 'type' ) ) {
										input.val( '111111' );
									} else if ( 'text' === input.attr( 'type' ) && input.data( 'fvZipcodeCountry' ) ) {
										var country =$( '[name=' + input.data( 'fvZipcodeCountry' ) + ']' ).val();
										switch ( country ) {
											case 'AT':
											case 'CH':
												input.val( '1111' );
												break;
											case 'DE':
												input.val( '11111' );
												break
										}
									} else if ( 'number' === input.attr( 'type' ) ) {
										var min = 0,
											max = 10;
										if ( input.attr( 'min' ) ) {
											min = parseInt( input.attr( 'min' ) );
										}

										if ( input.attr( 'max' ) ) {
											max = parseInt( input.attr( 'max' ) );
										}

										input.val( randomInt( min, max ) );

									} else if ( 'text' === input.attr( 'type' ) ) {
										input.val( randString( 5 ) );
									} else if ( 'TEXTAREA' === input.prop( 'tagName' ) ) {
										input.val( randString( 15 ) );
									}
								}

							    input.trigger( 'change' ).trigger( 'blur' );

							    if ( 0 < inputs.length ) {
								    setTimeout( loop, 50 );
							    }

						    };

						loop();

					} );
				})( jQuery );
			</script>
		<?php
	}

	public function get_domain() {
		return str_replace( 'www.', '', get_blog_details()->domain );
	}

	/**
	 * Generates a unused user name
	 *
	 * @return string
	 */
	public function get_new_user_name() {
		$number = 1;
		$base   = 'test';

		$user_name = $base . $number;

		while ( get_user_by( 'email', $user_name . '@' . $this->get_domain() ) instanceof WP_User ) {
			$number += 1;
			$user_name = $base . $number;
		}

		do {
			$result = wpmu_validate_user_signup( $user_name, $user_name . '@' . $this->get_domain() );
			if ( empty( $result['errors']->errors ) ) {
				break;
			}

			$number += 1;
			$user_name = $base . $number;
		} while ( true );


		return $user_name;
	}
}

add_action( 'init', array( 'WP_Autofill', 'get_instance' ) );

