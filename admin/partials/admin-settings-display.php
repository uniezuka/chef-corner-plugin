<div class="wrap">
    <h1>Chef's Corner Settings</h1>
    <?php settings_errors(); ?> 

    <div id="template_clone" style="display: none;">
        <div id="rename_clone" class="template_clone row form-group">
            <h4>Rename AQ Category</h4>
            <label>Old AQ Category Name <input type="text" class="regular-text" name="old_category_name[]" /></label>
            <label>New AQ Category Name <input type="text" class="regular-text" name="new_category_name[]" /></label>
            <input type="button" value="Remove" class="remove_template" /> 
        </div>

        <div id="exclude_clone" class="template_clone row form-group">
            <h4>Exclude an AQ category</h4>
            <label>AQ Category ID <input type="text" class="regular-text" name="exclude_category_id[]" /></label>
            <input type="button" value="Remove" class="remove_template" /> 
            <p class="description">The AQ Category Id. Refer to the AQ Category API response.</p>
        </div>
    </div>

    <form method="POST">  
        <?php 
            settings_fields($this->plugin_name . '-group');
        ?>  
        <table class="form-table">
            <tr valign="top">
                <th scope="row">AQ API Key</th>
                <td>
                    <input class="regular-text" type="text" name="chefs_corner_aq_api_key" value="<?php echo esc_attr(get_option('chefs_corner_aq_api_key')); ?>" />
                    <p class="description">Your AutoQuote API key. See <a href="https://support.aq-fes.com/s/article/Generating-API-Keys?language=en_US" target="_blank">link</a> for the generation of the API key.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Manufacturers</th>
                <td>
                    <input class="regular-text" type="text" name="chefs_corner_manufacturers" value="<?php echo esc_attr(get_option('chefs_corner_manufacturers')); ?>" />
                    <p class="description">List of Manufacturer names (separated by |) to get the list of products.</p>
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

        <h2>Category Ruleset<h2>

        <div>
            <strong>Add new rules</strong> 
            <select name="new_rules" id="new_rules">
                <option value="rename">Rename</option>
                <option value="exclude">Exclude</option>
            </select> 
            <button type="button" id="new_ruleset" class="page-title-action">Add</button>
        </div>

        <div id="ruleset">
            <?php 
                $ruleset = get_option('chefs_corner_category_ruleset');
                foreach($ruleset as $key => $value) {
                    $rule = $value;
            ?>
                <?php if ($rule->rule_type == 'rename') { ?>
                    <div class="template_clone row form-group">
                        <h4>Rename AQ Category</h4>
                        <label>Old AQ Category Name <input type="text" class="regular-text" name="old_category_name[]" value="<?php echo esc_attr($rule->old_category_name); ?>" /></label>
                        <label>New AQ Category Name <input type="text" class="regular-text" name="new_category_name[]" value="<?php echo esc_attr($rule->new_category_name); ?>" /></label>
                        <input type="button" value="Remove" class="remove_template" /> 
                    </div>
                <?php } ?>

                <?php if ($rule->rule_type == 'exclude') { ?>
                    <div class="template_clone row form-group">
                        <h4>Exclude an AQ category</h4>
                        <label>AQ Category ID <input type="text" class="regular-text" name="exclude_category_id[]" value="<?php echo esc_attr($rule->exclude_category_id); ?>" /></label>
                        <input type="button" value="Remove" class="remove_template" /> 
                        <p class="description">The AQ Category Id. Refer to the AQ Category API response.</p>
                    </div>
                <?php } ?>

            <?php } ?>
        </div>

        <?php submit_button(); ?>  
    </form> 
</div>