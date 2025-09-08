<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if we're using a traditional SQL database
        if ($this->isSqlDatabase()) {
            Schema::create('orchestral_performances', function (Blueprint $table) {
                $table->id();
                $table->string('event');
                $table->string('performer_name')->nullable();
                $table->string('environment');
                $table->json('data')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index('event');
                $table->index('performer_name');
                $table->index('environment');
                $table->index('occurred_at');
            });
        }

        // For MongoDB, the collection will be created automatically when first data is inserted
        // No explicit schema creation needed
    }

    public function down(): void
    {
        if ($this->isSqlDatabase()) {
            Schema::dropIfExists('orchestral_performances');
        } else {
            // For MongoDB, attempt to drop the collection if it exists
            try {
                $connection = config('database.default');
                if ($connection === 'mongodb') {
                    DB::connection('mongodb')
                        ->getCollection('orchestral_performances')
                        ->drop();
                }
            } catch (\Exception $e) {
                // Silently fail if collection doesn't exist or MongoDB driver not available
            }
        }
    }

    /**
     * Check if we're using a traditional SQL database (not MongoDB)
     */
    private function isSqlDatabase(): bool
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        return !in_array($driver, ['mongodb']);
    }
};