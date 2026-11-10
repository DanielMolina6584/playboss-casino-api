<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('contact_messages', function (Blueprint $collection) {
            $collection->index('created_at', options: ['name' => 'ix_contact_created']);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('contact_messages');
    }
};
