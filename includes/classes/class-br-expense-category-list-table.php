<?php
/**
 * Creates the WP_List_Table for displaying expense categories with totals.
 */
if ( ! class_exists( 'WP_List_Table' ) ) require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class BR_Expense_Category_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct( [ 'singular' => 'Category', 'plural' => 'Categories', 'ajax' => false ] );
    }

    public function get_columns() {
        return ['category_name' => 'Category Name', 'total_cost' => 'Total Cost'];
    }

    public function prepare_items() {
        global $wpdb;
        $this->_column_headers = [$this->get_columns(), [], []];
        
        // Date filtering
        $date_range = br_get_date_range(
            isset($_GET['range']) ? sanitize_key($_GET['range']) : 'this_month',
            isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null,
            isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null
        );

        $expenses_table = $wpdb->prefix . 'br_expenses';
        $categories_table = $wpdb->prefix . 'br_expense_categories';

        $query = $wpdb->prepare(
            "SELECT c.category_name, SUM(e.amount) as total_cost 
             FROM {$categories_table} c
             LEFT JOIN {$expenses_table} e ON c.id = e.category_id AND (e.expense_date BETWEEN %s AND %s)
             GROUP BY c.id, c.category_name
             ORDER BY c.category_name ASC",
            $date_range['start'], $date_range['end']
        );
        
        $this->items = $wpdb->get_results($query, ARRAY_A);
    }
    
    function column_total_cost($item) {
        return wc_price($item['total_cost'] ?? 0);
    }
    
    function column_default($item, $column_name) {
        return esc_html($item[$column_name]);
    }
}
