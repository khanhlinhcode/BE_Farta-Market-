<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_knowledge_chunks', function (Blueprint $table) {
            $table->text('retrieval_text')->nullable()->after('normalized_content');
        });

        DB::table('chat_knowledge_chunks')->orderBy('id')->eachById(function (object $chunk): void {
            DB::table('chat_knowledge_chunks')->where('id', $chunk->id)->update([
                'retrieval_text' => $chunk->content,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('chat_knowledge_chunks', function (Blueprint $table) {
            $table->dropColumn('retrieval_text');
        });
    }
};
