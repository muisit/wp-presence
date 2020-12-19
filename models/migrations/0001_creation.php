<?php

namespace WPPresence;

class Migration0001 extends MigrationObject {
    public function up() {
        $this->createTable("temp1","(id INT NOT NULL AUTO_INCREMENT, status INT null, PRIMARY KEY (`id`)) ENGINE = InnoDB ");
        $this->createTable("temp2","(id INT NOT NULL AUTO_INCREMENT, status INT null, PRIMARY KEY (`id`)) ENGINE = InnoDB ");

        return true;
    }

    public function down() {
        $this->dropTable("temp1");
        $this->dropTable("temp2");

        return true;
    }
}