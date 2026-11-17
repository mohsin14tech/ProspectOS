<?php

declare(strict_types=1);

namespace App\Service\Memory;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Document\VectorizerInterface;

/**
 * A small, deterministic, key-free vectorizer for Symfony AI Store: words and word
 * pairs are hashed into a fixed number of dimensions (TF weighted, L2 normalised).
 *
 * It finds lexically similar text ("independent cafe in Islamabad" ≈ "Islamabad cafe and
 * bakery"); it is not a semantic embedding model. Any VectorizerInterface backed by a
 * real embedding model can replace it without changing the callers.
 */
final class LexicalVectorizer implements VectorizerInterface
{
    public const DIMENSIONS = 512;

    private const STOPWORDS = ['a', 'an', 'and', 'the', 'of', 'to', 'in', 'on', 'for', 'with', 'is', 'are', 'was', 'be', 'it', 'its', 'this', 'that', 'we', 'our', 'they', 'their', 'or', 'by', 'at', 'as', 'from', 'not', 'no', 'but', 'before', 'after', 'evidence', 'court', 'lead', 'leads', 'sample', 'synthetic', 'unspecified', 'verified', 'candidate', 'web', 'search'];

    public function vectorize(string|\Stringable|\Symfony\AI\Store\Document\DocumentInterface|array $values, array $options = []): Vector|VectorDocument|array
    {
        if (\is_array($values)) {
            return array_map(fn ($value) => $this->vectorize($value, $options), $values);
        }
        if ($values instanceof TextDocument) {
            return new VectorDocument($values->getId(), $this->embed($values->getContent()), $values->getMetadata());
        }

        return $this->embed((string) $values);
    }

    public function embed(string $text): Vector
    {
        $vector = array_fill(0, self::DIMENSIONS, 0.0);
        $words = $this->words($text);
        foreach ($words as $index => $word) {
            $vector[$this->bucket($word)] += 1.0;
            if (isset($words[$index + 1])) {
                $vector[$this->bucket($word.' '.$words[$index + 1])] += 0.5;
            }
        }
        // A constant feature keeps empty text a valid (non-zero) vector for cosine distance.
        $vector[0] += 0.01;

        $length = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));

        return new Vector(array_map(static fn (float $v): float => $v / $length, $vector));
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_filter($words, static fn (string $w): bool => mb_strlen($w) > 2 && !\in_array($w, self::STOPWORDS, true));

        // Crude plural folding so "cafes" meets "cafe" and "clinics" meets "clinic".
        return array_values(array_map(static fn (string $w): string => mb_strlen($w) > 4 && str_ends_with($w, 's') ? mb_substr($w, 0, -1) : $w, $words));
    }

    private function bucket(string $feature): int
    {
        return 1 + (crc32($feature) % (self::DIMENSIONS - 1));
    }
}
