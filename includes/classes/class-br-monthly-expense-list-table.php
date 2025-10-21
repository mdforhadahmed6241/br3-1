<?php
/**
 * Creates the WP_List_Table for displaying monthly expenses.
 */
if ( ! class_exists( 'WP_List_Table' ) ) require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class BR_Monthly_Expense_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct( [ 'singular' => 'Monthly Expense', 'plural' => 'Monthly Expenses', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'reason' => 'Reason',
            'category_name' => 'Category',
            'amount' => 'Amount',
            'listed_date' => 'Entry Day',
            'actions' => 'Actions',
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $this->_column_headers = [$this->get_columns(), [], []];
        
        $expenses_table = $wpdb->prefix . 'br_monthly_expenses';
        $categories_table = $wpdb->prefix . 'br_expense_categories';

        $query = "SELECT e.*, c.category_name FROM {$expenses_table} e 
                  LEFT JOIN {$categories_table} c ON e.category_id = c.id
                  ORDER BY e.listed_date ASC";
        
        $this->items = $wpdb->get_results($query, ARRAY_A);
    }

    function column_amount($item) {
        return wc_price($item['amount']);
    }

    function column_listed_date($item) {
        return 'Day ' . esc_html($item['listed_date']);
    }

    function column_actions($item) {
        return sprintf(
            '<button type="button" class="button-link br-edit-monthly-expense-btn" data-id="%d"><span class="dashicons dashicons-edit"></span></button>' .
            '<button type="button" class="button-link-delete br-delete-monthly-expense-btn" data-id="%d"><span class="dashicons dashicons-trash"></span></button>',
            $item['id'], $item['id']
        );
    }
    
    function column_default($item, $column_name) {
        return esc_html($item[$column_name]);
    }
}
