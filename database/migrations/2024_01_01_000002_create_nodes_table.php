<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('host', 255);
            $table->integer('port')->default(8006);
            $table->enum('auth_type', ['api_token', 'username_password'])->default('api_token');
            $table->text('api_token')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->enum('virtualization', ['kvm', 'lxc', 'both'])->default('both');
            $table->enum('status', ['online', 'offline', 'maintenance'])->default('offline');
            $table->boolean('nat_enabled')->default(false);
            $table->integer('nat_start_port')->nullable();
            $table->integer('nat_end_port')->nullable();
            $table->string('nat_network')->nullable();
            $table->string('bridge')->default('vmbr0');
            $table->string('storage')->default('local-lvm');
            $table->integer('cpu_total')->default(0);
            $table->bigInteger('memory_total')->default(0);
            $table->bigInteger('disk_total')->default(0);
            $table->integer('cpu_used')->default(0);
            $table->bigInteger('memory_used')->default(0);
            $table->bigInteger('disk_used')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
