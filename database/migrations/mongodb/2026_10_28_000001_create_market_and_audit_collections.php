<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('markets', function (Blueprint $collection) {
            $collection->unique(['match_id' => 1, 'code' => 1], options: ['name' => 'uq_market_match_code']);
            $collection->index(['match_id' => 1, 'status' => 1], options: ['name' => 'ix_market_match_status']);
        });

        Schema::connection('mongodb')->create('bet_rules', function (Blueprint $collection) {
            $collection->unique('code', options: ['name' => 'uq_bet_rule_code']);
            $collection->index(['market_code' => 1, 'active' => 1], options: ['name' => 'ix_bet_rule_market_active']);
        });

        Schema::connection('mongodb')->create('audit_logs', function (Blueprint $collection) {
            $collection->index(['user_id' => 1, 'created_at' => -1], options: ['name' => 'ix_audit_user_created']);
            $collection->index(['event' => 1, 'created_at' => -1], options: ['name' => 'ix_audit_event_created']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'bet_rules', 'markets'] as $collection) {
            Schema::connection('mongodb')->dropIfExists($collection);
        }
    }
};
