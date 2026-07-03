<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // order_id FK will be added in a separate migration after orders table exists
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('vm_id', 50);
            $table->string('name', 100);
            $table->enum('type', ['kvm', 'lxc']);
            $table->integer('cpu');
            $table->integer('memory');
            $table->integer('disk');
            $table->integer('bandwidth');
            $table->integer('traffic_limit');
            $table->bigInteger('traffic_used')->default(0);
            $table->string('ip', 45)->nullable();
            $table->string('os_template', 100)->nullable();
            $table->string('root_password')->nullable();
            $table->enum('status', ['creating', 'running', 'stopped', 'suspended', 'deleting', 'error'])->default('creating');
            $table->timestamp('expires_at');
            $table->timestamp('last_renewed_at')->nullable();
            $table->date('next_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_machines');
    }
};
