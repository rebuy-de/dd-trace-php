<?php

namespace DDTrace\Tests\Unit\lib\util;

use DDTrace\Tests\Unit\BaseTestCase;

use function DDTrace\_util_normalize_incoming_path;
use function DDTrace\_util_normalize_outgoing_path;

class UrlsTest extends BaseTestCase
{
    protected function setUp()
    {
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_FRAGMENT_REGEX',
            'DD_TRACE_RESOURCE_URI_MAPPING_INCOMING',
            'DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING',
            'DD_TRACE_RESOURCE_URI_MAPPING',
        ]);
        parent::setUp();
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_FRAGMENT_REGEX',
            'DD_TRACE_RESOURCE_URI_MAPPING_INCOMING',
            'DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING',
            'DD_TRACE_RESOURCE_URI_MAPPING',
        ]);
    }

    public function testLegacyIsStillAppliedIfNewSettingsNotDefined()
    {
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_MAPPING=/user/*',
        ]);
        $this->assertSame('/user/?', _util_normalize_incoming_path('/user/123/nested/path'));
        $this->assertSame('/user/?', _util_normalize_outgoing_path('/user/123/nested/path'));
    }

    public function testLegacyIsIgnoredIfAtLeastOneNewSettingIsDefined()
    {
        // When DD_TRACE_RESOURCE_URI_MAPPING_INCOMING is also set
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_MAPPING=/user/*',
            'DD_TRACE_RESOURCE_URI_MAPPING_INCOMING=nested/*',
            'DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING',
            'DD_TRACE_RESOURCE_URI_FRAGMENT_REGEX',
        ]);
        $this->assertSame('/user/?/nested/?', _util_normalize_incoming_path('/user/123/nested/path'));
        $this->assertSame('/user/?/nested/path', _util_normalize_outgoing_path('/user/123/nested/path'));

        // When DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING is also set
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_MAPPING=/user/*',
            'DD_TRACE_RESOURCE_URI_MAPPING_INCOMING',
            'DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING=nested/*',
            'DD_TRACE_RESOURCE_URI_FRAGMENT_REGEX',
        ]);
        $this->assertSame('/user/?/nested/path', _util_normalize_incoming_path('/user/123/nested/path'));
        $this->assertSame('/user/?/nested/?', _util_normalize_outgoing_path('/user/123/nested/path'));

        // When DD_TRACE_RESOURCE_URI_FRAGMENT_REGEX is also set
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_MAPPING=/user/*',
            'DD_TRACE_RESOURCE_URI_MAPPING_INCOMING',
            'DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING',
            'DD_TRACE_RESOURCE_URI_FRAGMENT_REGEX=/^path$/',
        ]);
        $this->assertSame('/user/?/nested/?', _util_normalize_incoming_path('/user/123/nested/path'));
        $this->assertSame('/user/?/nested/?', _util_normalize_outgoing_path('/user/123/nested/path'));
    }

    public function testIncomingConfigurationDoesNotImpactOutgoing()
    {
        $this->putEnvAndReloadConfig(['DD_TRACE_RESOURCE_URI_MAPPING_INCOMING=before/*']);
        $this->assertSame('/before/something/after', _util_normalize_outgoing_path('/before/something/after'));
        $this->assertSame('/before/?/after', _util_normalize_incoming_path('/before/something/after'));
    }

    public function testOutgoingConfigurationDoesNotImpactIncoming()
    {
        $this->putEnvAndReloadConfig(['DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING=before/*']);
        $this->assertSame('/before/something/after', _util_normalize_incoming_path('/before/something/after'));
        $this->assertSame('/before/?/after', _util_normalize_outgoing_path('/before/something/after'));
    }

    public function testWrongIncomingConfigurationResultsInMissedPathNormalizationButDefaultStillWorks()
    {
        $this->putEnvAndReloadConfig(['DD_TRACE_RESOURCE_URI_MAPPING_INCOMING=no_asterisk,']);
        $this->assertSame('/no_asterisk/?/after', _util_normalize_incoming_path('/no_asterisk/123/after'));
    }

    public function testWrongOutgoingConfigurationResultsInMissedPathNormalizationButDefaultStillWorks()
    {
        $this->putEnvAndReloadConfig(['DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING=no_asterisk,']);
        $this->assertSame('/no_asterisk/?/after', _util_normalize_outgoing_path('/no_asterisk/123/after'));
    }

    public function testMixingFragmentRegexAndPatternMatchingIncoming()
    {
        $this->putEnvAndReloadConfig(['DD_TRACE_RESOURCE_URI_MAPPING_INCOMING=name/*']);
        $this->assertSame('/numeric/?/name/?', _util_normalize_incoming_path('/numeric/123/name/some_name'));
    }

    public function testMixingFragmentRegexAndPatternMatchingOutgoing()
    {
        $this->putEnvAndReloadConfig(['DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING=name/*']);
        $this->assertSame('/numeric/?/name/?', _util_normalize_outgoing_path('/numeric/123/name/some_name'));
    }

    /**
     * @dataProvider defaultPathNormalizationScenarios
     */
    public function testDefaultPathFragmentsNormalizationIncoming($uri, $expected)
    {
        $this->assertSame(_util_normalize_incoming_path($uri), $expected);
    }

    /**
     * @dataProvider defaultPathNormalizationScenarios
     */
    public function testDefaultPathFragmentsNormalizationOutgoing($uri, $expected)
    {
        $this->assertSame(_util_normalize_outgoing_path($uri), $expected);
    }

    public function defaultPathNormalizationScenarios()
    {
        return [
            // Defaults, no custom configuration
            'empty' => ['', '/'],
            'root' => ['/', '/'],

            'only_digits' => ['/123', '/?'],
            'starts_with_digits' => ['/123/path', '/?/path'],
            'ends_with_digits' => ['/path/123', '/path/?'],
            'has_digits' => ['/before/123/path', '/before/?/path'],

            'only_hex' => ['/0123456789abcdef', '/?'],
            'starts_with_hex' => ['/0123456789abcdef/path', '/?/path'],
            'ends_with_hex' => ['/path/0123456789abcdef', '/path/?'],
            'has_hex' => ['/before/0123456789abcdef/path', '/before/?/path'],

            'only_uuid' => ['/b968fb04-2be9-494b-8b26-efb8a816e7a5', '/?'],
            'starts_with_uuid' => ['/b968fb04-2be9-494b-8b26-efb8a816e7a5/path', '/?/path'],
            'ends_with_uuid' => ['/path/b968fb04-2be9-494b-8b26-efb8a816e7a5', '/path/?'],
            'has_uuid' => ['/before/b968fb04-2be9-494b-8b26-efb8a816e7a5/path', '/before/?/path'],

            'only_uuid_no_dash' => ['/b968fb042be9494b8b26efb8a816e7a5', '/?'],
            'starts_with_uuid_no_dash' => ['/b968fb042be9494b8b26efb8a816e7a5/path', '/?/path'],
            'ends_with_uuid_no_dash' => ['/path/b968fb042be9494b8b26efb8a816e7a5', '/path/?'],
            'has_uuid_no_dash' => ['/before/b968fb042be9494b8b26efb8a816e7a5/path', '/before/?/path'],

            'multiple_patterns' => ['/int/1/uuid/b968fb042be9494b8b26efb8a816e7a5/int/2', '/int/?/uuid/?/int/?']
        ];
    }

    public function testProvidedFragmentRegexAreAdditiveToDefaultFragmentRegexes()
    {
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_FRAGMENT_REGEX=/^some_name$/',
        ]);

        $this->assertSame('/int/?/name/?', _util_normalize_incoming_path('/int/123/name/some_name'));
        $this->assertSame('/int/?/name/?', _util_normalize_outgoing_path('/int/123/name/some_name'));
    }

    public function testWrongFragmentNormalizationRegexDoesNotCauseError()
    {
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_FRAGMENT_REGEX=/(((((]]]]]]wrong_regex$/',
        ]);

        $this->assertSame('/int/?', _util_normalize_incoming_path('/int/123'));
        $this->assertSame('/int/?', _util_normalize_outgoing_path('/int/123'));
    }

    public function testWrongFragmentNormalizationRegexDoesNotImpactOtherRegexes()
    {
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_FRAGMENT_REGEX=/(((((]]]]]]wrong_regex$/,/valid/',
        ]);

        $this->assertSame('/int/?/path/?', _util_normalize_incoming_path('/int/123/path/valid'));
        $this->assertSame('/int/?/path/?', _util_normalize_outgoing_path('/int/123/path/valid'));
    }

    public function testProvidedPathIsAddedLeadingSlashIfMissing()
    {
        $this->assertSame('/int/?', _util_normalize_incoming_path('int/123'));
        $this->assertSame('/int/?', _util_normalize_outgoing_path('int/123'));
    }

    public function testUriAcceptsTrailingSlash()
    {
        $this->assertSame('/int/?/', _util_normalize_incoming_path('/int/123/'));
        $this->assertSame('/int/?/', _util_normalize_outgoing_path('/int/123/'));
    }

    public function testSamePatternMultipleLocations()
    {
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_MAPPING_INCOMING=path/*',
            'DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING=path/*',
        ]);

        $this->assertSame(
            '/int/?/path/?/int/?/path/?',
            _util_normalize_incoming_path('/int/123/path/one/int/456/path/two')
        );
        $this->assertSame(
            '/int/?/path/?/int/?/path/?',
            _util_normalize_outgoing_path('/int/123/path/one/int/456/path/two')
        );
    }

    public function testPartialMatching()
    {
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_MAPPING_INCOMING=path/*-something',
            'DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING=path/*-something',
        ]);

        $this->assertSame(
            '/int/?/path/?-something/path/two-else',
            _util_normalize_incoming_path('/int/123/path/one-something/path/two-else')
        );
        $this->assertSame(
            '/int/?/path/?-something/path/two-else',
            _util_normalize_outgoing_path('/int/123/path/one-something/path/two-else')
        );
    }

    public function testComplexPatterns()
    {
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_MAPPING_INCOMING=path/*/*/then/something/*',
            'DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING=path/*/*/then/something/*',
        ]);

        $this->assertSame(
            '/int/?/path/?/?/then/something/?',
            _util_normalize_incoming_path('/int/123/path/one/two/then/something/else')
        );
        $this->assertSame(
            '/int/?/path/?/?/then/something/?',
            _util_normalize_outgoing_path('/int/123/path/one/two/then/something/else')
        );
    }

    public function testPatternCanNormalizeSingelFragment()
    {
        $this->putEnvAndReloadConfig([
            'DD_TRACE_RESOURCE_URI_MAPPING_INCOMING=*-something',
            'DD_TRACE_RESOURCE_URI_MAPPING_OUTGOING=*-something',
        ]);

        $this->assertSame(
            '/int/?/path/?-something/else',
            _util_normalize_incoming_path('/int/123/path/one-something/else')
        );
        $this->assertSame(
            '/int/?/path/?-something/else',
            _util_normalize_outgoing_path('/int/123/path/one-something/else')
        );
    }
}
