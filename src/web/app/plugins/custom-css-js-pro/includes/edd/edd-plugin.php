<?php

class CustomCSSandJS_LicenseForm {

    var $data = array();

    function __construct( $data = array() ) {
        $data['nonce'] = $data['license'] . '_nonce';
        $data['activate'] = $data['license'] . '_activate';
        $data['deactivate'] = $data['license'] . '_deactivate';
        $data['license_domain'] = $data['license'] . '_domain';
        if(!isset($data['license_beta'])) $data['license_beta'] = $data['license'] . '_beta';
        $this->data = $data;


        if( !class_exists( 'EDD_SL_Plugin_Updater_CCS' ) ) {
            include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
        }
    }


    /**
     * License form 
     */
    function license_page( $admin_notice ) {
        $license 	= get_option( $this->data['license_key'] );
        $domain     = get_option( $this->data['license_domain'] );
        $status 	= get_option( $this->data['license_status'] );
        $beta       = get_option( $this->data['license_beta'] );
        $license_hidden = substr_replace($license, str_repeat('*', 23), 5, 22);

        $license_data = false;
        if( $status !== false && $status == 'valid' ) {

            $api_body = array(
                'timeout' => 15,
                'sslverify' => false,
                'body' => array(
                    'edd_action'=> 'check_license',
                    'license' => $license, 
                    'item_name' => urlencode( $this->data['item_name']),
                    'url'       => home_url()
                )
            );
            $response = wp_remote_post( $this->data['store_url'], $api_body);

            if ( ! is_wp_error( $response ) ) {
                $license_data = json_decode( wp_remote_retrieve_body( $response ) );
            }

            if ( !$domain && isset($license_data->license) && $license_data->license == 'valid' ) {
                $home_url = str_replace( array('https://', 'http://'), '', home_url() );
                $domain = $home_url;
                update_option( $this->data['license_domain'], $home_url );
            }
        }

        ?>
        <?php if ( isset($admin_notice['msg']) && !empty($admin_notice['msg']) ) : ?>
            <div id="alert_messages">
                <div class="alert alert-dismissable alert-<?php echo $admin_notice['class']; ?>">
                <button type="button" class="close" data-dismiss="alert">×</button>
                <?php echo $admin_notice['msg']; ?>
                </div>
            </div>
        <?php endif; ?>

        <script type="text/javascript">
            jQuery(document).ready(function( $ ){
                var license_input = $('#<?php echo $this->data['license_key']; ?>');

                /* Enable/disable the Activate/Deactivate buttons */
                if (license_input.val().length > 0 ) {
                    $('#license-deactivate').prop('disabled', false);
                }

                var license_input_disable = function() {
                    if ( license_input.val().length > 0 ) {
                        $('#license-activate').prop('disabled', false);
                    } else {
                        $('#license-activate').prop('disabled', true);
                        $('#license-deactivate').prop('disabled', true);
                    }
                }
                license_input_disable();
                license_input.on('input', function() {
                    license_input_disable();
                });

            });
        </script>

        <style type="text/css">
            button:disabled { cursor: not-allowed !important;   }
            .panel-body a { text-decoration: underline; }
        </style>

        <form method="post" class="form-inline">

            <?php settings_fields( $this->data['license'] ); ?>

            <div class="form-group" style="margin-top: 20px; margin-bottom: 30px;">
                <input id="<?php echo $this->data['license_key']; ?>" name="<?php echo $this->data['license_key']; ?>" type="text" class="regular-text" value="<?php esc_attr_e( $license_hidden ); ?>" placeholder="Enter you license key" />
                <input type="hidden" name="<?php echo $this->data['license_key']; ?>_open" value="<?php esc_attr_e( $license ); ?>" />
                <button id="license-activate" class="button-secondary" name="<?php echo $this->data['activate']; ?>" value="Activate License" />Activate License</button>
                <button id="license-deactivate" disabled class="button-secondary" name="<?php echo $this->data['deactivate']; ?>" value="Deactivate License" />Deactivate License</button>
                <?php wp_nonce_field( $this->data['nonce'], $this->data['nonce'] ); ?>
            </div>
        </form>

        <?php

        $messages = array();

        if( $status !== false && $status == 'valid' ) {
            $renewal_link = 'https://www.silkypress.com/checkout/?edd_license_key=' . urlencode( $license ) . '&amp;utm_campaign=admin&amp;utm_source=licenses&amp;utm_medium=renew';
            $this_domain = str_replace(array('https://', 'http://'), '', home_url()); 
            $expires       = '';
            if ( isset($license_data->expires) && $license_data->expires != 'lifetime' ) {
                try {
                    $date_format = new DateTime( $license_data->expires );
                    $expires = date_format( $date_format, 'j M, Y');
                } catch (Exception $e) {
                    $expires = '';
                }
            }
			if ( !isset( $license_data ) ) $license_data = new stdClass();
            if ( !isset($license_data->license) ) @$license_data->license = '';


            // Message: error, the license wasn't activated for this domain
            if ( $domain && !empty($domain) && $domain != $this_domain ) {
                $messages[] = array(
                    'type' => 'danger',
                    'message' => sprintf( __('This license was activated for the <b>%s</b> website, not <b>%s</b> website. This will lead to an "Unauthorized" error when attempting to update the plugin. If you click the "Deactivate License" and then the "Activate License" button, then the update will work alright.'), $domain, $this_domain ),
                );
            }


            // Message: error, the license isn't active for this domain 
            if ( $license_data->license == 'site_inactive' ) {
                $messages[] = array(
                    'type' => 'danger',
                    'message' => sprintf( __('This license was activated in the past, but the domain changed. Currently the license isn\'t activated for the <b>%s</b> domain. This will lead to an "Unauthorized" error when attempting to update the plugin. If you click the "Deactivate License" and then the "Activate License" button, then the update will work alright.'), $this_domain ),
                );
            }


            // Message: error, the license is expired 
            if ( $license_data->license == 'expired' ) {
                $messages[] = array(
                    'type' => 'danger',
                    'message' => sprintf( __('This license expired on %s. Please <a href="%s" target="_blank">click here</a> to renew your license so you can keep the plugin up-to-date.'), $expires, $renewal_link),
                );
            }


            // Message: error, the license is inactive 
            if ( $license_data->license == 'inactive' ) {
                $messages[] = array(
                    'type' => 'danger',
                    'message' => sprintf( __('This license was disabled, therefore it cannot be activated.') ),
                );
            }


            // Message: The license is activated on this domain
            if ( $license_data->license == 'valid' ) {
                $messages[] = array(
                    'type' => 'info',
                    'message' => sprintf( __('The <code>%s</code> license is activated on the <code>%s</code> domain name.'), $license_hidden, $domain),
                );
            }


            // Message: license active on this domain. Expires on __. Link to customer area
            if ( $license_data && ($license_data->license == 'valid' || $license_data->license == 'expired' ) ) {
                $_limit         = isset($license_data->license_limit) ? $license_data->license_limit : 1;
                if ( $_limit < 2 ) $_limit .= '-site license'; else $_limit .= '-sites license';
                $_site_count    = isset($license_data->site_count) ? $license_data->site_count: 1;
                if ( !empty($expires) ) $expires = sprintf( __('It expires on %s. '), $expires );
                $_link          = 'https://www.silkypress.com/login/';

                if ( $_site_count > 1 ) $_site_count .= ' websites'; else $_site_count = ' one website';

                $messages[] = array(
                    'type' => 'info',
                    'message' => sprintf( __('Your %s is currently active on %s. %sYou can use the <a href="%s" target="_blank">silkypress.com customer area</a> to manage you license.'), $_limit, $_site_count, $expires, $_link),
                );
            }


            // Message: Click here to renew your license. Shown two months before expiring.
            if ( $license_data && ($license_data->license == 'valid' || $license_data->license == 'expired' ) && strtotime( $license_data->expires ) ) {
                if ( strtotime( $license_data->expires ) - time() < 60 * 60 * 24 * 60 ) {
                    $messages[] = array(
                        'type' => 'warning',
                        'message' => sprintf( __('<a href="%s" target="_blank">Click here</a> if you want to renew your license'), $renewal_link),
                    );
                }
            }

            
        } else {


            // Message: error, license inactive
            $messages[] = array(
                'type' => 'danger',
                'message' => sprintf( __('Currently there is no license activated on this website.')),
            );
            
        } 

        if ( count($messages) > 0 ) {
            foreach($messages as $_m) {
                echo '<div class="alert alert-'.$_m['type'].'">' . $_m['message'] . '</div>';
            }
        }

    }


    /**
     * Activate/deactivate the license
     */
    function activate_deactivate_license() {

        extract($this->data);

        if( ! isset( $_POST[$activate] ) && ! isset( $_POST[$deactivate] ) ) return; 
        if( !check_admin_referer( $nonce, $nonce ) ) return;

        $this->save_options();

        $edd_action = isset( $_POST[$deactivate] ) ? 'deactivate_license' : 'activate_license';

        if ( strstr($_POST[$license_key], '***') !== false && isset($_POST[$license_key.'_open'])) {
            $_POST[$license_key] = $_POST[$license_key.'_open'];
        }

        // Send request
        $post_body = array(
            'timeout'   => 15,
            'sslverify' => false,
            'body' => array(
                'edd_action'=> $edd_action,
                'license' 	=> trim( $_POST[$license_key] ),
                'item_name' => urlencode( $item_name), 
                'url'       => home_url()
            ),
        );
        $license_data = wp_remote_post( $store_url, $post_body );

        // If request error
        if ( is_wp_error( $license_data ) ) {
			if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL && defined( 'WP_ACCESSIBLE_HOSTS' ) && ! strpos( WP_ACCESSIBLE_HOSTS, 'www.silkypress.com' ) ) {
				$block_external = 'The website blocks external URL requests. Please add "www.silkypress.com" to the WP_ACCESSIBLE_HOSTS constant in the website\'s wp-config.php file';
			}
            return array(
                'class' => 'danger',
                'msg'   => isset( $block_external) ? $block_external : 'Something went wrong during license update request. [' . $license_data->get_error_message() . ']'
            );
        } 

        // Read the response
        $license_data = json_decode( wp_remote_retrieve_body( $license_data) );
        if ( !isset($license_data->success) && !isset($license_data->error ) ) return false;

        // The request was successful 
        $admin_notice = false;
        if ( isset($license_data->success) ) {

            $messages = array(
                'valid'         => __('Your license is working fine. Good job!'),
                'deactivated'   => __('Your license was successfully deactivated for this site.'),
            );

            if ( isset($messages[$license_data->license] ) ) {
                $admin_notice = array(
                    'class' => 'success',
                    'msg'   => $messages[$license_data->license],
                );
            }
        }

        // There something wrong with the license
        if ( isset($license_data->error) ) {

            $wrong      = __('Wrong license key. Make sure you\'re using the correct license.');
            $login_url  = 'https://www.silkypress.com/login/';
            $renew_url  = 'https://www.silkypress.com/checkout/?edd_license_key='.urlencode($license_key).'&utm_campaign=admin&utm_source=licenses&utm_medium=renew';
            $messages = array(
                'invalid'               => $wrong,
                'missing'               => $wrong,
                'key_mismatch'          => $wrong,
                'license_not_activable' => __('If you have a bundle package, please use each individual license for your products.'),
                'revoked'               => __('This license was revoked.'),
                'no_activations_left'   => __('No activations left. <a href="'.$login_url.'" target="_blank">Log in to your account</a> to extend your license.'),
                'invalid_item_id'       => __('Invalid item ID.'),
                'item_name_mismatch'    => __('Item names don\'t match.'),
                'expired'               => __('Your License has expired. <a href="'.$renew_url.'" target="_blank">Renew it now.</a>'),
                'item_inactive'         => __('This license is not active. Activate it now.'),
                'disabled'              => __('License key disabled.'),
                'site_inactive'         => __('The license is not active for this site. Activate it now.'),
                
            );

            if ( isset($messages[$license_data->error] ) ) {
                $admin_notice = array(
                    'class' => 'danger',
                    'msg'   => $messages[$license_data->error] . ' [error: '.$license_data->error.']',
                );
            }
        }


        if ( $edd_action == 'activate_license' ) {
            update_option( $license_status, $license_data->license );
            update_option( $license_domain, str_replace(array('https://', 'http://'), '', home_url()) );
        } elseif ( $license_data->license == 'deactivated' ) {
            delete_option( $license_status );
        }

        return $admin_notice;

    }


    /**
     * Save the options in the database
     */
    function save_options() {
        extract( $this->data );

        $old = get_option( $license_key );
        $new = $_POST[$license_key];
        if ( strstr($_POST[$license_key], '***') !== false && isset($_POST[$license_key.'_open'])) {
            $new = $_POST[$license_key.'_open'];
        }
        if( $old != $new ) {
            update_option( $license_key, $new );
            // new license has been entered, so must reactivate
            delete_option( $license_status ); 
        }

        $license_beta = isset($_POST[$license_beta]) ? '1' : '0';
        $licente_beta_old = get_option( $license_beta );
        if ( $license_beta !== $licente_beta_old ) {
            delete_site_transient('update_plugins');
            update_option( $license_beta, $license_beta);
        }
    }
}
