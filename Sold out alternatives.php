<?php
/*
Plugin Name: Sold out alternatives
Plugin URI: #
Description: The administrator can choose 4 alternative products for each product in the product editing column. When the target product is out of stock or sold out, three options are provided to the customer. The store chooses the best matching product and the customer himself Choose one of 4 alternatives or choose a refund. This feature is available for both simple and variable products.
Version: 1.0.0
Author: Dbryge
Author URI: #
*/

// add tab
add_filter('woocommerce_product_data_tabs', 'sold_out_alternatives_custom_tab');

// Add tab content
add_action('woocommerce_product_data_panels', 'sold_out_alternatives_custom_tab_content');

// save metadata
add_action('woocommerce_process_product_meta', 'sold_out_alternatives_save_custom_fields');

// Add custom tab
function sold_out_alternatives_custom_tab($tabs)
{
    // If the current product type is variable product, do not add "sold_out_alternatives" tab
    global $post;
    $product_type = get_post_meta($post->ID, '_product_type', true);
    if ($product_type === 'variable') {
        return $tabs;
    }

    // add new tab
    $tabs['sold_out_alternatives'] = array(
        'label'    => __('Sold out alternatives', 'your-plugin-textdomain'),
        'target'   => 'sold_out_alternatives_options',
        'class'    => array('show_if_simple'), 
    );

    return $tabs;
}

// Add tab content
function sold_out_alternatives_custom_tab_content()
{
    global $post;

    // Get saved data
    $replace_product_values = get_post_meta($post->ID, '_replace_product_values', true);

    // If the current product type is variable product, no content will be displayed
    $product_type = get_post_meta($post->ID, '_product_type', true);
    if ($product_type === 'variable') {
        return;
    }

    echo '<div id="sold_out_alternatives_options" class="panel woocommerce_options_panel">';
    
    // Replace Product ribbon
    echo '<h2>Replace product</h2>';

    // Display 4 input boxes
    for ($i = 1; $i <= 4; $i++) {
        echo '<p><input type="text" name="replace_product[]" value="' . (isset($replace_product_values[$i - 1]) ? esc_attr($replace_product_values[$i - 1]) : '') . '" /></p>';
    }

    // Load JavaScript scripts to handle automatch functionality
    echo '<script>
    jQuery(document).ready(function($) {
        // 自动匹配现有商品
        $("input[name^=\'replace_product\']").on("input", function() {
            var inputValue = $(this).val().toLowerCase();
            if (inputValue.length >= 3) {
                $(this).autocomplete({
                    source: function(request, response) {
                        $.ajax({
                            url: "' . admin_url('admin-ajax.php') . '",
                            type: "POST",
                            dataType: "json",
                            data: {
                                action: "sold_out_alternatives_search_products",
                                search_term: request.term
                            },
                            success: function(data) {
                                response(data);
                            }
                        });
                    },
                    minLength: 3
                });
            }
        });
    });
    </script>';

    echo '</div>';
}

// Handle Product Search Requests
add_action('wp_ajax_sold_out_alternatives_search_products', 'sold_out_alternatives_search_products');
add_action('wp_ajax_nopriv_sold_out_alternatives_search_products', 'sold_out_alternatives_search_products');
function sold_out_alternatives_search_products()
{
    $search_term = sanitize_text_field($_POST['search_term']);

    // Query products whose product names contain search keywords
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        's'              => $search_term,
    );

    $products = get_posts($args);

    $data = array();
    foreach ($products as $product) {
        $data[] = array(
            'label' => $product->post_title,
            'value' => $product->post_title,
        );
    }

    wp_send_json($data);
}


// Add tab content to variant product edit page
add_action('woocommerce_product_after_variable_attributes', 'sold_out_alternatives_custom_tab_content_variations', 10, 3);

// Add tab content
function sold_out_alternatives_custom_tab_content_variations($loop, $variation_data, $variation)
{
    // Get saved data
    $replace_product_values = get_post_meta($variation->ID, '_replace_product_values', true);

    echo '<div class="variation_options wc-metabox-content">';

    // Replace Product ribbon
    echo '<h2>' . __('Sold out alternatives', 'your-plugin-textdomain') . '</h2>';

    // Display 4 input boxes
    for ($i = 1; $i <= 4; $i++) {
        $value = isset($replace_product_values[$i - 1]) ? esc_attr($replace_product_values[$i - 1]) : '';
        echo '<p><input type="text" name="replace_product[]" value="' . $value . '" class="autocomplete-input" /></p>';
    }

    echo '</div>';

    // Load JavaScript scripts to handle automatch functionality
    echo '<script>
    jQuery(document).ready(function($) {
        // 自动匹配现有商品
        $(".autocomplete-input").on("input", function() {
            var inputValue = $(this).val().toLowerCase();
            if (inputValue.length >= 3) {
                $(this).autocomplete({
                    source: function(request, response) {
                        $.ajax({
                            url: "' . admin_url('admin-ajax.php') . '",
                            type: "POST",
                            dataType: "json",
                            data: {
                                action: "sold_out_alternatives_search_products",
                                search_term: request.term
                            },
                            success: function(data) {
                                response(data);
                            }
                        });
                    },
                    minLength: 3
                });
            }
        });
    });
    </script>';
}

// Save the data in the input box
add_action('woocommerce_save_product_variation', 'sold_out_alternatives_save_custom_fields', 10, 2);
function sold_out_alternatives_save_custom_fields($post_id)
{
    // Save Replace Product data
    if (isset($_POST['replace_product'])) {
        $replace_product_values = array_map('sanitize_text_field', $_POST['replace_product']);
        update_post_meta($post_id, '_replace_product_values', $replace_product_values);
    }
}


// Add a drop-down menu below each item on the shopping cart page
add_action('woocommerce_after_cart_item_name', 'sold_out_alternatives_dropdown', 10, 2);
function sold_out_alternatives_dropdown($cart_item, $cart_item_key)
{
    $product_id = $cart_item['product_id'];
    $variation_id = $cart_item['variation_id']; // Get the ID of a variable item

    // Get Best Match data
    $best_match_values = get_post_meta($product_id, '_best_match_values', true);

    // Get Replace Product data
    $replace_product_values = get_post_meta($product_id, '_replace_product_values', true);

    echo '<p>If the product is sold out or the quantity is insufficient, please choose:</p>';

    // drop down menu select action
    echo '<select name="product_action_' . esc_attr($cart_item_key) . '" class="product-action-select">';
    echo '<option value="find_best_match">Find Best Match</option>';
    echo '<option value="choose_similar_products">Choose Similar Products</option>';
    echo '<option value="refund">Refund</option>';
    echo '</select>';

    // Add hidden field for passing cart_item_key value
    echo '<input type="hidden" name="cart_item_key[]" value="' . esc_attr($cart_item_key) . '">';

    // Select Find Best Match from the drop-down menu
    echo '<select name="best_match_' . esc_attr($cart_item_key) . '" class="best-match-select" style="display: none;">';
    echo '<option value="">Select Best Match</option>'; 
    foreach ($best_match_values as $value) {
        echo '<option value="' . esc_attr($value) . '">' . esc_html($value) . '</option>';
    }
    echo '</select>';

    // Select from the drop-down menu Choose Similar Products
    echo '<select name="replace_product_' . esc_attr($cart_item_key) . '" class="replace-product-select" style="display: none;">';
    echo '<option value="">Select Similar Product</option>'; 

    // Get the "Similar Product" option of the variant based on the variant ID
    if ($variation_id) {
        $variation_replace_product_values = get_post_meta($variation_id, '_replace_product_values', true);
        if ($variation_replace_product_values) {
            foreach ($variation_replace_product_values as $value) {
                echo '<option value="' . esc_attr($value) . '">' . esc_html($value) . '</option>';
            }
        }
    } else {
        // If it is an ordinary product, the "Similar Product" option of the product itself will be displayed
        foreach ($replace_product_values as $value) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($value) . '</option>';
        }
    }

    echo '</select>';
}

// Add the corresponding JavaScript code to show or hide the drop-down menu according to the user's choice
add_action('wp_footer', 'sold_out_alternatives_js');
function sold_out_alternatives_js()
{
    ?>
    <script>
jQuery(document).ready(function($) {
    // Listen for dropdown menu selections
    $('select.product-action-select').on('change', function() {
        var action = $(this).val();
        var cartItemKey = $(this).attr('name').replace('product_action_', '');

        // Get the value of cart_item_key
        cartItemKey = $('input[name="cart_item_key[]"][value="' + cartItemKey + '"]').attr('value');

        var replaceProductValue = $('select[name="replace_product_' + cartItemKey + '"]').val();

        // Pass selected value to backend ($_POST)
        $.ajax({
            type: 'POST',
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            data: {
                action: 'update_sold_out_alternatives',
                cart_item_key: cartItemKey,
                product_action: action,
                replace_product: replaceProductValue 
            },
            success: function(response) {
                console.log('数据成功发送到后端。');
            },
            error: function(xhr, ajaxOptions, thrownError) {
                console.log('发送数据到后端时出现错误。');
            }
        });

        // Hide all dropdown menu options under the current product
        $(this).parent().siblings().find('select.replace-product-select').hide();

        // Hide the currently selected replace product select
        $('select.replace-product-select[name="replace_product_' + cartItemKey + '"]').hide();

        // Show the replace product select only if "choose similar products" is selected
        if (action === 'choose_similar_products') {
            $('select.replace-product-select[name="replace_product_' + cartItemKey + '"]').show();
        }
    });

    // Listen to the Choose Similar Products drop-down menu selection
    $('select.replace-product-select').on('change', function() {
        var cartItemKey = $(this).attr('name').replace('replace_product_', '');
        var replaceProductValue = $(this).val();

        $.ajax({
            type: 'POST',
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            data: {
                action: 'update_sold_out_alternatives',
                cart_item_key: cartItemKey,
                product_action: 'choose_similar_products',
                replace_product: replaceProductValue 
            },
            success: function(response) {
                console.log('数据成功发送到后端。');
            },
            error: function(xhr, ajaxOptions, thrownError) {
                console.log('发送数据到后端时出现错误。');
            }
        });
    });

    // Initialize the visibility of similar product selects
    $('select.replace-product-select').hide();
});
</script>
    <?php
}

// Add actions to handle AJAX requests
add_action('wp_ajax_update_sold_out_alternatives', 'update_sold_out_alternatives');
add_action('wp_ajax_nopriv_update_sold_out_alternatives', 'update_sold_out_alternatives');
function update_sold_out_alternatives()
{
    // get passed data
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    $product_action = isset($_POST['product_action']) ? sanitize_text_field($_POST['product_action']) : '';
    $replace_product = isset($_POST['replace_product']) ? sanitize_text_field($_POST['replace_product']) : '';

    // Save selected value in WooCommerce session
    WC()->session->set('sold_out_alternatives_' . $cart_item_key, array(
        'product_action' => $product_action,
        'replace_product' => $replace_product,
    ));

    wp_send_json_success();

    wp_die();
}

// Move action to save item data to bottom of cart page, use woocommerce_add_cart_item_data hook
add_filter('woocommerce_add_cart_item_data', 'save_sold_out_alternatives_to_cart', 10, 3);
function save_sold_out_alternatives_to_cart($cart_item_data, $product_id, $variation_id)
{
    $cart_item_key = md5(serialize($cart_item_data) . $product_id . $variation_id);

    // Get the data selected by the user
    $product_action = isset($_POST['product_action_' . $cart_item_key]) ? sanitize_text_field($_POST['product_action_' . $cart_item_key]) : '';
    $replace_product = isset($_POST['replace_product_' . $cart_item_key]) ? sanitize_text_field($_POST['replace_product_' . $cart_item_key]) : '';

    // Save the data selected by the user in the cart item
    $cart_item_data['sold_out_alternatives'] = array(
        'product_action' => $product_action,
        'replace_product' => $replace_product,
    );

    // Save option data to WooCommerce session
    WC()->session->set('sold_out_alternatives_' . $cart_item_key, $cart_item_data['sold_out_alternatives']);

    return $cart_item_data;
}


// Move the function that displays option data to the check out page
// add_action('woocommerce_review_order_before_submit', 'display_sold_out_alternatives_in_checkout', 10);
function display_sold_out_alternatives_in_checkout()
{
    // Get items in cart
    $cart_items = WC()->cart->get_cart();

    foreach ($cart_items as $cart_item_key => $cart_item) {
        // Get the option data saved in the session
        $sold_out_alternatives = WC()->session->get('sold_out_alternatives_' . $cart_item_key);

        // Debug statement, view option data
        // var_dump($sold_out_alternatives);

        if ($sold_out_alternatives) {
            $product_name = $cart_item['data']->get_name();
            $product_action = isset($sold_out_alternatives['product_action']) ? $sold_out_alternatives['product_action'] : '';
            $replace_product = isset($sold_out_alternatives['replace_product']) ? $sold_out_alternatives['replace_product'] : '';

            // remove underline
            $product_action = str_replace('_', ' ', $product_action);

            // Show option data
            echo '<p><strong>Item ID: </strong>' . esc_html($cart_item_key) . '</p>';
            echo '<p><strong>Product: </strong>' . esc_html($product_name) . '</p>';
            echo '<p><strong>Sold Out Alternative: </strong>' . esc_html($product_action) . '</p>';
            
            if ($product_action === 'find_best_match') {
                echo '<p><strong>Best Match: </strong>' . esc_html($best_match) . '</p>';
            } elseif ($product_action === 'choose_similar_products') {
                echo '<p><strong>Replace Product: </strong>' . esc_html($replace_product) . '</p>';
            }
        }
    }
}

// Save option data to line item metadata when creating a new line item
add_action('woocommerce_new_order_item', 'save_sold_out_alternatives_to_order_item_meta', 10, 3);
function save_sold_out_alternatives_to_order_item_meta($item_id, $cart_item_key, $values)
{
    if ( ! function_exists( 'WC' ) ) {
        return;
    }

    if ( is_null( WC()->session ) ) {
        // Session is not available, handle this situation accordingly.
        return;
    }
    
    // Get the option data saved in the session, and change the key name to "cart_item_key_" prefix
    $sold_out_alternatives = WC()->session->get('sold_out_alternatives_' . $cart_item_key);

    if ($sold_out_alternatives) {
        $product_action = isset($sold_out_alternatives['product_action']) ? sanitize_text_field($sold_out_alternatives['product_action']) : '';
        $replace_product = isset($sold_out_alternatives['replace_product']) ? sanitize_text_field($sold_out_alternatives['replace_product']) : '';

        // remove underline
        $product_action = str_replace('_', ' ', $product_action);

        // Save option data to line item metadata
        if (!empty($product_action)) {
            wc_add_order_item_meta($item_id, 'product_action', $product_action, false); 
        }

        if (!empty($replace_product)) {
            wc_add_order_item_meta($item_id, 'replace_product', $replace_product, false); 
        }
    }
}

// Save option data to line item metadata
add_action('woocommerce_checkout_create_order_line_item', 'save_sold_out_alternatives_to_order_item_meta_on_checkout', 10, 4);
function save_sold_out_alternatives_to_order_item_meta_on_checkout($item, $cart_item_key, $values, $order)
{
    // Get the option data saved in the session
    $sold_out_alternatives = WC()->session->get('sold_out_alternatives_' . $cart_item_key);

    if ($sold_out_alternatives) {
        $product_action = isset($sold_out_alternatives['product_action']) ? sanitize_text_field($sold_out_alternatives['product_action']) : '';
        $replace_product = isset($sold_out_alternatives['replace_product']) ? sanitize_text_field($sold_out_alternatives['replace_product']) : '';

        // remove underline
        $product_action = str_replace('_', ' ', $product_action);

        // Save option data to line item metadata
        if (!empty($product_action)) {
            $item->add_meta_data('product_action', $product_action, true); 
        }

        if (!empty($replace_product)) {
            $item->add_meta_data('replace_product', $replace_product, true); 
        }

        // Store option data into WC()->session
        WC()->session->set('sold_out_alternatives_' . $cart_item_key, $sold_out_alternatives);
    }

    $item->save();
}



// Added "Sold out alternatives" header
add_action('woocommerce_admin_order_item_headers', 'add_sold_out_alternatives_header');
function add_sold_out_alternatives_header()
{
    echo '<th class="item_sold_out_alternatives_action">' . esc_html__('Sold out alternatives action', 'woocommerce') . '</th>';
    echo '<th class="item_replace_product">' . esc_html__('Replace product', 'woocommerce') . '</th>'; 
}

// Show "Sold out alternatives action" and "Replace product" data
add_action('woocommerce_admin_order_item_values', 'display_sold_out_alternatives_order_item', 10, 3);
function display_sold_out_alternatives_order_item($_product, $item, $item_id)
{
    $product_action = wc_get_order_item_meta($item_id, 'product_action', true);
    $replace_product = wc_get_order_item_meta($item_id, 'replace_product', true);

    echo '<td class="item_sold_out_alternatives_action">' . esc_html($product_action) . '</td>';
    echo '<td class="item_replace_product">' . esc_html($replace_product) . '</td>';
}









