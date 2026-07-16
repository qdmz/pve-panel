<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->string('template_id', 100);
            $table->string('name', 200);
            $table->enum('type', ['kvm', 'lxc']);
            $table->string('source', 50)->nullable()->comment('iso, vm-template, lxc-template');
            $table->string('format', 50)->nullable();
            $table->bigInteger('size')->default(0);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['node_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_templates');
    }
};
