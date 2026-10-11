<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('nurseries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
        });
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->unsignedBigInteger('nursery_id');
            $table->string('name');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'nursery_id'])->references(['tenant_id', 'id'])->on('nurseries');
        });
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('name');
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
        });
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('child_id');
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'room_id'])->references(['tenant_id', 'id'])->on('rooms');
            $table->foreign(['tenant_id', 'child_id'])->references(['tenant_id', 'id'])->on('children');
            $table->index(['tenant_id', 'room_id', 'checked_out_at']);
        });
        DB::statement('CREATE UNIQUE INDEX check_ins_one_active_child ON check_ins (tenant_id, child_id) WHERE checked_out_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('children');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('nurseries');
        Schema::dropIfExists('tenants');
    }
};
