<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nat_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vm_id')->constrained('virtual_machines')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->string('local_ip', 45);
            $table->integer('local_port');
            $table->integer('public_port');
            $table->enum('protocol', ['tcp', 'udp', 'both'])->default('tcp');
            $table->string('description', 200)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nat_rules');
    }
};
