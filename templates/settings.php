<div class="wrap">
    <h2>Sift Ninja</h2>
    <form method="post" action="options.php"> 
        <?php @settings_fields('sift_ninja-group'); ?>

        <?php do_settings_sections('sift_ninja'); ?>

        <?php @submit_button(); ?>
    </form>
</div>
