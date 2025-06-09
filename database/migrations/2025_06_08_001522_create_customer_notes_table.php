<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Who created the note
            $table->enum('type', ['general', 'call', 'meeting', 'email', 'complaint', 'follow_up', 'payment', 'delivery', 'other'])->default('general');
            $table->string('subject')->nullable();
            $table->text('content');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->boolean('is_private')->default(false); // Only visible to creator
            $table->boolean('requires_follow_up')->default(false);
            $table->datetime('follow_up_date')->nullable();
            $table->foreignId('follow_up_assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['open', 'in_progress', 'completed', 'cancelled'])->default('open');
            $table->json('attachments')->nullable(); // Store file paths/URLs
            $table->json('tags')->nullable(); // Flexible tagging system
            $table->timestamps();

            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['requires_follow_up', 'follow_up_date']);
            $table->index(['status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
    }
};
