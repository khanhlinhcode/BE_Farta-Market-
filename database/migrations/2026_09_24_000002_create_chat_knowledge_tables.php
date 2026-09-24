<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('source_id', 120)->unique();
            $table->string('title', 180);
            $table->string('locale', 10)->default('vi');
            $table->string('topic', 80);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('published');
            $table->string('owner', 120);
            $table->char('checksum', 64);
            $table->date('source_updated_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'locale', 'topic']);
        });

        Schema::create('chat_knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('chat_knowledge_documents')->cascadeOnDelete();
            $table->string('section', 180);
            $table->text('content');
            $table->text('normalized_content');
            $table->unsignedInteger('position')->default(0);
            $table->char('checksum', 64);
            $table->timestamps();
            $table->unique(['document_id', 'position']);
            $table->index(['document_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_knowledge_chunks');
        Schema::dropIfExists('chat_knowledge_documents');
    }
};
