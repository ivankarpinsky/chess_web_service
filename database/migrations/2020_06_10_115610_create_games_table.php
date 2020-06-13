<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGamesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('games', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('token');
            $table->text('field')->default('[[{"type":"castle","color":"white"},{"type":"pawn","color":"white"},null,null,null,null,{"type":"pawn","color":"black"},{"type":"castle","color":"black"}],[{"type":"knight","color":"white"},{"type":"pawn","color":"white"},null,null,null,null,{"type":"pawn","color":"black"},{"type":"knight","color":"black"}],[{"type":"bishop","color":"white"},{"type":"pawn","color":"white"},null,null,null,null,{"type":"pawn","color":"black"},{"type":"bishop","color":"black"}],[{"type":"queen","color":"white"},{"type":"pawn","color":"white"},null,null,null,null,{"type":"pawn","color":"black"},{"type":"queen","color":"black"}],[{"type":"king","color":"white"},{"type":"pawn","color":"white"},null,null,null,null,{"type":"pawn","color":"black"},{"type":"king","color":"black"}],[{"type":"bishop","color":"white"},{"type":"pawn","color":"white"},null,null,null,null,{"type":"pawn","color":"black"},{"type":"bishop","color":"black"}],[{"type":"knight","color":"white"},{"type":"pawn","color":"white"},null,null,null,null,{"type":"pawn","color":"black"},{"type":"knight","color":"black"}],[{"type":"castle","color":"white"},{"type":"pawn","color":"white"},null,null,null,null,{"type":"pawn","color":"black"},{"type":"castle","color":"black"}]]'); // json
            $table->boolean('whitesTurn')->default(true);
            $table->boolean('finished')->default(false);
            $table->string('winner')->default('none');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('games');
    }
}
