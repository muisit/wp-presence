<?php

namespace WPPresence;

class Migration0004 extends MigrationObject {
    public function up() {
        global $wpdb;
        if(!$this->columnExists("wppres_item","config")) {
            $this->rawQuery("ALTER TABLE " . $wpdb->base_prefix . "wppres_item ADD column config TEXT NULL;");
        }
        return true;
    }

    public function down() {
        global $wpdb;
        if ($this->columnExists("wppres_item", "config")) {
            $this->rawQuery("ALTER TABLE ". $wpdb->base_prefix . "wppres_item DROP column config;");
        }
        return true;
    }
}