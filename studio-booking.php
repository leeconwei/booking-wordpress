<?php
/*
Plugin Name: Simple Studio Booking
Description: Photography studio booking with multi-day & multi-hour slots. Fully WooCommerce integrated.
Version: 1.2
Author: Con Wei
*/

if (!defined('ABSPATH')) exit;

/* -------------------------
DATABASE INSTALL / UPDATE
------------------------- */
register_activation_hook(__FILE__, 'studio_booking_install');
function studio_booking_install(){
    global $wpdb;
    $table = $wpdb->prefix.'studio_bookings';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
    id BIGINT NOT NULL AUTO_INCREMENT,
    booking_date DATE NOT NULL,
    booking_time VARCHAR(10) NOT NULL,
    user_id BIGINT NULL,
    order_id BIGINT NULL,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY unique_slot (booking_date, booking_time)
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
    wp_enqueue_script('studio-booking-js', plugin_dir_url(__FILE__) . 'js/booking-ui.js', ['jquery','flatpickr-js'], '1.2', true);

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
            <h2>Reserve Your Studio</h2>
            <p>Pick your date(s) and time(s)</p>
        </div>

        <div class="sb-calendar">
            <input id="booking-date" type="text" placeholder="Select Booking Date(s)">
        </div>

        <div id="sb-time-slots"></div>

        <div class="sb-summary">
            <div class="sb-summary-list"></div>
</div>
<div class="sb-summary-bottom">
            <p class="sb-summary-total">Total: RM <span id="booking-total">0</span></p>
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
ADMIN MENU
------------------------- */
add_action('admin_menu','studio_booking_admin_menu');
function studio_booking_admin_menu(){
    add_menu_page(
        'Bookings Calendar',
        'Bookings Calendar',
        'manage_options',
        'studio-booking-calendar',
        'studio_booking_calendar_page',
        'dashicons-calendar',
        20
    );

    add_submenu_page(
        'studio-booking-calendar',
        'Settings',
        'Settings',
        'manage_options',
        'studio-booking-settings',
        'studio_booking_settings_page'
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
    register_setting('studio_booking_settings_group','studio_booking_product_id');
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
<?php
$selected = get_option('studio_booking_product_id');
$products = function_exists('wc_get_products') ? wc_get_products(['limit' => -1]) : [];
?>
<tr>
<th>Booking Product</th>
<td>
<select name="studio_booking_product_id">
    <option value="">-- Select Product --</option>
    <?php foreach($products as $p): ?>
        <option value="<?php echo $p->get_id(); ?>"
            <?php selected($selected, $p->get_id()); ?>>
            <?php echo $p->get_name(); ?>
        </option>
    <?php endforeach; ?>
</select>
</td>
</tr>
</table>
<?php submit_button(); ?>
</form>
<code>[studio_booking_form]</code>
</div>
<?php }

/* -------------------------
ADMIN CALENDAR PAGE
------------------------- */
function studio_booking_calendar_page(){ ?>
<div class="wrap sb-admin">
    <div class="sb-admin-header">
        <h1>📅 Bookings</h1>
        <p>Manage your studio schedule</p>
    </div>

    <div class="sb-admin-stats">
        <div class="sb-card">
            <span class="sb-card-title">Today</span>
            <span class="sb-card-value" id="sb-today-count">0</span>
        </div>
        <div class="sb-card">
            <span class="sb-card-title">This Month</span>
            <span class="sb-card-value" id="sb-month-count">0</span>
        </div>
        <div class="sb-card">
            <span class="sb-card-title">Total Bookings</span>
            <span class="sb-card-value" id="sb-total-count">0</span>
        </div>
    </div>

    <div class="sb-calendar-card">
        <div id="studio-calendar"></div>
    </div>
</div>
<?php }

/* -------------------------
AJAX: GET BOOKED DAYS
------------------------- */
add_action('wp_ajax_get_booked_days','get_booked_days');
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
ADMIN SCRIPTS
------------------------- */
add_action('admin_enqueue_scripts','studio_booking_admin_scripts');
function studio_booking_admin_scripts($hook){
    if($hook !== 'toplevel_page_studio-booking-calendar') return;

    wp_enqueue_style('fullcalendar-css','https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css');
    wp_enqueue_style('studio-admin-style', plugin_dir_url(__FILE__) . 'admin-calendar.css');

    wp_enqueue_script('fullcalendar-js','https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', [], null, true);
    wp_enqueue_script('studio-admin-calendar', plugin_dir_url(__FILE__) . 'admin-calendar.js', ['jquery'], null, true);

    wp_localize_script('studio-admin-calendar','StudioBookingAjax',[
        'ajax_url'=>admin_url('admin-ajax.php')
    ]);
}

/* -------------------------
AJAX: ALL BOOKINGS
------------------------- */
add_action('wp_ajax_get_all_bookings','get_all_bookings');
function get_all_bookings(){
    global $wpdb;
    $table=$wpdb->prefix.'studio_bookings';

    $rows=$wpdb->get_results("
        SELECT booking_date, booking_time, user_id, customer_name, customer_email, customer_phone, status, notes 
        FROM $table
    ");

    $events=[];

    foreach($rows as $r){

        $name = !empty($r->customer_name) ? $r->customer_name : 'Guest';

        // 🎨 Color by status
        $color = '#3b82f6'; // default blue
        if($r->status === 'confirmed') $color = '#22c55e'; // green
        if($r->status === 'pending') $color = '#f59e0b'; // orange
        if($r->status === 'cancelled') $color = '#ef4444'; // red

        $events[] = [
            'title' => $name,
            'start' => $r->booking_date.'T'.$r->booking_time,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'customer_name' => $name,
                'email' => $r->customer_email,
                'phone' => $r->customer_phone,
                'status' => $r->status,
                'notes' => $r->notes,
                'time' => $r->booking_time,
                'date' => $r->booking_date
            ]
        ];
    }

    wp_send_json($events);
}

/* -------------------------
WOOCOMMERCE: ADD BOOKING TO CART
------------------------- */
add_action('wp_ajax_add_booking_to_cart','add_booking_to_cart');
add_action('wp_ajax_nopriv_add_booking_to_cart','add_booking_to_cart');

function get_booking_product_id(){
    return (int) get_option('studio_booking_product_id');
}

function add_booking_to_cart(){

    if(!class_exists('WC_Product')) wp_send_json_error();

    $slots = $_POST['slots'] ?? [];
    if(empty($slots)) wp_send_json_error('No slots');

    // ✅ FIX: support JSON or array
    $slots_arr = is_string($slots) ? json_decode(stripslashes($slots), true) : $slots;
    if(!is_array($slots_arr) || empty($slots_arr)) wp_send_json_error('Invalid slots');

    $product_id = get_booking_product_id();
    if(!$product_id) wp_send_json_error('No booking product');

    // Format slots
    $formatted = [];
    foreach($slots_arr as $date => $hours){
        foreach($hours as $hour){
            $formatted[] = "$date $hour";
        }
    }

    WC()->cart->empty_cart();

    WC()->cart->add_to_cart($product_id, 1, 0, [], [
        'studio_slots' => $formatted
    ]);

    wp_send_json_success([
        'redirect' => wc_get_checkout_url()
    ]);
}

/* -------------------------
WOOCOMMERCE: ADD CART ITEM DATA (FIXED - ONLY ONE)
------------------------- */
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id){

    if(isset($_POST['slots'])){
        $slots = is_string($_POST['slots']) 
            ? json_decode(stripslashes($_POST['slots']), true) 
            : $_POST['slots'];

        if(is_array($slots)){
            $formatted = [];
            foreach($slots as $date => $hours){
                foreach($hours as $hour){
                    $formatted[] = "$date $hour";
                }
            }
            $cart_item_data['studio_slots'] = $formatted;
        }
    }

    return $cart_item_data;
}, 10, 2);

/* -------------------------
WOOCOMMERCE: DYNAMIC PRICE
------------------------- */
add_action('woocommerce_before_calculate_totals', function($cart) {
    if(is_admin() && !defined('DOING_AJAX')) return;

    foreach($cart->get_cart() as $cart_item){
        if(!empty($cart_item['studio_slots'])){
            $hours = count($cart_item['studio_slots']);
            $rate = (float)get_option('studio_booking_hourly_rate', 80);
            $cart_item['data']->set_price($rate * $hours);
        }
    }
});

/* -------------------------
WOOCOMMERCE: DISPLAY IN CART
------------------------- */
add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
    if(!empty($cart_item['studio_slots'])){
        $item_data[] = [
            'name' => 'Booking Slots',
            'value' => implode(', ', $cart_item['studio_slots'])
        ];
    }
    return $item_data;
}, 10, 2);

/* -------------------------
WOOCOMMERCE: SAVE ORDER META
------------------------- */
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order){
    if(!empty($values['studio_slots']) && is_array($values['studio_slots'])){
        $item->add_meta_data('studio_slots', $values['studio_slots'], true);
    }
}, 10, 4);

/* -------------------------
WOOCOMMERCE: PAYMENT COMPLETE
------------------------- */
add_action('woocommerce_order_status_processing', 'studio_save_booking');
add_action('woocommerce_order_status_completed', 'studio_save_booking');

function studio_save_booking($order_id){

    error_log('🔥 PAYMENT HOOK FIRED: '.$order_id);

    global $wpdb;

    $order = wc_get_order($order_id);
    if(!$order) return;

    $table = $wpdb->prefix.'studio_bookings';

    $name  = trim($order->get_billing_first_name().' '.$order->get_billing_last_name());
    $email = $order->get_billing_email();
    $phone = $order->get_billing_phone();
    $user_id = $order->get_user_id();

    foreach($order->get_items() as $item){

        // ✅ FIX: handle serialized data
        $slots = maybe_unserialize($item->get_meta('studio_slots'));

        if(empty($slots) || !is_array($slots)){
            error_log('⚠️ NO SLOTS FOUND: '.print_r($slots,true));
            continue;
        }

        foreach($slots as $slot){

            if(strpos($slot, ' ') === false) continue;

            list($date, $time) = explode(' ', $slot);

            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE booking_date=%s AND booking_time=%s",
                $date, $time
            ));

            if($exists){
                error_log("⚠️ DUPLICATE: $date $time");
                continue;
            }

            $insert = $wpdb->insert($table, [
                'booking_date'   => $date,
                'booking_time'   => $time,
                'user_id'        => $user_id,
                'order_id'       => $order_id,
                'customer_name'  => $name,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'status'         => 'confirmed'
            ]);

            if($insert){
                error_log("✅ INSERTED: $date $time");
            } else {
                error_log("❌ DB ERROR: ".$wpdb->last_error);
            }
        }
    }
};

add_filter('woocommerce_order_item_permalink', '__return_false');

add_action('wp_ajax_get_booking_stats', 'get_booking_stats');
function get_booking_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'studio_bookings';
    $today = date('Y-m-d');
    $month = date('Y-m');

    $today_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE booking_date = %s", $today));
    $month_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE booking_date LIKE %s", $month . '%'));
    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    wp_send_json_success([
        'today' => (int)$today_count,
        'month' => (int)$month_count,
        'total' => (int)$total_count
    ]);
}

add_action('admin_footer-toplevel_page_studio-booking-calendar', function() {
    ?>
    <script type="text/javascript">
    (function($){
        console.log("Admin footer JS running");

        $("#sb-today-count").text("1");
        $("#sb-month-count").text("2");
        $("#sb-total-count").text("3");

        // real AJAX
        $.post(StudioBookingAjax.ajax_url, {action: "get_booking_stats"}, function(res){
            if(res.success){
                $("#sb-today-count").text(res.data.today);
                $("#sb-month-count").text(res.data.month);
                $("#sb-total-count").text(res.data.total);
            }
        });
    })(jQuery);
    </script>
    <?php
});