<?php
/**
 * Plugin Name: SECDASH
 * Plugin URI: http://secdash.com/
 * Description: A plugin which provides the SECDASH service with all information it needs.
 * Version: 1.0
 * Author: SECDASH UG (haftungsbeschraenkt)
 * Author URI: http://secdash.com/
 * License: GPL2
 */

/*
 * For a full list of contributors please check the AUTHORS file on https://github.com/secdash/secdash_wp_plugin/
 */

include 'secdash_options.php';
include 'secdash_utils.php';

class Secdash {
    private $secdash_api_url = "https://api.secdash.com/updater/1.0/";
    private $secdash_shared_secret_name = 'secdash_shared_secret';
    private $secdash_successful_initialized = 'secdash_successful_initialized';
    private $secdash_no_cookie_challenge_name = 'secdash_no_cookie_challenge';
    private $secdash_plugin_version = "1.0";
    private $license_key_regex = "/^[a-f0-9]{32}$/";
    private $utils;
    private $options;

    function __construct() {
        add_filter( 'query_vars', array( $this, 'registerQueryVars' ) );
        add_action( 'wp', array( $this, 'handleRequest' ) );
        add_action( 'admin_action_secdash_update_shared_secret', array( $this, 'updateSharedSecret' ) );

        // Add the textdomain and translation support
        add_action( 'plugins_loaded', array( $this, 'translation' ) );

        $this->utils   = new SecdashUtils;
        $this->options = new SecdashOptions;

        add_action( 'admin_menu', array( $this->options, 'registerOptionsMenu' ) );
    }

    /**
     * Handle the admin action "secdash_update_shared_secret".
     * By calling this via POST the SECDASH license key is updated. When
     * updating the license key a new shared secret is generated and the
     * website is re-registered with the SECDASH backend.
     */
    public function updateSharedSecret() {
        if ( empty( $_POST ) || ! wp_verify_nonce( $_POST['_wp_secdash_update_or_init'], 'secdash_update_or_init' ) ) {
            $this->settingsError( __( 'Missing License Key or CSRF attempt.', 'secdash'), 'error' );
            $this->redirectRefererAndExit();

            return false;
        }

        if ( preg_match( $this->license_key_regex, $_POST['secdash_license_key'], $matches ) !== 1 ) {
            $this->settingsError( __( 'Invalid license key.', 'secdash' ), 'error' );
            $this->redirectRefererAndExit();

            return false;
        }
        $option_reset_shared_secret = false;
        $license_key                = $_POST['secdash_license_key'];

        // Get the shared secret
        $shared_secret = get_option( $this->secdash_shared_secret_name );
        if ( ! $shared_secret || $option_reset_shared_secret ) {
            // if the shared secret wasn't created yet or the user forces a reset we generate a new one and update the database
            $shared_secret = $this->utils->generateChallenge( 128 );
            if ( ! update_option( $this->secdash_shared_secret_name, $shared_secret ) ) {
                $this->settingsError( __( "Can't write to database. Please verify that `update_options` is available or contact SECDASH to solve this problem.", 'secdash' ), 'error' );
                $this->redirectRefererAndExit();

                return false;
            }
        }

        // Build a hash containing the SECDASH registration data
        $request_data         = array(
            "crawlURL"      => get_site_url(),
            "sharedSecret"  => $shared_secret,
            "licenseKey"    => $license_key,
            "pluginVersion" => $this->secdash_plugin_version,
            "cmsType"       => "Wordpress"
        );
        $request_data_encoded = base64_encode( json_encode( $request_data ) );

        // Now try to register with SECDASH
        $registration_error        = null;
        $error_message             = "";
        $error_type                = "error";
        $allow_manual_registration = true;
        if ( $this->doBackendRegistration( $request_data, $registration_error, $allow_manual_registration ) ) {
            update_option($this->secdash_successful_initialized,true);
            $error_message = __( 'SECDASH was successfully initialized! <br/>We now continoulsy monitor your WordPress and your plugins for security issues and will notify you once an issue appears. <br/> <a href="https://www.secdash.com/board/">Details on SECDASH.com</a>', 'secdash' );
            $error_type = 'updated';
        } else {
            update_option($this->secdash_successful_initialized,false);
            $error_message = __( 'The initialization request could not be sent to the SECDASH API server:<br/>', 'secdash' );
            if ( $registration_error != null ) {
                $error_message .= "<p>$registration_error</p>";
            }
            if ($allow_manual_registration==true) {
                $error_message .= sprintf( __( 'Please use the code below to complete the initialization manually here: <a href="https://secdash.com/board/asset/create" target="_blank">SECDASH.com/board/asset/create</a>:<br/><textarea rows="15" cols="64">%s</textarea>', 'secdash' ), $request_data_encoded );
            }
        }

        $this->settingsError( $error_message, $error_type );
        $this->redirectRefererAndExit();
    }

    /*
     * Handler for "query_vars". This registers the secdash custom query
     * variables with Wordpress so that we can get access to them.
     */
    public function registerQueryVars( $vars ) {
        $vars[] = 'secdash';
        $vars[] = 'sd_response';
        $vars[] = 'sd_func';
        $vars[] = 'sd_nocookie';
        $vars[] = 'sd_reset';

        return $vars;
    }

    /*
     * Handler for the "wp" hook.
     * This is the main handler.
     * We check if the SECDASH functionality is called (by setting the "secdash"
     * query variable to a non-empty value) and prevent Wordpress from doing any
     * more work if this is the case.
     */
    public function handleRequest() {
        $secdash            = get_query_var( 'secdash' );
        $sd_response        = get_query_var( 'sd_response' );
        $sd_func            = get_query_var( 'sd_func' );
        $sd_nocookie        = get_query_var( 'sd_nocookie', false );
        $sd_reset_challenge = get_query_var( 'sd_reset' );

        // NOTE: Every handleXXX method is terminating the program flow by
        // calling die() or exit().
        if ( $secdash ) {
            if ( ! session_id() ) {
                session_start();
            }
            // SECDASH functionality is called
            if ( $sd_response ) {
                // We already got a response
                if ( $sd_func ) {
                    // And a specific method is called
                    $this->handleFunc( $sd_response, $sd_func, $sd_nocookie );
                } elseif ( $sd_reset_challenge ) {
                    // reset the challenge to something unknown
                    $this->resetChallenge( $sd_response, $sd_nocookie );
                } else {
                    // No method given, check if the response is valid
                    $this->handleResponse( $sd_response, $sd_nocookie );
                }
            }

            // No response given, send the challenge
            $this->handleChallenge( $sd_nocookie );
        }

        // else: SECDASH functionality is not required, just continue with the
        // normal flow.
    }

    /*
     * Handles the creation & output of a new challenge
     */
    private function handleChallenge( $no_cookie = false ) {
        $challenge = $this->utils->generateChallenge();
        if ( $no_cookie ) {
            update_option( $this->secdash_no_cookie_challenge_name, $challenge );
        } else {
            $_SESSION["secdash_challenge"] = $challenge;
        }

        echo $challenge;
        exit();
    }


    /*
     * Handles the resets of the stored challenge so that replay attacks are not possible (at a later point in time)
     */
    private function resetChallenge( $sd_response, $no_cookie ) {
        if ( ! $this->utils->verifyResponse( $sd_response, $no_cookie ) ) {
            die();
        }

        update_option( $this->secdash_no_cookie_challenge_name, $this->utils->generateChallenge() );
        exit();
    }

    /*
     * Handles a given response by verifying it and printing version information
     * it the response is valid.
     */
    private function handleResponse( $sd_response, $no_cookie = false ) {
        if ( ! $this->utils->verifyResponse( $sd_response, $no_cookie ) ) {
            die();
        }
        $data = array(
            "pluginname"    => "SecDash Wordpress",
            "pluginversion" => $this->secdash_plugin_version
        );
        $this->sd_send_json( $data );
    }

    /*
     * If Wordpress is older than 3.5 wp_send_json isn't available
     * This offers an alternative implementations.
     */

    private function sd_send_json( $data ) {
        if ( function_exists( 'wp_send_json' ) ) {
            wp_send_json( $data );
        } else {
            // Set headers
            @header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
            // send json
            echo json_encode( $data );
            exit( 0 );
        }

    }

    /*
     * Handles the "main functionality" of the plugin.
     * This is for example collecting & providing the information about all
     * installed plugins.
     */
    private function handleFunc( $sd_response, $sd_func, $no_cookie = false ) {
        if ( ! $this->utils->verifyResponse( $sd_response, $no_cookie ) ) {
            die();
        }

        if ( $sd_func == 'versions' ) {
            $data = $this->collectInformations();
            $this->sd_send_json( $data );
        }
    }

    /*
     * Collects informations (like version) about the webserver, CMS (Wordpress)
     * and all installed plugins.
     * @return array
     */
    private function collectInformations() {

        // Reload version.php since some plugins like to change the global wp_version
        include( ABSPATH . WPINC . '/version.php' );

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $data = array(
            "Webserver" => $this->utils->getWebserver(),
            "CMS"       => array(
                "name"    => "Wordpress",
                "version" => $wp_version
            ),
            "Plugins"   => $this->collectPlugins()
        );

        return $data;
    }

    /*
     * Collect all information about all plugins.
     * @return array
     */
    private function collectPlugins() {
        $plugins = array();
        foreach ( get_plugins() as $file => $data ) {
            $name    = array_key_exists( 'Title', $data ) ? $data['Title'] : $data['Name'];
            $version = $data['Version'];

            if ( array_key_exists( 'Name', $data ) ) {
                unset( $data['Name'] );
            }
            if ( array_key_exists( 'Title', $data ) ) {
                unset( $data['Title'] );
            }
            if ( array_key_exists( 'Version', $data ) ) {
                unset( $data['Version'] );
            }

            $data['Active'] = is_plugin_active( $file );
            /*
            * Added by Patrick @10. Apr 2015
            * The file name is acutally the qualified identifier for a plugin -,-
            */
            if ( strpos( $file, "/" ) === false ) {
                $data['FileName'] = $file;
            } else {
                $data['FileName'] = explode( "/", $file );
                $data['FileName'] = $data['FileName'][0];
            }

            $plugins[] = array(
                "name"    => $name,
                "version" => $version,
                "extra"   => $data
            );
        }

        return $plugins;
    }

    /*
     * Helper for adding a new error to the 'settings_error' transient. This is
     * then used to display the given errors in the admin interface.
     * Since we're not using the settings API directly we have to synchronize
     * the transient with the global $wp_settings_errors variable.
     */
    private function settingsError( $msg, $type ) {
        global $wp_settings_errors;
        add_settings_error( 'secdash_shared_secret', '', $msg, $type );
        set_transient( 'settings_errors', $wp_settings_errors );
    }

    private function redirectRefererAndExit() {
        wp_redirect( $_SERVER['HTTP_REFERER'] );
        exit();
    }

    private function getLocalizedStatusMessage( $statusCode ) {
        switch ( $statusCode ) {
            case '603':
                $ret = __( "SECDASH's API backend can't reach the target url.<br/>Please verifiy the following conditions are given:", 'secdash' );
                $ret .= '<ul style="list-style-type: disc; padding-left:20px;">';
                $ret .= '<li style="margin-bottom:0px;">' . __( "This website is currently not in maintenance mode", 'secdash' ) . "</li>";
                $ret .= '<li style="margin-bottom:0px;">' . __( "The index.php is reachable for our server (our server is not blocked, this page is not hidden behind a .htpasswd, etc.)", 'secdash' ) . "</li>";
                $ret .= '<li style="margin-bottom:0px;">' . __( "The SITE_URL returns the correct URL for this website", 'secdash' ) . "</li>";
                $ret .= '<li style="margin-bottom:0px;">' . __( "Requests to this website don't time out on a regular basis", 'secdash' ) . "</li>";
                $ret .= '<li style="margin-bottom:0px;">' . __( "For further assistance contact support@secdash.com", 'secdash' ) . "</li>";
                $ret .= "</ul>";

                return $ret;
                break;
            case '604':
                $ret = __('Invalid license key. You can find your license key on <a href="https://secdash.com/board/">SECDASH.com</a>','secdash');
                return $ret;
        }

        return "";
    }

    private function prettyPrintBackendError( $error, $html = true ) {
        $msg = 'Error #' . $error['statusCode'] . ":\r\n";

        if ( $html ) {
            $msg .= "<br/>";
        }
        $localizedMsg = $this->getLocalizedStatusMessage( $error['statusCode'] );
        if ( $localizedMsg == "" ) {
            $msg .= $error['statusMessage'];
            if ( $html ) {
                $msg .= "<br/>";
            }
        } else {
            $msg .= $localizedMsg;
        }

        return $msg;
    }

    /*
     * Registers the plugin/website with the SECDASH backend by POSTing a JSON
     * object containing all necessary data.
     */
    private function doBackendRegistration( $request_data, &$error_msg, &$allow_manual_registration) {
        $url     = $this->secdash_api_url;
        $options = array(
            'http' => array(
                'header'        => "Content-Type: application/json\r\n",
                'method'        => 'POST',
                'content'       => json_encode( $request_data ),
                'ignore_errors' => true
            )
        );

        if ( ! ini_get( 'allow_url_fopen' ) ) {
            $error_msg = __( "Outgoing HTTP connections are not allowed on this server. (allow_url_fopen is set to 'off' in php.ini )", 'secdash' );

            return false;
        }

        $context     = stream_context_create( $options );
        $result      = file_get_contents( $url, false, $context );
        $json_result = json_decode( $result, true );

        if ( ! $result || ! $json_result || sizeof( $json_result ) == 0 ) {
            $error_msg = __( "Can't connect to SECDASH API Server. Please proceed with manual registration.", 'secdash' );
            return false;
        }

        $matches = array();
        preg_match( '#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $matches );
        if ( $matches[1] != '200' ) {
            if ( array_key_exists( 'statusCode', $json_result ) && array_key_exists( 'statusMessage', $json_result ) ) {
                if($json_result['statusCode']!='700') {
                    $allow_manual_registration=false;
                }

                $error_msg = $this->prettyPrintBackendError( $json_result );
            } else {
                $error_msg = sprintf( __( 'Invalid HTTP status code %s. Please contact SECDASH to solve this problem.', 'secdash' ), $matches[1] );
            }

            return false;
        } else {
            if ( ! array_key_exists( 'statusCode', $json_result ) ) {
                $error_msg = sprintf( "Invalid response. Please contact SECDASH to solve this problem.", 'secdash' );
                return false;
            } else {
                if ( $json_result['statusCode'] <= 1 ) {
                    // Successful initialized.
                    if ( $json_result['statusCode'] == 0 ) {
                        // First initialization
                        $error_msg = "init";
                    } else {
                        // Update
                        $error_msg = "update";
                    }
                } else {
                    if($json_result['statusCode']!='700') {
                        $allow_manual_registration=false;
                    }
                    $error_msg = $this->prettyPrintBackendError( $json_result );

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Add Translation Support
     */
    public function translation() {
        load_plugin_textdomain( 'secdash', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

}

new Secdash;
?>
