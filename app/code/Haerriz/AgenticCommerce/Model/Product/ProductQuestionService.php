<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Product;

use Magento\Framework\Exception\LocalizedException;
use Haerriz\AgenticCommerce\Model\Config;

/**
 * Conservative product Q&A over Magento storefront data.
 *
 * A missing literal evidence match is reported as "not found in the supplied storefront information",
 * never converted into a fabricated negative claim.
 */
class ProductQuestionService
{
    private const STOP = [
        'about','after','also','are','can','could','does','for','from','have','how','into','its','may','product',
        'that','the','their','there','this','what','when','where','which','with','would','you','your','please','tell',
        'include','includes','including','support','supports','suitable','based','description','information','details','use','used','using','work','works'
    ];

    public function __construct(private ProductContentService $content, private Config $config) {}

    public function answer(string $sku, string $question, int $storeId, ?int $customerGroupId = null): array
    {
        $question = trim($question);
        if ($question === '') {
            throw new LocalizedException(__('A product question is required.'));
        }
        $data = $this->content->get($sku, $storeId, $customerGroupId);
        $tokens = $this->tokens($question);
        $evidence = [];

        $sources = [];
        if ($data['short_description'] !== '') $sources[] = ['source'=>'short_description','text'=>$data['short_description']];
        if ($data['description'] !== '') $sources[] = ['source'=>'description','text'=>$data['description']];
        foreach ((array)$data['specifications'] as $spec) {
            $sources[] = ['source'=>'specification','text'=>(string)$spec['label'] . ': ' . (string)$spec['value']];
        }
        foreach ((array)$data['highlights'] as $highlight) {
            $sources[] = ['source'=>'highlight','text'=>(string)$highlight];
        }

        foreach ($sources as $source) {
            $sentences = $source['source'] === 'description' || $source['source'] === 'short_description'
                ? $this->content->sentences((string)$source['text'])
                : [(string)$source['text']];
            foreach ($sentences as $sentence) {
                $score = $this->score($sentence, $tokens);
                if ($score <= 0) continue;
                $evidence[] = ['source'=>(string)$source['source'],'text'=>mb_substr($sentence, 0, 650),'score'=>$score];
            }
        }
        usort($evidence, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: (mb_strlen($a['text']) <=> mb_strlen($b['text'])));
        $evidence = array_slice($evidence, 0, $this->config->getMaxQaEvidence($storeId));
        foreach ($evidence as &$row) unset($row['score']);
        unset($row);

        $name = (string)($data['product']['name'] ?? $sku);
        if ($evidence !== []) {
            $answer = (string)__('Based on the storefront information for %1: %2', $name, (string)$evidence[0]['text']);
            $status = 'evidence_found';
        } else {
            $answer = (string)__('I could not find evidence for that in %1’s storefront description or approved specifications. That does not necessarily mean the product lacks it; the catalog information may simply not state it.', $name);
            $status = 'not_stated';
        }

        return [
            'sku' => (string)($data['product']['sku'] ?? $sku),
            'name' => $name,
            'question' => mb_substr($question, 0, 1000),
            'status' => $status,
            'answer' => $answer,
            'evidence' => $evidence,
            'assistant_message' => $answer,
        ];
    }

    private function score(string $text, array $tokens): int
    {
        if ($tokens === []) return 0;
        $haystack = mb_strtolower($text);
        $score = 0;
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 3) continue;
            if (str_contains($haystack, $token)) {
                $score += mb_strlen($token) >= 7 ? 3 : 2;
            }
        }
        return $score;
    }

    private function tokens(string $question): array
    {
        $question = mb_strtolower($question);
        $parts = preg_split('/[^\p{L}\p{N}_-]+/u', $question) ?: [];
        $parts = array_values(array_unique(array_filter($parts, static function (string $token): bool {
            return mb_strlen($token) >= 3 && !in_array($token, self::STOP, true);
        })));
        return array_slice($parts, 0, 16);
    }
}
