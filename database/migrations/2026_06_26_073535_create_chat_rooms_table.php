<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
           $table->ulid('id')->primary(); //     
            $table->string('firebase_chat_id')->unique();    
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};
