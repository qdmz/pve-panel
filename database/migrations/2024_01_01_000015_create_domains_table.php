<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vm_id')->constrained('virtual_machines')->cascadeOnDelete();
            $table->string('domain', 255);
            $table->integer('target_port')->default(80);
            $table->boolean('ssl_enabled')->default(false);
            $table->enum('ssl_status', ['none', 'pending', 'active', 'failed'])->default('none');
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
