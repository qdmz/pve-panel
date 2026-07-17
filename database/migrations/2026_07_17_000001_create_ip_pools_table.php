<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('ipv4')->comment('ipv4, ipv6');
            $table->string('subnet', 45)->comment('CIDR notation, e.g. 10.0.0.0/24');
            $table->string('gateway', 45)->nullable();
            $table->string('bridge', 50)->default('vmbr1');
            $table->string('dhcp_range_start', 45)->nullable();
            $table->string('dhcp_range_end', 45)->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pool_id')->constrained('ip_pools')->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->string('mac_address', 17)->nullable();
            $table->unsignedBigInteger('vm_id')->nullable()->index();
            $table->string('status', 20)->default('free')->comment('free, reserved, allocated');
            $table->timestamp('allocated_at')->nullable();
            $table->timestamps();

            $table->unique(['pool_id', 'ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
        Schema::dropIfExists('ip_pools');
    }
};
