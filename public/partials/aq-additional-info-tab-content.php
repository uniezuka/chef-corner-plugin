<?php
    global $product;

    $mfrShortName = get_post_meta($product->get_id(), 'aq_product_mfrShortName', true );
    $mfrModel = get_post_meta($product->get_id(), 'aq_product_mfrModel', true );
    $unitsPerCase = get_post_meta($product->get_id(), 'aq_product_unitsPerCase', true );
    $shippingWeight = get_post_meta($product->get_id(), 'aq_product_shippingWeight', true );
    $productDepth = get_post_meta($product->get_id(), 'aq_product_productDepth', true );
    $productWidth = get_post_meta($product->get_id(), 'aq_product_productWidth', true );
    $productHeight = get_post_meta($product->get_id(), 'aq_product_productHeight', true );

    function display_html_template($label, $value) {
        if (!$value) return;

        echo '<tr>';
        echo '<th>' . $label . '</th>';
        echo '<td>' . $value . '</td>';
        echo '</tr>';
    }
?>

<table>
    <tbody>

    <?php display_html_template('Brand', $mfrShortName); ?>
    <?php display_html_template('MFR #', $mfrModel); ?>
    <?php display_html_template('Quantity per Unit', $unitsPerCase); ?>
    <?php display_html_template('Weight', $shippingWeight); ?>
    <?php display_html_template('Depth', $productDepth); ?>
    <?php display_html_template('Width', $productWidth); ?>
    <?php display_html_template('Height', $productHeight); ?>

    </tbod>
</table>