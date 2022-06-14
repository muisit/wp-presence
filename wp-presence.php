<?php

/**
 * WP-Presence
 *
 * @package             wp-presence
 * @author              Michiel Uitdehaag
 * @copyright           2020 Michiel Uitdehaag for muis IT
 * @licenses            GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:         wp-presence
 * Plugin URI:          https://github.com/muisit/wp-presence
 * Description:         Simple tally of inventory or people's presence
 * Version:             1.1.11
 * Requires at least:   5.4
 * Requires PHP:        7.2
 * Author:              Michiel Uitdehaag
 * Author URI:          https://www.muisit.nl
 * License:             GNU GPLv3
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:         wp-presence
 * Domain Path:         /languages
 *
 * This file is part of wp-presence.
 *
 * wp-presence is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * wp-presence is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with wp-presence.  If not, see <https://www.gnu.org/licenses/>.
 */


function wppresence_activate() {
    require_once(__DIR__.'/activate.php');
    $activator = new \WPPresence\Activator();
    error_log("wppresence activate hook");
    $activator->activate();
}

function wppresence_deactivate() {
    require_once(__DIR__.'/activate.php');
    $activator = new \WPPresence\Activator();
    $activator->deactivate();
}

function wppresence_uninstall() {
    require_once(__DIR__ . '/activate.php');
    $activator = new \WPPresence\Activator();
    $activator->uninstall();
}

function wppresence_upgrade_function($upgrader_object, $options) {
    $current_plugin_path_name = plugin_basename(__FILE__);

    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        foreach ($options['plugins'] as $each_plugin) {
            if ($each_plugin == $current_plugin_path_name) {
                require_once(__DIR__ . '/activate.php');
                $activator = new \WPPresence\Activator();
                $activator->upgrade();
            }
        }
    }
}
function wppresence_plugins_loaded() {
    require_once(__DIR__ . '/activate.php');
    $activator = new \WPPresence\Activator();
    $activator->update();
}

function wppresence_ajax_handler($page) {
    require_once(__DIR__ . '/api.php');
    $dat = new \WPPresence\API();
    $dat->resolve();
}

function wppresence_display_admin_page() {
    require_once(__DIR__ . '/display.php');
    $dat = new \WPPresence\Display();
    $dat->adminPage();
}

function wppresence_enqueue_scripts($page) {
    require_once(__DIR__ . '/display.php');
    $dat = new \WPPresence\Display();
    $dat->scripts($page);
}

function wppresence_admin_menu() {
	add_menu_page(
		__( 'Presence' ),
		__( 'Presence' ),
		'manage_wppresence',
		'wppresence',
        'wppresence_display_admin_page',
        'dashicons-media-spreadsheet',
        100
	);
}
function wppresence_shortcode($atts) {
    require_once(__DIR__ . '/display.php');
    $actor = new \WPPresence\Display();
    return $actor->shortCode("frontend",$atts);
}


if (defined('ABSPATH')) {
    register_activation_hook( __FILE__, 'wppresence_activate' );
    register_deactivation_hook( __FILE__, 'wppresence_deactivate' );
    register_uninstall_hook(__FILE__, 'wppresence_uninstall');
    add_action('upgrader_process_complete', 'wppresence_upgrade_function', 10, 2);
    add_action('plugins_loaded', 'wppresence_plugins_loaded');

    add_action( 'admin_enqueue_scripts', 'wppresence_enqueue_scripts' );
    add_action( 'admin_menu', 'wppresence_admin_menu' );
    add_action('wp_ajax_wppresence', 'wppresence_ajax_handler');
    add_action('wp_ajax_nopriv_wppresence', 'wppresence_ajax_handler');
    add_shortcode( 'wp-presence', 'wppresence_shortcode' );

}
