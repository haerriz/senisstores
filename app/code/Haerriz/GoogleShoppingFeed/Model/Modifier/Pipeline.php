<?php
namespace Haerriz\GoogleShoppingFeed\Model\Modifier;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\ModifierPipelineInterface;
use Magento\Catalog\Model\Product;

class Pipeline implements ModifierPipelineInterface
{
    private $legacyPool;

    public function __construct(Pool $legacyPool)
    {
        $this->legacyPool = $legacyPool;
    }

    public function apply($value, array $modifiers, Product $product, FeedProfileInterface $profile)
    {
        $this->validate($modifiers);
        foreach ($modifiers as $modifier) {
            $code = (string)($modifier['code'] ?? '');
            $argument = $modifier['value'] ?? $modifier['argument'] ?? null;
            switch ($code) {
                case 'strip_html':
                    $value = strip_tags((string)$value);
                    break;
                case 'normalize_symbols':
                    $value = preg_replace('/[^\p{L}\p{N}\p{P}\p{Zs}\r\n\t]/u', '', (string)$value);
                    break;
                case 'normalize_whitespace':
                    $value = trim((string)preg_replace('/\s+/u', ' ', (string)$value));
                    break;
                case 'truncate':
                    $value = mb_substr((string)$value, 0, max(0, (int)$argument));
                    break;
                case 'prepend':
                    $value = (string)$argument . (string)$value;
                    break;
                case 'append':
                    $value = (string)$value . (string)$argument;
                    break;
                case 'upper':
                    $value = mb_strtoupper((string)$value);
                    break;
                case 'lower':
                    $value = mb_strtolower((string)$value);
                    break;
                case 'title':
                    $value = mb_convert_case((string)$value, MB_CASE_TITLE);
                    break;
                case 'replace':
                    $value = str_replace(
                        (string)($modifier['search'] ?? ''),
                        (string)($modifier['replace'] ?? ''),
                        (string)$value
                    );
                    break;
                case 'regex_replace':
                    $value = preg_replace(
                        (string)($modifier['pattern'] ?? ''),
                        (string)($modifier['replace'] ?? ''),
                        (string)$value
                    );
                    break;
                case 'round':
                    $precision = (int)($modifier['precision'] ?? 2);
                    $mode = (string)($modifier['mode'] ?? 'nearest');
                    $factor = 10 ** $precision;
                    $number = (float)$value * $factor;
                    $value = ($mode === 'up' ? ceil($number) : ($mode === 'down' ? floor($number) : round($number)))
                        / $factor;
                    break;
                case 'enum_map':
                    $map = (array)($modifier['map'] ?? []);
                    $value = array_key_exists((string)$value, $map) ? $map[(string)$value] : $value;
                    break;
                case 'date_format':
                    $timestamp = strtotime((string)$value);
                    $value = $timestamp === false ? $value : date((string)($argument ?: 'c'), $timestamp);
                    break;
                case 'default':
                    if ($value === null || $value === '') {
                        $value = $argument;
                    }
                    break;
                case 'legacy':
                    $value = $this->legacyPool->apply($value, (string)$argument, $product);
                    break;
            }
        }
        return $value;
    }

    public function validate(array $modifiers)
    {
        $supported = [
            'strip_html',
            'normalize_symbols',
            'normalize_whitespace',
            'truncate',
            'prepend',
            'append',
            'upper',
            'lower',
            'title',
            'replace',
            'regex_replace',
            'round',
            'enum_map',
            'date_format',
            'default',
            'legacy',
        ];
        foreach ($modifiers as $modifier) {
            $code = (string)($modifier['code'] ?? '');
            if (!in_array($code, $supported, true)) {
                throw new \InvalidArgumentException('Unsupported modifier: ' . $code);
            }
            if ($code === 'regex_replace') {
                $pattern = (string)($modifier['pattern'] ?? '');
                set_error_handler(static function () {
                });
                $valid = $pattern !== '' && preg_match($pattern, '') !== false;
                restore_error_handler();
                if (!$valid) {
                    throw new \InvalidArgumentException('Invalid regular expression modifier.');
                }
            }
            if ($code === 'enum_map' && !is_array($modifier['map'] ?? null)) {
                throw new \InvalidArgumentException('Enum map modifier requires a map object.');
            }
        }
    }
}
