/**
 * Business Report - Expense Management Admin JS
 */
jQuery(function($) {
    'use strict';

    const wrapper = $('.br-wrap');
    if (!wrapper.length) return;

    // --- Modal Handling ---
    const expenseModal = $('#br-expense-modal');
    const categoryModal = $('#br-category-modal');
    const monthlyExpenseModal = $('#br-monthly-expense-modal');
    const customRangeModal = $('#br-custom-range-filter-modal');

    function openModal(modal) {
        const datepickers = modal.find('.br-datepicker');
        if (!datepickers.hasClass('hasDatepicker')) {
            datepickers.datepicker({ dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true });
        }
        modal.fadeIn(200);
    }

    function closeModal(modal) {
        modal.fadeOut(200);
    }

    $(window).on('click', e => $(e.target).is('.br-modal') && closeModal($(e.target)));
    wrapper.on('click', '.br-modal-close, .br-modal-cancel', function() {
        closeModal($(this).closest('.br-modal'));
    });

    // Dropdown Toggle
    wrapper.on('click', '.br-dropdown-toggle', function(e) {
        e.preventDefault();
        $(this).next('.br-dropdown-menu').fadeToggle(100);
    });

    // Open Custom Range Filter Modal
    wrapper.on('click', '#br-custom-range-trigger', function(e) {
        e.preventDefault();
        const activeTabLink = $('.nav-tab-wrapper .nav-tab-active').attr('href');
        let currentTab = 'expense_list'; // default tab
        if (activeTabLink) {
            const urlParams = new URLSearchParams(activeTabLink.split('?')[1]);
            if (urlParams.has('tab')) {
                currentTab = urlParams.get('tab');
            }
        }
        customRangeModal.find('input[name="tab"]').val(currentTab);
        $('.br-dropdown-menu').fadeOut(100);
        openModal(customRangeModal);
    });

    // Populate category dropdowns
    function populateCategorySelects(newCategory = null) {
        if (newCategory) {
            br_expense_ajax.categories.push(newCategory);
            br_expense_ajax.categories.sort((a, b) => a.category_name.localeCompare(b.category_name));
        }
        
        const selects = $('#expense_category_id, #monthly_expense_category_id');
        selects.empty();
        selects.append('<option value="">-- Select Category --</option>');
        $.each(br_expense_ajax.categories, (i, category) => {
            selects.append($('<option>', { value: category.id, text: category.category_name }));
        });
    }
    populateCategorySelects();


    // --- EVENT HANDLERS ---
    
    // Open "Add Category" Modal
    wrapper.on('click', '#br-add-category-btn', () => openModal(categoryModal));
    
    // Open "Add Expense" Modal
    wrapper.on('click', '#br-add-expense-btn', function() {
        $('#br-expense-modal-title').text('Add Expense');
        $('#br-expense-form')[0].reset();
        $('#expense_id').val('');
        $('#expense_date').val(new Date().toISOString().slice(0, 10)); // Today's date
        openModal(expenseModal);
    });
    
    // Open "Add Monthly Expense" Modal
    wrapper.on('click', '#br-add-monthly-expense-btn', function() {
        $('#br-monthly-expense-modal-title').text('Add Monthly Expense');
        $('#br-monthly-expense-form')[0].reset();
        $('#monthly_expense_id').val('');
        openModal(monthlyExpenseModal);
    });

    // --- FORM SUBMISSIONS ---
    
    // Add Category Form
    wrapper.on('submit', '#br-category-form', function(e) {
        e.preventDefault();
        const button = $(this).find('button[type="submit"]');
        button.prop('disabled', true);
        
        $.post(br_expense_ajax.ajax_url, {
            action: 'br_add_expense_category',
            nonce: br_expense_ajax.nonce,
            category_name: $('#category_name').val()
        }).done(response => {
            if (response.success) {
                alert(response.data.message);
                populateCategorySelects(response.data.new_category);
                closeModal(categoryModal);
                $(this)[0].reset();
            } else {
                alert(response.data.message);
            }
        }).fail(() => alert('An error occurred.'))
        .always(() => button.prop('disabled', false));
    });
    
    // Add/Edit Expense Form
    wrapper.on('submit', '#br-expense-form', function(e) {
        e.preventDefault();
        const button = $(this).find('button[type="submit"]');
        button.prop('disabled', true);

        const data = {
            action: 'br_save_expense',
            nonce: br_expense_ajax.nonce,
            expense_id: $('#expense_id').val(),
            expense_reason: $('#expense_reason').val(),
            expense_amount: $('#expense_amount').val(),
            expense_category_id: $('#expense_category_id').val(),
            expense_date: $('#expense_date').val(),
        };
        
        $.post(br_expense_ajax.ajax_url, data)
            .done(response => {
                alert(response.data.message);
                if (response.success) window.location.reload();
            })
            .fail(() => alert('An error occurred.'))
            .always(() => button.prop('disabled', false));
    });
    
    // Add/Edit Monthly Expense Form
    wrapper.on('submit', '#br-monthly-expense-form', function(e) {
        e.preventDefault();
        const button = $(this).find('button[type="submit"]');
        button.prop('disabled', true);

        const data = {
            action: 'br_save_monthly_expense',
            nonce: br_expense_ajax.nonce,
            monthly_expense_id: $('#monthly_expense_id').val(),
            monthly_expense_reason: $('#monthly_expense_reason').val(),
            monthly_expense_amount: $('#monthly_expense_amount').val(),
            monthly_expense_category_id: $('#monthly_expense_category_id').val(),
            monthly_expense_listed_date: $('#monthly_expense_listed_date').val(),
        };
        
        $.post(br_expense_ajax.ajax_url, data)
            .done(response => {
                alert(response.data.message);
                if (response.success) window.location.reload();
            })
            .fail(() => alert('An error occurred.'))
            .always(() => button.prop('disabled', false));
    });


    // --- EDIT & DELETE ACTIONS ---
    
    // Edit Expense
    wrapper.on('click', '.br-edit-expense-btn', function() {
        const id = $(this).data('id');
        $.post(br_expense_ajax.ajax_url, { action: 'br_get_expense', nonce: br_expense_ajax.nonce, id: id })
            .done(response => {
                if (response.success) {
                    const d = response.data;
                    $('#br-expense-modal-title').text('Edit Expense');
                    $('#expense_id').val(d.id);
                    $('#expense_reason').val(d.reason);
                    $('#expense_amount').val(d.amount);
                    $('#expense_category_id').val(d.category_id);
                    $('#expense_date').val(d.expense_date);
                    openModal(expenseModal);
                } else {
                    alert(response.data.message);
                }
            });
    });

    // Delete Expense
    wrapper.on('click', '.br-delete-expense-btn', function() {
        if (!confirm('Are you sure?')) return;
        const id = $(this).data('id');
        $.post(br_expense_ajax.ajax_url, { action: 'br_delete_expense', nonce: br_expense_ajax.nonce, id: id })
            .done(response => {
                alert(response.data.message);
                if (response.success) window.location.reload();
            });
    });
    
    // Edit Monthly Expense
    wrapper.on('click', '.br-edit-monthly-expense-btn', function() {
        const id = $(this).data('id');
        $.post(br_expense_ajax.ajax_url, { action: 'br_get_monthly_expense', nonce: br_expense_ajax.nonce, id: id })
            .done(response => {
                if (response.success) {
                    const d = response.data;
                    $('#br-monthly-expense-modal-title').text('Edit Monthly Expense');
                    $('#monthly_expense_id').val(d.id);
                    $('#monthly_expense_reason').val(d.reason);
                    $('#monthly_expense_amount').val(d.amount);
                    $('#monthly_expense_category_id').val(d.category_id);
                    $('#monthly_expense_listed_date').val(d.listed_date);
                    openModal(monthlyExpenseModal);
                } else {
                    alert(response.data.message);
                }
            });
    });

    // Delete Monthly Expense
    wrapper.on('click', '.br-delete-monthly-expense-btn', function() {
        if (!confirm('Are you sure?')) return;
        const id = $(this).data('id');
        $.post(br_expense_ajax.ajax_url, { action: 'br_delete_monthly_expense', nonce: br_expense_ajax.nonce, id: id })
            .done(response => {
                alert(response.data.message);
                if (response.success) window.location.reload();
            });
    });
});

