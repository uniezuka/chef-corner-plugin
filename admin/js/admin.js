(function($){
    $(document).ready(function () {
        $('#new_ruleset').on('click', function () {
            var template_html = null;

            var new_rule_command = $('#new_rules').children("option:selected").val();

            if (new_rule_command === 'rename') {
                template_html = $('#rename_clone').clone(true);
            }
            else if (new_rule_command === 'move_products') {
                template_html = $('#move_product_clone').clone(true);
            }
            else if (new_rule_command === 'exclude') {
                template_html = $('#exclude_clone').clone(true);
            }

            if (template_html === null) return;
            
            template_html.removeAttr("id");
            template_html.removeAttr("style");

            $("#ruleset").append(template_html);
        });

        $('.remove_template').on('click', function () {
            $(this).closest('.template_clone').remove();
        });
    });
})(jQuery.noConflict());