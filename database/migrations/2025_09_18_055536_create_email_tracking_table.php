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
        Schema::create('email_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_id')->unique(); // Unique tracking identifier
            $table->string('email_type'); // invitation, welcome, notification, etc.
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->unsignedBigInteger('invitation_id')->nullable(); // Link to invitation if applicable
            $table->unsignedBigInteger('user_id')->nullable(); // Link to user if applicable
            $table->unsignedBigInteger('organization_id')->nullable();
            
            // Email content and metadata
            $table->string('subject');
            $table->text('content_hash')->nullable(); // Hash of email content for tracking changes
            $table->json('metadata')->nullable(); // Additional tracking data
            
            // Delivery tracking
            $table->enum('status', ['pending', 'sent', 'delivered', 'opened', 'clicked', 'bounced', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            // Tracking data
            $table->integer('open_count')->default(0);
            $table->integer('click_count')->default(0);
            $table->json('open_details')->nullable(); // IP, user agent, location, etc.
            $table->json('click_details')->nullable(); // Which links were clicked
            
            // Error tracking
            $table->text('error_message')->nullable();
            $table->string('provider_response')->nullable(); // Response from email provider
            
            $table->timestamps();
            
            // Indexes
            $table->index(['email_type', 'status']);
            $table->index(['recipient_email', 'created_at']);
            $table->index(['invitation_id']);
            $table->index(['user_id']);
            $table->index(['organization_id']);
            $table->index(['tracking_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_tracking');
    }
};