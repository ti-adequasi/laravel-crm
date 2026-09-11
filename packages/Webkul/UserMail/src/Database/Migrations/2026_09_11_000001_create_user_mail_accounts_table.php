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
        Schema::create('user_mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            $table->string('host');
            $table->unsignedSmallInteger('port');
            $table->string('username');
            // Encrypted (Laravel's 'encrypted' cast) — stored as ciphertext,
            // so text() rather than string(), same as the access/refresh
            // token columns on google_contact_accounts.
            $table->text('password');
            $table->string('encryption')->nullable();
            $table->string('from_address');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_mail_accounts');
    }
};
