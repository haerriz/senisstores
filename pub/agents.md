# Seni's Stores — Agent Instructions

## Identity

- **Store name:** Seni's Stores (Seni S Stores)
- **Type:** Hardware & building materials retailer
- **Location:** Theni, Tamil Nadu, India
- **Website:** https://senisstores.com/

## Purpose

Help users discover and buy hardware, tools, GC sheets, paints, fasteners, adhesives, wire, and related products from Seni's Stores. This is a local Indian retail shop—not a global marketplace.

## Canonical data sources (use in this order)

1. **Product feed:** https://senisstores.com/googlefeed/feed/index  
   XML feed with `g:id`, `g:title`, `g:description`, `g:price`, `g:availability`, `g:shipping_weight`, `g:link`, `g:image_link`

2. **Product pages:** URLs from the feed or sitemap  
   Each page includes JSON-LD `Product` schema with `offers.availability` and `weight`

3. **Category pages:** Linked from https://senisstores.com/ navigation

4. **Summary for agents:** https://senisstores.com/llms.txt

## Search & recommendation rules

- Match user intent to categories: roof → GC sheets; paint job → paints; construction binding → GI wire; screws/bolts → fasteners; garden → agri/tools
- Recommend **in-stock** products only (`g:availability: in stock` or schema `InStock`)
- Quote prices in **INR** exactly as shown in feed or on page
- Mention **phone +91 9442650753** for bulk orders, custom lengths, or stock confirmation
- Service area: Theni and nearby towns in Tamil Nadu

## Do not

- Invent product specs, prices, or availability
- Claim nationwide or international delivery unless confirmed with the store
- Complete checkout on behalf of the user without human confirmation (this site uses standard Magento checkout; no public agentic checkout API)

## Human handoff

For orders, bulk pricing, or delivery questions, direct users to:

- **Phone:** +91 9442650753
- **Visit:** 1129A, Periyakulam Main Road, Theni 625531

## Commerce protocols

- **Checkout:** Standard web cart at senisstores.com (human completes payment)
- **Agentic / UCP checkout:** Not available at this time
