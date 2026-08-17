<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('advs', function (Blueprint $table) {
        
        $table->boolean('is_featured')->default(false); 
    });
}

public function down()
{
    Schema::table('advs', function (Blueprint $table) {
        $table->dropColumn('is_featured');
    });
}
};
