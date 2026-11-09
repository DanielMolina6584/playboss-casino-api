<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('match_stats', function (Blueprint $collection) {
            $collection->unique('match_id', options: ['name' => 'uq_match_stats_match']);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('match_stats');
    }
};
