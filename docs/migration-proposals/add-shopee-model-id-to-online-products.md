# Migration Proposal: Add `shopee_model_id` to `online_products`

## Problem

Shopee products with variations/models cannot be automatically synchronized
because the `online_products` table does not store which Shopee model/variation
a local product is connected to.

Without this column:
- Automatic sync must skip variation products
- Manual sync requires fetching variation details from Shopee live every time

## Solution

Add a nullable `shopee_model_id` column to the `online_products` table.

## Column Specification

```sql
ALTER TABLE online_products
    ADD COLUMN shopee_model_id BIGINT NULL AFTER product_id;
```

## Connection Flow Update

After this migration is applied:

1. When a user converts a Shopee product with variations:
   - Fetch available Shopee models via `get_item_base_info`
   - Show model selection dropdown in the conversion modal
   - Save the selected `model_id` to `online_products.shopee_model_id`
2. Non-variation products: `shopee_model_id` remains `NULL` (item-level stock update)

## Behavior

| Scenario | `shopee_model_id` | Stock Update Target |
|---|---|---|
| Non-variation item | `NULL` | Item-level (`model_id: 0`) |
| Variation item with model mapped | `123456` | Model-level (`model_id: 123456`) |
| Variation item without model mapped | `NULL` | Skipped with error message |

## Why Now?

The current implementation skips variation products during auto-sync. This
migration enables full variation support.

## Files Affected

- `database/migrations/XXXX_add_shopee_model_id_to_online_products_table.php`
- `app/Models/OnlineProduct.php` — add `shopee_model_id` to `$fillable`
- `app/Jobs/SyncShopeeProductInventory.php` — `resolveModelId()` already reads `shopee_model_id`
- `resources/views/Special_User/master_list/Online-Product-Configuration.blade.php` — add model selection to conversion modal
- `routes/web.php` — convert flow to save model_id
