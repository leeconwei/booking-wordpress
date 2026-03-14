<?php
/*
Plugin Name: Simple Studio Booking
Description: Photography studio booking with multi-day & multi-hour slots.
Version: 1.0
Author: Con Wei
*/

if (!defined('ABSPATH')) exit;

/* -------------------------
DATABASE INSTALL
------------------------- */
register_activation_hook(__FILE__, 'studio_booking_install');
function studio_booking_install(){
    global $wpdb;
    $table = $wpdb->prefix.'studio_bookings';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id INT NOT NULL AUTO_INCREMENT,
        booking_date DATE NOT NULL,
        booking_time VARCHAR(10) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id)
    ) $charset;";

    require_once(ABSPATH.'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/* -------------------------
FRONTEND SCRIPTS & STYLES
------------------------- */
add_action('wp_enqueue_scripts', 'studio_booking_enqueue_scripts');
function studio_booking_enqueue_scripts() {
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], false, true);

    wp_enqueue_style('studio-booking-css', plugin_dir_url(__FILE__) . 'css/booking-ui.css');
    wp_enqueue_script('studio-booking-js', plugin_dir_url(__FILE__) . 'js/booking-ui.js', ['jquery','flatpickr-js'], '1.0', true);

    wp_localize_script('studio-booking-js', 'StudioBookingAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'hourly_rate' => get_option('studio_booking_hourly_rate', 80),
        'start_hour' => get_option('studio_booking_start_hour', 8),
        'end_hour' => get_option('studio_booking_end_hour', 20)
    ]);
}

/* -------------------------
SHORTCODE
------------------------- */
add_shortcode('studio_booking_form','studio_booking_form');
function studio_booking_form() {
    ob_start(); ?>
    <div id="studio-booking-ui">
        <div class="sb-header">
            <h2>Book Your Studio</h2>
            <p>Select multiple days and hours</p>
        </div>

        <div class="sb-calendar">
            <label>Select Dates</label>
            <input id="booking-date" type="text" placeholder="Select Dates">
        </div>

        <div id="sb-time-slots"></div>

        <div class="sb-summary">
            <div class="sb-summary-list"></div>
            <div class="sb-summary-total">Total: RM <span id="booking-total">0</span></div>
            <button id="booking-submit" class="sb-btn">Book Now</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* -------------------------
AJAX: GET BOOKED SLOTS
------------------------- */
add_action('wp_ajax_get_booked_slots','get_booked_slots');
add_action('wp_ajax_nopriv_get_booked_slots','get_booked_slots');
function get_booked_slots(){
    global $wpdb;
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    if(!$date) wp_send_json_success([]);

    $results = $wpdb->get_col(
        $wpdb->prepare("SELECT booking_time FROM {$wpdb->prefix}studio_bookings WHERE booking_date=%s", $date)
    );
    wp_send_json_success($results);
}

/* -------------------------
AJAX: SAVE BOOKING
------------------------- */
add_action('wp_ajax_save_booking','save_booking');
add_action('wp_ajax_nopriv_save_booking','save_booking');
function save_booking(){
    global $wpdb;
    $slots = isset($_POST['slots']) && is_array($_POST['slots']) ? $_POST['slots'] : [];
    foreach($slots as $date => $hours){
        foreach($hours as $hour){
            $wpdb->insert(
                $wpdb->prefix.'studio_bookings',
                ['booking_date'=>$date, 'booking_time'=>$hour]
            );
        }
    }
    wp_send_json_success();
}

/* -------------------------
ADMIN MENU
------------------------- */
add_action('admin_menu','studio_booking_admin_menu');
function studio_booking_admin_menu(){
    add_menu_page(
        'Studio Booking','Studio Booking','manage_options','studio-booking-settings','studio_booking_settings_page'
    );

    add_submenu_page(
        'studio-booking-settings','Bookings','Bookings','manage_options','studio-booking-calendar','studio_booking_calendar_page'
    );

    add_submenu_page(
        'studio-booking-settings','Settings','Settings','manage_options','studio-booking-config','studio_booking_settings_page'
    );
}

/* -------------------------
ADMIN SETTINGS
------------------------- */
add_action('admin_init','studio_booking_register_settings');
function studio_booking_register_settings(){
    register_setting('studio_booking_settings_group','studio_booking_hourly_rate');
    register_setting('studio_booking_settings_group','studio_booking_start_hour');
    register_setting('studio_booking_settings_group','studio_booking_end_hour');
}

/* -------------------------
ADMIN SETTINGS PAGE
------------------------- */
function studio_booking_settings_page(){ ?>
<div class="wrap">
<h1>Studio Booking Settings</h1>
<form method="post" action="options.php">
<?php settings_fields('studio_booking_settings_group'); ?>
<table class="form-table">
<tr><th>Hourly Rate (RM)</th><td><input type="number" name="studio_booking_hourly_rate" value="<?php echo esc_attr(get_option('studio_booking_hourly_rate',80)); ?>"></td></tr>
<tr><th>Start Hour</th><td><input type="number" name="studio_booking_start_hour" value="<?php echo esc_attr(get_option('studio_booking_start_hour',8)); ?>"></td></tr>
<tr><th>End Hour</th><td><input type="number" name="studio_booking_end_hour" value="<?php echo esc_attr(get_option('studio_booking_end_hour',20)); ?>"></td></tr>
</table>
<?php submit_button(); ?>
</form>
<p>Use shortcode:</p>
<code>[studio_booking_form]</code>
</div>
<?php }

/* -------------------------
ADMIN CALENDAR PAGE
------------------------- */
function studio_booking_calendar_page(){ ?>
<div class="wrap">
  <h1>Studio Bookings Calendar</h1>

  <div id="studio-calendar" style="max-width:1100px; margin-top:20px;"></div>
</div>
<?php }

add_action('wp_ajax_get_booked_days','get_booked_days');
add_action('wp_ajax_nopriv_get_booked_days','get_booked_days');

function get_booked_days(){
    global $wpdb;

    $table = $wpdb->prefix.'studio_bookings';

    $results = $wpdb->get_results("
        SELECT booking_date, COUNT(*) as total
        FROM $table
        GROUP BY booking_date
    ");

    wp_send_json_success($results);
}

/* -------------------------
ADMIN CALENDAR SCRIPTS & AJAX
------------------------- */
add_action('admin_enqueue_scripts', 'studio_booking_admin_scripts');
function studio_booking_admin_scripts($hook){
    // Only load on our calendar page
    if($hook != 'studio-booking-settings_page_studio-booking-calendar') return;

    // FullCalendar CSS & JS
    wp_enqueue_style(
        'fullcalendar-css',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css'
    );

    wp_enqueue_script(
        'fullcalendar-js',
        'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
        [],
        null,
        true
    );

    // Our admin JS
    wp_enqueue_script(
        'studio-admin-calendar',
        plugin_dir_url(__FILE__) . 'admin-calendar.js',
        ['fullcalendar-js','jquery'],
        null,
        true
    );

    wp_localize_script('studio-admin-calendar','StudioBookingAjax',[
        'ajax_url'=>admin_url('admin-ajax.php')
    ]);
}

/* -------------------------
AJAX: Fetch all bookings
------------------------- */
add_action('wp_ajax_get_all_bookings','get_all_bookings');
function get_all_bookings(){
    global $wpdb;
    $table = $wpdb->prefix.'studio_bookings';

    $rows = $wpdb->get_results("SELECT id, booking_date, booking_time FROM $table");

    $events = [];
    foreach($rows as $r){
        $events[] = [
            'id' => $r->id,
            'title' => 'Booked',
            'start' => $r->booking_date.'T'.$r->booking_time,
        ];
    }

    wp_send_json($events);
}