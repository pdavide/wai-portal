<?php

namespace Tests\Unit;

use App\Enums\UserPermission;
use App\Enums\WebsiteAccessType;
use Tests\TestCase;

/**
 * Website access enum tests.
 */
class WebsiteAccessTypeTest extends TestCase
{
    /**
     * Test website access mappings from user permissions.
     */
    public function testWebsiteAccessTypeFromUserPermission(): void
    {
        $this->assertEquals(WebsiteAccessType::NO_ACCESS, WebsiteAccessType::fromUserPermission(UserPermission::NO_ACCESS));
        $this->assertEquals(WebsiteAccessType::VIEW, WebsiteAccessType::fromUserPermission(UserPermission::READ_ANALYTICS));
        $this->assertEquals(WebsiteAccessType::WRITE, WebsiteAccessType::fromUserPermission(UserPermission::MANAGE_ANALYTICS));
    }
}
