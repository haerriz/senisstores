#!/usr/bin/env python3
"""Build the supervised intent corpus used by the bundled RizAI neural intent model.

The corpus intentionally contains commerce-language examples only. It does not contain customer
PII, catalog exports, order history, payment data or proprietary production conversations.
"""
from __future__ import annotations

import hashlib
import json
import random
from pathlib import Path

OUT = Path(__file__).with_name("commerce_intents.jsonl")
SEED = 4305

PRODUCTS = ["running shoes", "laptop", "red shirt", "wireless mouse", "coffee maker", "backpack", "headphones", "office chair"]
CATEGORIES = ["shoes", "electronics", "mens clothing", "bags", "home appliances", "accessories"]
PAGES = ["returns page", "shipping page", "privacy policy", "contact page", "about us page", "terms page"]

examples: dict[str, set[str]] = {k: set() for k in [
    "search_products", "search_categories", "get_catalog_navigation", "get_store_information",
    "answer_store_question", "search_pages", "get_cart", "get_wishlist", "get_recent_orders",
    "get_checkout_state", "get_customer_profile", "get_newsletter_status", "product_content",
    "compare_products", "inventory", "price", "recommendations", "smalltalk", "out_of_scope",
]}

def add(label: str, *phrases: str) -> None:
    for phrase in phrases:
        phrase = " ".join(phrase.strip().split())
        if phrase:
            examples[label].add(phrase)

# Product discovery
for p in PRODUCTS:
    add("search_products",
        f"show me {p}", f"find {p}", f"search for {p}", f"i need {p}", f"do you have {p}",
        f"browse {p}", f"list {p}", f"what {p} do you sell", f"help me find {p}")
add("search_products",
    "show products under 5000", "find something under my budget", "show the cheapest products",
    "show premium products", "show products in stock", "find new arrivals", "show best sellers",
    "i am looking for a product", "help me shop", "show me what i can buy")

# Category discovery
for c in CATEGORIES:
    add("search_categories",
        f"find the {c} category", f"open the {c} section", f"show the {c} category",
        f"where is the {c} category", f"take me to {c} category")
add("search_categories", "search categories for electronics", "find a category", "which category has laptops")

# Catalog navigation
add("get_catalog_navigation",
    "show all categories", "what categories are available", "browse the catalog", "list store categories",
    "show me the catalog sections", "what can i browse", "display product categories", "catalog menu",
    "show the category tree", "what departments do you have")

# Store information
add("get_store_information",
    "what is your phone number", "what is the store phone", "how can i contact the store",
    "what is your email address", "give me the store email", "where is the store located",
    "what is your address", "what are your opening hours", "when are you open", "what is this store called",
    "tell me the store name", "what is the website contact number", "how do i reach customer care")

# Store policy / CMS knowledge Q&A
add("answer_store_question",
    "what is your return policy", "can i return an item", "how do refunds work", "what is the refund policy",
    "how long does shipping take", "tell me about delivery", "what is the warranty policy",
    "do you offer warranty", "what is your privacy policy", "how do you handle personal data",
    "what are the store terms", "do you ship internationally", "what payment policy do you have",
    "explain your cancellation policy", "what is your exchange policy", "can i exchange a product")

# CMS page navigation
for p in PAGES:
    add("search_pages", f"open the {p}", f"take me to the {p}", f"find the {p}", f"show the {p}")
add("search_pages", "open contact us", "go to about us", "find your faq page", "show me the terms and conditions page")

# Cart read
add("get_cart",
    "show my cart", "what is in my cart", "view my basket", "show cart summary", "how many items are in my cart",
    "what have i added to cart", "display my shopping cart", "show my basket contents", "check my cart")

# Wishlist read
add("get_wishlist",
    "show my wishlist", "what is in my wishlist", "view my saved items", "show my wish list", "display my wishlist",
    "what products did i save", "check my wishlist", "show items i saved for later")

# Orders read
add("get_recent_orders",
    "show my orders", "show my recent orders", "what did i order", "view order history", "list my purchases",
    "show previous orders", "what are my latest orders", "open my order history", "display recent purchases")

# Checkout state read
add("get_checkout_state",
    "show checkout status", "am i ready to checkout", "what is missing from checkout", "checkout progress",
    "can i place my order now", "is checkout complete", "what do i need before checkout", "check checkout readiness",
    "show the checkout state", "is my cart ready to order")

# Customer profile read
add("get_customer_profile",
    "show my profile", "show my account details", "what is my customer profile", "view my account information",
    "display my profile", "show the details on my account", "what information is on my account")

# Newsletter status read
add("get_newsletter_status",
    "am i subscribed to the newsletter", "check newsletter status", "show my newsletter status",
    "tell me if i get the newsletter", "is my account subscribed to newsletter", "newsletter subscription status")

# Product content read
add("product_content",
    "describe this product", "tell me about this product", "show product details", "summarize the product description",
    "what are its features", "show its specifications", "tell me the main features", "what does this product include",
    "give me the product description", "explain the first product", "tell me about the first one", "show details for the second product")

# Comparison read
add("compare_products",
    "compare these products", "compare the first two", "what is the difference between these two",
    "compare product one and product two", "which of these products is better", "show similarities and differences",
    "compare the first and third products", "help me choose between these two", "compare their features")

# Inventory read
add("inventory",
    "is this product in stock", "check stock for this product", "how many are left", "is the first product available",
    "show inventory", "check availability", "is it available now", "do you have this item in stock",
    "what is the stock status", "is the second one available")

# Price read
add("price",
    "what is the price", "how much does this cost", "show the price of the first product", "what does this item cost",
    "check product price", "tell me the current price", "how much is the second one", "show pricing",
    "what is its price", "price of this product")

# Recommendations read
add("recommendations",
    "show related products", "recommend something similar", "show alternatives to this product",
    "what else goes with this", "show upsell products", "show cross sell products", "recommend products like this",
    "show similar items", "what else should i consider", "give me related recommendations")

# Small talk
add("smalltalk",
    "hello", "hi", "hey", "good morning", "good afternoon", "good evening", "thanks", "thank you",
    "how are you", "who are you", "what can you help me with", "nice to meet you")

# Out-of-scope/general requests deliberately represented to reduce accidental catalog routing.
add("out_of_scope",
    "what is the capital of france", "calculate 29 times 17", "write me a poem", "tell me a joke",
    "what is the weather today", "who won the football match", "explain quantum physics", "translate this sentence",
    "write python code", "what is the latest news", "book me a flight", "play a song", "set an alarm",
    "what is two plus two", "tell me about the moon", "solve this math problem")


# Additional independently worded base utterances improve semantic coverage without copying
# production/customer conversations. Group-aware splitting below keeps every base phrase and all of
# its polite variants on only one side of the train/validation boundary.
EXTRA = {
    "get_store_information": [
        "give me your contact details", "how can i call you", "where can i find your shop",
        "tell me your business address", "when does the shop close", "when does the shop open",
        "what time do you close", "what time do you open", "how do i email support",
        "share the customer service email", "share the customer service number", "where are you based",
        "show store contact information", "i need your office address", "how can i reach your team",
        "store location please", "contact details please", "tell me your working hours",
    ],
    "answer_store_question": [
        "are returns accepted", "how many days do i have to return something", "can i get my money back",
        "when will my refund arrive", "what are the delivery rules", "what are the shipping charges rules",
        "what happens if my item is damaged", "how does replacement work", "can an order be cancelled",
        "tell me the exchange rules", "explain the warranty terms", "how is customer data protected",
        "explain your terms of service", "what is the store policy on returns", "can i send a purchase back",
        "do you allow refunds", "what is your delivery policy", "what is your cancellation rule",
        "how does your privacy policy work", "tell me about your shipping policy",
    ],
    "get_catalog_navigation": [
        "show me all departments", "list every department", "what sections are in the shop",
        "show the main shopping sections", "let me browse by department", "open the category menu",
        "what product departments exist", "show the full catalog menu", "browse all departments",
        "display all shopping categories", "show top level categories", "take me through the catalog",
        "let me explore the catalog", "show the departments list", "what can i shop by category",
        "show your product sections", "give me the catalog menu", "list the main categories",
    ],
    "get_cart": [
        "open my cart", "open my basket", "review my cart", "review my basket", "cart contents",
        "basket contents", "what items are currently in my basket", "what items are currently in my cart",
        "show cart totals", "show basket summary", "let me see my shopping bag", "open shopping bag",
        "what is my cart total", "tell me what is in the basket", "display cart items",
        "view current cart", "check my shopping bag", "show items in basket",
    ],
    "get_wishlist": [
        "open my saved products", "show saved products", "open saved for later", "view saved for later",
        "what did i add to my wish list", "list my favorite products", "show my favorites",
        "view my favorites", "show products on my wish list", "open wish list", "saved items list",
        "which products are on my wishlist", "display my saved products", "show things i bookmarked",
        "open my product favorites", "what have i wishlisted", "review my wishlist", "list saved items",
    ],
    "get_recent_orders": [
        "open my purchases", "show purchase history", "show order history", "list previous purchases",
        "what have i bought recently", "show my last orders", "show my latest purchases",
        "view previous purchases", "list recent orders", "what orders have i made", "display my past orders",
        "show my past purchases", "review my order history", "open recent purchases", "show purchase records",
        "what was my last order", "show my earlier orders", "view my latest order history",
    ],
    "get_checkout_state": [
        "show my checkout progress", "what step of checkout am i on", "what remains before i can order",
        "is checkout ready", "what checkout information is missing", "show missing checkout details",
        "review checkout", "show order readiness", "am i ready to place the order", "what is left in checkout",
        "check whether checkout is ready", "show checkout requirements", "what do i still need to select",
        "show current checkout step", "review my checkout status", "is everything ready for ordering",
    ],
    "get_customer_profile": [
        "open my customer account", "view my profile details", "show account information",
        "what details are saved on my profile", "show my personal account details", "review my account",
        "display customer profile", "open profile information", "show saved account details",
        "what profile information do you have", "view customer details", "show my registered account info",
        "show account profile", "open my profile page information", "review my profile data",
    ],
    "get_newsletter_status": [
        "do i receive marketing emails", "am i on the mailing list", "check if i am on the mailing list",
        "do i receive your newsletter", "show email subscription status", "check my email subscription",
        "is newsletter enabled for my account", "am i opted in to newsletters", "show mailing list status",
        "tell me whether i am subscribed", "check newsletter preference", "show newsletter preference",
        "is my newsletter subscription active", "do i get store newsletters", "display subscription status",
    ],
    "product_content": [
        "explain this item", "what can you tell me about this item", "show the specifications for this item",
        "list the features of this item", "summarize this product", "what are the key specs",
        "what are the key features", "give me more information about this product", "show technical details",
        "show product specifications", "what does the first item offer", "tell me more about the first result",
        "describe the second result", "show details of this item", "what is special about this product",
        "summarize the first result", "give me the specs", "explain the product features",
    ],
    "compare_products": [
        "compare the two results", "compare item one with item two", "put these products side by side",
        "show a side by side comparison", "what differs between the first two", "how are these two different",
        "compare their specifications", "compare their prices and features", "which one suits me better",
        "help me decide between the first two", "contrast these products", "show the differences between them",
        "compare result one and result three", "compare the selected items", "give me a product comparison",
        "compare both options", "what are the pros and differences of these products", "compare their details",
    ],
    "inventory": [
        "is this item available", "is the item currently available", "check if this is available to buy",
        "check whether the first result is in stock", "stock status for the first result", "availability of this item",
        "do you still have this product", "can i buy this right now", "is there stock remaining",
        "tell me whether it is sold out", "is the second result in stock", "check product availability now",
        "what quantity is available", "show the availability status", "do you have any units left",
        "is this sold out", "check current inventory status", "tell me if this is available",
    ],
    "price": [
        "tell me the price", "show current product price", "what is the current cost", "how much is this item",
        "what does the first result cost", "price for the first result", "show me its current cost",
        "tell me how much it is", "what is the selling price", "display the item price", "current price please",
        "show the cost of the second result", "how much would i pay for this", "give me the product cost",
        "tell me its selling price", "what is the listed price", "show price details", "check the current cost",
    ],
    "recommendations": [
        "suggest similar products", "suggest alternatives", "what products are similar to this",
        "show me products like this one", "what other options are related", "recommend another similar item",
        "show matching alternatives", "what can i buy instead of this", "suggest related items",
        "recommend accessories for this", "show products that go well with this", "what should i consider instead",
        "find comparable alternatives", "show more like this", "give me similar recommendations",
        "suggest products related to the first result", "what else is recommended with this", "show alternative items",
    ],
    "smalltalk": [
        "hello there", "hi there", "hey there", "good day", "thanks a lot", "many thanks",
        "appreciate it", "thank you very much", "what are you", "what do you do", "how can you assist me",
        "can you help me shop", "nice talking to you", "greetings", "how is it going", "good to see you",
        "thanks for the help", "tell me what you can do",
    ],
    "out_of_scope": [
        "tell me a random fact", "write a short story", "compose an email for me", "solve an algebra equation",
        "what is the current temperature outside", "give me the cricket score", "who is the prime minister",
        "explain relativity", "generate javascript code", "summarize world news", "find a hotel for me",
        "reserve a restaurant table", "send a text message", "remind me tomorrow", "start a timer",
        "convert dollars to euros", "what time is it in london", "draw a picture", "write a song",
        "tell me the history of rome", "help with my homework", "what is five squared", "translate hello to french",
        "recommend a movie", "give me medical advice", "what is todays weather", "show the stock market news",
        "create a spreadsheet", "write a resume", "plan my vacation",
    ],
}
for label, phrases in EXTRA.items():
    add(label, *phrases)

# Controlled language augmentation. Keep semantics unchanged and avoid mutation verbs.
# IMPORTANT: split by base utterance first, then generate polite variants inside that split. This
# prevents near-duplicate variants of the same base sentence from leaking across train/validation.
prefixes = ["", "please ", "can you ", "could you ", "i want you to ", "i need you to "]
suffixes = ["", " please", " for me", " right now"]
rng = random.Random(SEED)
final: list[dict[str, str]] = []

for label, base_set in sorted(examples.items()):
    bases = sorted(base_set)
    rng.shuffle(bases)
    val_n = max(2, int(round(len(bases) * 0.20)))
    validation_bases = set(bases[:val_n])

    for base in bases:
        split = "validation" if base in validation_bases else "train"
        variants = {base}
        # Generate the same bounded family of politeness variants, all bound to the base's split.
        for pref in prefixes[1:4]:
            variants.add(pref + base)
        for suf in suffixes[1:3]:
            variants.add(base + suf)
        for text in sorted(variants):
            group_id = hashlib.sha256(f"{label}\0{base}".encode("utf-8")).hexdigest()[:16]
            final.append({"text": text, "label": label, "split": split, "group_id": group_id})

# Deduplicate without moving examples across their group-derived split.
unique: dict[tuple[str, str], dict[str, str]] = {}
for row in final:
    key = (row["text"].lower(), row["label"])
    unique.setdefault(key, row)
final = list(unique.values())
rng.shuffle(final)

with OUT.open("w", encoding="utf-8") as f:
    for row in final:
        f.write(json.dumps(row, ensure_ascii=False) + "\n")

print(f"wrote {len(final)} examples to {OUT}")
for label in sorted(examples):
    train_n = sum(1 for r in final if r["label"] == label and r["split"] == "train")
    val_n = sum(1 for r in final if r["label"] == label and r["split"] == "validation")
    print(f"{label:24s} train={train_n:4d} validation={val_n:4d}")
