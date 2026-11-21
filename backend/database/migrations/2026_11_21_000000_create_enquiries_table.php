<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('product_id', 64);
            $table->string('product_title');
            $table->unsignedInteger('product_price_minor');
            $table->string('product_currency', 3);
            $table->string('name', 100);
            $table->string('email', 254);
            $table->string('phone', 30)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 16)->default('received')->index();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
