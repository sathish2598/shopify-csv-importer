#!/usr/bin/env bash
# Verifies the imported products exist on Shopify and are in the configured collection.
set -euo pipefail
cd "$(dirname "$0")"

TOKEN=$(grep '^SHOPIFY_ACCESS_TOKEN=' .env | cut -d= -f2)
STORE=$(grep '^SHOPIFY_STORE_URL=' .env | cut -d= -f2)
VERSION=$(grep '^SHOPIFY_API_VERSION=' .env | cut -d= -f2)
COLLECTION=$(grep '^SHOPIFY_COLLECTION_ID=' .env | cut -d= -f2)

QUERY=$(cat <<EOF
{ shop { name } productsCount { count } collection(id: "gid://shopify/Collection/${COLLECTION}") { title } products(first: 15, query: "handle:modern-desk-lamp OR handle:ergonomic-office-chair OR handle:wireless-bluetooth-speaker OR handle:premium-yoga-mat OR handle:stainless-steel-water-bottle OR handle:handcrafted-ceramic-mug OR handle:organic-cotton-t-shirt OR handle:smart-fitness-tracker OR handle:premium-coffee-beans OR handle:minimalist-wall-clock") { nodes { title handle inCollection(id: "gid://shopify/Collection/${COLLECTION}") variants(first: 1) { nodes { sku price inventoryQuantity } } } } }
EOF
)

curl -s -X POST "https://${STORE}/admin/api/${VERSION}/graphql.json" \
  -H "X-Shopify-Access-Token: ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d "$(python3 -c 'import json,sys; print(json.dumps({"query": sys.argv[1]}))' "$QUERY")" |
python3 -c '
import json, sys

data = json.load(sys.stdin)["data"]
nodes = data["products"]["nodes"]
print(f"\nStore: {data['"'"'shop'"'"']['"'"'name'"'"']} — {data['"'"'productsCount'"'"']['"'"'count'"'"']} products total")
print(f"Target collection: \"{data['"'"'collection'"'"']['"'"'title'"'"']}\"")
print(f"\n{'"'"'Product'"'"':32} {'"'"'SKU'"'"':10} {'"'"'Price'"'"':>8} {'"'"'Qty'"'"':>4}  In Collection?")
print("-" * 75)
for n in nodes:
    v = n["variants"]["nodes"][0]
    mark = "YES" if n["inCollection"] else "NO !!"
    print(f"{n['"'"'title'"'"']:32} {v['"'"'sku'"'"']:10} {v['"'"'price'"'"']:>8} {v['"'"'inventoryQuantity'"'"']:>4}  {mark}")
print(f"\n{len(nodes)} products found on Shopify.")
'
