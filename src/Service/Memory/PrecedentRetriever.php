<?php

declare(strict_types=1);

namespace App\Service\Memory;

use App\Entity\Prospect;
use App\Entity\ProspectDecision;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\InMemory\Store;
use Symfony\AI\Store\Retriever;

/**
 * Finds past team decisions about similar leads with Symfony AI Store: decisions are
 * indexed as documents in an in-memory Store, and a Retriever runs a vector query for
 * the lead being argued. Only the workspace's own decisions are ever indexed (callers
 * pass them in), and the index lives for one call.
 */
final class PrecedentRetriever
{
    /** Cosine similarity a decision must reach to count as "similar". */
    public const MIN_SIMILARITY = 0.2;

    public function __construct(private readonly LexicalVectorizer $vectorizer = new LexicalVectorizer())
    {
    }

    /**
     * @param list<ProspectDecision> $decisions candidate decisions from the same workspace
     *
     * @return list<array{decision: ProspectDecision, similarity: float}> most similar first
     */
    public function similar(Prospect $prospect, array $decisions, int $limit): array
    {
        $byId = [];
        $store = new Store();
        foreach ($decisions as $decision) {
            $byId[(int) $decision->getId()] = $decision;
            $store->add($this->vectorizer->vectorize(new TextDocument((int) $decision->getId(), $this->describe($decision), new Metadata(['decision' => (int) $decision->getId()]))));
        }
        if ([] === $byId) {
            return [];
        }

        $found = [];
        foreach ((new Retriever($store, $this->vectorizer))->retrieve($this->query($prospect), ['maxItems' => $limit, 'semanticRatio' => 1.0]) as $document) {
            $similarity = 1.0 - (float) $document->getScore();
            $decision = $byId[(int) $document->getId()] ?? null;
            if (null !== $decision && $similarity >= self::MIN_SIMILARITY) {
                $found[] = ['decision' => $decision, 'similarity' => round($similarity, 3)];
            }
        }

        return $found;
    }

    private function describe(ProspectDecision $decision): string
    {
        $example = $decision->getProspect();

        return implode(' ', [$example->getName(), $example->getCategory(), $example->getLocation(), $decision->getReason()]);
    }

    private function query(Prospect $prospect): string
    {
        return implode(' ', [$prospect->getName(), $prospect->getCategory(), $prospect->getLocation()]);
    }
}
