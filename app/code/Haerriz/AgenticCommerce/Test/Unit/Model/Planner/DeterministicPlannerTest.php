<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Planner;

use Haerriz\AgenticCommerce\Model\AttributeMetadataService;
use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Intent\KnowledgeIntentMatcher;
use Haerriz\AgenticCommerce\Model\Knowledge\KnowledgeService;
use Haerriz\AgenticCommerce\Model\Planner\DeterministicPlanner;
use PHPUnit\Framework\TestCase;

class DeterministicPlannerTest extends TestCase
{
    private function subject(): DeterministicPlanner
    {
        $metadata = $this->createMock(AttributeMetadataService::class);
        $metadata->method('getMetadata')->willReturn([
            [
                'code' => 'color', 'label' => 'Color', 'frontend_input' => 'select',
                'options' => [['value' => '49', 'label' => 'Black'], ['value' => '50', 'label' => 'Blue']],
            ],
            [
                'code' => 'brand', 'label' => 'Brand', 'frontend_input' => 'select',
                'options' => [['value' => '1', 'label' => 'Puma'], ['value' => '2', 'label' => 'Nike']],
            ],
        ]);
        $config = $this->createMock(Config::class);
        $config->method('getPageSize')->willReturn(8);
        return new DeterministicPlanner(
            $metadata,
            $config,
            new KnowledgeIntentMatcher($this->createMock(KnowledgeService::class))
        );
    }

    public function testDonationQuestionUsesCmsKnowledgeTool(): void
    {
        $plan = $this->subject()->plan('can i donate');
        self::assertSame('answer_store_question', $plan['tools'][0]['name']);
    }

    public function testShowOneCostliestIsFreshSearchNotRecentReference(): void
    {
        $plan = $this->subject()->plan('show 1 premium product course but costliest', [
            'recent_products' => [['sku' => 'OLD-1']],
        ]);
        self::assertSame('search_products', $plan['tools'][0]['name']);
        self::assertSame(1, $plan['tools'][0]['arguments']['page_size']);
        self::assertSame(['attribute' => 'price', 'direction' => 'DESC'], $plan['tools'][0]['arguments']['sort']);
        self::assertSame('premium course', $plan['tools'][0]['arguments']['phrase']);
    }

    public function testLatestProductsUsesCreatedAtDescending(): void
    {
        $plan = $this->subject()->plan('latest products');
        self::assertSame('search_products', $plan['tools'][0]['name']);
        self::assertSame(['attribute' => 'created_at', 'direction' => 'DESC'], $plan['tools'][0]['arguments']['sort']);
        self::assertSame('', $plan['tools'][0]['arguments']['phrase']);
    }

    public function testCommonShoppingTyposUseDeterministicCatalogIntent(): void
    {
        $plan = $this->subject()->plan('shwo cheepest prodcts');
        self::assertSame('search_products', $plan['tools'][0]['name']);
        self::assertSame('', $plan['tools'][0]['arguments']['phrase']);
        self::assertSame(['attribute' => 'price', 'direction' => 'ASC'], $plan['tools'][0]['arguments']['sort']);
    }

    public function testIncorrectGrammarCheapProductsUsesPriceSort(): void
    {
        $plan = $this->subject()->plan('products cheap show');
        self::assertSame('search_products', $plan['tools'][0]['name']);
        self::assertSame('', $plan['tools'][0]['arguments']['phrase']);
        self::assertSame(['attribute' => 'price', 'direction' => 'ASC'], $plan['tools'][0]['arguments']['sort']);
    }

    public function testExplicitLatestProductsDoesNotReuseFailedQuery(): void
    {
        $plan = $this->subject()->plan('Show latest products', ['query_phrase' => 'failed old query']);
        self::assertSame('', $plan['tools'][0]['arguments']['phrase']);
        self::assertSame(['attribute' => 'created_at', 'direction' => 'DESC'], $plan['tools'][0]['arguments']['sort']);
    }

    public function testBestSellingProductUsesBestsellerSortWithoutLiteralPhrase(): void
    {
        $plan = $this->subject()->plan("what's the best selling product?");
        self::assertSame('search_products', $plan['tools'][0]['name']);
        self::assertSame('', $plan['tools'][0]['arguments']['phrase']);
        self::assertSame(['attribute' => 'bestseller', 'direction' => 'DESC'], $plan['tools'][0]['arguments']['sort']);
    }

    public function testComparesRecentProductsByOrdinal(): void
    {
        $plan = $this->subject()->plan('compare the first and third products', ['recent_products' => [
            ['sku' => 'A'], ['sku' => 'B'], ['sku' => 'C'],
        ]]);
        self::assertSame('compare_recent_products', $plan['tools'][0]['name']);
        self::assertSame([1, 3], $plan['tools'][0]['arguments']['indexes']);
    }

    public function testOpenThirdUsesRecentProductReference(): void
    {
        $plan = $this->subject()->plan('open the third product', ['recent_products' => [
            ['sku' => 'A'], ['sku' => 'B'], ['sku' => 'C'],
        ]]);
        self::assertSame('open_recent_product', $plan['tools'][0]['name']);
        self::assertSame(3, $plan['tools'][0]['arguments']['index']);
    }

    public function testAddLastShownProductUsesServerRecentCount(): void
    {
        $plan = $this->subject()->plan('add the last shown product to my cart', ['recent_products' => [
            ['sku' => 'A'], ['sku' => 'B'],
        ]]);
        self::assertSame('add_recent_product_to_cart', $plan['tools'][0]['name']);
        self::assertSame(2, $plan['tools'][0]['arguments']['index']);
    }

    public function testExplicitSkuAddUsesExactSkuTool(): void
    {
        $plan = $this->subject()->plan('add product SKU COURSE-001 to my cart');
        self::assertSame('add_product_to_cart', $plan['tools'][0]['name']);
        self::assertSame('COURSE-001', $plan['tools'][0]['arguments']['sku']);
    }

    public function testCustomOptionExclusion(): void
    {
        $plan = $this->subject()->plan('no Puma shoes');
        self::assertSame('search_products', $plan['tools'][0]['name']);
        $brand = array_values(array_filter(
            $plan['tools'][0]['arguments']['filters'],
            static fn(array $filter): bool => $filter['attribute'] === 'brand'
        ))[0];
        self::assertSame('nin', $brand['condition']);
        self::assertSame(['1'], $brand['values']);
    }

    public function testUpdatesCartItemQuantityFromServerCartContext(): void
    {
        $planner = $this->subject();
        $result = $planner->plan('set the second cart item quantity to 3', [
            'cart_items' => [['item_id' => 11], ['item_id' => 22]],
        ]);
        self::assertSame('update_cart_item', $result['tools'][0]['name']);
        self::assertSame(2, $result['tools'][0]['arguments']['index']);
        self::assertSame(3.0, $result['tools'][0]['arguments']['quantity']);
    }

    public function testClearsCurrentCart(): void
    {
        $planner = $this->subject();
        $result = $planner->plan('clear my cart', ['cart_items' => [['item_id' => 11]]]);
        self::assertSame('clear_cart', $result['tools'][0]['name']);
    }

    public function testAppliesExplicitCouponCode(): void
    {
        $plan = $this->subject()->plan('apply coupon SAVE10');
        self::assertSame('apply_coupon', $plan['tools'][0]['name']);
        self::assertSame('SAVE10', $plan['tools'][0]['arguments']['code']);
    }

    public function testReadsWishlist(): void
    {
        $plan = $this->subject()->plan('show my wishlist');
        self::assertSame('get_wishlist', $plan['tools'][0]['name']);
    }

    public function testAddsLastRecentProductToWishlist(): void
    {
        $plan = $this->subject()->plan('save the last shown product to my wishlist', [
            'recent_products' => [['sku' => 'A'], ['sku' => 'B']],
        ]);
        self::assertSame('add_recent_product_to_wishlist', $plan['tools'][0]['name']);
        self::assertSame(2, $plan['tools'][0]['arguments']['index']);
    }

    public function testReadsRecentOrders(): void
    {
        $plan = $this->subject()->plan('show my recent orders');
        self::assertSame('get_recent_orders', $plan['tools'][0]['name']);
    }

    public function testReadsExactOrderStatus(): void
    {
        $plan = $this->subject()->plan('what is the status of order #100000123');
        self::assertSame('get_order', $plan['tools'][0]['name']);
        self::assertSame('100000123', $plan['tools'][0]['arguments']['order_number']);
    }

    public function testRecommendationsUseServerRecentReference(): void
    {
        $plan = $this->subject()->plan('show recommendations similar to the second product', [
            'recent_products' => [['sku' => 'A'], ['sku' => 'B']],
        ]);
        self::assertSame('get_recommendations', $plan['tools'][0]['name']);
        self::assertSame(2, $plan['tools'][0]['arguments']['index']);
    }

    public function testStorePolicyQuestionUsesCmsKnowledgeTool(): void
    {
        $plan = $this->subject()->plan('what is your return policy?');
        self::assertSame('answer_store_question', $plan['tools'][0]['name']);
    }


    public function testContactNumberUsesStoreInformationTool(): void
    {
        $plan = $this->subject()->plan('contact number');
        self::assertSame('get_store_information', $plan['tools'][0]['name']);
    }

    /** @dataProvider storeIdentityQuestions */
    public function testStoreIdentityQuestionsUseAuthoritativeStoreInformationTool(string $question): void
    {
        $plan = $this->subject()->plan($question);
        self::assertSame('get_store_information', $plan['tools'][0]['name']);
    }

    public function storeIdentityQuestions(): array
    {
        return [
            'assistant and website' => ['who are you? What website is this?'],
            'owner' => ['who is the owner of the site?'],
            'capabilities' => ['what can you do?'],
            'bounded typo' => ['who is owner of this webiste?'],
        ];
    }

    public function testArithmeticIsDeclinedWithoutCatalogSearch(): void
    {
        $plan = $this->subject()->plan('whats 2 + 2');
        self::assertSame([], $plan['tools']);
        self::assertStringContainsString('storefront', $plan['assistant_message']);
    }

    public function testWebsitePurposeUsesCmsKnowledge(): void
    {
        $plan = $this->subject()->plan('what is this website about?');
        self::assertSame('answer_store_question', $plan['tools'][0]['name']);
    }

    public function testShopAddressUsesStoreInformation(): void
    {
        $plan = $this->subject()->plan('address of the shop');
        self::assertSame('get_store_information', $plan['tools'][0]['name']);
    }

    public function testNaturalAddressQuestionUsesStoreInformation(): void
    {
        $plan = $this->subject()->plan("what's the address?");
        self::assertSame('get_store_information', $plan['tools'][0]['name']);
    }

    public function testOpenEndedDefinitionUsesCmsKnowledgeBeforeCatalogSearch(): void
    {
        $plan = $this->subject()->plan("what's blended learning");
        self::assertSame('answer_store_question', $plan['tools'][0]['name']);
    }

    public function testCompareFirstTwoCollectiveReference(): void
    {
        $plan = $this->subject()->plan('compare the first two', ['recent_products' => [
            ['sku' => 'A'], ['sku' => 'B'], ['sku' => 'C'],
        ]]);
        self::assertSame('compare_recent_products', $plan['tools'][0]['name']);
        self::assertSame([1, 2], $plan['tools'][0]['arguments']['indexes']);
    }

    public function testCompareWithoutRecentProductsDoesNotFallBackToCatalogSearch(): void
    {
        $plan = $this->subject()->plan('compare the first two');
        self::assertSame([], $plan['tools']);
        self::assertNotSame('', $plan['assistant_message']);
    }

    public function testCheaperOptionsRefinesExistingQueryInsteadOfSearchingThoseWords(): void
    {
        $plan = $this->subject()->plan('show cheaper options', ['query_phrase' => 'pediatric emergency']);
        self::assertSame('search_products', $plan['tools'][0]['name']);
        self::assertSame('pediatric emergency', $plan['tools'][0]['arguments']['phrase']);
        self::assertSame(['attribute' => 'price', 'direction' => 'ASC'], $plan['tools'][0]['arguments']['sort']);
    }

    public function testLegacyProductNameAddResolvesAgainstServerRecentProducts(): void
    {
        $plan = $this->subject()->plan(
            'add this product to cart Pediatric Emergency Assessment Recognition and Stabilization Poster Set',
            ['recent_products' => [
                ['sku' => 'PEARS-POSTER', 'name' => 'Pediatric Emergency Assessment Recognition and Stabilization Poster Set'],
                ['sku' => 'OTHER', 'name' => 'Family and Friends CPR Student Manual'],
            ]]
        );
        self::assertSame('add_recent_product_to_cart', $plan['tools'][0]['name']);
        self::assertSame(1, $plan['tools'][0]['arguments']['index']);
    }

    public function testNaturalCatalogQuestionRemainsAProductSearch(): void
    {
        $plan = $this->subject()->plan('what courses do you have');
        self::assertSame('search_products', $plan['tools'][0]['name']);
        self::assertSame('courses', $plan['tools'][0]['arguments']['phrase']);
    }

    public function testCheapestProductsIsCatalogSortNotUnknownIntent(): void
    {
        $plan = $this->subject()->plan('show cheapest products');
        self::assertSame('search_products', $plan['tools'][0]['name']);
        self::assertSame(['attribute' => 'price', 'direction' => 'ASC'], $plan['tools'][0]['arguments']['sort']);
    }


    public function testExplicitCheapestProductsDoesNotReuseFailedQuery(): void
    {
        $plan = $this->subject()->plan('Show cheapest products', ['query_phrase' => 'failed old query']);
        self::assertSame('', $plan['tools'][0]['arguments']['phrase']);
        self::assertSame(['attribute' => 'price', 'direction' => 'ASC'], $plan['tools'][0]['arguments']['sort']);
    }

    public function testSingleRecentProductPronounResolvesInventory(): void
    {
        $plan = $this->subject()->plan('is this product in stock?', ['recent_products' => [
            ['sku' => 'A', 'name' => 'Example Product'],
        ]]);
        self::assertSame('get_inventory', $plan['tools'][0]['name']);
        self::assertSame(1, $plan['tools'][0]['arguments']['index']);
        self::assertSame('is this product in stock?', $plan['tools'][0]['arguments']['query']);
    }

    public function testPartialRecentProductNameResolvesInventorySafely(): void
    {
        $plan = $this->subject()->plan('how many Pediatric Emergency Poster are left?', ['recent_products' => [
            ['sku' => 'PEARS', 'name' => 'Pediatric Emergency Assessment Recognition and Stabilization Poster Set'],
            ['sku' => 'CPR', 'name' => 'Family and Friends CPR Student Manual'],
        ]]);
        self::assertSame('get_inventory', $plan['tools'][0]['name']);
        self::assertSame(1, $plan['tools'][0]['arguments']['index']);
    }

    public function testAmbiguousInventoryPronounDoesNotSearchCatalog(): void
    {
        $plan = $this->subject()->plan('how many are left?', ['recent_products' => [
            ['sku' => 'A', 'name' => 'Product A'], ['sku' => 'B', 'name' => 'Product B'],
        ]]);
        self::assertSame([], $plan['tools']);
        self::assertNotSame('', $plan['assistant_message']);
    }

    public function testPartialRecentProductNameResolvesPrice(): void
    {
        $plan = $this->subject()->plan('what is the price of CPR Manual?', ['recent_products' => [
            ['sku' => 'PEARS', 'name' => 'Pediatric Emergency Assessment Recognition and Stabilization Poster Set'],
            ['sku' => 'CPR', 'name' => 'Family and Friends CPR Student Manual'],
        ]]);
        self::assertSame('get_product_price', $plan['tools'][0]['name']);
        self::assertSame(2, $plan['tools'][0]['arguments']['index']);
    }

    public function testNegatedCartMutationIsNeverPlanned(): void
    {
        $plan = $this->subject()->plan('do not clear my cart');
        self::assertSame([], $plan['tools']);
        self::assertStringContainsString('not make that change', $plan['assistant_message']);
    }

    public function testSignInRoutesToNativeSecureLogin(): void
    {
        $plan = $this->subject()->plan('sign in');
        self::assertSame('navigate', $plan['tools'][0]['name']);
        self::assertSame('login', $plan['tools'][0]['arguments']['target']);
    }

    public function testRegistrationRoutesToNativeSecureAccountCreation(): void
    {
        $plan = $this->subject()->plan('create an account');
        self::assertSame('navigate', $plan['tools'][0]['name']);
        self::assertSame('register', $plan['tools'][0]['arguments']['target']);
    }

    public function testPasswordResetNeverCollectsPasswordInChat(): void
    {
        $plan = $this->subject()->plan('reset my password');
        self::assertSame('navigate', $plan['tools'][0]['name']);
        self::assertSame('forgot_password', $plan['tools'][0]['arguments']['target']);
        self::assertStringContainsString('password', strtolower($plan['assistant_message']));
    }


    public function testDescribesRecentProductByOrdinal(): void
    {
        $plan = $this->subject()->plan('describe the second product', ['recent_products' => [
            ['sku' => 'A', 'name' => 'Alpha Course'], ['sku' => 'B', 'name' => 'Beta Course'],
        ]]);
        self::assertSame('get_product_content', $plan['tools'][0]['name']);
        self::assertSame(2, $plan['tools'][0]['arguments']['index']);
    }

    public function testProductDescriptionResolvesPartialRecentName(): void
    {
        $plan = $this->subject()->plan('what is the description of CPR Manual?', ['recent_products' => [
            ['sku' => 'PEARS', 'name' => 'Pediatric Emergency Poster'],
            ['sku' => 'CPR', 'name' => 'Family and Friends CPR Manual'],
        ]]);
        self::assertSame('get_product_content', $plan['tools'][0]['name']);
        self::assertSame(2, $plan['tools'][0]['arguments']['index']);
    }

    public function testProductGalleryUsesContentTool(): void
    {
        $plan = $this->subject()->plan('show me images of the first product', ['recent_products' => [
            ['sku' => 'A'], ['sku' => 'B'],
        ]]);
        self::assertSame('get_product_content', $plan['tools'][0]['name']);
        self::assertSame(1, $plan['tools'][0]['arguments']['index']);
    }

    public function testDescriptionComparisonCarriesDescriptionFocus(): void
    {
        $plan = $this->subject()->plan('compare the first two based on description', ['recent_products' => [
            ['sku' => 'A'], ['sku' => 'B'], ['sku' => 'C'],
        ]]);
        self::assertSame('compare_recent_products', $plan['tools'][0]['name']);
        self::assertSame([1, 2], $plan['tools'][0]['arguments']['indexes']);
        self::assertSame(['description'], $plan['tools'][0]['arguments']['focus']);
    }

    public function testMixedDescriptionPriceStockComparisonUsesRichComparator(): void
    {
        $plan = $this->subject()->plan('compare the first two based on description, price and stock', ['recent_products' => [
            ['sku' => 'A'], ['sku' => 'B'], ['sku' => 'C'],
        ]]);
        self::assertSame('compare_recent_products', $plan['tools'][0]['name']);
        self::assertContains('description', $plan['tools'][0]['arguments']['focus']);
        self::assertContains('price', $plan['tools'][0]['arguments']['focus']);
        self::assertContains('inventory', $plan['tools'][0]['arguments']['focus']);
    }

    public function testExactSkuComparisonUsesRichComparator(): void
    {
        $plan = $this->subject()->plan('compare SKU ABC-1 and SKU XYZ-2 based on specs');
        self::assertSame('compare_products', $plan['tools'][0]['name']);
        self::assertSame(['ABC-1', 'XYZ-2'], $plan['tools'][0]['arguments']['skus']);
        self::assertSame(['attributes'], $plan['tools'][0]['arguments']['focus']);
    }

    public function testGroundedQuestionUsesProductQuestionTool(): void
    {
        $plan = $this->subject()->plan('does the second product mention pediatric use?', ['recent_products' => [
            ['sku' => 'A'], ['sku' => 'B'],
        ]]);
        self::assertSame('answer_product_question', $plan['tools'][0]['name']);
        self::assertSame(2, $plan['tools'][0]['arguments']['index']);
        self::assertSame('does the second product mention pediatric use?', $plan['tools'][0]['arguments']['question']);
    }

    public function testReviewLookupSupportsRecentProductReference(): void
    {
        $plan = $this->subject()->plan('show reviews for the second product', ['recent_products' => [
            ['sku' => 'A'], ['sku' => 'B'],
        ]]);
        self::assertSame('get_product_reviews', $plan['tools'][0]['name']);
        self::assertSame(2, $plan['tools'][0]['arguments']['index']);
    }

    public function testDescriptionWithoutReferenceAsksForProduct(): void
    {
        $plan = $this->subject()->plan('what is the description?');
        self::assertSame([], $plan['tools']);
        self::assertStringContainsString('Which product', $plan['assistant_message']);
    }


    public function testWhichIsBetterForGoalUsesGroundedRichComparison(): void
    {
        $plan = $this->subject()->plan('which one is better for pediatric training?', ['recent_products' => [
            ['sku' => 'A'], ['sku' => 'B'],
        ]]);
        self::assertSame('compare_recent_products', $plan['tools'][0]['name']);
        self::assertSame([1, 2], $plan['tools'][0]['arguments']['indexes']);
        self::assertSame('pediatric training', $plan['tools'][0]['arguments']['goal']);
    }

}
