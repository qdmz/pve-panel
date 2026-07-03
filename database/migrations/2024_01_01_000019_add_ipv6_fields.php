<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add ipv6_bridge to nodes
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('ipv6_bridge', 50)->nullable()->after('bridge')
                ->comment('Bridge for IPv6 (e.g. vmbr2)');
        });

        // Add ipv6_address and nat_ipv4 to virtual_machines
        Schema::table('virtual_machines', function (Blueprint $table) {
            $table->string('ipv6_address', 45)->nullable()->after('ip')
                ->comment('Auto-assigned IPv6 address');
            $table->string('nat_ipv4', 45)->nullable()->after('ipv6_address')
                ->comment('Auto-assigned NAT IPv4 from vmbr1 subnet');
        });
    }

    public function down(): void
    {
        Schema::table('virtual_machines', function (Blueprint $table) {
            $table->dropColumn(['ipv6_address', 'nat_ipv4']);
        });

        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('ipv6_bridge');
        });
    }
};
