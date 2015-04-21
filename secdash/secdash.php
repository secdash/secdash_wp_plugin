<?php
/**
 * Plugin Name: SECDASH
 * Plugin URI: http://secdash.com/
 * Description: A plugin which provides the SECDASH service with all information it needs.
 * Version: 0.9.1
 * Author: SECDASH UG (haftungsbeschraenkt)
 * Author URI: http://secdash.com/
 * License: GPL2
 */

include 'secdash_options.php';
include 'secdash_utils.php';

class Secdash {
    private $secdash_api_url = "https://api.secdash.com/updater/1.0/";
    private $secdash_shared_secret_name = 'secdash_shared_secret';
    private $secdash_plugin_version = "0.9.1";
    private $utils;
    private $options;

    function __construct() {
        add_action('init', array($this, 'registerSession'));
        add_filter('query_vars', array($this, 'registerQueryVars'));
        add_action('wp', array($this, 'handleRequest'));
        add_action('admin_action_secdash_update_shared_secret', array($this, 'updateSharedSecret'));

        $this->utils = new SecdashUtils;
        $this->options = new SecdashOptions;

        add_action('admin_menu', array($this->options, 'registerOptionsMenu'));
    }

    /**
     * Handle init event. Starts a PHP session for the current client.
     */
    public function registerSession() {
        if(!session_id()) {
            session_start();
        }
    }

    /**
     * Handle the admin action "secdash_update_shared_secret".
     * By calling this via POST the SECDASH license key is updated. When
     * updating the license key a new shared secret is generated and the
     * website is re-registered with the SECDASH backend.
     */
    public function updateSharedSecret() {
        $license_key = $_POST['secdash_license_key'];

        if(!$license_key) {
            $this->settingsError('You have to enter your license key.', 'error');
            $this->redirectRefererAndExit();
        }

        // Generate a new shared secret (re-using the generateChallenge method)
        // and save it into the database.
        $new_shared_secret = $this->utils->generateChallenge(128);
        if(!update_option($this->secdash_shared_secret_name, $new_shared_secret)) {
            settings_error('Could not save shared secret to the database. Please contact SECDASH to solve this problem.', 'error');
            return false;
        }

        // Build a hash containing the SECDASH registration data
        $request_data = array(
            "crawlURL" => get_site_url(),
            "sharedSecret" => $new_shared_secret,
            "licenseKey" => $license_key,
            "pluginVersion" => $this->secdash_plugin_version,
            "cmsType" => "Wordpress"
        );
        $request_data_encoded = base64_encode(json_encode($request_data));

        // Now try to register with SECDASH
        $registration_error = null;
        $error_message = "";
        $error_type = "error";
        if($this->doBackendRegistration($request_data, $registration_error)) {
            $error_message = 'Successfully (re-)registered with SECDASH!';
            $error_type = 'updated';
        } else {
            $error_message = 'Sorry, I was unable to send the registration request to the SECDASH server.<br/>';
            if($registration_error != null) {
                $error_message .= "The server error was:\r\n<br/>$registration_error<br/>";
            }
            $error_message .= 'Please use this code for manual registration:<br/>'.$this->formatBase64($request_data_encoded);
        }

        $this->settingsError($error_message, $error_type);

        $this->redirectRefererAndExit();
    }

    /*
     * Handler for "query_vars". This registers the secdash custom query
     * variables with Wordpress so that we can get access to them.
     */
    public function registerQueryVars($vars) {
        $vars[] = 'secdash';
        $vars[] = 'sd_response';
        $vars[] = 'sd_func';
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
        $secdash = get_query_var('secdash');
        $sd_response = get_query_var('sd_response');
        $sd_func = get_query_var('sd_func');

        // NOTE: Every handleXXX method is terminating the program flow by
        // calling die() or exit().
        if($secdash) {
            // SECDASH functionality is called
            if($sd_response) {
                // We already got a response
                if($sd_func) {
                    // And a specific method is called
                    $this->handleFunc($sd_response, $sd_func);
                } else {
                    // No method given, check if the response is valid
                    $this->handleResponse($sd_response);
                }
            }

            // No response given, send the challenge
            $this->handleChallenge();
        }

        // else: SECDASH functionality is not required, just continue with the
        // normal flow.
    }

    /*
     * Handles the creation & output of a new challenge
     */
    private function handleChallenge() {
        $challenge = $this->utils->generateChallenge();
        $_SESSION["secdash_challenge"] = $challenge;
        echo $challenge;
        exit();
    }

    /*
     * Handles a given response by verifying it and printing version information
     * it the response is valid.
     */
    private function handleResponse($sd_response) {
        if(!$this->utils->verifyResponse($sd_response)) {
            die();
        }

        wp_send_json(array(
            "pluginname" => "SecDash Wordpress",
            "pluginversion" => $this->secdash_plugin_version
        ));
    }

    /*
     * Handles the "main functionality" of the plugin.
     * This is for example collecting & providing the information about all
     * installed plugins.
     */
    private function handleFunc($sd_response, $sd_func) {
        if(!$this->utils->verifyResponse($sd_response)) {
            die();
        }

        if($sd_func == 'versions') {
            $data = $this->collectInformations();
            wp_send_json($data);
        }
    }

    /*
     * Collects informations (like version) about the webserver, CMS (Wordpress)
     * and all installed plugins.
     * @return array
     */
    private function collectInformations() {
        global $wp_version;

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $data = array(
            "Webserver" => $this->utils->getWebserver(),
            "CMS" => array(
                "name" => "Wordpress",
                "version" => $wp_version
            ),
            "Plugins" => $this->collectPlugins()
        );

        return $data;
    }

    /*
     * Collect all information about all plugins.
     * @return array
     */
    private function collectPlugins() {
        $plugins = array();
        foreach(get_plugins() as $file => $data) {
            $name = array_key_exists('Title', $data) ? $data['Title'] : $data['Name'];
            $version = $data['Version'];

            if(array_key_exists('Name', $data))
                unset($data['Name']);
            if(array_key_exists('Title', $data))
                unset($data['Title']);
            if(array_key_exists('Version', $data))
                unset($data['Version']);

            $data['Active'] = is_plugin_active($file);
            /*
            * Added by Patrick @10. Apr 2015
            * The file name is acutally the qualified identifier for a plugin -,-
            */
            if (strpos($file,"/")===false) {
            $data['FileName'] = $file;
                } else {
            $data['FileName'] = explode("/",$file)[0];
            }
            
            $plugins[] = array(
                "name" => $name,
                "version" => $version,
                "extra" => $data
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
    private function settingsError($msg, $type) {
        global $wp_settings_errors;
        add_settings_error('secdash_shared_secret', '', $msg, $type);
        set_transient('settings_errors', $wp_settings_errors);
    }

    private function formatBase64($data) {
        $result = "";
        for($i = 0; $i < strlen($data); $i++) {
            $result = $result . $data[$i];
            if($i > 0 && $i % 32 == 0) {
                $result = $result . "\r\n<br/>";
            }
        }
        return $result;
    }

    private function redirectRefererAndExit() {
        wp_redirect($_SERVER['HTTP_REFERER']);
        exit();
    }

    private function prettyPrintBackendError($error, $html = true) {
        $msg = 'Error #'.$error['statusCode'].":\r\n";

        if($html) {
            $msg .= "<br/>";
        }

        $msg .= $error['statusMessage'];
        if($html) {
            $msg .= "<br/>";
        }

        return $msg;
    }

    /*
     * Registers the plugin/website with the SECDASH backend by POSTing a JSON
     * object containing all necessary data.
     */
    private function doBackendRegistration($request_data, &$error_msg) {
        $url = $this->secdash_api_url;
        $options = array(
            'http' => array(
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($request_data),
                'ignore_errors' => true
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $json_result = json_decode($result, true);

        if(!$result || !$json_result || sizeof($json_result) == 0) {
            $error_msg = "Invalid response. Please contact SECDASH to solve this problem.";
            return false;
        }

        $matches = array();
        preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $matches);
        if($matches[1] != '200') {
            if(array_key_exists('statusCode', $json_result) && array_key_exists('statusMessage', $json_result)) {
                $error_msg = $this->prettyPrintBackendError($json_result);
            } else {
                $error_msg = "Invalid HTTP status code $matches[1]. Please contact SECDASH to solve this problem.";
            }
            return false;
        } else {
            if(!array_key_exists('statusCode', $json_result)) {
                $error_msg = "Invalid response. Please contact SECDASH to solve this problem.";
                return false;
            } else if($json_result['statusCode'] > 1) {
                $error_msg = $this->prettyPrintBackendError($json_result);
                return false;
            }
        }

        return true;
    }
}

new Secdash;
?>
