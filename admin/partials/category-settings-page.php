<div class="wrap">
    <h2>Category Settings<h2>

    <?php settings_errors(); ?>  

    <div id="template_clone" style="display: none;">
        <div id="rename_clone" class="template_clone row form-group">
            <h4>Rename AQ Category</h4>
            <input type="hidden" value="rename" name="rule[]" />
            <label>Old AQ Category Name <input type="text" class="regular-text" name="old_category_name[]" /></label>
            <label>New AQ Category Name <input type="text" class="regular-text" name="new_category_name[]" /></label>
        </div>

        <div id="exclude_clone" class="template_clone row form-group">
            <h4>Exclude an AQ category</h4>
            <input type="hidden" value="rename" name="rule[]" />
            <label>AQ Category ID <input type="text" class="regular-text" name="category_id[]" /></label>
            <p class="description">The AQ Category Id. Refer to the AQ API response.</p>
        </div>
    </div>

    <form method="POST" action="options.php">  
        <?php 
            settings_fields($this->plugin_name . '-group');
            do_settings_sections($this->plugin_name . '-group'); 

            $ruleset = get_option('chefs_corner_category_ruleset');
        ?>  

        <div>
            <strong>Add new rules</strong> 
            <select name="new_rules" id="new_rules">
                <option value="rename">Rename</option>
                <option value="exclude">Exclude</option>
            </select> 
            <button type="button" id="new_ruleset" class="page-title-action">Add</button>
        </div>

        <div id="ruleset">
        </div>

        <?php submit_button(); ?>  
    </div>
</div>