<?php
class SecdashOptions {
    private $secdash_shared_secret_name = 'secdash_shared_secret';

    public function registerOptionsMenu() {
        add_options_page( __( 'SECDASH Options', 'secdash' ), __( 'SECDASH', 'secdash' ), 'manage_options', 'secdash-options-menu-identifier', array($this, 'optionsPage'));

        add_action('admin_init', array($this, 'registerOptions'));
    }

    public function registerOptions() {
        register_setting('secdash-registration-group', $this->secdash_shared_secret_name);
    }

    public function optionsPage() {
        global $wp_settings_errors;

        if (!current_user_can('manage_options'))  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        $wp_settings_errors = get_transient('settings_errors');
    ?>
        <div class="wrap">
        <h2><?php _e( 'SECDASH Options', 'secdash' ); ?></h2>
        <?php settings_errors(); set_transient('settings_errors', array()); ?>
        <form method="post" action="<?php echo admin_url('admin.php'); ?>">

        <input type="hidden" name="action" value="secdash_update_shared_secret" />

        <h4><?php _e( 'For (re-)registering with SECDASH please enter your licence key:', 'secdash' ); ?></h4>
    <?php
        if(get_option($this->secdash_shared_secret_name)) {
    ?>
        <div class='updated settings-error'>
            <?php _e( 'This plugin is already registered with SECDASH. Submitting this form will result in updating the registration.', 'secdash' ); ?>
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

        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Save Changes', 'secdash' ); ?>"  />

        </form>
        </div>
<?php
    }
}
?>
