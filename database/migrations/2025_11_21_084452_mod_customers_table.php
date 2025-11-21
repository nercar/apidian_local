<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
        public function up()
    {
        DB::statement("ALTER TABLE `apidian`.`customers` MODIFY COLUMN `address` varchar(255) CHARACTER SET utf8 COLLATE utf8_spanish_ci NULL DEFAULT NULL AFTER `phone`;");
    }
    
    /**
     * Reverse the migrations.
    *
    * @return void
    */
    public function down()
    {
        DB::statement("ALTER TABLE `apidian`.`customers` MODIFY COLUMN `address` varchar(120) CHARACTER SET utf8 COLLATE utf8_spanish_ci NULL DEFAULT NULL AFTER `phone`;");
    }
}
