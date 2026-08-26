#!/usr/bin/env python3
"""Build a deterministic, PII-free SFT seed corpus for a future RizAI generative transformer.

The assistant target is deliberately model-neutral strict JSON. At runtime RizAiLocalLlmProvider
accepts either native OpenAI tool_calls or this JSON envelope, then re-validates names against the
ToolPolicy-filtered tool definitions supplied for the current shopper context.
"""
from __future__ import annotations

import hashlib
import json
import random
from pathlib import Path

OUT = Path(__file__).with_name("data") / "rizai-commerce-sft-v1.jsonl"
SEED = 4305
SYSTEM = (
    "You are RizAI, a governed planning model for Adobe Commerce. Magento is authoritative for "
    "catalog, price, inventory, customer, quote, checkout and order facts. Never invent those facts. "
    "Return only strict JSON: {\"tools\":[{\"name\":\"tool_name\",\"arguments\":{...}}]}. "
    "Use only allowed Magento tools. Consequential actions must use preparation/confirmation tools."
)

PRODUCTS = ["running shoes", "office chair", "wireless headphones", "coffee machine", "laptop", "backpack", "red shirt", "gaming mouse"]
CATEGORIES = ["electronics", "shoes", "bags", "home appliances", "accessories", "mens clothing"]

rows: list[dict] = []

def add(user: str, tools: list[dict], family: str, context: dict | None = None) -> None:
    payload = {"tools": tools}
    runtime_user = json.dumps({"message": user, "context": context or {}}, ensure_ascii=False, separators=(",", ":"))
    key = hashlib.sha256((family + "\0" + user).encode("utf-8")).hexdigest()[:16]
    rows.append({
        "messages": [
            {"role": "system", "content": SYSTEM},
            {"role": "user", "content": runtime_user},
            {"role": "assistant", "content": json.dumps(payload, ensure_ascii=False, separators=(",", ":"))},
        ],
        "meta": {"family": family, "group_id": key, "split": "validation" if int(key[:4], 16) % 5 == 0 else "train"},
    })

# Catalog discovery.
for p in PRODUCTS:
    for q in [f"find {p}", f"show me {p}", f"i need {p}", f"help me shop for {p}"]:
        add(q, [{"name": "search_products", "arguments": {"phrase": q, "filters": {}, "sort": {}, "page_size": 6, "current_page": 1}}], "search_products")
    add(f"show me {p} under 5000", [{"name": "search_products", "arguments": {"phrase": p, "filters": {"price": {"to": 5000}}, "sort": {}, "page_size": 6, "current_page": 1}}], "search_products_budget")
for c in CATEGORIES:
    add(f"open the {c} category", [{"name": "search_categories", "arguments": {"query": c, "limit": 5}}], "search_categories")
add("show me all the main departments", [{"name": "get_catalog_navigation", "arguments": {"limit": 20}}], "catalog_navigation")
add("what sections can i browse", [{"name": "get_catalog_navigation", "arguments": {"limit": 20}}], "catalog_navigation")

# Store/CMS grounding.
for q in ["what is your phone number", "where is your store", "what time do you open", "how can i email support"]:
    add(q, [{"name": "get_store_information", "arguments": {"topic": q}}], "store_information")
for q in ["what is your return policy", "how do refunds work", "explain the shipping policy", "what does your warranty cover", "how do you protect my data"]:
    add(q, [{"name": "answer_store_question", "arguments": {"query": q, "limit": 3}}], "store_policy")
for page in ["privacy policy", "terms and conditions", "shipping information", "about us", "returns page"]:
    add(f"open the {page} page", [{"name": "search_pages", "arguments": {"query": page, "limit": 5}}], "cms_navigation")

# Server-owned recent-product context. These index references are intentionally not SKUs invented by the model.
for q, tool, args in [
    ("tell me more about the first product", "get_product_content", {"index": 1}),
    ("how much is the first one", "get_product_price", {"index": 1}),
    ("is the first one in stock", "get_inventory", {"index": 1, "query": "is the first one in stock"}),
    ("show alternatives to the first item", "get_recommendations", {"index": 1, "type": "related", "limit": 6}),
    ("compare the first two products", "compare_recent_products", {"indexes": [1, 2], "focus": [], "goal": ""}),
    ("compare the first two based on description and price", "compare_recent_products", {"indexes": [1, 2], "focus": ["description", "price"], "goal": ""}),
]:
    add(q, [{"name": tool, "arguments": args}], "recent_product")

# Account and checkout reads.
for q, tool, args in [
    ("show my cart", "get_cart", {}),
    ("show my wishlist", "get_wishlist", {}),
    ("show my recent orders", "get_recent_orders", {"limit": 5}),
    ("show my profile", "get_customer_profile", {}),
    ("am i subscribed to the newsletter", "get_newsletter_status", {}),
    ("is checkout ready", "get_checkout_state", {}),
    ("show shipping methods", "get_shipping_methods", {}),
    ("show payment methods", "get_payment_methods", {}),
    ("show my saved addresses", "get_customer_addresses", {}),
]:
    add(q, [{"name": tool, "arguments": args}], "account_checkout")

# Safe multi-step plans.
add("find running shoes and then check stock of the first one", [
    {"name": "search_products", "arguments": {"phrase": "running shoes", "filters": {}, "sort": {}, "page_size": 6, "current_page": 1}},
    {"name": "get_inventory", "arguments": {"index": 1, "query": "check stock"}},
], "multi_step")
add("find laptops then compare the first two", [
    {"name": "search_products", "arguments": {"phrase": "laptops", "filters": {}, "sort": {}, "page_size": 6, "current_page": 1}},
    {"name": "compare_recent_products", "arguments": {"indexes": [1, 2], "focus": [], "goal": ""}},
], "multi_step")
add("tell me the price and stock of the first item", [
    {"name": "get_product_price", "arguments": {"index": 1}},
    {"name": "get_inventory", "arguments": {"index": 1, "query": "stock"}},
], "multi_step")

# State mutations are learnable, but authorization remains outside the model.
for q, tool, args in [
    ("add the first result to my cart", "add_recent_product_to_cart", {"index": 1, "quantity": 1}),
    ("add the first result to my wishlist", "add_recent_product_to_wishlist", {"index": 1}),
    ("apply coupon SAVE10", "apply_coupon", {"coupon_code": "SAVE10"}),
    ("remove the coupon", "remove_coupon", {}),
    ("clear my cart", "clear_cart", {}),
    ("subscribe me to the newsletter", "subscribe_newsletter", {}),
    ("unsubscribe me from the newsletter", "unsubscribe_newsletter", {}),
]:
    add(q, [{"name": tool, "arguments": args}], "mutation")

# Consequential operations use prepare/confirmation, never a fictional direct order-placement tool.
add("place my order", [{"name": "prepare_place_order", "arguments": {}}], "consequential_prepare")
add("delete my saved address number two", [{"name": "prepare_delete_saved_address", "arguments": {"index": 2}}], "consequential_prepare")
add("yes confirm that action", [{"name": "confirm_pending_action", "arguments": {}}], "confirmation")
add("cancel that pending action", [{"name": "cancel_pending_action", "arguments": {}}], "confirmation")

# No-tool outcomes teach abstention rather than generic catalog fallback.
for q in ["what is two plus two", "write a poem", "what is the weather today", "who won the football match", "tell me a random history fact"]:
    add(q, [], "out_of_scope")

rng = random.Random(SEED)
rng.shuffle(rows)
OUT.parent.mkdir(parents=True, exist_ok=True)
with OUT.open("w", encoding="utf-8") as f:
    for row in rows:
        f.write(json.dumps(row, ensure_ascii=False) + "\n")
print(f"wrote {len(rows)} SFT examples to {OUT}")
