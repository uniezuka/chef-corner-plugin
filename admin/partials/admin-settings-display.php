<div class="wrap">
    <h1>Chef's Corner Settings</h1>
    <?php settings_errors(); ?> 

    <form method="POST">  
        <?php 
            settings_fields($this->plugin_name . '-group');
        ?>  
        <table class="form-table">
            <tr valign="top">
                <th scope="row">GoPositivie Developer ID</th>
                <td>
                    <input class="regular-text" type="text" name="chefs_corner_go_postive_developer_id" value="<?php echo esc_attr(get_option('chefs_corner_go_postive_developer_id')); ?>" />
                    <p class="description">Your GoPositivie Developer ID.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">GoPositivie Developer Key</th>
                <td>
                    <input class="regular-text" type="text" name="chefs_corner_go_postive_developer_key" value="<?php echo esc_attr(get_option('chefs_corner_go_postive_developer_key')); ?>" />
                    <p class="description">Your GoPositivie Developer Key.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Notify Email</th>
                <td>
                    <input class="regular-text" type="text" name="chefs_corner_notify_email" value="<?php echo esc_attr(get_option('chefs_corner_notify_email')); ?>" />
                    <p class="description">Email of the recipient to which the CRON will notify to.</p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>  
    </form> 
</div>