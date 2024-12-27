<?php
/**
 * CFTZ_Module_CF7
 *
 * @package         Cf7_To_ActionNetwork
 * @subpackage      CFTZ_Module_CF7
 * @since           1.0.0
 *
 */

// If this file is called directly, call the cops.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! class_exists( 'CFTZ_Module_CF7' ) ) {
    class CFTZ_Module_CF7 {

        public static function wpcf7_contact_form_properties( $properties, $contact_form ) {

        /**
         * The Core object
         *
         * @since    1.0.0
         * @var      Cf7_To_ActionNetwork    $core   The core class
         */
        private $core;

        /**
         * The Module Indentify
         *
         * @since    1.0.0
         */
        const MODULE_SLUG = 'cf7';

        /**
         * Metadata identifier
         *
         * @since    1.0.0
         */
        const METADATA = 'ctz_actionnetwork';

        /**
         * Define the core functionalities into plugin.
         *
         * @since    1.0.0
         * @param    Cf7_To_ActionNetwork      $core   The Core object
         */
        public function __construct( Cf7_To_ActionNetwork $core ) {
            $this->core = $core;
        }

        /**
         * Register all the hooks for this module
         *
         * @since    1.0.0
         * @access   private
         */
        private function define_hooks() {
            $this->core->add_filter( 'wpcf7_editor_panels', __CLASS__ . '::wpcf7_editor_panels' );
            $this->core->add_action( 'wpcf7_save_contact_form', __CLASS__ . '::wpcf7_save_contact_form' );
            $this->core->add_filter( 'wpcf7_skip_mail', __CLASS__ . '::wpcf7_skip_mail', 10, 2 );
            $this->core->add_action( 'wpcf7_mail_sent', __CLASS__ . '::wpcf7_mail_sent', 10, 1 );

            $this->core->add_filter( 'wpcf7_contact_form_properties', 'wpcf7_contact_form_properties', 10, 2 );
            $this->core->add_filter( 'wpcf7_pre_construct_contact_form_properties', __CLASS__ . '::wpcf7_contact_form_properties', 10, 2 );
            $this->core->add_filter('wpcf7_pre_construct_contact_form_properties', array('CFTZ_Module_CF7', 'wpcf7_contact_form_properties'), 10, 2);

            // Admin Hooks
            $this->core->add_action( 'admin_notices', __CLASS__ . '::check_cf7_plugin' );
        }

        /**
         * Check Contact Form 7 Plugin is active
         * It's a dependency in this version
         *
         * @since    1.0.0
         * @access   private
         */
        public static function check_cf7_plugin() {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
            }

            if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
                return;
            }

            echo '<div class="notice notice-error is-dismissible">';
            echo '<p>' . sprintf(esc_html__( "You need to install/activate %1\$s Contact Form 7%2\$s plugin to use %3\$s CF7 to ActionNetwork %4\$s", 'connect-cf7-actionnetwork' ),'<a href="' . esc_url( 'http://contactform7.com/' ) . '" target="_blank">','</a>','<strong>','</strong>') . '</p>';

            $screen = get_current_screen();
            if ( $screen->id == 'plugins' ) {
                echo '.</p></div>';
                return;
            }

            if ( file_exists( ABSPATH . WP_PLUGIN_DIR . '/contact-form-7/wp-contact-form-7.php' ) ) {
                $url = 'plugins.php';
            } else {
                $url = 'plugin-install.php?tab=search&s=Contact+form+7';
            }

            echo '<p><a href="' . esc_url( admin_url( $url ) ) . '">' . esc_html__( "Do it now?", 'connect-cf7-actionnetwork' ) . '</a></p>';
            echo '</div>';
        }

        /**
         * Filter the 'wpcf7_editor_panels' to add necessary tabs
         *
         * @since    1.0.0
         * @param    array              $panels     Panels in CF7 Administration
         */
        public function wpcf7_editor_panels( $panels ) {
            $panels['actionnetwork-panel'] = array(
                'title'     => __( 'ActionNetwork', 'cf7-actionnetwork' ),
                'callback'  => [ $this, 'actionnetwork_panel_html' ],
            );

            return $panels;
        }

        /**
         * Add actionnetwork panel HTML
         *
         * @since    1.0.0
         * @param    WPCF7_ContactForm  $contactform    Current ContactForm Obj
         */
        public function actionnetwork_panel_html( WPCF7_ContactForm $contactform ) {
            require plugin_dir_path( __FILE__ ) . 'admin/actionnetwork-panel-html.php';
        }

        /**
         * Action 'wpcf7_save_contact_form' to save properties do Contact Form Post
         *
         * @since    1.0.0
         * @param    WPCF7_ContactForm  $contactform    Current ContactForm Obj
         */
        public function wpcf7_save_contact_form( $contact_form ) {
            $new_properties = [];

            if ( ! isset( $_POST['cf7an_nonce'] ) || ! wp_verify_nonce( $_POST['cf7an_nonce'], 'cf7an_save_contact_form' ) ) {
                return;
            }

            if ( ! current_user_can( 'edit_post', $contact_form->id() ) ) {
                return;
            }

            if ( isset( $_POST['ctz-actionnetwork-activate'] ) && $_POST['ctz-actionnetwork-activate'] == '1' ) {
                $new_properties[ 'activate' ] = '1';
            } else {
                $new_properties[ 'activate' ] = '0';
            }

            if ( isset( $_POST['ctz-actionnetwork-hook-url'] ) ) {
                $hook_urls_raw = sanitize_textarea_field( wp_unslash( $_POST['ctz-actionnetwork-hook-url'] ) );
				// Unslash the POST data first
				$hook_urls_raw = sanitize_textarea_field( wp_unslash( $_POST['ctz-actionnetwork-hook-url'] ) ); // Unslash once before processing

				// Process each URL, removing placeholders and sanitizing
				$hook_urls = array_filter( array_map( function( $hook_url ) {
					$placeholders = self::get_hook_url_placeholders( $hook_url );

					foreach ( $placeholders as $key => $placeholder ) {
						$hook_url = str_replace( $placeholder, '_____' . $key . '_____', $hook_url );
					}

					// Sanitize the URL post replacement
					$hook_url = esc_url_raw( $hook_url );

					foreach ( $placeholders as $key => $placeholder ) {
						$hook_url = str_replace( '_____' . $key . '_____', $placeholder, $hook_url );
					}

					return $hook_url;
				}, explode( PHP_EOL, $hook_urls_raw) ));

				$new_properties['hook_url'] = $hook_urls;
			}

            if ( isset( $_POST['ctz-actionnetwork-send-mail'] ) && $_POST['ctz-actionnetwork-send-mail'] == '1' ) {
                $new_properties[ 'send_mail' ] = '1';
            } else {
                $new_properties[ 'send_mail' ] = '0';
            }

            if ( isset( $_POST['ctz-special-mail-tags'] ) ) {
                $new_properties[ 'special_mail_tags' ] = sanitize_textarea_field( wp_unslash( $_POST['ctz-special-mail-tags'] ) );
            }

            if ( isset( $_POST['ctz-custom-headers'] ) ) {
                $new_properties[ 'custom_headers' ] = sanitize_textarea_field( wp_unslash( $_POST['ctz-custom-headers'] ) );
            }

            $properties = $contact_form->get_properties();
            $old_properties = $properties[ self::METADATA ];
            $properties[ self::METADATA ] = array_merge( $old_properties, $new_properties );
            $contact_form->set_properties( $properties );
        }

        /**
         * Filter the 'wpcf7_contact_form_properties' to add necessary properties
         *
         * @since    1.0.0
         * @param    array              $properties     ContactForm Obj Properties
         * @param    obj                $instance       ContactForm Obj Instance
         */
        public function wpcf7_contact_form_properties( $properties, $instance ) {
            if ( ! isset( $properties[ self::METADATA ] ) ) {
                $properties[ self::METADATA ] = array(
                    'activate'          => '0',
                    'hook_url'          => [],
                    'send_mail'         => '0',
                    'special_mail_tags' => '',
                    'custom_headers'    => '',
                );
            }

            return $properties;
        }

        /**
         * Filter the 'wpcf7_skip_mail' to skip if necessary
         *
         * @since    1.0.0
         * @param    bool               $skip_mail      true/false
         * @param    obj                $contact_form   ContactForm Obj
         */
        public function wpcf7_skip_mail( $skip_mail, $contact_form ) {
            $properties = $contact_form->prop( self::METADATA );

            if ( $this->can_submit_to_actionnetwork( $contact_form ) ) {
                return empty( $properties['send_mail'] );
            }

            return $skip_mail;
        }

        /**
         * Get reference of previous URL
         * 
         * @return string Referred URL or empty string
         */
        private function get_referrer_data() {
            return isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        }

        /**
         * Action 'wpcf7_mail_sent' to send data to ActionNetwork
         *
         * @since    1.0.0
         * @param    obj                $contact_form   ContactForm Obj
         */
        public function wpcf7_mail_sent( $contact_form ) {
            $properties = $contact_form->prop( self::METADATA );

            if ( ! $this->can_submit_to_actionnetwork( $contact_form ) ) {
                return;
            }

            $smt_data = $this->get_data_from_special_mail_tags( $contact_form );
            $cf_data = $this->get_data_from_contact_form( $contact_form );

            $data = array_merge( $smt_data, $cf_data );

            // Capture the referer, which could be the last visited URL
            $current_url = $this->get_referrer_data();
            // Clean the URL to remove parameters
            $clean_url = strtok($current_url, '?');

            // Extract the "source" parameter using parse_url() and parse_str()
            $source = 'contact-form-7';
            $url_components = wp_parse_url($current_url);
            if (isset($url_components['query'])) {
                parse_str($url_components['query'], $params);
                $source = isset($params['source']) ? sanitize_text_field($params['source']) : 'contact-form-7';
            }

            $errors = [];

            foreach ( (array) $properties['hook_url'] as $hook_url ) {
                if ( empty( $hook_url ) ) {
                    continue;
                }

                // Try/Catch to support exception on request
                try {
                    $placeholders = CFTZ_Module_CF7::get_hook_url_placeholders( $hook_url );
                    foreach ( $placeholders as $key => $placeholder ) {
                        $value = ( $data[ $key ] ?? '' );
                        if ( ! is_scalar( $value ) ) {
                            $value = implode( '|', $value );
                        }

                        /**
                         * Filter: ctz_hook_url_placeholder
                         *
                         * You can change the placeholder replacement in hook_url;
                         *
                         * @param $value        string      Urlencoded replace value.
                         * @param $placeholder  string      The placeholder to be replaced [$key].
                         * @param $key          string      The key of placeholder.
                         * @param $data         string      Data to be sent to actionnetwork.
                         *
                         * @since 3.0.0
                         */
                        $value =  apply_filters( 'cf7an_hook_url_placeholder', urlencode( $value ), $placeholder, $key, $data );

                        $hook_url = str_replace( $placeholder, $value, $hook_url );
                    }

                    $core_fields = [
                        'family_name' => '',
                        'given_name' => '',
                        'postal_code' => '',
                        'address_lines' => [''],
                        'locality' => '',
                        'region' => '',
                        'country' => '',
                        'address' => '',
                        'status' => '',
                        'number' => ''
                    ];
                    
                    $person_data = [
                        "person" => [
                            "family_name" => "",
                            "given_name" => "",
                            "custom_fields" => [],
                            "postal_addresses" => [
                                [
                                    "postal_code" => "",
                                    "address_lines" => [""],
                                    "locality" => "",
                                    "region" => "",
                                    "country" => ""
                                ]
                            ],
                            "email_addresses" => [
                                [
                                    "address" => "",
                                    "status" => ""
                                ]
                            ],
                            "phone_numbers" => [
                                [
                                    "number" => "",
                                    "status" => ""
                                ]
                            ]
                        ],
                        "triggers" => [
                            "autoresponse" => [
                                "enabled" => true
                            ]
                        ],
                        "action_network:referrer_data" => [
                            "source" => $source,
                            "website" => $clean_url
                        ]
                    ];
                    
                    // Fill core fields from $data
                    foreach ($data as $key => $value) {
                        if (in_array($key, array_keys($core_fields))) {
                            if ($key == 'address') {
                                $person_data["person"]["email_addresses"][0]["address"] = $value;
                            } elseif ($key == 'status') {
                                $person_data["person"]["email_addresses"][0]["status"] = $value;
                            } elseif ($key == 'number') {
                                $person_data["person"]["phone_numbers"][0]["number"] = $value;
                            } elseif (in_array($key, ['postal_code', 'address_lines', 'locality', 'region', 'country'])) {
                                // Make sure address_lines is an array
                                if ($key == 'address_lines') {
                                    $person_data["person"]["postal_addresses"][0][$key] = is_array($value) ? $value : [$value];
                                } else {
                                    $person_data["person"]["postal_addresses"][0][$key] = $value;
                                }
                            } else {
                                $person_data["person"][$key] = $value;
                            }
                        } else {
                            // Treat as custom field
                            $person_data["person"]["custom_fields"][$key] = $value;
                        }
                    }
                    $data = $person_data;

                    /**
                     * Action: ctz_trigger_actionnetwork
                     *
                     * You can add your own actions to process the hook.
                     * We send it using CFTZ_Module_ActionNetwork::pull_the_trigger().
                     *
                     * @since  1.0.0
                     */
                    
                    do_action( 'cf7an_trigger_actionnetwork', $data, $hook_url, $properties, $contact_form );
                } catch (Exception $exception) {
                    $errors[] = array(
                        'actionnetwork'   => $hook_url,
                        'exception' => $exception,
                    );

                    /**
                     * Filter: ctz_trigger_actionnetwork_error_message
                     *
                     * The 'ctz_trigger_actionnetwork_error_message' filter change the message in case of error.
                     * Default is CF7 error message, but you can access exception to create your own.
                     *
                     * You can ignore errors returning false:
                     * add_filter( 'ctz_trigger_actionnetwork_error_message', '__return_empty_string' );
                     *
                     * @since 1.4.0
                     */
                    $error_message =  apply_filters( 'cf7an_trigger_actionnetwork_error_message', $contact_form->message( 'mail_sent_ng' ), $exception );

                    // If empty ignore
                    if ( empty( $error_message ) ) continue;

                    // Submission error
                    $submission = WPCF7_Submission::get_instance();
                    $submission->set_status( 'mail_failed' );
                    $submission->set_response( $error_message );
                    break;
                }
            }

            // If empty ignore
            if ( empty( $errors ) ) return;

            /**
             * Action: ctz_trigger_actionnetwork_errors
             *
             * If we have errors, we skiped them in 'ctz_trigger_actionnetwork_error_message' filter.
             * You can now submit your own error.
             *
             * @since  2.4.0
             */
            do_action( 'cf7an_trigger_actionnetwork_errors', $errors, $contact_form );
        }

        /**
         * Retrieve a array with data from Contact Form data
         *
         * @since    1.0.0
         * @param    obj                $contact_form   ContactForm Obj
         */
        private function get_data_from_contact_form( $contact_form ) {
            $data = [];

            // Submission
            $submission = WPCF7_Submission::get_instance();
            $uploaded_files = ( ! empty( $submission ) ) ? $submission->uploaded_files() : [];

            // Upload Info
            $wp_upload_dir = wp_get_upload_dir();
            $upload_path = CFTZ_UPLOAD_DIR . '/' . $contact_form->id() . '/' . uniqid();

            $upload_url = $wp_upload_dir['baseurl'] . '/' . $upload_path;
            $upload_dir = $wp_upload_dir['basedir'] . '/' . $upload_path;

            $tags = $contact_form->scan_form_tags();
            foreach ( $tags as $tag ) {
                if ( empty( $tag->name ) ) continue;

                // Regular Tags
                $value = ( ! empty( $_POST[ $tag->name ] ) ) ? sanitize_text_field( wp_unslash( $_POST[ $tag->name ] ) ) : '';

                if ( is_array( $value ) ) {
                    foreach ( $value as $key => $v ) {
                        $value[ $key ] = stripslashes( $v );
                    }
                }

                if ( is_string( $value ) ) {
                    $value = stripslashes( $value );
                }

                // Files
                if ( $tag->basetype === 'file' && ! empty( $uploaded_files[ $tag->name ] ) ) {
                    $files = $uploaded_files[ $tag->name ];

                    $copied_files = [];
                    foreach ( (array) $files as $file ) {
                        wp_mkdir_p( $upload_dir );

                        $filename = wp_unique_filename( $upload_dir, $tag->name . '-' . basename( $file ) );

                        if ( ! copy( $file, $upload_dir . '/' . $filename ) ) {
                            $submission = WPCF7_Submission::get_instance();
                            $submission->set_status( 'mail_failed' );
                            $submission->set_response( $contact_form->message( 'upload_failed' ) );

                            continue;
                        }

                        $copied_files[] = $upload_url . '/' . $filename;
                    }

                    $value = $copied_files;

                    if (count($value) === 1) {
                        $value = $value[0];
                    }
                }

                // Support to Pipes
                $pipes = $tag->pipes;
                if ( WPCF7_USE_PIPE && $pipes instanceof WPCF7_Pipes && ! $pipes->zero() ) {
                    if ( is_array( $value) ) {
                        $new_value = [];

                        foreach ( $value as $v ) {
                            $new_value[] = $pipes->do_pipe( wp_unslash( $v ) );
                        }

                        $value = $new_value;
                    } else {
                        $value = $pipes->do_pipe( wp_unslash( $value ) );
                    }
                }

                // Support to Free Text on checkbox and radio
                if ( $tag->has_option( 'free_text' ) && in_array( $tag->basetype, [ 'checkbox', 'radio' ] ) ) {
                    $free_text_label = end( $tag->values );
                    $free_text_name  = $tag->name . '_free_text';
                    $free_text_value = ( ! empty( $_POST[ $free_text_name ] ) ) ? sanitize_text_field( wp_unslash( $_POST[ $free_text_name ] ) ) : '';

                    if ( is_array( $value ) ) {
                        foreach ( $value as $key => $v ) {
                            if ( $v !== $free_text_label ) {
                                continue;
                            }

                            $value[ $key ] = stripslashes( $free_text_value );
                        }
                    }

                    if ( is_string( $value ) && $value === $free_text_label ) {
                        $value = stripslashes( $free_text_value );
                    }
                }

                // Support to "actionnetwork" option (rename field value)
                $key = $tag->name;
                $actionnetwork_key = $tag->get_option( 'actionnetwork' );

                if ( ! empty( $actionnetwork_key ) && ! empty( $actionnetwork_key[0] ) ) {
                    $key = $actionnetwork_key[0];
                }

                $data[ $key ] = $value;
            }

            /**
             * You can filter data retrieved from Contact Form tags with 'ctz_get_data_from_contact_form'
             *
             * @param $data             Array 'field => data'
             * @param $contact_form     ContactForm obj from 'wpcf7_mail_sent' action
             */
            return apply_filters( 'cf7an_get_data_from_contact_form', $data, $contact_form );
        }

        /**
         * Retrieve a array with data from Special Mail Tags
         *
         * @link https://contactform7.com/special-mail-tags
         *
         * @since    1.3.0
         * @param    obj                $contact_form   ContactForm Obj
         */
        private function get_data_from_special_mail_tags( $contact_form ) {
            $tags = [];
            $data = [];

            $properties = $contact_form->prop( self::METADATA );
            if ( ! empty( $properties['special_mail_tags'] ) ) {
                $tags = self::get_special_mail_tags_from_string( $properties['special_mail_tags'] );
            }

            foreach ( $tags as $key => $tag ) {
                $mail_tag = new WPCF7_MailTag( sprintf( '[%s]', $tag ), $tag, '' );
                $value = '';

                // Support to "_raw_" values. @see WPCF7_MailTag::__construct()
                if ( $mail_tag->get_option( 'do_not_heat' ) ) {
                    $value = apply_filters( 'cf7an_special_mail_tags', '', $mail_tag->tag_name(), false, $mail_tag );
                    $value = sanitize_text_field( wp_unslash( $_POST[ $mail_tag->field_name() ] ?? '' ) );
                }

                $value = apply_filters( 'cf7an_special_mail_tags', $value, $mail_tag->tag_name(), false, $mail_tag );
                $data[ $key ] = $value;
            }

            /**
             * You can filter data retrieved from Special Mail Tags with 'ctz_get_data_from_special_mail_tags'
             *
             * @param $data             Array 'field => data'
             * @param $contact_form     ContactForm obj from 'wpcf7_mail_sent' action
             */
            return apply_filters( 'cf7an_get_data_from_special_mail_tags', $data, $contact_form );
        }

        /**
         * Check we can submit a form to ActionNetwork
         *
         * @since    1.0.0
         * @param    obj                $contact_form   ContactForm Obj
         */
        private function can_submit_to_actionnetwork( $contact_form ) {
            $properties = $contact_form->prop( self::METADATA );

            if ( empty( $properties ) || empty( $properties['activate'] ) || empty( $properties['hook_url'] ) ) {
                return false;
            }

            return true;
        }

        /**
         * Special Mail Tags from a configuration string
         *
         * @since    1.3.1
         * @param    string     $string
         * @return   array      $data       Array { key => tag }
         */
        public static function get_special_mail_tags_from_string( $string ) {
            $data = [];
            $tags = [];

            preg_match_all( '/\[[^\]]*]/', $string, $tags );
            $tags = ( ! empty( $tags[0] ) ) ? $tags[0] : $tags;

            foreach ( $tags as $tag_data ) {
                if ( ! is_string( $tag_data ) || empty( $tag_data ) ) continue;

                $tag_data = substr( $tag_data, 1, -1 );
                $tag_data = explode( ' ', $tag_data );

                if ( empty( $tag_data[0] ) ) continue;

                $tag = $tag_data[0];
                $key = ( ! empty( $tag_data[1] ) ) ? $tag_data[1] : $tag;

                if ( empty( $key ) ) continue;

                $data[ $key ] = $tag;
            }

            return $data;
        }

        /**
         * List placeholders from hook_url
         *
         * @since    3.0.0
         * @param    string     $hook_url
         * @return   array      $placeholders
         */
        public static function get_hook_url_placeholders( $hook_url ) {
            $placeholders = [];

            preg_match_all( '/\[{1}[^\[\]]+\]{1}/', $hook_url, $matches );

            foreach ( $matches[0] as $placeholder ) {
                $placeholder = substr( $placeholder, 1, -1 );
                $placeholders[ $placeholder ] = '[' . $placeholder . ']';
            }

            return $placeholders;
        }

        /**
         * Run the module.
         *
         * @since    1.0.0
         */
        public function run() {
            $this->define_hooks();
        }
    }
}

// Ensure the method is called on an instance of the class
add_action('admin_notices', array('CFTZ_Module_CF7', 'check_cf7_plugin'));

}
