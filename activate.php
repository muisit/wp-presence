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

 class Activator {

    public function deactivate() {

    }

    public function uninstall() {
        // instantiate the Migrate model and run the activate method
        require_once(__DIR__ . '/models/base.php');
        require_once(__DIR__ . "/models/migration.php");
        $model = new Migration();
        $model->uninstall();
    }

    public function activate() {
        update_option('wppresence_version', 'new');
        $this->update();

        error_log("adding capability to role administrator");
        $role = get_role( 'administrator' );
        $role->add_cap( 'manage_wppresence', true );        
    }

    public function upgrade() {
        update_option('wppresence_version', 'new');
    }

    public function update() {
        if(get_option("wppresence_version") == "new") {
            // instantiate the Migrate model and run the activate method
            require_once(__DIR__ . '/models/base.php');
            require_once(__DIR__ . "/models/migration.php");
            // this loads all database migrations from file and executes
            // all those that are not yet marked as migrated
            $model = new Migration();
            $model->activate();
            update_option('wppresence_version', strftime('%F %T'));
        }
    }
 }