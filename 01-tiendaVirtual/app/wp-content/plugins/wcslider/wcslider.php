<?php
/*
Plugin Name: BXSlider para WordPress
Plugin URI: http://easy-webdev.com
Description: Agrega BXSlider a WooCommerce
Version: 1.0
Author: Jose Ali Rivas Gomez
Author URI: 
Text Domain: 
Domain Path: 
*/

defined('ABSPATH') or die('No Script kiddies please!');

define('WCSLIDER_PATH', plugin_dir_url(__FILE__));

function wcslider_scripts(){
    wp_enqueue_style('bxslider', WCSLIDER_PATH . 'css/jquery.bxslider.min.css');
    if(wp_script_is('jquery', 'enqueued')){
        return;
    }else{
        wp_enqueue_script('jquery');
    }
    wp_enqueue_script('bxsliderjs', WCSLIDER_PATH . 'js/jquery.bxslider.min.js');
}
add_action('wp_enqueue_scripts', 'wcslider_scripts');

function wcslider_shortcode(){
    $args = array(
        'posts_per_page' => 10,
        'post_type' => 'product',
        'tax_query' => array(
            array(
                'taxonomy' => 'product_visibility',
                'field' => 'name',
                'terms' => 'featured',
                'operator' => 'IN',
            )
        )
    );
    $slider_productos = new WP_Query($args);
    while($slider_productos->have_posts()): $slider_productos->the_post(); ?>
        <li>
            <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('shop_catalog');
                the_title('<h3>', '</h3>'); ?>

            </a>
        </li>


    <?php
    endwhile; wp_reset_postdata();
    echo "</ul>";
}
add_shortcode('wcslider', 'wcslider_shortcode');

function wcslider_ejecutar() { ?>
    <script>
        $ = jQuery.noConflict();
        $(document).ready(function(){
            $('.slider').bxSlider({
                auto: true,
                minSlides: 4,
                maxSlides: 4,
                slideWidth: 250,
                slideMargin: 10,
                moveSlides: 1
            });
        });
    </script>

    <?php
}
add_action('wp_footer', 'wcslider_ejecutar');


























