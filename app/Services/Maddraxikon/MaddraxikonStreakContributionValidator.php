<?php

namespace App\Services\Maddraxikon;

use App\Enums\MaddraxikonContributionType;
use App\Exceptions\MaddraxikonApiException;
use App\Models\MaddraxikonContribution;

final class MaddraxikonStreakContributionValidator
{
    public function reason(MaddraxikonContribution $source, array $revision, array $page): ?string
    {
        if (! array_key_exists('exists', $revision) || ! array_key_exists('exists', $page)) {
            throw new MaddraxikonApiException('Unvollständige Serienprüfung.');
        }
        if ($source->bot || $source->anonymous || $source->user_hidden) {
            return 'source_hidden_or_bot';
        }
        if (! in_array($source->namespace_id, config('maddraxikon.allowed_namespaces'), true)) {
            return 'namespace_excluded';
        }
        if (! $revision['exists'] || ! $page['exists']) {
            return 'revision_missing';
        }
        foreach (['user_hidden', 'sha1_hidden', 'text_hidden', 'suppressed'] as $flag) {
            if ($revision[$flag] ?? false) {
                return 'revision_hidden';
            }
        }
        if (! isset($revision['user_id'], $revision['page_id'], $revision['tags']) || ! is_array($revision['tags'])) {
            throw new MaddraxikonApiException('Unvollständige Revisionsidentität.');
        }
        if ($revision['user_id'] !== $source->wiki_user_id || $revision['page_id'] !== $source->page_id) {
            return 'revision_identity_mismatch';
        }
        // Current tags, not the immutable import snapshot, allow restoration.
        if (in_array('mw-reverted', $revision['tags'] ?? [], true)) {
            return 'revision_reverted';
        }
        if ($source->type === MaddraxikonContributionType::New) {
            if (! isset($page['namespace_id'], $page['redirect'])) {
                throw new MaddraxikonApiException('Unvollständige Artikelprüfung.');
            }
            if ($source->namespace_id !== (int) config('maddraxikon.article_namespace') || ($page['namespace_id'] ?? null) !== (int) config('maddraxikon.article_namespace')) {
                return 'not_an_article';
            }
            if ($page['redirect'] ?? false) {
                return 'page_is_redirect';
            }
        }

        return null;
    }
}
