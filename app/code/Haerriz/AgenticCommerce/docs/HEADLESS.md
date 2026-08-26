# Headless / PWA GraphQL Guide

> Version 5.0.0 · Reviewed 2026-08-26 · See [API and channels](API_AND_CHANNELS.md) and [Architecture](ARCHITECTURE.md).

`Haerriz_AgenticCommerce` exposes the same agent service used by Luma/Blank and Hyvä through a GraphQL-first contract. Product discovery, dynamic attributes, conversation ownership, previous-product references and guarded cart operations therefore do not depend on the bundled storefront UI.

## 1. Identity model

There are three different identifiers. Keep them separate:

| Value | Purpose | Authority |
|---|---|---|
| `client_id` | High-entropy anonymous browser/app-install identifier | Client stores it; it is not authentication |
| `conversation_id` | Public opaque conversation identifier | Server creates it and enforces ownership |
| customer token | Magento customer authentication | Magento GraphQL authentication context |

`session_id` is retained only as a deprecated alias for `client_id`. New integrations should use `client_id` and `conversation_id` explicitly.

Generate/persist a high-entropy `client_id`, or omit it on the first `agenticCommerceStartConversation` request and persist the value returned by the server. Never use a Magento customer ID as a client identifier.

## Capability and store-profile discovery

Query `agenticCommerceCapabilities` before enabling optional UI and `agenticCommerceStoreProfile` for public assistant/site/organization/contact/channel facts. REST clients can use `/V1/agentic-commerce/capabilities` and `/V1/agentic-commerce/store-profile`.

Headless clients must render only structured products returned for the current turn. A CMS/informational/refused response has no product cards and should not reuse cards from client state.

## 2. Start a conversation

```graphql
mutation StartAgentConversation($input: AgenticCommerceStartConversationInput) {
  agenticCommerceStartConversation(input: $input) {
    id
    title
    status
    client_id
    viewer { is_customer customer_id }
  }
}
```

```json
{
  "input": {
    "client_id": "YOUR_PERSISTED_OPAQUE_CLIENT_ID"
  }
}
```

## 3. Chat / product discovery

```graphql
mutation AgentChat($input: AgenticCommerceChatInput!) {
  agenticCommerceChat(input: $input) {
    conversation_id
    client_id
    message
    total_count
    query_phrase
    viewer { is_customer customer_id }
    filters { attribute condition values label }
    facets {
      attribute
      label
      options { value label count }
    }
    products {
      id
      sku
      name
      url
      image
      price
      regular_price
      formatted_price
      formatted_regular_price
      custom_attributes { code label value }
    }
    actions { type label url auto_navigate }
    cart {
      cart_id
      items_count
      subtotal
      formatted_subtotal
      items { item_id sku name qty row_total formatted_row_total }
    }
    page_info { current_page page_size total_pages }
  }
}
```

Example variables:

```json
{
  "input": {
    "message": "show 3 black shoes under 5000 cheapest first",
    "conversation_id": "PUBLIC_CONVERSATION_ID",
    "client_id": "OPAQUE_CLIENT_ID",
    "query_phrase": "",
    "current_filters": []
  }
}
```

There is intentionally **no** `recent_products` GraphQL input. Commands such as `open the third product`, `compare the first and third products`, and `add the last shown product to cart` resolve against product results persisted in the server-owned conversation context.

## 4. Guest cart

Use Magento's native guest-cart mutation first:

```graphql
mutation {
  createEmptyCart
}
```

The returned string is Magento's masked guest cart ID. Supply it as `cart_id` on Agentic Commerce chat/cart operations:

```json
{
  "input": {
    "message": "add the first shown product to my cart",
    "conversation_id": "PUBLIC_CONVERSATION_ID",
    "client_id": "OPAQUE_CLIENT_ID",
    "cart_id": "MAGENTO_MASKED_CART_ID"
  }
}
```

Anonymous/headless requests never accept a numeric quote ID. The module resolves the masked ID, rejects customer-owned quotes, and rejects a quote belonging to another store view.

## 5. Direct add to cart

Simple/virtual products that need no shopper option selection can be added directly:

```graphql
mutation AgentAdd($input: AgenticCommerceAddToCartInput!) {
  agenticCommerceAddToCart(input: $input) {
    added
    requires_options
    assistant_message
    actions { type label url auto_navigate }
    cart {
      items_count
      formatted_subtotal
      items { item_id sku name qty formatted_row_total }
    }
  }
}
```

```json
{
  "input": {
    "sku": "SKU-123",
    "quantity": 1,
    "cart_id": "MASKED_GUEST_CART_ID",
    "client_id": "OPAQUE_CLIENT_ID"
  }
}
```

For configurable, bundle, grouped, downloadable, or another product type requiring selections, the service returns `requires_options: true` plus a PDP action. Use Magento's native option-aware cart mutation after the shopper selects required options.

The bundled storefront product-card button also sends an exact-SKU add command. The agent validates that the exact SKU appears in an explicit shopper cart-add request before allowing its `add_product_to_cart` tool.

## 6. Read, remove, update, and clear cart

Read:

```graphql
query AgentCart($cartId: String, $clientId: String) {
  agenticCommerceCart(cart_id: $cartId, client_id: $clientId) {
    cart_id
    items_count
    subtotal
    formatted_subtotal
    items { item_id sku name qty row_total formatted_row_total }
  }
}
```

Remove an item from the currently authorized quote:

```graphql
mutation AgentRemove($input: AgenticCommerceRemoveFromCartInput!) {
  agenticCommerceRemoveFromCart(input: $input) {
    removed
    assistant_message
    cart { items_count formatted_subtotal items { item_id sku name qty } }
  }
}
```

```json
{
  "input": {
    "item_id": 42,
    "cart_id": "MASKED_GUEST_CART_ID",
    "client_id": "OPAQUE_CLIENT_ID"
  }
}
```

Update quantity:

```graphql
mutation AgentUpdateCart($input: AgenticCommerceUpdateCartItemInput!) {
  agenticCommerceUpdateCartItem(input: $input) {
    updated
    removed
    assistant_message
    cart { items_count formatted_subtotal items { item_id sku name qty } }
  }
}
```

```json
{
  "input": {
    "item_id": 42,
    "quantity": 3,
    "cart_id": "MASKED_GUEST_CART_ID",
    "client_id": "OPAQUE_CLIENT_ID"
  }
}
```

Quantity `0` removes the item.

Clear:

```graphql
mutation AgentClearCart($input: AgenticCommerceClearCartInput) {
  agenticCommerceClearCart(input: $input) {
    cleared
    assistant_message
    cart { items_count formatted_subtotal }
  }
}
```

Direct GraphQL mutations use the quote's own `item_id`, but the quote itself is first resolved from trusted customer identity or an authorized masked guest cart ID. Conversational tools are stricter: the LLM receives/uses visible positions such as first/second/last and never chooses arbitrary Magento quote item IDs.

## 7. Customer login

Use Magento's native customer-token mutation:

```graphql
mutation CustomerLogin($email: String!, $password: String!) {
  generateCustomerToken(email: $email, password: $password) {
    token
  }
}
```

Send subsequent GraphQL requests with:

```text
Authorization: Bearer CUSTOMER_TOKEN
```

Do **not** send `customer_id` to Agentic Commerce. The resolvers derive it from Magento's trusted GraphQL context. Admin/integration identities are not accepted as customer identities.

If the same browser had anonymous Agentic Commerce conversations before login, continue sending its existing `client_id`. The module claims guest conversations for that same high-entropy client into the authenticated customer account. After claim, those conversations are no longer retrievable anonymously.

For authenticated shoppers the active quote is loaded with Magento's customer quote service; the client cannot select a numeric customer quote ID.

## 8. Conversation list and history

```graphql
query AgentHistory($clientId: String) {
  agenticCommerceConversations(client_id: $clientId, limit: 20, page: 1) {
    client_id
    viewer { is_customer customer_id }
    items {
      id
      title
      status
      created_at
      updated_at
      last_message_at
    }
  }
}
```

Load a conversation:

```graphql
query AgentConversation($id: String!, $clientId: String) {
  agenticCommerceConversation(id: $id, client_id: $clientId) {
    id
    title
    status
    client_id
    viewer { is_customer customer_id }
    messages {
      id
      role
      content
      created_at
      products { sku name url image formatted_price custom_attributes { code label value } }
      actions { type label url auto_navigate }
      cart {
        items_count
        formatted_subtotal
        items { item_id sku name qty }
      }
      filters { attribute condition values label }
      facets { attribute label options { value label count } }
      total_count
      query_phrase
      page_info { current_page page_size total_pages }
    }
  }
}
```

Assistant payloads are stored with messages so a PWA can reconstruct product/filter/cart cards on resume. Treat controls rendered from old messages as historical/read-only; issue a new live command for a mutation.

## 9. Close a conversation

```graphql
mutation CloseAgentConversation($input: AgenticCommerceCloseConversationInput!) {
  agenticCommerceCloseConversation(input: $input) {
    success
    client_id
    viewer { is_customer }
  }
}
```

A closed conversation is not silently reused for a new chat turn.

## 10. Dynamic custom EAV attributes

```graphql
query AgentAttributes($search: String) {
  agenticCommerceAttributes(search: $search) {
    code
    label
    frontend_input
    is_filterable
    is_filterable_in_search
    is_searchable
    is_visible_on_front
    used_in_product_listing
    options { value label }
  }
}
```

Use this metadata to construct PWA filters dynamically. Custom select/multiselect labels in natural language are normalized to Magento option values by the server.

## 11. Auto-navigation in headless clients

`agenticCommerceConfig.auto_navigation` reports the store configuration. Returned actions can contain `auto_navigate: true` only for explicit navigation intentions judged unambiguous by the server.

A PWA should still own routing:

```js
const action = turn.actions.find(a => a.auto_navigate);
if (turnConfig.auto_navigation && action) {
  router.push(mapMagentoUrlToPwaRoute(action.url));
}
```

Do not automatically replay navigation from persisted history. If a category/CMS search has several choices, render them for the shopper instead.

## 12. React-style state example

```js
const input = {
  message,
  conversation_id: agentState.conversationId || null,
  client_id: agentState.clientId || null,
  cart_id: isCustomer ? null : guestMaskedCartId,
  query_phrase: catalogState.search || '',
  current_filters: catalogState.filters
};

const data = await graphql(AGENT_CHAT_MUTATION, { input }, customerToken);
const turn = data.agenticCommerceChat;

setAgentState(s => ({
  ...s,
  clientId: turn.client_id,
  conversationId: turn.conversation_id
}));

setCatalogState(s => ({
  ...s,
  search: turn.query_phrase,
  filters: turn.filters,
  products: turn.products
}));
```

## 13. PWA Studio / custom React checklist

- Persist `client_id` in durable client storage; store the Magento customer token according to your application's security policy.
- Keep `conversation_id` distinct from `client_id`.
- Never send a `customer_id` or previous-product authority from the client.
- Use only masked guest cart IDs for anonymous headless cart calls.
- Continue the prior `client_id` after login to claim that client's guest history.
- Query `agenticCommerceProductOptions` and send validated `selections` to `agenticCommerceAddToCart` when the option group is marked `chat_supported`. For unsafe/unsupported option types such as required file uploads, hand off to the native product UI.
- Treat agent actions as routing intents and translate Magento URLs if the PWA route map differs.
- Keep conversation/cart GraphQL responses shopper-specific; do not put them in a shared public cache.
- Synchronize `query_phrase` and `current_filters` with your PWA catalog state when you want chat and normal filters to operate on the same state.

## 14. Retention

Conversation cleanup runs from Magento cron per store view. Guest and customer histories have separate Admin-configured retention windows. Deleting a conversation cascades to its stored messages through declarative schema constraints.


## 15. Agentic capabilities manifest

```graphql
query {
  agenticCommerceCapabilities {
    module
    module_version
    api_version
    enabled
    search_provider
    channels
    features
    tools {
      name
      category
      risk_level
      mutates_state
      requires_customer
    }
  }
}
```

This is useful for PWA/React clients that need progressive feature detection.

## 16. Coupons

```graphql
mutation ApplyAgentCoupon($input: AgenticCommerceApplyCouponInput!) {
  agenticCommerceApplyCoupon(input: $input) {
    assistant_message
    coupon_applied
    cart { coupon_code discount_amount formatted_discount_amount grand_total formatted_grand_total }
  }
}
```

For guests include the masked Magento `cart_id`; authenticated customers use the customer-token context. The conversational planner only applies a coupon when the shopper explicitly supplied the code.

## 17. Wishlist (authenticated customers)

```graphql
query MyAgentWishlist($clientId: String) {
  agenticCommerceWishlist(client_id: $clientId) {
    items_count
    items { item_id sku name url image price formatted_price }
  }
}

mutation SaveAgentProduct($input: AgenticCommerceAddToWishlistInput!) {
  agenticCommerceAddToWishlist(input: $input) {
    assistant_message
    wishlist { items_count items { sku name url } }
  }
}
```

Wishlist operations require a real Magento customer token. `customer_id` is never accepted as an input.

## 18. Orders and tracking metadata (authenticated customers)

```graphql
query AgentOrders($clientId: String) {
  agenticCommerceOrders(client_id: $clientId, limit: 5) {
    number status status_label created_at grand_total formatted_grand_total
    tracking { carrier number }
  }
}
```

Exact order lookup uses `agenticCommerceOrder(number: ...)` and is scoped to the authenticated customer and current store view.

## 19. Optional Adobe Live Search / Catalog Service

Set **Stores > Configuration > General > Agentic Commerce > Catalog & Attributes > Search Provider** to Adobe Live Search, then configure the SaaS environment ID and API key. The adapter calls Adobe Catalog Service `productSearch`. If the call fails, the module falls back to the native Magento search adapter for availability.

For headless Adobe Live Search implementations, storefront behavioral event collection is still the responsibility of the storefront. The module's browser event bridge can be mapped into an Adobe event collector or your analytics layer.

## 20. Browser event contract for Hyvä/custom storefronts

The bundled UI emits:

```text
haerriz:agentic:state
haerriz:agentic:turn-start
haerriz:agentic:turn-complete
haerriz:agentic:turn-error
haerriz:agentic:product-click
haerriz:agentic:product-action
```

Example:

```js
assistantRoot.addEventListener('haerriz:agentic:product-click', (event) => {
  const { sku, position } = event.detail;
  // Map this into your PWA/Adobe storefront event collection.
});
```

Do not treat these browser events as authorization. They are telemetry/integration hooks only; all commerce mutations still execute through the server-side tools/GraphQL APIs.


## 21. Inventory, requested quantity and stock privacy

Query authoritative storefront availability without exposing MSI source-level inventory:

```graphql
query AgentInventory($sku: String!, $qty: Float!) {
  agenticCommerceInventory(sku: $sku, requested_qty: $qty) {
    sku
    is_salable
    requested_qty
    requested_qty_salable
    min_sale_qty
    max_sale_qty
    qty_increments
    backorderable
    low_stock
    quantity_exposed
    salable_qty
    status
    message
  }
}
```

`salable_qty` is nullable and is only populated when the Magento Admin setting permits exact quantity exposure. The API intentionally does not expose MSI `source_code`, source items or per-warehouse quantities.

Batch comparison is available through `agenticCommerceInventories(skus: [...])`.

## 22. Configurable variant availability

Resolve a shopper selection against the real configurable child SKU:

```graphql
query VariantAvailability($sku: String!, $selections: [AgenticCommerceProductOptionSelectionInput!]) {
  agenticCommerceVariantAvailability(sku: $sku, requested_qty: 1, selections: $selections) {
    parent_sku
    complete
    matched_sku
    selected { code value }
    missing_attributes
    assistant_message
    candidates {
      sku
      name
      attributes { code value }
      inventory { is_salable quantity_exposed salable_qty message }
      price { final_price formatted_final_price }
    }
  }
}
```

Example variables:

```json
{
  "sku": "SHIRT-CONFIG",
  "selections": [
    {"code": "color", "values": ["Red"]},
    {"code": "size", "values": ["XL"]}
  ]
}
```

The server resolves labels/values against Magento configurable metadata and bounds candidate expansion. Ambiguous combinations remain unresolved rather than guessing a child SKU.

## 23. Product option-aware add to cart

Discover supported option groups first:

```graphql
query ProductOptions($sku: String!) {
  agenticCommerceProductOptions(sku: $sku) {
    sku
    type
    requires_options
    chat_supported
    groups {
      code
      attribute_code
      label
      input_mode
      required
      multiple
      chat_supported
      values { value label sku price price_type }
    }
  }
}
```

Then submit exact selections:

```graphql
mutation AgentAddConfigured($input: AgenticCommerceAddToCartInput!) {
  agenticCommerceAddToCart(input: $input) {
    added
    requires_options
    assistant_message
    cart { items_count formatted_grand_total items { sku name qty options { code value } } }
  }
}
```

```json
{
  "input": {
    "sku": "SHIRT-CONFIG",
    "quantity": 1,
    "cart_id": "MASKED_GUEST_CART_ID",
    "client_id": "OPAQUE_CLIENT_ID",
    "selections": [
      {"code": "color", "values": ["Red"]},
      {"code": "size", "values": ["XL"]}
    ]
  }
}
```

The server validates the selection and builds the Magento buy request. Unsupported/unsafe required option types return a product-UI handoff instead of guessing.

## 24. Checkout state machine

Read the checkout state:

```graphql
query AgentCheckout($cartId: String, $clientId: String) {
  agenticCommerceCheckout(cart_id: $cartId, client_id: $clientId) {
    ready
    is_virtual
    missing
    guest_email
    shipping_method
    payment_method
    requirements { code label satisfied }
    available_shipping_methods { carrier_code method_code carrier_title method_title amount formatted_amount available }
    available_payment_methods { code title }
    cart { items_count formatted_grand_total }
  }
}
```

Headless clients can then set guest email, shipping/billing address, shipping method and payment method through the dedicated mutations in `etc/schema.graphqls`. Payment input is method-code only; card/PAN/CVV/password fields are intentionally absent from this module's GraphQL contract.

## 25. Saved customer addresses in headless checkout

Authenticated shoppers can use an already-owned Magento address without resending PII:

```graphql
mutation UseSavedShipping($input: AgenticCommerceUseSavedAddressInput!) {
  agenticCommerceUseSavedShippingAddress(input: $input) {
    ready
    missing
    shipping_address { id firstname lastname city postcode country_id }
    available_shipping_methods { carrier_code method_code formatted_amount }
  }
}
```

The resolver reloads the address and verifies ownership against the authenticated Magento customer before applying it to the quote. `agenticCommerceUseSavedBillingAddress` follows the same rule.

## 26. Customer profile/address management

Authenticated PWA clients can query `agenticCommerceCustomerProfile` and `agenticCommerceCustomerAddresses`, and can use:

- `agenticCommerceUpdateCustomerProfile`
- `agenticCommerceSaveCustomerAddress`
- `agenticCommerceDeleteCustomerAddress`

Customer identity is always taken from Magento authentication context. There is no customer-ID selector in these mutations.

## 27. Confirmation-gated order placement

The agent prepares a consequential action and returns an opaque confirmation token. The final mutation is:

```graphql
mutation ConfirmAgentAction($input: AgenticCommerceConfirmActionInput!) {
  agenticCommerceConfirmAction(input: $input) {
    placed
    assistant_message
    order { number status status_label grand_total formatted_grand_total }
  }
}
```

The server revalidates identity, quote state and the confirmation fingerprint before executing the action, and confirmation tokens are single-use.

## 28. Product experience aggregation

For a PDP-style agent panel, one query can retrieve a composed storefront experience:

```graphql
query ProductExperience($sku: String!) {
  agenticCommerceProductExperience(sku: $sku, requested_qty: 1, review_limit: 3) {
    product { sku name url image formatted_price custom_attributes { code label value } }
    short_description
    description
    categories { id name url }
    inventory { is_salable quantity_exposed salable_qty message }
    price { regular_price final_price discount_percent formatted_final_price }
    options { type requires_options chat_supported groups { code label input_mode required chat_supported } }
    reviews { total_count items { title detail nickname created_at } }
    assistant_message
  }
}
```

This keeps product, price, inventory, options and review context under the same trusted customer/store context.


## 29. Product Intelligence

### Product content / description / media

```graphql
query AgentProductContent($sku: String!) {
  agenticCommerceProductContent(sku: $sku) {
    product { sku name url image formatted_price }
    short_description
    description
    highlights
    specifications { code label value }
    media_gallery { url label position }
    assistant_message
  }
}
```

Use this for prompts such as `describe this product`, `show its specifications`, or `show the images of the second product`. The server returns a bounded shopper-safe projection; executable/non-shopper HTML bodies are removed from description text.

### Grounded product Q&A

```graphql
query AgentProductQuestion($sku: String!, $question: String!) {
  agenticCommerceProductQuestion(sku: $sku, question: $question) {
    sku
    status
    answer
    evidence { source text }
  }
}
```

`status` distinguishes evidence-backed answers from `not_stated`. A missing claim is not automatically interpreted as a negative product property.

### Rich comparison

```graphql
query AgentProductComparison(
  $skus: [String!]!,
  $focus: [String!],
  $goal: String
) {
  agenticCommerceProductComparison(skus: $skus, focus: $focus, goal: $goal) {
    goal
    products { sku name url image formatted_price }
    rows {
      key
      label
      values { sku value }
    }
    similarities
    differences
    goal_assessment { sku name score evidence }
    assistant_message
  }
}
```

Example variables:

```json
{
  "skus": ["COURSE-A", "COURSE-B"],
  "focus": ["description", "attributes", "price", "inventory", "reviews"],
  "goal": "pediatric training"
}
```

Comparison is bounded to four products. `goal_assessment` is a match against explicit catalog evidence, not an objective quality ranking.

### REST equivalents

```text
GET  /rest/V1/agentic-commerce/product/{sku}/intelligence
POST /rest/V1/agentic-commerce/product/question
POST /rest/V1/agentic-commerce/product/compare
```

The REST facade delegates to the same Product Intelligence services as GraphQL/chat.

## 30. PWA Studio / Venia integration example

Do not duplicate Magento business logic in Venia. Treat the module as a GraphQL capability backend and compose it into the existing Apollo state.

```js
import { gql, useLazyQuery } from '@apollo/client';

const PRODUCT_COMPARISON = gql`
  query ProductComparison($skus: [String!]!, $focus: [String!], $goal: String) {
    agenticCommerceProductComparison(skus: $skus, focus: $focus, goal: $goal) {
      products { sku name image formatted_price }
      rows { key label values { sku value } }
      similarities
      differences
      goal_assessment { sku name score evidence }
      assistant_message
    }
  }
`;

export function useAgenticComparison() {
  return useLazyQuery(PRODUCT_COMPARISON, {
    fetchPolicy: 'network-only'
  });
}
```

Recommended Venia/PWA integration rules:

- keep `client_id` and `conversation_id` separate;
- pass the Magento customer token through the existing authenticated GraphQL transport, never a customer ID;
- keep guest quote authority as Magento's masked cart ID;
- use direct mutations/actions when a UI control already knows the exact SKU, option selection, cart item, address or shipping/payment method;
- use agent chat for natural-language discovery/orchestration;
- map `auto_navigate` to the PWA route table rather than blindly assigning Magento PHP-storefront URLs;
- render Product Intelligence evidence and `not_stated` distinctly so the UI does not imply unsupported certainty;
- do not shared-cache customer/cart/conversation responses.


## Commerce Brain capability discovery

External AI configuration remains server-side. A headless client can discover non-secret runtime metadata:

```graphql
query AgentCapabilities {
  agenticCommerceCapabilities {
    module_version
    api_version
    ai_provider
    ai_fallback_providers
    external_data_scope
    reasoning_mode
    adaptive_learning
    response_synthesis
    channels
    features
  }
}
```

API keys and provider credentials are never returned.

## Shopper feedback

When enabled, a headless client can submit a thumbs-up/down signal only for a tool that actually executed in the owned conversation:

```graphql
mutation AgentFeedback($input: AgenticCommerceFeedbackInput!) {
  agenticCommerceFeedback(input: $input) { accepted rating assistant_message }
}
```

Example variables:

```json
{
  "input": {
    "conversation_id": "PUBLIC-CONVERSATION-ID",
    "client_id": "OPAQUE-GUEST-ID",
    "message": "customer care details",
    "tool_name": "get_store_information",
    "rating": 1
  }
}
```

The server validates conversation ownership and prior tool audit before learning from the feedback.
