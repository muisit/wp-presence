<?php

namespace WPPresence;

class Migration0001 extends MigrationObject {
    public function up() {
        $this->createTable("wppres_presence","(
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `item_id` int(11) NOT NULL,
            `created` datetime NOT NULL,
            `creator` int(11) NOT NULL,
            `state` varchar(20) COLLATE utf8_bin NOT NULL,
            `remark` TEXT NULL , 
            PRIMARY KEY (`id`)) ENGINE=InnoDB");

        $this->createTable("wppres_item","(
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) COLLATE utf8_bin NOT NULL,
            `type` varchar(20) COLLATE utf8_bin NOT NULL,
            `created` datetime NOT NULL,
            `creator` int(11) NOT NULL,
            `modified` datetime NOT NULL,
            `modifier` int(11) NOT NULL,
            `state` varchar(20) COLLATE utf8_bin NOT NULL,
            PRIMARY KEY (`id`)) ENGINE=InnoDB");

        $this->createTable("wppres_eva","( 
            `id` INT NOT NULL AUTO_INCREMENT , 
            `item_id` INT NOT NULL , 
            `name` VARCHAR(255) NOT NULL , 
            `value` TEXT NULL , 
            `type` VARCHAR(20) NOT NULL , 
            `remark` TEXT NULL , 
            `sorting` INT NOT NULL,
            `modified` DATETIME NOT NULL , 
            `modifier` INT NOT NULL , 
            PRIMARY KEY (`id`)) ENGINE = InnoDB");
        return true;
    }

    public function down() {
        $this->dropTable("wppres_presence");
        $this->dropTable("wppres_item");
        $this->dropTable("wppres_eva");
        return true;
    }
}