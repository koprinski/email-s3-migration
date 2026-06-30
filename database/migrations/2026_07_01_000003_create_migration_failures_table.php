<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_failures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('run_id')->index();
            $table->unsignedBigInteger('email_id')->index();
            $table->string('phase');
            $table->string('exception_class');
            $table->text('message');
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('failed_at');

            $table->index(['email_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_failures');
    }
};
