<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            // null = platform-wide (manufacturer manuals, safety rules); set = tenant's own documents.
            $table->string('tenant_id')->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->string('title');
            $table->string('source')->nullable();
            $table->longText('content');
            $table->string('content_hash', 64);
            $table->string('status')->default('pending'); // pending | indexed | failed
            $table->string('error')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->string('embedding_model')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['tenant_id', 'source']);
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedInteger('ordinal');
            $table->string('heading');
            $table->text('content');
            $table->unsignedInteger('token_estimate');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1024)');
        DB::statement("ALTER TABLE document_chunks ADD COLUMN tsv tsvector GENERATED ALWAYS AS (to_tsvector('english', heading || ' ' || content)) STORED");
        DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX document_chunks_tsv_idx ON document_chunks USING gin (tsv)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('documents');
    }
};
