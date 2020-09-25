<div class="wrap">
    <h2>Backups Files<h2>
    <span>Download and import a backup file. See <a href="https://docs.woocommerce.com/document/product-csv-importer-exporter/" target="_blank">link</a> on how to import csv files to WooCommerce.</span>

    <div style="margin:10px; padding:10px">
    <?php 
        foreach($links as $key => $link) {
            echo '<a style="display:block;margin:10px;" href="' . $link . '">' . $key . '</a>';
        }
    ?>
    </div>
</div>