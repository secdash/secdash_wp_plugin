<?php
include 'secdash_upgrader_skin.php';
class SecdashUtils {
    protected $_phpInfoArray = null;
    private $secdash_shared_secret_name = 'secdash_shared_secret';
    private $secdash_no_cookie_challenge_name = 'secdash_no_cookie_challenge';
    private $secdash_successful_initialized = 'secdash_successful_initialized';
    /**
     * @param int $hexLength max 32
     */
    public function generateChallenge($hexLength = 32)
    {
        $len = floor($hexLength / 2);
        if (function_exists('openssl_random_pseudo_bytes')) {
            $challenge = bin2hex(openssl_random_pseudo_bytes($len));
        } elseif (function_exists('mcrypt_create_iv')) {
            // Required in PHP > 5.3
            srand(make_seed());
            $challenge = bin2hex(mcrypt_create_iv($len, MCRYPT_DEV_URANDOM));
        } else {
            // This is not great but right now too many pages are based on old PHP Versions to remove it.
             $challenge = chr( mt_rand( ord( 'a' ) ,ord( 'z' ) ) ) .substr( md5( time( ) ) ,1 );
            if ($hexLength < 32) {
                $challenge = substr($challenge, 0, $hexLength);
            }
        }
        return $challenge;
    }
    
    public function sd_send_json( $data ) {
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
    /**
    * Checks and installs all available plugin updates. 
    * We will fail silently if necessary file rights are not given etc.
    */
    public function update_all_plugins() {  
        // Force a new fetch by saying we are running as a CRON job.
        define("DOING_CRON",true);
        wp_version_check(array(),true); 
        $plugins = array_keys(get_plugin_updates());
        if (count($plugins) == 0) 
        {
            $this->sd_send_json(array('updates' => array()));
        }
        // Create a silent upgrader
    $upgrader = new Plugin_Upgrader(new SecdashUpgraderSkin());
    // get upgrader results, null => Failed!
    $results = $upgrader->bulk_upgrade($plugins);
    $ret = ['updates' => json_encode($results)];
    // that's it, send results
    $this->sd_send_json($ret);
    }

    private function makeSeed() 
    {
        function make_seed()
        {
          list($usec, $sec) = explode(' ', microtime());
          return (float) $sec + ((float) $usec * 100000);
        }
    }

    /*
     * Verifies a given response.
     * @return bool true if the response is valid
     */
    public function verifyResponse($sd_response,$no_cookie=false) {
        $shared_secret = get_option($this->secdash_shared_secret_name);
        // Not yet initialized
        if(!$shared_secret) {
            return false;
        }

        if ($no_cookie) {
            // If we have can't use cookies the last challenge will be stored in the database
            $challenge = get_option($this->secdash_no_cookie_challenge_name);
            // The challenge has to be available
            if (!$challenge) {
                return false;
            }
        } else {
            // use challenge f
            $challenge = $_SESSION['secdash_challenge'];
        }
        $correct_response = hash('sha512', $shared_secret . $challenge);
        return ($correct_response == $sd_response);
    }

    /**
     * @return array
     */
    public function getWebserver()
    {
        $software = explode('/', $_SERVER['SERVER_SOFTWARE']);

        $phpExtensions = array();
        foreach (get_loaded_extensions() as $extensionName) {
            $extension = array(
                'name'      => (string)$extensionName,
                'version'   => (string)phpversion($extensionName),
            );
            if (empty($extension['version'])) {
                unset($extension['version']);
            }
            $phpExtensions[] = $extension;
        }
        $phpVersion = explode('-', phpversion());

        $webserver = array(
            'name'          => $software[0],
            'version'       => isset($software[1]) ? $software[1] : null,
            'php'           => array(
                'version'       => (string)$phpVersion[0],
                'extensions'    => $phpExtensions,
            ),
            'ssl'           => array(),
            'os'            => php_uname(),
        );
        if (extension_loaded('openssl')) {
            $phpInfo = $this->getPhpInfoArray();
            $sslText = explode(' ', OPENSSL_VERSION_TEXT);
            $webserver['ssl'] = array(
                'module'            => 'openssl',
                'version'           => isset($sslText[1]) ? $sslText[1] : null,
                'version_number'    => (string)OPENSSL_VERSION_NUMBER,
                'version_text'      => (string)OPENSSL_VERSION_TEXT,
            );
        } else {
            $webserver['ssl'] = array(
                'module'    => 'unknown',
            );
        }

        return $webserver;
    }

    /**
     * @return array|mixed
     */
    public function getPhpInfoArray()
    {
        if ($this->_phpInfoArray) {
            return $this->_phpInfoArray;
        }

        try {

            ob_start();
            phpinfo(INFO_ALL);

            $pi = preg_replace(
                array(
                    '#^.*<body>(.*)</body>.*$#m', '#<h2>PHP License</h2>.*$#ms',
                    '#<h1>Configuration</h1>#',  "#\r?\n#", "#</(h1|h2|h3|tr)>#", '# +<#',
                    "#[ \t]+#", '#&nbsp;#', '#  +#', '# class=".*?"#', '%&#039;%',
                    '#<tr>(?:.*?)" src="(?:.*?)=(.*?)" alt="PHP Logo" /></a><h1>PHP Version (.*?)</h1>(?:\n+?)</td></tr>#',
                    '#<h1><a href="(?:.*?)\?=(.*?)">PHP Credits</a></h1>#',
                    '#<tr>(?:.*?)" src="(?:.*?)=(.*?)"(?:.*?)Zend Engine (.*?),(?:.*?)</tr>#',
                    "# +#", '#<tr>#', '#</tr>#'),
                array(
                    '$1', '', '', '', '</$1>' . "\n", '<', ' ', ' ', ' ', '', ' ',
                    '<h2>PHP Configuration</h2>'."\n".'<tr><td>PHP Version</td><td>$2</td></tr>'.
                    "\n".'<tr><td>PHP Egg</td><td>$1</td></tr>',
                    '<tr><td>PHP Credits Egg</td><td>$1</td></tr>',
                    '<tr><td>Zend Engine</td><td>$2</td></tr>' . "\n" .
                    '<tr><td>Zend Egg</td><td>$1</td></tr>', ' ', '%S%', '%E%'
                ), ob_get_clean()
            );

            $sections = explode('<h2>', strip_tags($pi, '<h2><th><td>'));
            unset($sections[0]);

            $pi = array();
            foreach ($sections as $section) {
                $n = substr($section, 0, strpos($section, '</h2>'));
                preg_match_all(
                    '#%S%(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?%E%#',
                    $section,
                    $askapache,
                    PREG_SET_ORDER
                );
                foreach ($askapache as $m) {
                    if (!isset($m[0]) || !isset($m[1]) || !isset($m[2])) {
                        continue;
                    }
                    $pi[$n][$m[1]]=(!isset($m[3])||$m[2]==$m[3])?$m[2]:array_slice($m,2);
                }
            }

        } catch (Exception $exception) {
            return array();
        }

        $this->_phpInfoArray = $pi;

        return $this->_phpInfoArray;
    }
}
?>
