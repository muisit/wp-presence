<?php

/**
 * WP-Presence activation routines
 *
 * @package             wp-presence
 * @author              Michiel Uitdehaag
 * @copyright           2020 Michiel Uitdehaag for muis IT
 * @licenses            GPL-3.0-or-later
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


 namespace WPPresence;

 class Display {
    public function adminPage() {
        echo <<<HEREDOC
        <div id="wppresence-root"></div>
HEREDOC;
    }

    public function scripts($page)  {
        if (in_array($page, array("toplevel_page_wppresence"))) {
            $script = plugins_url('/dist/app.js', __FILE__);
            $this->enqueue_code($script, hash_file("sha256",__DIR__."/dist/app.js"));
            $hash=hash_file("sha256",__DIR__."/dist/app.css");
            wp_enqueue_style( 'wppresence', plugins_url('/dist/app.css', __FILE__), array(), $hash);
        }
    }

    private function enqueue_code($script,$hsh) {
        // insert a small piece of html to load the ranking react script
        wp_enqueue_script('wppresence', $script, array('jquery', 'wp-element'), $hsh);
        require_once(__DIR__ . '/api.php');
        $dat = new \WPPresence\API();
        $nonce = wp_create_nonce($dat->createNonceText());
        wp_localize_script(
            'wppresence',
            'wppresence',
            array(
                'url' => admin_url('admin-ajax.php?action=wppresence'),
                'nonce'    => $nonce,
            )
        );
    }

    public function shortCode($name, $attributes) {
        $filename = dirname(__FILE__)."/dist/$name.js";
        if(file_exists($filename)) {
            $script = plugins_url('/dist/'.$name.'.js', __FILE__);
            $this->enqueue_code($script, hash_file("sha256", $filename));
            wp_enqueue_style('wppresence', plugins_url('/dist/app.css', __FILE__), array(), '1.0.0');
        }
        $output = "<div id='wppresence-$name'></div>";
        return $output;
    }    


}