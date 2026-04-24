<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\VoteType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class VoteExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('vote_emoji', [$this, 'getVoteEmoji']),
        ];
    }

    /**
     * Filtre Twig pour afficher l'emoji d'un VoteType.
     */
    public function getVoteEmoji(string|VoteType $type): string
    {
        if (!$type instanceof VoteType) {
            $type = VoteType::tryFrom($type);
        }

        return $type?->emoji() ?? '❓';
    }
}