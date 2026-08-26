<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Planner;

use Haerriz\AgenticCommerce\Api\PlannerRuleProviderInterface;
use Haerriz\AgenticCommerce\Model\Ai\ProviderInterface;
use Haerriz\AgenticCommerce\Model\Context\CommerceContextGraph;
use Haerriz\AgenticCommerce\Model\Learning\AdaptiveLearningService;
use Haerriz\AgenticCommerce\Model\Tool\ToolRegistry;
use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;

/**
 * Connected RizAI Commerce Brain.
 *
 * Planning is intentionally hybrid/neuro-symbolic:
 *   extension rules -> deterministic safety grammar -> proven routing memory -> optional external
 *   model -> local learned-weight neural intent model -> deterministic fallback.
 *
 * Consequential/deterministically locked tools remain owned by the deterministic safety kernel.
 */
class CompositePlanner implements PlannerInterface
{
    /** @param PlannerRuleProviderInterface[] $ruleProviders */
    public function __construct(
        private ProviderInterface $provider,
        private DeterministicPlanner $deterministicPlanner,
        private ToolRegistry $toolRegistry,
        private ToolPolicy $toolPolicy,
        private CommerceContextGraph $contextGraph,
        private AdaptiveLearningService $learning,
        private NeuralIntentPlanner $neuralPlanner,
        private MultiStepPlanEnhancer $enhancer,
        private array $ruleProviders = []
    ) {}

    public function plan(string $message, array $context = []): array
    {
        $context['commerce_graph'] = $this->contextGraph->build($context);

        foreach ($this->ruleProviders as $provider) {
            if (!$provider instanceof PlannerRuleProviderInterface) {
                continue;
            }
            $plan = $provider->plan($message, $context);
            if (is_array($plan) && (!empty($plan['tools']) || trim((string)($plan['assistant_message'] ?? '')) !== '')) {
                return $this->enhancer->enhance($message, $context, $plan);
            }
        }

        $deterministic = $this->deterministicPlanner->plan($message, $context);
        $first = (string)($deterministic['tools'][0]['name'] ?? '');
        if ($first !== '' && $this->toolPolicy->isDeterministicLocked($first)) {
            return $this->enhancer->enhance($message, $context, $deterministic);
        }

        // Exact, repeatedly proven safe read aliases outrank probabilistic inference.
        if ($first === '' && trim((string)($deterministic['assistant_message'] ?? '')) !== '') {
            $learned = $this->learning->learnedPlan($message, $context);
            if ($learned !== null) {
                return $this->enhancer->enhance($message, $context, $learned);
            }
        }

        // Merchant-selected external LLMs are allowed to improve non-locked planning. They still
        // return bounded tool proposals and cannot authorize a tool.
        $aiPlan = $this->provider->plan(
            $message,
            $context,
            $this->toolPolicy->filterDefinitions($this->toolRegistry->getDefinitions(), $context)
        );
        if (is_array($aiPlan) && !empty($aiPlan['tools'])) {
            return $this->enhancer->enhance($message, $context, $aiPlan);
        }

        // RizAI's local neural network is the API-key-free learned-weight fallback. To reduce risk,
        // it may replace only an unresolved/no-tool plan or the broad catalog-search fallback; it
        // never replaces a deterministically locked action.
        if ($first === '' || $first === 'search_products') {
            $neural = $this->neuralPlanner->plan($message, $context);
            if ($neural !== null && (!empty($neural['tools']) || trim((string)($neural['assistant_message'] ?? '')) !== '')) {
                return $this->enhancer->enhance($message, $context, $neural);
            }
        }

        return $this->enhancer->enhance($message, $context, $deterministic);
    }
}
