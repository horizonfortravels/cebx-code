<?php

use App\Models\VerificationRestriction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('verification_restrictions')) {
            return;
        }

        Schema::create('verification_restrictions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('restriction_key')->unique();
            $table->text('description')->nullable();
            $table->json('applies_to_statuses');
            $table->enum('restriction_type', [
                VerificationRestriction::TYPE_BLOCK_FEATURE,
                VerificationRestriction::TYPE_QUOTA_LIMIT,
            ]);
            $table->unsignedInteger('quota_value')->nullable();
            $table->string('feature_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_restrictions');
    }
};
