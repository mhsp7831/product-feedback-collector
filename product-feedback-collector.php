<?php

/**
 * Plugin Name: جمع آور بازخورد محصول
 * Description: افزونه قدرتمند وردپرس برای جمع آوری بازخورد محصولات در ووکامرس. این افزونه فرآیند جمع‌آوری و مدیریت بازخورد کاربران برای محصولات را از طریق ادغام عمیق با ووکامرس ساده می‌کند.
 * Version: 1.0.0
 * Author: MHSP :)
 * Author URI: https://github.com/mhsp7831
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) exit;

// شورت‌کد برای نمایش اطلاعات کتاب بر اساس product_id
function bff_show_product_info_by_id($atts)
{
    $atts = shortcode_atts([
        'show' => 'both', // title, image, both
    ], $atts, 'product_info');

    // فقط مقادیر مجاز را بپذیر
    $allowed_values = ['title', 'image', 'both'];
    if (!in_array($atts['show'], $allowed_values, true)) {
        $atts['show'] = 'both';
    }

    if (!isset($_GET['product_id'])) return '';

    $product_id = intval($_GET['product_id']);
    $product = wc_get_product($product_id);

    if (!$product || $product->get_status() !== 'publish') {
        return '<div class="product-info error" style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px;">محصول پیدا نشد.</div>';
    }

    $title = esc_html($product->get_name());
    $thumbnail = $product->get_image('medium');

    $output = "<div class='product-info'>";
    if ($atts['show'] === 'title' || $atts['show'] === 'both') {
        $output .= "<h2>{$title}</h2>";
    }
    if ($atts['show'] === 'image' || $atts['show'] === 'both') {
        $output .= "<div>{$thumbnail}</div>";
    }
    $output .= "</div>";

    return $output;
}
add_shortcode('product_info', 'bff_show_product_info_by_id');

// افزودن متاباکس برای لینک پایه بازخورد
function bff_add_feedback_base_url_metabox()
{
    add_meta_box(
        'bff_feedback_url',
        'لینک پایه بازخورد',
        'bff_render_feedback_base_url_metabox',
        'product',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'bff_add_feedback_base_url_metabox');

// رندر کردن متاباکس
function bff_render_feedback_base_url_metabox($post)
{
    $value = get_post_meta($post->ID, '_bff_feedback_base_url', true);
    $product_id = $post->ID;

    echo '<label for="bff_feedback_url_field">آدرس صفحه بازخورد:</label>';
    echo '<input type="text" id="bff_feedback_url_field" name="bff_feedback_url_field" value="' . esc_attr($value) . '" style="width:100%;direction:ltr;" />';

    // اگر لینکی وارد شده، نمایش لینک و دکمه‌ها
    if (!empty($value)) {
        $clean_url = rtrim($value, '/');
        $final_url = esc_url($clean_url . '?product_id=' . $product_id);
        echo '<p style="margin:10px 0 5px;">لینک نهایی بازخورد:</p>';
        echo '<a href="' . urldecode($final_url) . '" target="_blank" style="color:#0073aa;direction:ltr;display:block;margin-bottom:10px;">' . urldecode($final_url) . '</a>';
        echo '<div>';
        echo '<button type="button" class="button button-small" onclick="window.open(\'' . $final_url . '\', \'_blank\')" style="width: 100%;padding:0;">باز کردن لینک بازخورد</button>';
        echo '<button type="button" class="button button-small" onclick="copyToClipboard(\'' . $final_url . '\')" style="width: 100%;margin-top:3px;">کپی لینک</button>';
        echo '</div>';
        echo '
            <em style="display:block;color:#666;margin-top:10px;">
                برای نمایش عنوان و تصویر این محصول در صفحه بازخورد، از کد کوتاه زیر استفاده کنید:
                <code style="letter-spacing:-1px;">[product_info show="title/image/both"]</code>
            </em>
        ';
    }
}

// ذخیره‌سازی لینک پایه بازخورد
function bff_save_feedback_base_url($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== 'product') return;

    if (array_key_exists('bff_feedback_url_field', $_POST)) {
        $feedback_url = esc_url_raw($_POST['bff_feedback_url_field']);
        $feedback_url = strtok($feedback_url, '?');
        $feedback_url = rtrim($feedback_url, '/');
        update_post_meta(
            $post_id,
            '_bff_feedback_base_url',
            $feedback_url
        );
    }
}
add_action('save_post', 'bff_save_feedback_base_url');

// افزودن ستون جدید
function bff_add_feedback_link_column($columns)
{
    $columns['feedback_link'] = 'لینک بازخورد';
    return $columns;
}
add_filter('manage_product_posts_columns', 'bff_add_feedback_link_column');

// اضافه کردن استایل برای عرض ستون
function bff_feedback_column_style()
{
    echo '<style>
        .column-feedback_link {
            width: 10% !important;
        }
        .column-custom_column {
            width: 5% !important;
        }
    </style>';
}
add_action('admin_head', 'bff_feedback_column_style');

// مقداردهی به ستون
function bff_show_feedback_link_column($column, $post_id)
{
    if ($column === 'feedback_link') {
        $base_url = get_post_meta($post_id, '_bff_feedback_base_url', true);
        if ($base_url) {
            $clean_base_url = urldecode(rtrim($base_url, '/'));
            $final_url = esc_url($clean_base_url . '?product_id=' . $post_id);
            echo '<div style="direction:ltr;text-align:end;">';
            echo '<button type="button" class="button button-small" onclick="window.open(\'' . $final_url . '\', \'_blank\')" style="width: 100%;padding:0;">باز کردن لینک بازخورد</button>';
            echo '<button type="button" class="button button-small" onclick="copyToClipboard(\'' . $final_url . '\')" style="width: 100%;margin-top:3px;">کپی لینک</button>';
            echo '</div>';
            echo '<input type="hidden" class="feedback-base-url" value="' . esc_attr($clean_base_url) . '" />';
        } else {
            echo '<span style="color: #888;" data-base-url="">وارد نشده</span>';
            echo '<input type="hidden" class="feedback-base-url" value="" />';
        }
    }
}
add_action('manage_product_posts_custom_column', 'bff_show_feedback_link_column', 10, 2);

// اضافه کردن اسکریپت برای کپی کردن لینک
function bff_add_copy_script()
{
    global $post_type;
    if ($post_type !== 'product') return;
?>
    <script type="text/javascript">
        function copyToClipboard(text) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                alert('لینک با موفقیت کپی شد');
            } catch (err) {
                alert('خطا در کپی کردن لینک');
            }
            document.body.removeChild(textarea);
        }
    </script>
<?php
}
add_action('admin_footer-edit.php', 'bff_add_copy_script');
add_action('admin_footer-post.php', 'bff_add_copy_script');
add_action('admin_footer-post-new.php', 'bff_add_copy_script');

// اضافه کردن فیلد به ویرایش سریع
function bff_add_quick_edit_field($column_name, $post_type)
{
    if ($post_type !== 'product' || $column_name !== 'feedback_link') return;
?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label>
                <span class="title">لینک پایه بازخورد</span>
                <span class="input-text-wrap">
                    <input type="text" name="bff_feedback_url_field" class="text" value="">
                </span>
            </label>
        </div>
    </fieldset>
<?php
}
add_action('quick_edit_custom_box', 'bff_add_quick_edit_field', 10, 2);

// اضافه کردن اسکریپت برای پر کردن فیلد ویرایش سریع
function bff_quick_edit_script()
{
    global $post_type;
    if ($post_type !== 'product') return;
?>
    <script type="text/javascript">
        jQuery(function($) {
            var $wp_inline_edit = inlineEditPost.edit;

            inlineEditPost.edit = function(id) {
                $wp_inline_edit.apply(this, arguments);

                var post_id = 0;
                if (typeof(id) == 'object') {
                    post_id = parseInt(this.getId(id));
                }

                if (post_id > 0) {
                    var $post_row = $('#post-' + post_id);
                    var $feedback_url_value = $post_row.find('.feedback-base-url').val();

                    var $edit_row = $('#edit-' + post_id);
                    $edit_row.find('input[name="bff_feedback_url_field"]').val($feedback_url_value);
                }
            };
        });
    </script>
    <?php
}
add_action('admin_footer-edit.php', 'bff_quick_edit_script');

// ذخیره‌سازی لینک پایه بازخورد در ویرایش سریع
function bff_save_quick_edit_data($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== 'product') return;

    if (isset($_POST['bff_feedback_url_field'])) {
        $feedback_url = esc_url_raw($_POST['bff_feedback_url_field']);
        $feedback_url = strtok($feedback_url, '?');
        $feedback_url = rtrim($feedback_url, '/');
        update_post_meta(
            $post_id,
            '_bff_feedback_base_url',
            $feedback_url
        );
    }
}
add_action('save_post', 'bff_save_quick_edit_data');

// اضافه کردن فیلتر برای محصولات با لینک
function bff_add_feedback_link_filter()
{
    global $typenow;
    if ($typenow === 'product') {
        $current = isset($_GET['has_feedback_link']) ? $_GET['has_feedback_link'] : '';
    ?>
        <select name="has_feedback_link">
            <option value="">همه محصولات</option>
            <option value="1" <?php selected($current, '1'); ?>>دارای لینک بازخورد</option>
            <option value="0" <?php selected($current, '0'); ?>>بدون لینک بازخورد</option>
        </select>
<?php
    }
}
add_action('restrict_manage_posts', 'bff_add_feedback_link_filter');

// اعمال فیلتر
function bff_apply_feedback_link_filter($query)
{
    global $pagenow;
    if (is_admin() && $pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'product') {
        if (isset($_GET['has_feedback_link'])) {
            $has_link = $_GET['has_feedback_link'];
            if ($has_link === '1') {
                $query->set('meta_query', array(
                    array(
                        'key' => '_bff_feedback_base_url',
                        'compare' => 'EXISTS'
                    ),
                    array(
                        'key' => '_bff_feedback_base_url',
                        'value' => '',
                        'compare' => '!='
                    )
                ));
            } elseif ($has_link === '0') {
                $query->set('meta_query', array(
                    'relation' => 'OR',
                    array(
                        'key' => '_bff_feedback_base_url',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_bff_feedback_base_url',
                        'value' => '',
                        'compare' => '='
                    )
                ));
            }
        }
    }
}
add_action('pre_get_posts', 'bff_apply_feedback_link_filter');

// اضافه کردن ستون لینک بازخورد به خروجی CSV
function bff_add_feedback_link_to_export_columns($columns)
{
    $columns['feedback_link'] = 'لینک بازخورد';
    return $columns;
}
add_filter('woocommerce_product_export_column_names', 'bff_add_feedback_link_to_export_columns');
add_filter('woocommerce_product_export_product_default_columns', 'bff_add_feedback_link_to_export_columns');

// اضافه کردن داده لینک بازخورد به خروجی CSV
function bff_add_feedback_link_to_export_data($row, $product)
{
    $base_url = get_post_meta($product->get_id(), '_bff_feedback_base_url', true);
    if ($base_url) {
        $clean_base_url = urldecode(rtrim($base_url, '/'));
        $row['feedback_link'] = esc_url($clean_base_url . '?product_id=' . $product->get_id());
    } else {
        $row['feedback_link'] = '';
    }
    return $row;
}
add_filter('woocommerce_product_export_row_data', 'bff_add_feedback_link_to_export_data', 10, 2);
