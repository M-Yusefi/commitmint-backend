<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->date('date'); 
            $table->boolean('done');
            $table->unsignedTinyInteger('prod');     
            $table->unsignedTinyInteger('focus');    
            $table->unsignedTinyInteger('honest');   
            $table->string('opinion')->nullable();   
            $table->string('improve')->nullable();   
            $table->string('reason')->nullable();    
            $table->string('promise')->nullable();   
            $table->string('mood')->nullable();      
            $table->timestamps();
            $table->unique(['goal_id', 'date']);
        });    
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
