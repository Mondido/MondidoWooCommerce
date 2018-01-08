<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var string $url */
?>
<script type="text/javascript">
    var isInIframe = (window.location != window.parent.location) ? true : false;
    if (isInIframe == true) {
        window.top.location.href = '<?php echo esc_html($url); ?>';
    } else {
        window.location.href = '<?php echo esc_html($url); ?>';
    }
</script>
