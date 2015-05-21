<?php
class SecdashOptions {
    private $secdash_successful_initialized = 'secdash_successful_initialized';
    private $secdash_shared_secret_name = 'secdash_shared_secret';

    public function registerOptionsMenu() {
        add_options_page( __( 'Initialize SECDASH', 'secdash' ), __( 'SECDASH', 'secdash' ), 'manage_options', 'secdash-options-menu-identifier', array($this, 'optionsPage'));

        add_action('admin_init', array($this, 'registerOptions'));
    }

    public function registerOptions() {
        register_setting('secdash-registration-group', $this->secdash_shared_secret_name);
    }

    public function optionsPage() {
        global $wp_settings_errors;

        if (!current_user_can('manage_options'))  {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'secdash' ) );
        }

        $wp_settings_errors = get_transient('settings_errors');
    ?>
        <div class="wrap">
        <h2><?php _e( 'Initialize SECDASH', 'secdash' ); ?></h2>

            <p>
            <h3><?php _e('What happens here?','secdash')?></h3>
                <?php _e('In order to monitor your website SECDASH is required to be able to retrieve the currently installed WordPress- and plugin versions. The initialization process is required to connect this WordPress website to your SECDASH profile and to generate a shared key that is used to retrieve the version information from this website in a secure manner. ','secdash')?>
            </p>
            <p>
                <?php _e('Please note that the initialization is not possible while WordPress is in maintenance mode or while the index.php is not reachable.','secdash')?>
            </p>

        <form method="post" action="<?php echo admin_url('admin.php'); ?>">
        <input type="hidden" name="action" value="secdash_update_shared_secret" />

        <p style="font-weight: bold;"><?php _e( 'To initialize SECDASH please enter your license key (for security reasons this key will NOT be stored in your WordPress database). <br/>The SECDASH license key is available on <a href="https://secdash.com/board" title="SECDASH Homepage">SECDASH.com</a>:', 'secdash' ); ?></p>
    <?php
        if(get_option($this->secdash_successful_initialized)) {
    ?>
        <div class='updated settings-error'>
            <?php _e( 'SECDASH has been successfully activated. Re-enter your license key to repeat the initialization (e.g. after the location of this website changed).', 'secdash' ); ?>
        </div>
    <?php
        }
    ?>
        <table class="form-table">
            <tr valign="top">
            <th scope="row"><?php _e( 'License Key:', 'secdash' ); ?></th>
            <td><input type="text" name="secdash_license_key" value="" /></td>
            </tr>
        </table>
            <?php wp_nonce_field('secdash_update_or_init','_wp_secdash_update_or_init'); ?>
        <?php settings_errors(); set_transient('settings_errors', array()); ?>
        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Initialize SECDASH', 'secdash' ); ?>"  />

        </form>
        </div>
<?php
    }
}
?>
