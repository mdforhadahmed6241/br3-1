<?php
/**
 * Expense Management Functionality for Business Report Plugin
 *
 * @package BusinessReport
 */

if ( ! defined( 'WPINC' ) ) die;

// Include the List Table classes.
require_once plugin_dir_path( __FILE__ ) . 'classes/class-br-expense-list-table.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/class-br-monthly-expense-list-table.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/class-br-expense-category-list-table.php';

/**
 * =================================================================================
 * 1. ADMIN ASSETS
 * =================================================================================
 */

function br_expense_admin_enqueue_scripts( $hook ) {
    if ( 'business-report_page_br-expense' !== $hook ) return;

    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style( 'wp-jquery-ui-dialog' );

    $js_version = filemtime( plugin_dir_path( __FILE__ ) . '../assets/js/admin-expenses.js' );
    wp_enqueue_script( 'br-expense-admin-js', plugin_dir_url( __FILE__ ) . '../assets/js/admin-expenses.js', [ 'jquery', 'jquery-ui-datepicker' ], $js_version, true );
    
    wp_localize_script( 'br-expense-admin-js', 'br_expense_ajax', [ 
        'ajax_url' => admin_url( 'admin-ajax.php' ), 
        'nonce' => wp_create_nonce( 'br_expense_nonce' ),
        'categories' => br_get_expense_categories() // Pass categories to JS
    ]);
}
add_action( 'admin_enqueue_scripts', 'br_expense_admin_enqueue_scripts' );


/**
 * =================================================================================
 * 2. DATABASE & HELPER FUNCTIONS
 * =================================================================================
 */
function br_get_expense_categories() {
    global $wpdb;
    return $wpdb->get_results("SELECT id, category_name FROM {$wpdb->prefix}br_expense_categories ORDER BY category_name ASC");
}

/**
 * =================================================================================
 * 3. ADMIN PAGE RENDERING
 * =================================================================================
 */

function br_expense_page_html() {
    if(!current_user_can('manage_woocommerce')) return;
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'expense_list';
    ?>
    <div class="wrap br-wrap">
        <div class="br-header"><h1><?php _e('Expense Management','business-report');?></h1></div>
        <h2 class="nav-tab-wrapper">
            <a href="?page=br-expense&tab=expense_list" class="nav-tab <?php echo $active_tab == 'expense_list' ? 'nav-tab-active' : '';?>"><?php _e('Expense List','business-report');?></a>
            <a href="?page=br-expense&tab=monthly_expense" class="nav-tab <?php echo $active_tab == 'monthly_expense' ? 'nav-tab-active' : '';?>"><?php _e('Monthly Expense','business-report');?></a>
            <a href="?page=br-expense&tab=expense_category" class="nav-tab <?php echo $active_tab == 'expense_category' ? 'nav-tab-active' : '';?>"><?php _e('Expense Category','business-report');?></a>
        </h2>
        <div class="br-page-content">
            <?php 
            switch($active_tab){
                case 'monthly_expense': br_monthly_expense_tab_html(); break;
                case 'expense_category': br_expense_category_tab_html(); break;
                default: br_expense_list_tab_html(); break;
            }
            ?>
        </div>
        <?php 
        br_expense_modals_html(); 
        ?>
    </div>
    <?php 
}

function br_expense_list_tab_html() {
    $expense_list_table = new BR_Expense_List_Table();
    $expense_list_table->prepare_items();
    ?>
    <div class="br-filters br-expense-filters">
        <?php br_render_date_filters_html('expense_list'); ?>
        <div class="br-page-actions">
            <?php $expense_list_table->search_box(__('Search Expenses', 'business-report'), 'expense_search'); ?>
            <button id="br-add-category-btn" class="button"><?php _e('Add Category', 'business-report');?></button>
            <button id="br-add-expense-btn" class="button button-primary"><?php _e('Add Expense', 'business-report');?></button>
        </div>
    </div>
    <form id="br-expense-list-form" method="post">
        <?php $expense_list_table->display(); ?>
    </form>
    <?php
}

function br_monthly_expense_tab_html() {
    $monthly_expense_table = new BR_Monthly_Expense_List_Table();
    $monthly_expense_table->prepare_items();
    ?>
     <div class="br-filters br-expense-filters">
        <div></div>
        <div class="br-page-actions">
            <button id="br-add-monthly-expense-btn" class="button button-primary"><?php _e('Add Monthly Expense', 'business-report');?></button>
        </div>
    </div>
    <form id="br-monthly-expense-list-form" method="post">
        <?php $monthly_expense_table->display(); ?>
    </form>
    <?php
}

function br_expense_category_tab_html() {
    $category_table = new BR_Expense_Category_List_Table();
    $category_table->prepare_items();
    ?>
    <div class="br-filters br-expense-filters">
        <?php br_render_date_filters_html('expense_category'); ?>
    </div>
     <form id="br-expense-category-list-form" method="post">
        <?php $category_table->display(); ?>
    </form>
    <?php
}


function br_expense_modals_html() {
    ?>
    <!-- Add/Edit Expense Modal -->
    <div id="br-expense-modal" class="br-modal" style="display: none;">
        <div class="br-modal-content">
            <button class="br-modal-close">&times;</button>
            <h3 id="br-expense-modal-title"><?php _e('Add Expense','business-report');?></h3>
            <form id="br-expense-form">
                <input type="hidden" id="expense_id" name="expense_id" value="">
                <label for="expense_reason"><?php _e('Reason','business-report');?></label>
                <textarea id="expense_reason" name="expense_reason" rows="3" required></textarea>
                <div class="form-row">
                    <div>
                        <label for="expense_date"><?php _e('Date','business-report');?></label>
                        <input type="text" id="expense_date" name="expense_date" class="br-datepicker" value="<?php echo date('Y-m-d'); ?>" autocomplete="off" required>
                    </div>
                    <div>
                        <label for="expense_category_id"><?php _e('Category','business-report');?></label>
                        <select id="expense_category_id" name="expense_category_id" required></select>
                    </div>
                </div>
                <label for="expense_amount"><?php _e('Amount','business-report');?></label>
                <input type="number" step="0.01" id="expense_amount" name="expense_amount" required>
                <div class="form-footer"><div></div><div>
                    <button type="button" class="button br-modal-cancel"><?php _e('Cancel','business-report');?></button>
                    <button type="submit" class="button button-primary"><?php _e('Save Expense','business-report');?></button>
                </div></div>
            </form>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="br-category-modal" class="br-modal" style="display: none;">
        <div class="br-modal-content" style="max-width: 400px;">
            <button class="br-modal-close">&times;</button>
            <h3><?php _e('Add New Category','business-report');?></h3>
            <form id="br-category-form">
                <label for="category_name"><?php _e('Category Name','business-report');?></label>
                <input type="text" id="category_name" name="category_name" required>
                <div class="form-footer"><div></div><div>
                    <button type="button" class="button br-modal-cancel"><?php _e('Cancel','business-report');?></button>
                    <button type="submit" class="button button-primary"><?php _e('Save Category','business-report');?></button>
                </div></div>
            </form>
        </div>
    </div>
    
    <!-- Add/Edit Monthly Expense Modal -->
    <div id="br-monthly-expense-modal" class="br-modal" style="display: none;">
        <div class="br-modal-content">
            <button class="br-modal-close">&times;</button>
            <h3 id="br-monthly-expense-modal-title"><?php _e('Add Monthly Expense','business-report');?></h3>
            <form id="br-monthly-expense-form">
                <input type="hidden" id="monthly_expense_id" name="monthly_expense_id" value="">
                <label for="monthly_expense_reason"><?php _e('Reason','business-report');?></label>
                <textarea id="monthly_expense_reason" name="monthly_expense_reason" rows="3" required></textarea>
                <div class="form-row">
                    <div>
                        <label for="monthly_expense_listed_date"><?php _e('Entry Day of Month','business-report');?></label>
                        <input type="number" id="monthly_expense_listed_date" name="monthly_expense_listed_date" min="1" max="31" required>
                    </div>
                     <div>
                        <label for="monthly_expense_category_id"><?php _e('Category','business-report');?></label>
                        <select id="monthly_expense_category_id" name="monthly_expense_category_id" required></select>
                    </div>
                </div>
                <label for="monthly_expense_amount"><?php _e('Amount','business-report');?></label>
                <input type="number" step="0.01" id="monthly_expense_amount" name="monthly_expense_amount" required>

                <div class="form-footer"><div></div><div>
                    <button type="button" class="button br-modal-cancel"><?php _e('Cancel','business-report');?></button>
                    <button type="submit" class="button button-primary"><?php _e('Save Monthly Expense','business-report');?></button>
                </div></div>
            </form>
        </div>
    </div>
    <?php
}

/**
 * =================================================================================
 * 4. AJAX HANDLERS
 * =================================================================================
 */

// --- CATEGORY AJAX ---
function br_ajax_add_expense_category() {
    check_ajax_referer('br_expense_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message' => 'Permission denied.']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'br_expense_categories';
    $name = sanitize_text_field($_POST['category_name']);
    
    if(empty($name)) wp_send_json_error(['message' => 'Category name cannot be empty.']);
    
    $result = $wpdb->insert($table, ['category_name' => $name], ['%s']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Category added.', 'new_category' => ['id' => $wpdb->insert_id, 'category_name' => $name]]);
    } else {
        wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
    }
}
add_action('wp_ajax_br_add_expense_category', 'br_ajax_add_expense_category');


// --- EXPENSE AJAX ---
function br_ajax_save_expense() {
    check_ajax_referer('br_expense_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message' => 'Permission denied.']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'br_expenses';
    $id = intval($_POST['expense_id']);
    $data = [
        'reason' => sanitize_textarea_field($_POST['expense_reason']),
        'amount' => floatval($_POST['expense_amount']),
        'category_id' => intval($_POST['expense_category_id']),
        'expense_date' => sanitize_text_field($_POST['expense_date']),
    ];

    if ($id > 0) {
        $result = $wpdb->update($table, $data, ['id' => $id]);
    } else {
        $result = $wpdb->insert($table, $data);
    }

    if ($result !== false) {
        wp_send_json_success(['message' => 'Expense saved successfully.']);
    } else {
        wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
    }
}
add_action('wp_ajax_br_save_expense', 'br_ajax_save_expense');

function br_ajax_get_expense() {
    check_ajax_referer('br_expense_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message' => 'Permission denied.']);
    
    global $wpdb;
    $id = intval($_POST['id']);
    $expense = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}br_expenses WHERE id = %d", $id), ARRAY_A);
    
    if ($expense) {
        wp_send_json_success($expense);
    } else {
        wp_send_json_error(['message' => 'Expense not found.']);
    }
}
add_action('wp_ajax_br_get_expense', 'br_ajax_get_expense');

function br_ajax_delete_expense() {
    check_ajax_referer('br_expense_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message' => 'Permission denied.']);

    global $wpdb;
    $id = intval($_POST['id']);
    $result = $wpdb->delete($wpdb->prefix . 'br_expenses', ['id' => $id], ['%d']);

    if ($result) {
        wp_send_json_success(['message' => 'Expense deleted.']);
    } else {
        wp_send_json_error(['message' => 'Could not delete expense.']);
    }
}
add_action('wp_ajax_br_delete_expense', 'br_ajax_delete_expense');


// --- MONTHLY EXPENSE AJAX ---
function br_ajax_save_monthly_expense() {
    check_ajax_referer('br_expense_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message' => 'Permission denied.']);

    global $wpdb;
    $table = $wpdb->prefix . 'br_monthly_expenses';
    $id = intval($_POST['monthly_expense_id']);
    $data = [
        'reason' => sanitize_textarea_field($_POST['monthly_expense_reason']),
        'amount' => floatval($_POST['monthly_expense_amount']),
        'category_id' => intval($_POST['monthly_expense_category_id']),
        'listed_date' => intval($_POST['monthly_expense_listed_date']),
    ];

    if ($id > 0) {
        $result = $wpdb->update($table, $data, ['id' => $id]);
    } else {
        $result = $wpdb->insert($table, $data);
    }

    if ($result !== false) {
        wp_send_json_success(['message' => 'Monthly expense saved successfully.']);
    } else {
        wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
    }
}
add_action('wp_ajax_br_save_monthly_expense', 'br_ajax_save_monthly_expense');

function br_ajax_get_monthly_expense() {
    check_ajax_referer('br_expense_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message' => 'Permission denied.']);

    global $wpdb;
    $id = intval($_POST['id']);
    $expense = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}br_monthly_expenses WHERE id = %d", $id), ARRAY_A);
    
    if ($expense) {
        wp_send_json_success($expense);
    } else {
        wp_send_json_error(['message' => 'Monthly expense not found.']);
    }
}
add_action('wp_ajax_br_get_monthly_expense', 'br_ajax_get_monthly_expense');


function br_ajax_delete_monthly_expense() {
    check_ajax_referer('br_expense_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message' => 'Permission denied.']);
    
    global $wpdb;
    $id = intval($_POST['id']);
    $result = $wpdb->delete($wpdb->prefix . 'br_monthly_expenses', ['id' => $id], ['%d']);

    if ($result) {
        wp_send_json_success(['message' => 'Monthly expense deleted.']);
    } else {
        wp_send_json_error(['message' => 'Could not delete monthly expense.']);
    }
}
add_action('wp_ajax_br_delete_monthly_expense', 'br_ajax_delete_monthly_expense');


/**
 * =================================================================================
 * 5. CRON JOB FUNCTION
 * =================================================================================
 */
function br_process_monthly_expenses_cron() {
    global $wpdb;
    $monthly_expenses_table = $wpdb->prefix . 'br_monthly_expenses';
    $expenses_table = $wpdb->prefix . 'br_expenses';

    $current_day = (int) current_time('j');
    $current_month_start = current_time('Y-m-01');

    $expenses_to_add = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$monthly_expenses_table} WHERE listed_date = %d AND (last_added IS NULL OR last_added < %s)",
        $current_day,
        $current_month_start
    ));

    if (empty($expenses_to_add)) {
        return; // No expenses to add today
    }

    $today = current_time('Y-m-d');

    foreach ($expenses_to_add as $expense) {
        // Insert into main expenses table
        $wpdb->insert($expenses_table, [
            'reason' => $expense->reason,
            'amount' => $expense->amount,
            'category_id' => $expense->category_id,
            'expense_date' => $today,
        ]);

        // Update the last_added date for the monthly expense
        $wpdb->update(
            $monthly_expenses_table,
            ['last_added' => $today],
            ['id' => $expense->id]
        );
    }
}
