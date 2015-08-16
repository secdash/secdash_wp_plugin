<?php 
    	require_once(ABSPATH .  'wp-admin/includes/misc.php');
    	require_once(ABSPATH .  'wp-admin/includes/file.php');
    	require_once(ABSPATH .  'wp-admin/includes/plugin.php');
    	require_once(ABSPATH .  'wp-admin/includes/update.php');
    	require_once(ABSPATH .  'wp-admin/includes/class-wp-upgrader.php');
    	/*
    	Outputless Upgrader Skin (bit of a hack)
    	*/
class SecdashUpgraderSkin extends WP_Upgrader_Skin {
	public function set_upgrader(&$upgrader) {
        if ( is_object($upgrader) )
            $this->upgrader =& $upgrader;
        $this->add_strings();
    }
 
    public function add_strings() {
    }
 
    public function set_result($result) {
        $this->result = $result;
    }
 
    public function request_filesystem_credentials( $error = false, $context = false, $allow_relaxed_file_ownership = false ) {
    	// we won't do anything here right now, we might want to use this later to allow (S)FTP Updates. 
    	return;
    }
 
    public function header() {}
    public function footer() {}
 
    public function error($errors) {}
 
    public function feedback($string) {}
    public function before() {}
    public function after() {}
 

    protected function decrement_update_count( $type ) {}
 
    public function bulk_header() {}
    public function bulk_footer() {}	
}
?>