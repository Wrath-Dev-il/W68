<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('masterlist');

        if (!$schema->hasTable('products') || !$schema->hasColumn('products', 'specification')) {
            return;
        }

        // W68_PRODUCT_SPECIFICATION_TEXT_20261015
        // Vehicle fitment/specification can contain many multi-line applications,
        // so VARCHAR(255) is too small. TEXT preserves the complete specification.
        DB::connection('masterlist')->statement(
            'ALTER TABLE `products` MODIFY `specification` TEXT NULL'
        );
    }

    public function down(): void
    {
        $schema = Schema::connection('masterlist');

        if (!$schema->hasTable('products') || !$schema->hasColumn('products', 'specification')) {
            return;
        }

        $maxLength = (int) (DB::connection('masterlist')
            ->table('products')
            ->selectRaw('MAX(CHAR_LENGTH(specification)) as max_length')
            ->value('max_length') ?? 0);

        if ($maxLength > 255) {
            throw new \RuntimeException(
                'Cannot shrink products.specification back to VARCHAR(255) because existing specification data exceeds 255 characters.'
            );
        }

        DB::connection('masterlist')->statement(
            'ALTER TABLE `products` MODIFY `specification` VARCHAR(255) NULL'
        );
    }
};
