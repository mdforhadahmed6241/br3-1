<?php
/**
 * Creates the WP_List_Table for displaying expenses.
 */

if ( ! class_exists( 'WP_List_Table' ) ) require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

class BR_Expense_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [ 'singular' => 'Expense', 'plural' => 'Expenses', 'ajax' => false ] );
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'reason' => 'Reason',
            'category_name' => 'Category',
            'amount' => 'Amount',
            'expense_date' => 'Date',
            'actions' => 'Actions',
        ];
    }

    public function get_sortable_columns() {
		return [
			'reason' => [ 'reason', false ],
			'category_name' => [ 'category_name', false ],
			'amount' => [ 'amount', false ],
			'expense_date' => [ 'expense_date', true ], // true means it's the default sort
		];
	}

    public function prepare_items() {
        global $wpdb;
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        // Date filtering
        $date_range = br_get_date_range(
            isset($_GET['range']) ? sanitize_key($_GET['range']) : 'this_month',
            isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null,
            isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null
        );

        $expenses_table = $wpdb->prefix . 'br_expenses';
        $categories_table = $wpdb->prefix . 'br_expense_categories';

        $where_clauses = [];
        $where_clauses[] = $wpdb->prepare("e.expense_date BETWEEN %s AND %s", $date_range['start'], $date_range['end']);

        // Search
        if (!empty($_REQUEST['s'])) {
            $search_term = '%' . $wpdb->esc_like(sanitize_text_field($_REQUEST['s'])) . '%';
            $where_clauses[] = $wpdb->prepare("(e.reason LIKE %s OR c.category_name LIKE %s)", $search_term, $search_term);
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        
        $orderby = !empty($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'expense_date';
        $order = !empty($_GET['order']) ? strtoupper(sanitize_key($_GET['order'])) : 'DESC';
        if (!in_array($orderby, array_keys($this->get_sortable_columns()))) $orderby = 'expense_date';
        if (!in_array($order, ['ASC', 'DESC'])) $order = 'DESC';

        $query = "SELECT e.*, c.category_name FROM {$expenses_table} e 
                  LEFT JOIN {$categories_table} c ON e.category_id = c.id
                  {$where_sql} ORDER BY {$orderby} {$order}";

        $all_items = $wpdb->get_results($query, ARRAY_A);
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($all_items);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ]);

        $this->items = array_slice($all_items, (($current_page - 1) * $per_page), $per_page);
    }

    function column_cb($item) {
        return sprintf('<input type="checkbox" name="expense[]" value="%s" />', $item['id']);
    }

    function column_amount($item) {
        return wc_price($item['amount']);
    }

    function column_expense_date($item) {
        return (new DateTime($item['expense_date']))->format('M j, Y');
    }

    function column_actions($item) {
        return sprintf(
            '<button type="button" class="button-link br-edit-expense-btn" data-id="%d"><span class="dashicons dashicons-edit"></span></button>' .
            '<button type="button" class="button-link-delete br-delete-expense-btn" data-id="%d"><span class="dashicons dashicons-trash"></span></button>',
            $item['id'], $item['id']
        );
    }
    
    function column_default($item, $column_name) {
        return esc_html($item[$column_name]);
    }
}
