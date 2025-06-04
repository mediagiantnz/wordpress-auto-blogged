<?php
// File: wp-auto-blogger/admin/classes/class-wpab-schedule-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_Schedule_Handler {

    public function __construct() {
        // Save schedule from admin form
        add_action( 'admin_post_wpab_save_schedule', array( $this, 'handle_save_schedule' ) );

        // WP-Cron hook: actual publishing + re-schedule
        add_action( 'wpab_publish_scheduled_post', array( $this, 'publish_scheduled_post' ) );
        
        // Debug action for testing
        add_action( 'admin_init', array( $this, 'maybe_run_test_cron' ) );
    }

    /**
     * Debug function to test cron manually
     */
    public function maybe_run_test_cron() {
        if ( isset( $_GET['wpab_test_cron'] ) && $_GET['wpab_test_cron'] === '1' && current_user_can( 'manage_options' ) ) {
            error_log('[WPAB TEST CRON] Manually triggering publish_scheduled_post');
            $this->publish_scheduled_post();
            wp_die('Cron test completed. Check your error logs.');
        }
    }

    /**
     * Display the Schedule Settings Page
     */
    public function display_schedule_page() {
        // Load existing settings
        $schedule = get_option( 'wpab_schedule_settings', array(
            'frequency'  => 'daily',
            'number'     => 1,
            'start_hour' => '09', // default 9 AM
        ) );

        // We'll auto-set end_hour as start+3 (mod 24)
        $start_val = (int) $schedule['start_hour'];
        $end_val   = ($start_val + 3) % 24;
        $schedule['end_hour'] = str_pad($end_val, 2, '0', STR_PAD_LEFT);

        ?>
        <div class="wrap">
            <h1>Schedule Settings (Random Post Time in a 3-Hour Window)</h1>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <?php wp_nonce_field( 'wpab_save_schedule', 'wpab_schedule_nonce' ); ?>
                <input type="hidden" name="action" value="wpab_save_schedule">

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="frequency">Publish Frequency</label></th>
                        <td>
                            <?php
                            $freq = isset($schedule['frequency']) ? $schedule['frequency'] : 'daily';
                            ?>
                            <select id="frequency" name="frequency" required>
                                <option value="daily"   <?php selected($freq, 'daily');   ?>>Daily</option>
                                <option value="weekly"  <?php selected($freq, 'weekly');  ?>>Weekly</option>
                                <option value="monthly" <?php selected($freq, 'monthly'); ?>>Monthly</option>
                                <option value="yearly"  <?php selected($freq, 'yearly');  ?>>Yearly</option>
                            </select>

                            <input type="number" name="number"
                                   value="<?php echo esc_attr($schedule['number']); ?>"
                                   min="1" style="width:60px; margin-left:10px;" required />
                            <p class="description">How many blogs to publish per interval (e.g. 5 daily).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="publish_start_hour">Publish Start Hour</label></th>
                        <td>
                            <select id="publish_start_hour" name="publish_start_hour" required>
                                <?php $this->render_hour_options($schedule['start_hour']); ?>
                            </select>
                            <p class="description">
                                We auto-add +3 hours to form a 3-hour window. e.g. If 09, window is 09–12.
                                The plugin will pick a random hour/minute in that window each day.
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Auto Publish Enabled</th>
                        <td>
                            <input type="checkbox" id="auto_publish" name="auto_publish" value="1"
                                <?php checked( get_option('wpab_auto_publish', false ) ); ?> />
                            <label for="auto_publish">Enable Auto Publishing of Approved Topics</label>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Schedule Settings' ); ?>
            </form>

            <hr>
            <h2>Schedule Overview</h2>
            <?php $this->display_schedule_overview(); ?>
        </div>
        <?php
    }

    /**
     * Render hour dropdown for 0..23
     */
    private function render_hour_options( $selected_hour ) {
        for ( $hour = 0; $hour < 24; $hour++ ) {
            $hour_padded = str_pad($hour, 2, '0', STR_PAD_LEFT);
            // For display, e.g. 9 AM
            $display = date('g A', strtotime("$hour:00"));
            echo '<option value="' . esc_attr($hour_padded) . '" '
                . selected($selected_hour, $hour_padded, false) . '>'
                . esc_html($display) . '</option>';
        }
    }

    /**
     * Saving schedule form
     */
    public function handle_save_schedule() {
        if ( ! isset($_POST['wpab_schedule_nonce'])
            || ! wp_verify_nonce($_POST['wpab_schedule_nonce'], 'wpab_save_schedule')
        ) {
            wp_die('Security check failed.');
        }
        if ( ! current_user_can('manage_options') ) {
            wp_die('Insufficient permissions.');
        }

        $frequency  = isset($_POST['frequency'])          ? sanitize_text_field($_POST['frequency']) : 'daily';
        $number     = isset($_POST['number'])             ? absint($_POST['number']) : 1;
        $start_hour = isset($_POST['publish_start_hour']) ? sanitize_text_field($_POST['publish_start_hour']) : '09';
        $auto_publish = isset($_POST['auto_publish']) && $_POST['auto_publish'] == '1';

        // Force a 3-hour window => end_hour
        $start_val = (int)$start_hour;
        $end_val   = ($start_val + 3) % 24;
        $end_str   = str_pad($end_val, 2, '0', STR_PAD_LEFT);

        // store
        update_option('wpab_schedule_settings', array(
            'frequency'  => $frequency,
            'number'     => $number,
            'start_hour' => $start_hour,
            'end_hour'   => $end_str,
        ));
        update_option('wpab_auto_publish', $auto_publish);

        // Schedule next random event
        $this->clear_scheduled_publishing();
        $this->schedule_next_event(); // we do single-event logic

        wp_redirect( admin_url('admin.php?page=wpab-schedule&message=schedule_saved') );
        exit;
    }

    /**
     * Display schedule overview with next run time
     */
    private function display_schedule_overview() {
        $schedule = get_option('wpab_schedule_settings', array(
            'frequency'  => 'daily',
            'number'     => 1,
            'start_hour' => '09',
            'end_hour'   => '12',
        ));
        $approved_topics = get_option('wpab_topics', array());
        $approved_topics = array_filter($approved_topics, function($t){
            return isset($t['status']) && $t['status'] === 'approved';
        });
        $approved_count = count($approved_topics);

        // next run
        $timestamp = wp_next_scheduled('wpab_publish_scheduled_post');

        // run-out
        $freq   = $schedule['frequency'];
        $number = (int) $schedule['number'];
        $intervals_needed = $number > 0 ? ceil($approved_count / $number) : 0;
        $run_out_date = new DateTime();

        switch($freq){
            case 'daily':
                $run_out_date->modify("+{$intervals_needed} days");
                break;
            case 'weekly':
                $run_out_date->modify("+{$intervals_needed} weeks");
                break;
            case 'monthly':
                $run_out_date->modify("+{$intervals_needed} months");
                break;
            case 'yearly':
                $run_out_date->modify("+{$intervals_needed} years");
                break;
        }

        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Approved Topics</th>
                <td><?php echo esc_html($approved_count); ?></td>
            </tr>
            <tr valign="top">
                <th scope="row">Current Frequency</th>
                <td><?php echo esc_html($schedule['number'] . ' ' . ucfirst($schedule['frequency'])); ?></td>
            </tr>
            <tr valign="top">
                <th scope="row">Time Window</th>
                <td>
                    <?php
                    $start_str = DateTime::createFromFormat('H',$schedule['start_hour'])->format('g A');
                    $end_str   = DateTime::createFromFormat('H',$schedule['end_hour'])->format('g A');
                    echo esc_html("$start_str to $end_str (3 hours)");
                    ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Auto Publish</th>
                <td>
                    <?php echo get_option('wpab_auto_publish', false) ? 'Yes' : 'No'; ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Next Scheduled Publish</th>
                <td>
                    <?php
                    if($timestamp){
                        echo esc_html(date_i18n(get_option('date_format').' '.get_option('time_format'), $timestamp));
                    } else {
                        echo 'No scheduled event found.';
                    }
                    ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Estimated Run-Out Date</th>
                <td>
                    <?php
                    if($intervals_needed === 0){
                        echo 'No approved topics or no run-out.';
                    } else {
                        echo esc_html($run_out_date->format('F j, Y'));
                    }
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * schedule_next_event(): picks a random hour/minute in [start_hour..end_hour) for single event
     * Then schedules wpab_publish_scheduled_post once.
     */
    private function schedule_next_event() {
        $auto_publish = get_option('wpab_auto_publish', false);
        if( ! $auto_publish ) {
            return;
        }

        $schedule = get_option('wpab_schedule_settings', array(
            'frequency'  => 'daily',
            'number'     => 1,
            'start_hour' => '09',
            'end_hour'   => '12',
        ));

        // If freq != daily, we fallback or do your custom logic
        // We'll just do daily single events for demonstration
        $start_val = (int)$schedule['start_hour'];
        $end_val   = (int)$schedule['end_hour'];

        // We'll pick a random hour in [start_val..end_val-1], random minute 0..59
        // If user picks 09..12 => hours are [9..11]
        $random_hour = rand($start_val, max($start_val, $end_val-1));
        $random_min  = rand(0,59);

        $tz_string = get_option('timezone_string') ?: 'UTC';
        $tz = new DateTimeZone($tz_string);
        $now = new DateTime('now', $tz);

        $next_run = clone $now;
        $next_run->setTime($random_hour, $random_min, 0);

        // If that random time is already past today, do +1 day
        if( $next_run->getTimestamp() <= $now->getTimestamp() ) {
            $next_run->modify('+1 day');
        }

        // Schedule single event
        wp_schedule_single_event($next_run->getTimestamp(), 'wpab_publish_scheduled_post');

        // For debugging
        error_log("[schedule_next_event] Setting single event at "
            . $next_run->format('Y-m-d H:i:s') . " in the random window ["
            . $schedule['start_hour'] . ".." . $schedule['end_hour'] . "). Hour="
            . $random_hour . ", Minute=" . $random_min );
    }

    /**
     * Clear existing events
     */
    private function clear_scheduled_publishing() {
        $timestamp = wp_next_scheduled('wpab_publish_scheduled_post');
        while( $timestamp ) {
            wp_unschedule_event($timestamp, 'wpab_publish_scheduled_post');
            $timestamp = wp_next_scheduled('wpab_publish_scheduled_post');
        }
    }

    /**
     * publish_scheduled_post(): run by WP-Cron at the single event time
     * Publishes up to N topics, then re-schedules next random time for tomorrow.
     */
    public function publish_scheduled_post() {
        error_log('[publish_scheduled_post] Fired. Will publish topics and schedule next day');

        // Check if auto-publish is enabled
        $auto_publish = get_option('wpab_auto_publish', false);
        if (!$auto_publish) {
            error_log('[publish_scheduled_post] Auto-publish is disabled, exiting');
            return;
        }

        // Load schedule
        $schedule = get_option('wpab_schedule_settings', array(
            'frequency'  => 'daily',
            'number'     => 1,
            'start_hour' => '09',
            'end_hour'   => '12',
        ));
        $number = (int)$schedule['number'];

        // Are we in the window?
        // Actually, we don't strictly need to check if it's in the window now, because we scheduled it randomly in the window
        // But if you want to enforce skip if outside, keep the logic
        // We'll skip it for brevity

        // get approved
        $all_topics = get_option('wpab_topics', array());
        $approved = array_filter($all_topics, function($t){
            return (isset($t['status']) && $t['status'] === 'approved');
        });
        if(empty($approved)){
            error_log('[publish_scheduled_post] No approved topics, scheduling next event anyway');
            // Re-schedule next day randomly
            $this->schedule_next_event();
            return;
        }

        require_once plugin_dir_path(__FILE__) . 'class-wpab-content-generator.php';
        $generator = new WPAB_Content_Generator();

        $to_publish = min($number, count($approved));
        error_log("[publish_scheduled_post] Attempting to publish $to_publish topic(s). Found ".count($approved)." approved.");

        for($i=0; $i < $to_publish; $i++){
            $keys = array_keys($approved);
            $rand_key = $keys[array_rand($keys)];
            $topic = $approved[$rand_key];

            $post_id = $generator->generate_post($topic);
            if(is_wp_error($post_id)) {
                error_log('[publish_scheduled_post] Error: ' . $post_id->get_error_message());
                unset($approved[$rand_key]);
                continue;
            }
            unset($approved[$rand_key]);
            // remove from all_topics
            foreach($all_topics as $idx => $t) {
                if($t['title'] === $topic['title']
                    && $t['description'] === $topic['description']
                    && $t['status'] === 'approved'){
                    unset($all_topics[$idx]);
                    break;
                }
            }
            $all_topics = array_values($all_topics);
            update_option('wpab_topics', $all_topics);

            error_log("[publish_scheduled_post] Published post_id=$post_id for {$topic['title']}");
        }

        // Now re-schedule next random single event for tomorrow
        error_log('[publish_scheduled_post] Completed. Re-scheduling next event.');
        $this->schedule_next_event();
    }
}
