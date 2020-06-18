<?php

namespace DDTrace;

use DDTrace\Http\Urls;

function _util_normalize_outgoing_path($uriPath)
{
    return _util_uri_apply_rules($uriPath, /* incoming */ false);
}

function _util_normalize_incoming_path($uriPath)
{
    return _util_uri_apply_rules($uriPath, /* incoming */ true);
}

/**
 * @param string $uriPath
 * @param boolean $incoming
 * @return string
 */
function _util_uri_apply_rules($uriPath, $incoming)
{
    if ($uriPath === '/' || $uriPath === '' || null === $uriPath) {
        return '/';
    }

    // We always expect leading slash
    if ($uriPath[0] !== '/') {
        $uriPath = '/' . $uriPath;
    }

    $fragmentRegexes = \ddtrace_config_path_fragment_regex();
    $incomingMappings = \ddtrace_config_path_mapping_incoming();
    $outgoingMappings = \ddtrace_config_path_mapping_outgoing();

    // We can now be in one of 3 cases:
    //   1) At least one among DD_TRACE_RESOURCE_URI_FRAGMENT_REGEX and DD_TRACE_RESOURCE_URI_MAPPING_INCOMING|OUTGOING
    //      is defined. Then ignore legacy DD_TRACE_RESOURCE_URI_MAPPING and apply *new normalization*.
    //   2) Only DD_TRACE_RESOURCE_URI_MAPPING is defined, then apply *legacy normalization* for backward compatibility.
    //   3) Nothing is defined, then apply *new normalization*.

    // DEPRECATED: Applying legacy normalization for backward compatibility if preconditions are matched.
    if (
        empty($fragmentRegexes)
            && empty($incomingMappings)
            && empty($outgoingMappings)
            && !empty($legacyMappings = getenv('DD_TRACE_RESOURCE_URI_MAPPING'))
    ) {
        $normalizer = new Urls(explode(',', $legacyMappings));
        return $normalizer->normalize($uriPath);
    }

    // It's easier to work on a fragment basis. So we take a $uriPath and we normalize it to a meanigful
    // array of fragments.
    // E.g. $fragments will contain:
    //    '/some//path/123/and/something-else/' =====> ['some', '', 'path', '123', 'and', 'something-else']
    //          ^^......note that empty fragments are kept......^^
    $fragments = array_map(function ($raw) {
        return strtolower(trim($raw));
    }, explode('/', $uriPath));

    $result = $uriPath;

    foreach (($incoming ? $incomingMappings : $outgoingMappings) as $rawMapping) {
        $normalizedMapping = strtolower(trim($rawMapping));
        if ('' === $normalizedMapping) {
            continue;
        }

        $regex = '/\\/' . str_replace('*', '[^\\/?#]+', str_replace('/', '\\/', $normalizedMapping)) . '/';
        $replacement = '/' . str_replace('*', '?', $normalizedMapping);
        $result = preg_replace($regex, $replacement, $result);
    }

    $fragments = explode('/', $result);
    $defaultPlusConfiguredfragmentRegexes = array_merge(DEFAULT_URI_PART_NORMALIZE_REGEXES, $fragmentRegexes);
    // Now applying fragment regex normalization
    foreach ($defaultPlusConfiguredfragmentRegexes as $fragmentRegex) {
        foreach ($fragments as &$fragment) {
            $matchResult = @preg_match($fragmentRegex, $fragment);
            if (1 === $matchResult) {
                $fragment = '?';
            }
        }
    }

    return implode('/', $fragments);
}

function _util_path_matching_pattern_to_regex($mappingFragments)
{
    $regex = '/';
}
