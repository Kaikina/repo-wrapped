<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repo_stats', function (Blueprint $table) {
            $table->id();
            $table->string('owner');
            $table->string('repo');
            $table->string('username');
            $table->json('stats');
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['owner', 'repo', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repo_stats');
    }
};
