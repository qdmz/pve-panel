<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vm_id')->constrained('virtual_machines')->cascadeOnDelete();
            $table->string('snapshot_id', 100);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->bigInteger('size')->default(0);
            $table->enum('status', ['creating', 'active', 'deleting', 'error'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');
    }
};
