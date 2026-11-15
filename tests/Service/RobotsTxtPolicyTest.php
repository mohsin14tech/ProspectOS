<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\RobotsTxtPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RobotsTxtPolicy::class)]
final class RobotsTxtPolicyTest extends TestCase
{
    public function testSpecificAgentGroupOverridesWildcard(): void
    {
        $policy = new RobotsTxtPolicy();
        $rules = "User-agent: *\nDisallow: /\n\nUser-agent: ProspectOS\nAllow: /public\nDisallow: /private";

        self::assertTrue($policy->allows($rules, 'https://example.com/public/about'));
        self::assertFalse($policy->allows($rules, 'https://example.com/private/contact'));
    }

    public function testMostSpecificRuleWinsAndAllowWinsOnTie(): void
    {
        $policy = new RobotsTxtPolicy();
        $rules = "User-agent: *\nDisallow: /private\nDisallow: /private/public$\nAllow: /private/public$";

        self::assertTrue($policy->allows($rules, 'https://example.com/private/public'));
        self::assertFalse($policy->allows($rules, 'https://example.com/private/public?exact=1'));
        self::assertFalse($policy->allows("User-agent: *\nDisallow: /private", 'https://example.com/private?x=1'));
    }

    public function testWildcardAndEmptyDisallowRules(): void
    {
        $policy = new RobotsTxtPolicy();

        $rules = "User-agent: *\nDisallow:\nDisallow: /assets/*.pdf$";
        self::assertTrue($policy->allows($rules, 'https://example.com/assets/manual.html'));
        self::assertFalse($policy->allows($rules, 'https://example.com/assets/manual.pdf'));
    }
}
