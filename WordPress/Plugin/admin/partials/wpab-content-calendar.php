<?php
// File: wp-auto-blogger/admin/partials/wpab-content-calendar.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Retrieve any messages from the URL
$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

?>
<div class="wrap">
    <h1>Content Calendar</h1>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                switch ( $message ) {
                    case 'topics_generated':
                        echo 'Topics have been successfully generated.';
                        break;
                    case 'topics_updated':
                        echo 'Topics have been successfully updated.';
                        break;
                    case 'service_pages_updated':
                        echo 'Service pages have been successfully updated.';
                        break;
                    case 'no_action_selected':
                        echo 'Please select at least one topic and choose a bulk action.';
                        break;
                    case 'activation_error':
                        echo 'An error occurred while activating the license.';
                        break;
                    case 'invalid':
                        echo 'Invalid or expired license. Please check your license key.';
                        break;
                    case 'deactivated':
                        echo 'License has been successfully deactivated.';
                        break;
                    case 'deactivation_failed':
                        echo 'Failed to deactivate the license.';
                        break;
                    default:
                        echo 'Operation completed.';
                        break;
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <h2>Add Service Pages</h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php
        wp_nonce_field( 'wpab_add_service_pages', 'wpab_nonce' );
        ?>
        <input type="hidden" name="action" value="wpab_add_service_pages">
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="service_pages">Service Page URLs</label></th>
                <td>
                    <textarea id="service_pages" name="service_pages" rows="5" cols="50"><?php echo esc_textarea( implode( "\n", get_option( 'wpab_service_pages', array() ) ) ); ?></textarea>
                    <p class="description">Enter one service page URL per line.</p>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Save Service Pages' ); ?>
    </form>

    <hr>

    <h2>Generate New Topics</h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php
        wp_nonce_field( 'wpab_generate_topics', 'wpab_nonce' );
        ?>
        <input type="hidden" name="action" value="wpab_generate_topics">
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="number_of_topics">Number of Topics</label></th>
                <td>
                    <input type="number" id="number_of_topics" name="number_of_topics" value="10" min="1" max="100" />
                    <p class="description">Specify how many topics to generate.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="auto_approve_topics">Auto Approve Topics</label></th>
                <td>
                    <input type="checkbox" id="auto_approve_topics" name="auto_approve_topics" value="1" <?php checked( get_option( 'wpab_auto_approve_topics', false ) ); ?> />
                    <label for="auto_approve_topics">Automatically approve all generated topics.</label>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Generate Topics' ); ?>
    </form>

    <hr>

    <h2>Existing Topics</h2>
    <?php
    $topics_obj = new WPAB_Topics();
    $topics_obj->display_topics_table();
    ?>
</div>
