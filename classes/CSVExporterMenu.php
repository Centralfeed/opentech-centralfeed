
<div class="wrap">
    <h1>CentralFeed</h1>
    <form method="post" action="options.php">
        <?php
        // output security fields for the registered setting "iss_urls"
        settings_fields('centralfeed_options');
        // output setting sections and their fields
        // (sections are registered for "iss_urls", each field is registered to a specific section)
        do_settings_sections('centralfeed_options');
        // output save settings button
        submit_button(__('Save Settings', 'centralfeed_options'));
        ?>
    </form>
</div>