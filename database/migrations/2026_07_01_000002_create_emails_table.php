<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('loan_id')->index();
            $table->unsignedBigInteger('email_template_id')->index();
            $table->string('receiver_email');
            $table->string('sender_email');
            $table->string('subject');

            $table->text('body')->nullable();
            $table->json('file_ids');

            $table->string('body_s3_path')->nullable();
            $table->json('file_s3_paths')->nullable();

            $table->timestamp('migrated_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->index(['migrated_at', 'id']);
            $table->index(['verified_at', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
